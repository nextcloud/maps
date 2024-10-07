<?php

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Arne Hamann
 * @copyright Arne Hamann 2019
 */

namespace OCA\Maps\Service;

use OCA\Maps\BackgroundJob\LookupMissingGeoJob;
use OCP\BackgroundJob\IJobList;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IAppData;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IMemcache;
use OpenLocationCode\OpenLocationCode;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Reader;

/**
 * Class AddressService
 *
 * The address service can be used to get lat lng information for an address.
 * The service takes care of caching and rate limits.
 *
 * The first time an address is looked up it will not be in the database.
 * So an external lookup of the address is tried and the result is saved in the db.
 * If the lookup is successful the result is returned and any further lookup of
 * this address is resolved local.
 * If the lookup failed, a cron job is added to lookup the address later.
 *
 *
 * @package OCA\Maps\Service
 */
class AddressService {
	private $dbconnection;
	private $jobList;
	private $appData;

	/** @var IMemcache */
	private $memcache;

	public function __construct(
		ICacheFactory $cacheFactory,
		private LoggerInterface $logger,
		IJobList $jobList,
		IAppData $appData,
		IDBConnection $dbconnection,
	) {
		$this->dbconnection = $dbconnection;
		$this->memcache = $cacheFactory->createLocal('maps');
		$this->jobList = $jobList;
		$this->appData = $appData;
	}

	// converts the address to geo lat;lon
	public function addressToGeo($adr, $uri): string {
		$geo = $this->lookupAddress($adr, $uri);
		return strval($geo[0]).';'.strval($geo[1]);
	}

