<?php

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Maps\Service;

use OC\Archive\ZIP;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class DevicesService {

	private $importUserId;
	private $currentXmlTag;
	private $importDevName;
	private $importFileName;
	private $currentPoint;
	private $currentPointList;
	private $trackIndex;
	private $pointIndex;
	private $insideTrk;

	public function __construct(
		private LoggerInterface $logger,
		private IL10N $l10n,
		private IDBConnection $dbconnection,
	) {
	}

	private function db_quote_escape_string($str) {
		return $this->dbconnection->quote($str);
	}

	/**
	 * @param string $userId
	 * @param int $pruneBefore
	 * @return array with devices
	 */
	public function getDevicesFromDB($userId) {
		$devices = [];
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->select('id', 'user_agent', 'color')
			->from('maps_devices', 'd')
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();

		while ($row = $req->fetch()) {
			$devices[intval($row['id'])] = [
				'id' => intval($row['id']),
				'user_agent' => $row['user_agent'],
				'color' => $row['color'],
				'isShareable' => true,
				'isDeleteable' => true,
				'isUpdateable' => true,
				'isReadable' => true,
				'shares' => []
			];
		}
		$req->closeCursor();
		return $devices;
	}

	/**
	 * @param string[] $tokens
	 * @return array
	 * @throws Exception
	 */
	public function getDevicesByTokens(array $tokens) {
		$devices = [];
		$qb = $this->dbconnection->getquerybuilder();
		$qb->select('d.id', 'd.user_agent', 'd.color', 's.token')
			->from('maps_devices', 'd')
			->innerJoin('d', 'maps_device_shares', 's', $qb->expr()->eq('d.id', 's.device_id'))
			->where(
				$qb->expr()->in('s.token', $qb->createNamedParameter($tokens, IQueryBuilder::PARAM_STR_ARRAY))
			);
		$req = $qb->execute();

		while ($row = $req->fetch()) {
			if (array_key_exists(intval($row['id']), $devices)) {
				$devices[intval($row['id'])]['tokens'][] = $row['token'];
			} else {
				$devices[intval($row['id'])] = [
					'id' => intval($row['id']),
					'user_agent' => $row['user_agent'],
					'color' => $row['color'],
					'isShareable' => false,
					'isDeleteable' => true,
					'isUpdateable' => false,
					'isReadable' => true,
					'shares' => [],
					'tokens' => [$row['token']]
				];
			}
		}
		$req->closeCursor();
		return $devices;
	}

	/**
	 * @param $userId
	 * @param $deviceId
	 * @param int|null $pruneBefore
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function getDevicePointsFromDB($userId, $deviceId, ?int $pruneBefore = 0, ?int $limit = null, ?int $offset = null) {
		$qb = $this->dbconnection->getQueryBuilder();
		// get coordinates
		$qb->selectDistinct(['p.id', 'lat', 'lng', 'timestamp', 'altitude', 'accuracy', 'battery'])
			->from('maps_device_points', 'p')
			->innerJoin('p', 'maps_devices', 'd', $qb->expr()->eq('d.id', 'p.device_id'))
			->where(
				$qb->expr()->eq('p.device_id', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('d.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		if (intval($pruneBefore) > 0) {
			$qb->andWhere(
				$qb->expr()->gt('timestamp', $qb->createNamedParameter(intval($pruneBefore), IQueryBuilder::PARAM_INT))
			);
		}
		if (!is_null($offset)) {
			$qb->setFirstResult($offset);
		}
		if (!is_null($limit)) {
			$qb->setMaxResults($limit);
		}
		$qb->orderBy('timestamp', 'DESC');
		$req = $qb->execute();

		$points = [];
		while ($row = $req->fetch()) {
			$points[] = [
				'id' => intval($row['id']),
				'lat' => floatval($row['lat']),
				'lng' => floatval($row['lng']),
				'timestamp' => intval($row['timestamp']),
				'altitude' => is_numeric($row['altitude']) ? floatval($row['altitude']) : null,
				'accuracy' => is_numeric($row['accuracy']) ? floatval($row['accuracy']) : null,
				'battery' => is_numeric($row['battery']) ? floatval($row['battery']) : null
			];
		}
		$req->closeCursor();

		return array_reverse($points);
	}

	/**
	 * @param string[] $token
	 * @param int|null $pruneBefore
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return array
	 * @throws Exception
	 */
	public function getDevicePointsByTokens(array $tokens, ?int $pruneBefore = 0, ?int $limit = 10000, ?int $offset = 0) {
		$qb = $this->dbconnection->getQueryBuilder();
		// get coordinates
		$or = [];
		foreach ($tokens as $token) {
			$or[] = $qb->expr()->andX(
				$qb->expr()->eq('s.token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR)),
				$qb->expr()->lte('p.timestamp', 's.timestamp_to'),
				$qb->expr()->gte('p.timestamp', 's.timestamp_from')
			);
		}
		$qb->select('p.id', 'lat', 'lng', 'timestamp', 'altitude', 'accuracy', 'battery')
			->from('maps_device_points', 'p')
			->innerJoin('p', 'maps_device_shares', 's', $qb->expr()->eq('p.device_id', 's.device_id'))
			->where($qb->expr()->orX(...$or));

		if (intval($pruneBefore) > 0) {
			$qb->andWhere(
				$qb->expr()->gt('timestamp', $qb->createNamedParameter(intval($pruneBefore), IQueryBuilder::PARAM_INT))
			);
		}
		if (!is_null($offset)) {
			$qb->setFirstResult($offset);
		}
		if (!is_null($limit)) {
			$qb->setMaxResults($limit);
		}
		$qb->orderBy('timestamp', 'DESC');
		$req = $qb->execute();

		$points = [];
		while ($row = $req->fetch()) {
			$points[] = [
				'id' => intval($row['id']),
				'lat' => floatval($row['lat']),
				'lng' => floatval($row['lng']),
				'timestamp' => intval($row['timestamp']),
				'altitude' => is_numeric($row['altitude']) ? floatval($row['altitude']) : null,
				'accuracy' => is_numeric($row['accuracy']) ? floatval($row['accuracy']) : null,
				'battery' => is_numeric($row['battery']) ? floatval($row['battery']) : null
			];
		}
		$req->closeCursor();

		return array_reverse($points);
	}

	/**
	 * @param $userId
	 * @param $deviceId
	 * @return array
	 * @throws Exception
	 */
	public function getDeviceTimePointsFromDb($userId, $deviceId) {
		$qb = $this->dbconnection->getQueryBuilder();
		// get coordinates
		$qb->select('lat', 'lng', 'timestamp')
			->from('maps_device_points', 'p')
			->innerJoin('p', 'maps_devices', 'd', $qb->expr()->eq('d.id', 'p.device_id'))
			->where(
				$qb->expr()->eq('p.device_id', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('d.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		$qb->orderBy('timestamp', 'ASC');
		$req = $qb->execute();

		$points = [];
		while ($row = $req->fetch()) {
			$points[intval($row['timestamp'])] = [floatval($row['lat']), floatval($row['lng'])];
		}
		$req->closeCursor();
		return $points;
	}

	public function getOrCreateDeviceFromDB($userId, $userAgent) {
		$deviceId = null;
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->select('id')
			->from('maps_devices', 'd')
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('user_agent', $qb->createNamedParameter($userAgent, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();

		while ($row = $req->fetch()) {
			$deviceId = intval($row['id']);
			break;
		}
		$req->closeCursor();

		if ($deviceId === null) {
			$qb->insert('maps_devices')
				->values([
					'user_agent' => $qb->createNamedParameter($userAgent, IQueryBuilder::PARAM_STR),
					'user_id' => $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)
				]);
			$req = $qb->execute();
			$deviceId = $qb->getLastInsertId();
		}
		return $deviceId;
	}

	public function addPointToDB($deviceId, $lat, $lng, $ts, $altitude, $battery, $accuracy) {
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->insert('maps_device_points')
			->values([
				'device_id' => $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_STR),
				'lat' => $qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR),
				'lng' => $qb->createNamedParameter($lng, IQueryBuilder::PARAM_STR),
				'timestamp' => $qb->createNamedParameter(intval($ts), IQueryBuilder::PARAM_INT),
				'altitude' => $qb->createNamedParameter(is_numeric($altitude) ? $altitude : null, IQueryBuilder::PARAM_STR),
				'battery' => $qb->createNamedParameter(is_numeric($battery) ? $battery : null, IQueryBuilder::PARAM_STR),
				'accuracy' => $qb->createNamedParameter(is_numeric($accuracy) ? $accuracy : null, IQueryBuilder::PARAM_STR)
			]);
		$req = $qb->execute();
		$pointId = $qb->getLastInsertId();
		return $pointId;
	}

	public function addPointsToDB($deviceId, $points) {
		$values = [];
		foreach ($points as $p) {
			$value = '('.
				$this->db_quote_escape_string($deviceId).', '.
				$this->db_quote_escape_string($p['lat']).', '.
				$this->db_quote_escape_string($p['lng']).', '.
				$this->db_quote_escape_string($p['date']).', '.
				((isset($p['altitude']) and is_numeric($p['altitude'])) ? $this->db_quote_escape_string(floatval($p['altitude'])) : 'NULL').', '.
				((isset($p['battery']) and is_numeric($p['battery'])) ? $this->db_quote_escape_string(floatval($p['battery'])) : 'NULL').', '.
				((isset($p['accuracy']) and is_numeric($p['accuracy'])) ? $this->db_quote_escape_string(floatval($p['accuracy'])) : 'NULL').')';
			array_push($values, $value);
		}
		$valuesStr = implode(', ', $values);
		$sql = '
            INSERT INTO *PREFIX*maps_device_points
            (device_id, lat, lng, timestamp,
             altitude, battery, accuracy)
            VALUES '.$valuesStr.' ;';
		$req = $this->dbconnection->prepare($sql);
		$req->execute();
		$req->closeCursor();
	}

	public function getDeviceFromDB($id, $userId) {
		$device = null;
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->select('id', 'user_agent', 'color')
			->from('maps_devices', 'd')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		if ($userId !== null) {
			$qb->andWhere(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		}
		$req = $qb->execute();

		while ($row = $req->fetch()) {
			$device = [
				'id' => intval($row['id']),
				'user_agent' => $row['user_agent'],
				'color' => $row['color']
			];
			break;
		}
		$req->closeCursor();
		return $device;
	}

	public function editDeviceInDB($id, $color, $name) {
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->update('maps_devices');
		if (is_string($color) && strlen($color) > 0) {
			$qb->set('color', $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR));
		}
		if (is_string($name) && strlen($name) > 0) {
			$qb->set('user_agent', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR));
		}
		$qb->where(
			$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
		);
		$req = $qb->execute();
	}

	public function deleteDeviceFromDB($id) {
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->delete('maps_devices')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->execute();

		$qb->delete('maps_device_points')
			->where(
				$qb->expr()->eq('device_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->execute();
	}

	public function countPoints($userId, $deviceIdList, $begin, $end) {
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*) AS co'))
			->from('maps_devices', 'd')
			->innerJoin('d', 'maps_device_points', 'p', $qb->expr()->eq('d.id', 'p.device_id'))
			->where(
				$qb->expr()->eq('d.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		if (is_array($deviceIdList) and count($deviceIdList) > 0) {
			$or = $qb->expr()->orx();
			foreach ($deviceIdList as $deviceId) {
				$or->add($qb->expr()->eq('d.id', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT)));
			}
			$qb->andWhere($or);
		} else {
			return 0;
		}
		if ($begin !== null && is_numeric($begin)) {
			$qb->andWhere(
				$qb->expr()->gt('p.timestamp', $qb->createNamedParameter(intval($begin), IQueryBuilder::PARAM_INT))
			);
		}
		if ($end !== null && is_numeric($end)) {
			$qb->andWhere(
				$qb->expr()->lt('p.timestamp', $qb->createNamedParameter(intval($end), IQueryBuilder::PARAM_INT))
			);
		}
		$req = $qb->execute();
		$count = 0;
		while ($row = $req->fetch()) {
			$count = intval($row['co']);
			break;
		}

		return $count;
	}

	public function exportDevices($userId, $handler, $deviceIdList, $begin, $end, $appVersion, $filename) {
		$gpxHeader = $this->generateGpxHeader($filename, $appVersion, count($deviceIdList));
		fwrite($handler, $gpxHeader);

		foreach ($deviceIdList as $devid) {
			$nbPoints = $this->countPoints($userId, [$devid], $begin, $end);
			if ($nbPoints > 0) {
				$this->getAndWriteDevicePoints($devid, $begin, $end, $handler, $nbPoints, $userId);
			}
		}
		fwrite($handler, '</gpx>');
	}

	private function generateGpxHeader($name, $appVersion, $nbdev = 0) {
		date_default_timezone_set('UTC');
		$dt = new \DateTime();
		$date = $dt->format('Y-m-d\TH:i:s\Z');
		$gpxText = '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>' . "\n";
		$gpxText .= '<gpx xmlns="http://www.topografix.com/GPX/1/1"' .
			' xmlns:gpxx="http://www.garmin.com/xmlschemas/GpxExtensions/v3"' .
			' xmlns:wptx1="http://www.garmin.com/xmlschemas/WaypointExtension/v1"' .
			' xmlns:gpxtpx="http://www.garmin.com/xmlschemas/TrackPointExtension/v1"' .
			' creator="Nextcloud Maps v' .
			$appVersion. '" version="1.1"' .
			' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' .
			' xsi:schemaLocation="http://www.topografix.com/GPX/1/1' .
			' http://www.topografix.com/GPX/1/1/gpx.xsd' .
			' http://www.garmin.com/xmlschemas/GpxExtensions/v3' .
			' http://www8.garmin.com/xmlschemas/GpxExtensionsv3.xsd' .
			' http://www.garmin.com/xmlschemas/WaypointExtension/v1' .
			' http://www8.garmin.com/xmlschemas/WaypointExtensionv1.xsd' .
			' http://www.garmin.com/xmlschemas/TrackPointExtension/v1' .
			' http://www.garmin.com/xmlschemas/TrackPointExtensionv1.xsd">' . "\n";
		$gpxText .= '<metadata>' . "\n" . ' <time>' . $date . '</time>' . "\n";
		$gpxText .= ' <name>' . $name . '</name>' . "\n";
		if ($nbdev > 0) {
			$gpxText .= ' <desc>' . $nbdev . ' device'.($nbdev > 1 ? 's' : '').'</desc>' . "\n";
		}
		$gpxText .= '</metadata>' . "\n";
		return $gpxText;
	}

	private function getAndWriteDevicePoints($devid, $begin, $end, $fd, $nbPoints, $userId) {
		$device = $this->getDeviceFromDB($devid, $userId);
		$devname = $device['user_agent'];
		$qb = $this->dbconnection->getQueryBuilder();

		$gpxText = '<trk>' . "\n" . ' <name>' . $devname . '</name>' . "\n";
		$gpxText .= ' <trkseg>' . "\n";
		fwrite($fd, $gpxText);

		$chunkSize = 10000;
		$pointIndex = 0;

		while ($pointIndex < $nbPoints) {
			$gpxText = '';
			$qb->select('id', 'lat', 'lng', 'timestamp', 'altitude', 'accuracy', 'battery')
				->from('maps_device_points', 'p')
				->where(
					$qb->expr()->eq('device_id', $qb->createNamedParameter($devid, IQueryBuilder::PARAM_INT))
				);
			if (intval($begin) > 0) {
				$qb->andWhere(
					$qb->expr()->gt('timestamp', $qb->createNamedParameter(intval($begin), IQueryBuilder::PARAM_INT))
				);
			}
			if (intval($end) > 0) {
				$qb->andWhere(
					$qb->expr()->lt('timestamp', $qb->createNamedParameter(intval($end), IQueryBuilder::PARAM_INT))
				);
			}
			$qb->setFirstResult($pointIndex);
			$qb->setMaxResults($chunkSize);
			$qb->orderBy('timestamp', 'ASC');
			$req = $qb->execute();

			while ($row = $req->fetch()) {
				$id = intval($row['id']);
				$lat = floatval($row['lat']);
				$lng = floatval($row['lng']);
				$epoch = $row['timestamp'];
				$date = '';
				if (is_numeric($epoch)) {
					$epoch = intval($epoch);
					$dt = new \DateTime("@$epoch");
					$date = $dt->format('Y-m-d\TH:i:s\Z');
				}
				$alt = $row['altitude'];
				$acc = $row['accuracy'];
				$bat = $row['battery'];

				$gpxExtension = '';
				$gpxText .= '  <trkpt lat="'.$lat.'" lon="'.$lng.'">' . "\n";
				$gpxText .= '   <time>' . $date . '</time>' . "\n";
				if (is_numeric($alt)) {
					$gpxText .= '   <ele>' . sprintf('%.2f', floatval($alt)) . '</ele>' . "\n";
				}
				if (is_numeric($acc) && intval($acc) >= 0) {
					$gpxExtension .= '     <accuracy>' . sprintf('%.2f', floatval($acc)) . '</accuracy>' . "\n";
				}
				if (is_numeric($bat) && intval($bat) >= 0) {
					$gpxExtension .= '     <batterylevel>' . sprintf('%.2f', floatval($bat)) . '</batterylevel>' . "\n";
				}
				if ($gpxExtension !== '') {
					$gpxText .= '   <extensions>'. "\n" . $gpxExtension;
					$gpxText .= '   </extensions>' . "\n";
				}
				$gpxText .= '  </trkpt>' . "\n";
			}
			$req->closeCursor();

			// write the chunk
			fwrite($fd, $gpxText);
			$pointIndex = $pointIndex + $chunkSize;
		}
		$gpxText = ' </trkseg>' . "\n";
		$gpxText .= '</trk>' . "\n";
		fwrite($fd, $gpxText);
	}

	public function importDevices($userId, $file) {
		$lowerFileName = strtolower($file->getName());
		if ($this->endswith($lowerFileName, '.gpx')) {
			return $this->importDevicesFromGpx($userId, $file);
		} elseif ($this->endswith($lowerFileName, '.kml')) {
			$fp = $file->fopen('r');
			$name = $file->getName();
			return $this->importDevicesFromKml($userId, $fp, $name);
		} elseif ($this->endswith($lowerFileName, '.kmz')) {
			return $this->importDevicesFromKmz($userId, $file);
		}
	}

	public function importDevicesFromGpx($userId, $file) {
		$this->currentPointList = [];
		$this->importUserId = $userId;
		$this->importFileName = $file->getName();
		$this->trackIndex = 1;
		$this->insideTrk = false;

		$xml_parser = xml_parser_create();
		xml_set_object($xml_parser, $this);
		xml_set_element_handler($xml_parser, 'gpxStartElement', 'gpxEndElement');
		xml_set_character_data_handler($xml_parser, 'gpxDataElement');

		$fp = $file->fopen('r');

		// using xml_parse to be able to parse file chunks in case it's too big
		while ($data = fread($fp, 4096000)) {
			if (!xml_parse($xml_parser, $data, feof($fp))) {
				$this->logger->error(
					'Exception in '.$file->getName().' parsing at line '.
					  xml_get_current_line_number($xml_parser).' : '.
					  xml_error_string(xml_get_error_code($xml_parser)),
					['app' => 'maps']
				);
				return 0;
			}
		}
		fclose($fp);
		xml_parser_free($xml_parser);

		return ($this->trackIndex - 1);
	}

	private function gpxStartElement($parser, $name, $attrs) {
		//$points, array($lat, $lon, $ele, $timestamp, $acc, $bat, $sat, $ua, $speed, $bearing)
		$this->currentXmlTag = $name;
		if ($name === 'TRK') {
			$this->importDevName = '';
			$this->pointIndex = 1;
			$this->currentPointList = [];
			$this->insideTrk = true;
		} elseif ($name === 'TRKPT') {
			$this->currentPoint = [];
			if (isset($attrs['LAT'])) {
				$this->currentPoint['lat'] = floatval($attrs['LAT']);
			}
			if (isset($attrs['LON'])) {
				$this->currentPoint['lng'] = floatval($attrs['LON']);
			}
		}
		//var_dump($attrs);
	}

	private function gpxEndElement($parser, $name) {
		if ($name === 'TRK') {
			$this->insideTrk = false;
			// log last track points
			if (count($this->currentPointList) > 0) {
				if ($this->importDevName === '') {
					$this->importDevName = $this->importFileName.' '.$this->trackIndex;
				}
				$devid = $this->getOrCreateDeviceFromDB($this->importUserId, $this->importDevName);
				$this->addPointsToDB($devid, $this->currentPointList);
			}
			$this->trackIndex++;
			unset($this->currentPointList);
		} elseif ($name === 'TRKPT') {
			// store track point

			// convert date
			if (isset($this->currentPoint['date'])) {
				$time = new \DateTime($this->currentPoint['date']);
				$timestamp = $time->getTimestamp();
				$this->currentPoint['date'] = $timestamp;
			}
			array_push($this->currentPointList, $this->currentPoint);
			// if we have enough points, we log them and clean the points array
			if (count($this->currentPointList) >= 500) {
				if ($this->importDevName === '') {
					$this->importDevName = 'device'.$this->trackIndex;
				}
				$devid = $this->getOrCreateDeviceFromDB($this->importUserId, $this->importDevName);
				$this->addPointsToDB($devid, $this->currentPointList);
				unset($this->currentPointList);
				$this->currentPointList = [];
			}
			$this->pointIndex++;
		}
	}

	private function gpxDataElement($parser, $data) {
		$d = trim($data);
		if (!empty($d)) {
			if ($this->currentXmlTag === 'ELE') {
				$this->currentPoint['altitude'] = (isset($this->currentPoint['altitude'])) ? $this->currentPoint['altitude'].$d : $d;
			} elseif ($this->currentXmlTag === 'BATTERYLEVEL') {
				$this->currentPoint['battery'] = (isset($this->currentPoint['battery'])) ? $this->currentPoint['battery'].$d : $d;
			} elseif ($this->currentXmlTag === 'ACCURACY') {
				$this->currentPoint['accuracy'] = (isset($this->currentPoint['accuracy'])) ? $this->currentPoint['accuracy'].$d : $d;
			} elseif ($this->insideTrk and $this->currentXmlTag === 'TIME') {
				$this->currentPoint['date'] = (isset($this->currentPoint['date'])) ? $this->currentPoint['date'].$d : $d;
			} elseif ($this->insideTrk and $this->currentXmlTag === 'NAME') {
				$this->importDevName = $this->importDevName . $d;
			}
		}
	}

	public function importDevicesFromKmz($userId, $file) {
		$path = $file->getStorage()->getLocalFile($file->getInternalPath());
		$name = $file->getName();
		$zf = new ZIP($path);
		if (count($zf->getFiles()) > 0) {
			$zippedFilePath = $zf->getFiles()[0];
			$fstream = $zf->getStream($zippedFilePath, 'r');

			$nbImported = $this->importDevicesFromKml($userId, $fstream, $name);
		} else {
			$nbImported = 0;
		}
		return $nbImported;
	}

	public function importDevicesFromKml($userId, $fp, $name) {
		$this->trackIndex = 1;
		$this->importUserId = $userId;
		$this->importFileName = $name;
		$xml_parser = xml_parser_create();
		xml_set_object($xml_parser, $this);
		xml_set_element_handler($xml_parser, 'kmlStartElement', 'kmlEndElement');
		xml_set_character_data_handler($xml_parser, 'kmlDataElement');

		while ($data = fread($fp, 4096000)) {
			if (!xml_parse($xml_parser, $data, feof($fp))) {
				$this->logger->error(
					'Exception in '.$name.' parsing at line '.
					  xml_get_current_line_number($xml_parser).' : '.
					  xml_error_string(xml_get_error_code($xml_parser)),
				);
				return 0;
			}
		}
		fclose($fp);
		xml_parser_free($xml_parser);
		return ($this->trackIndex - 1);
	}

	private function kmlStartElement($parser, $name, $attrs) {
		$this->currentXmlTag = $name;
		if ($name === 'GX:TRACK') {
			if (isset($attrs['ID'])) {
				$this->importDevName = $attrs['ID'];
			} else {
				$this->importDevName = $this->importFileName.' '.$this->trackIndex;
			}
			$this->pointIndex = 1;
			$this->currentPointList = [];
		} elseif ($name === 'WHEN') {
			$this->currentPoint = [];
		}
		//var_dump($attrs);
	}

	private function kmlEndElement($parser, $name) {
		if ($name === 'GX:TRACK') {
			// log last track points
			if (count($this->currentPointList) > 0) {
				$devid = $this->getOrCreateDeviceFromDB($this->importUserId, $this->importDevName);
				$this->addPointsToDB($devid, $this->currentPointList);
			}
			$this->trackIndex++;
			unset($this->currentPointList);
		} elseif ($name === 'GX:COORD') {
			// convert date
			if (isset($this->currentPoint['date'])) {
				$time = new \DateTime($this->currentPoint['date']);
				$timestamp = $time->getTimestamp();
				$this->currentPoint['date'] = $timestamp;
			}
			// get latlng
			if (isset($this->currentPoint['coords'])) {
				$spl = explode(' ', $this->currentPoint['coords']);
				if (count($spl) > 1) {
					$this->currentPoint['lat'] = floatval($spl[1]);
					$this->currentPoint['lng'] = floatval($spl[0]);
					if (count($spl) > 2) {
						$this->currentPoint['altitude'] = floatval($spl[2]);
					}
				}
			}
			// store track point
			array_push($this->currentPointList, $this->currentPoint);
			// if we have enough points, we log them and clean the points array
			if (count($this->currentPointList) >= 500) {
				$devid = $this->getOrCreateDeviceFromDB($this->importUserId, $this->importDevName);
				$this->addPointsToDB($devid, $this->currentPointList);
				unset($this->currentPointList);
				$this->currentPointList = [];
			}
			$this->pointIndex++;
		}
	}

	private function kmlDataElement($parser, $data) {
		$d = trim($data);
		if (!empty($d)) {
			if ($this->currentXmlTag === 'WHEN') {
				$this->currentPoint['date'] = (isset($this->currentPoint['date'])) ? $this->currentPoint['date'].$d : $d;
			} elseif ($this->currentXmlTag === 'GX:COORD') {
				$this->currentPoint['coords'] = (isset($this->currentPoint['coords'])) ? $this->currentPoint['coords'].$d : $d;
			}
		}
	}

	private function endswith($string, $test) {
		$strlen = strlen($string);
		$testlen = strlen($test);
		if ($testlen > $strlen) {
			return false;
		}
		return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
	}

	/**
	 * @param $folder
	 * @param bool $isCreatable
	 * @return mixed
	 * @throws NotFoundException
	 */
	public function getSharedDevicesFromFolder($folder, bool $isCreatable = true) {
		try {
			$file = $folder->get('.device_shares.json');
		} catch (NotFoundException $e) {
			if ($isCreatable) {
				$file = $folder->newFile('.device_shares.json', $content = '[]');
			} else {
				throw new NotFoundException();
			}
		}
		return json_decode($file->getContent(), true);
	}

}