	/**
	 * Safely looks up an adr string
	 * First: Checks if the adress is known and in the db
	 *      Uses this geo if it was looked up externally
	 *      Look's it up if it was not looked up
	 * @param $adr
	 * @param $uri ressource identifier (contact URI for example)
	 * @return array($lat,$lng,$lookedUp)
	 */
	public function lookupAddress($adr, $uri): array {
		$adr_norm = strtolower(preg_replace('/\s+/', '', $adr));
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->select('id', 'lat', 'lng', 'looked_up')
			->from('maps_address_geo')
			->where($qb->expr()->eq('object_uri', $qb->createNamedParameter($uri, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('adr_norm', $qb->createNamedParameter($adr_norm, IQueryBuilder::PARAM_STR)));
		$req = $qb->execute();
		$lat = null;
		$lng = null;
		$inDb = false;
		while ($row = $req->fetch()) {
			if ($row['looked_up']) {
				$id = $row['id'];
				$lat = $row['lat'];
				$lng = $row['lng'];
				$lookedUp = false;
				$inDb = true;
			} else {
				$id = $row['id'];
				// if it's in the DB but not yet looked up, we can do it now
				// we first check if this address was already looked up
				$geo = $this->lookupAddressInternal($adr);
				// if not, ask external service
				if (!$geo[2]) {
					$geo = $this->lookupAddressExternal($adr);
				}
				$lat = $geo[0];
				$lng = $geo[1];
				$lookedUp = $geo[2];
				$inDb = true;
			}
			break;
		}
		$req->closeCursor();
		$qb = $this->dbconnection->getQueryBuilder();
		// if it's still not in the DB, it means the lookup did not happen yet
		// so we can schedule it for later
		if (!$inDb) {
			$foo = $this->scheduleForLookup($adr, $uri);
			$id = $foo[0];
			$lat = $foo[1];
			$lng = $foo[2];
			$lookedUp = $foo[3];

		} else {
			if ($lookedUp) {
				$qb->update('maps_address_geo')
					->set('lat', $qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR))
					->set('lng', $qb->createNamedParameter($lng, IQueryBuilder::PARAM_STR))
					->set('object_uri', $qb->createNamedParameter($uri, IQueryBuilder::PARAM_STR))
					->set('looked_up', $qb->createNamedParameter($lookedUp, IQueryBuilder::PARAM_BOOL))
					->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR)));
				$req = $qb->execute();
			}
		}

		return [$lat, $lng, $lookedUp];
	}

	private function lookupAddressInternal($adr): array {
		$res = [null, null, false];

		if (OpenLocationCode::isFull($adr)) {
			$decoded = OpenLocationCode::decode($adr);
			$res[0] = $decoded['latitudeCenter'];
			$res[1] = $decoded['longitudeCenter'];
			$res[2] = true;
			return $res;
		}

		$adr_norm = strtolower(preg_replace('/\s+/', '', $adr));

		$qb = $this->dbconnection->getQueryBuilder();
		$qb->select('lat', 'lng')
			->from('maps_address_geo')
			->where($qb->expr()->eq('looked_up', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('adr_norm', $qb->createNamedParameter($adr_norm, IQueryBuilder::PARAM_STR)));
		$req = $qb->execute();
		while ($row = $req->fetch()) {
			$res[0] = $row['lat'];
			$res[1] = $row['lng'];
			$res[2] = true;
		}
		$req->closeCursor();

		return $res;
	}

	// looks up the address on external provider returns lat, lon, lookupstate
	// do lookup only if last one occured more than one second ago
	private function lookupAddressExternal($adr): array {
		if (time() - intval($this->memcache->get('lastAddressLookup')) >= 1) {
			$opts = [
				'http' => [
					'method' => 'GET',
					'user_agent' => 'Nextcloud Maps app',
				]
			];
			$context = stream_context_create($opts);

			// we get rid of "post office box" field
			$splitted_adr = explode(';', $adr);
			if (count($splitted_adr) > 2) {
				array_shift($splitted_adr);
			}

			// remove blank lines (#706)
			$splitted_adr = array_filter(array_map('trim', $splitted_adr));
			$query_adr = implode(', ', $splitted_adr);

			$result_json = @file_get_contents(
				'https://nominatim.openstreetmap.org/search.php?q='.urlencode($query_adr).'&format=json',
				false,
				$context
			);
			if ($result_json !== false) {
				$result = \json_decode($result_json, true);
				if (!(key_exists('request_failed', $result) and $result['request_failed'])) {
					$this->logger->debug('External looked up address: ' . $adr . ' with result' . print_r($result, true));
					$this->memcache->set('lastAddressLookup', time());
					$lat = null;
					$lon = null;
					foreach ($result as $addr) {
						if (key_exists('lat', $addr) and
							key_exists('lon', $addr)
						) {
							if (is_null($lat) or
								(key_exists('class', $addr) and
									($addr['class'] == 'building' or $addr['class'] == 'place'))) {
								$lat = $addr['lat'];
								$lon = $addr['lon'];
							}
						}
					}
					return [$lat, $lon, true];
				}
			}
			$this->logger->debug('Externally looked failed');
		}
		return [null, null, false];
	}

	// launch lookup for all addresses of the vCard
	public function scheduleVCardForLookup($cardData, $cardUri) {
		$vCard = Reader::read($cardData);

		$this->cleanUpDBContactAddresses($vCard, $cardUri);

		foreach ($vCard->children() as $property) {
			if ($property->name === 'ADR') {
				$adr = $property->getValue();
				if ($adr !== ';;;;;;') {
					$this->lookupAddress($property->getValue(), $cardUri);
				}
			}
		}
	}

	private function cleanUpDBContactAddresses($vCard, $uri) {
		$qb = $this->dbconnection->getQueryBuilder();
		// get all vcard addresses
		$vCardAddresses = [];
		foreach ($vCard->children() as $property) {
			if ($property->name === 'ADR') {
				$adr = $property->getValue();
				array_push($vCardAddresses, $adr);
			}
		}
		// check which addresses from DB is not in the vCard anymore
		$adrIdToDelete = [];
		$qb->select('id', 'adr')
			->from('maps_address_geo')
			->where($qb->expr()->eq('object_uri', $qb->createNamedParameter($uri, IQueryBuilder::PARAM_STR)));
		$req = $qb->execute();
		while ($row = $req->fetch()) {
			if (!in_array($row['adr'], $vCardAddresses)) {
				array_push($adrIdToDelete, $row['id']);
			}
		}
		$req->closeCursor();

		foreach ($adrIdToDelete as $id) {
			$qb = $this->dbconnection->getQueryBuilder();
			$qb->delete('maps_address_geo')
				->where(
					$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
				);
			$req = $qb->execute();
		}
	}

	public function deleteDBContactAddresses($uri) {
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->delete('maps_address_geo')
			->where(
				$qb->expr()->eq('object_uri', $qb->createNamedParameter($uri, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();
	}

	// schedules the address for an external lookup
	private function scheduleForLookup($adr, $uri): array {
		$geo = $this->lookupAddressInternal($adr);
		// if not found internally, ask external service
		if (!$geo[2]) {
			$geo = $this->lookupAddressExternal($adr);
		}
		$adr_norm = strtolower(preg_replace('/\s+/', '', $adr));
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->insert('maps_address_geo')
			->values([
				'adr' => $qb->createNamedParameter($adr, IQueryBuilder::PARAM_STR),
				'adr_norm' => $qb->createNamedParameter($adr_norm, IQueryBuilder::PARAM_STR),
				'object_uri' => $qb->createNamedParameter($uri, IQueryBuilder::PARAM_STR),
				'lat' => $qb->createNamedParameter($geo[0], IQueryBuilder::PARAM_STR),
				'lng' => $qb->createNamedParameter($geo[1], IQueryBuilder::PARAM_STR),
				'looked_up' => $qb->createNamedParameter($geo[2], IQueryBuilder::PARAM_BOOL),
			]);
		$req = $qb->execute();
		$id = $qb->getLastInsertId();
		if (!$geo[2]) {
			$this->jobList->add(LookupMissingGeoJob::class, []);
		}
		return [$id, $geo[0], $geo[1], $geo[2]];
	}

	// looks up the geo information which have not been looked up
	// this is called by the Cron job
	public function lookupMissingGeo($max = 200):bool {
		// stores if all addresses where looked up
		$lookedUpAll = true;
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->select('adr', 'object_uri')
			->from('maps_address_geo')
			->where($qb->expr()->eq('looked_up', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->setMaxResults($max);
		$req = $qb->execute();
		$result = $req->fetchAll();
		$req->closeCursor();
		$i = 0;
		foreach ($result as $row) {
			$i++;
			$geo = $this->lookupAddress($row['adr'], $row['object_uri']);
			// lookup failed
			if (!$geo[2]) {
				$lookedUpAll = false;
			}
			\sleep(1);
			\usleep(\rand(100, 100000));
		}
		// not all addresses where loaded from database
		if ($i === $max) {
			$lookedUpAll = false;
		}
		if ($lookedUpAll) {
			$this->logger->debug('Successfully looked up all addresses during cron job');
		} else {
			$this->logger->debug('Failed to look up all addresses during cron job');
		}
		return $lookedUpAll;
	}
}
