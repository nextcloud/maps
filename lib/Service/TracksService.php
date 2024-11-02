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

use OC\Files\Search\SearchBinaryOperator;
use OC\Files\Search\SearchComparison;
use OC\Files\Search\SearchQuery;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchComparison;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\Share\IManager;
use Psr\Log\LoggerInterface;

class TracksService {

	public const TRACK_MIME_TYPES = ['application/gpx+xml'];

	private $qb;

	public function __construct(
		private LoggerInterface $logger,
		private IL10N $l10n,
		private IRootFolder $root,
		private IManager $shareManager,
		private IDBConnection $dbconnection,
	) {
	}

	public function rescan($userId) {
		$userFolder = $this->root->getUserFolder($userId);
		$tracks = $this->gatherTrackFiles($userFolder, true);
		$this->deleteAllTracksFromDB($userId);
		foreach ($tracks as $track) {
			$this->addTrackToDB($userId, $track->getId(), $track);
			yield $track->getPath();
		}
	}

	public function addByFile(Node $file) {
		$userFolder = $this->root->getUserFolder($file->getOwner()->getUID());
		if ($this->isTrack($file)) {
			$this->addTrackToDB($file->getOwner()->getUID(), $file->getId(), $file);
		}
	}

	// add the file for its owner and users that have access
	// check if it's already in DB before adding
	public function safeAddByFile(Node $file) {
		$ownerId = $file->getOwner()->getUID();
		$userFolder = $this->root->getUserFolder($ownerId);
		if ($this->isTrack($file)) {
			$this->safeAddTrack($file, $ownerId);
			// is the file accessible to other users ?
			$accesses = $this->shareManager->getAccessList($file);
			foreach ($accesses['users'] as $uid) {
				if ($uid !== $ownerId) {
					$this->safeAddTrack($file, $uid);
				}
			}
			return true;
		} else {
			return false;
		}
	}

	public function safeAddByFileIdUserId($fileId, $userId) {
		$userFolder = $this->root->getUserFolder($userId);
		$files = $userFolder->getById($fileId);
		if (empty($files)) {
			return;
		}
		$file = array_shift($files);
		if ($file !== null and $this->isTrack($file)) {
			$this->safeAddTrack($file, $userId);
		}
	}

	public function safeAddByFolderIdUserId($folderId, $userId) {
		$folders = $this->root->getById($folderId);
		if (empty($folders)) {
			return;
		}
		$folder = array_shift($folders);
		if ($folder !== null) {
			$tracks = $this->gatherTrackFiles($folder, true);
			foreach ($tracks as $track) {
				$this->safeAddTrack($track, $userId);
			}
		}
	}

	// avoid adding track if it already exists in the DB
	private function safeAddTrack($track, $userId) {
		// filehooks are triggered several times (2 times for file creation)
		// so we need to be sure it's not inserted several times
		// by checking if it already exists in DB
		// OR by using file_id in primary key
		if ($this->getTrackByFileIDFromDB($track->getId(), $userId) === null) {
			$this->addTrackToDB($userId, $track->getId(), $track);
		}
	}

	// add all tracks of a folder taking care of shared accesses
	public function safeAddByFolder($folder) {
		$tracks = $this->gatherTrackFiles($folder, true);
		foreach ($tracks as $track) {
			$this->safeAddByFile($track);
		}
	}

	public function addByFolder(Node $folder) {
		$tracks = $this->gatherTrackFiles($folder, true);
		foreach ($tracks as $track) {
			$this->addTrackToDB($folder->getOwner()->getUID(), $track->getId(), $track);
		}
	}

	// delete track only if it's not accessible to user anymore
	// it might have been shared multiple times by different users
	public function safeDeleteByFileIdUserId($fileId, $userId) {
		$userFolder = $this->root->getUserFolder($userId);
		$files = $userFolder->getById($fileId);
		if (!is_array($files) or count($files) === 0) {
			$this->deleteByFileIdUserId($fileId, $userId);
		}
	}

	public function deleteByFile(Node $file) {
		$this->deleteByFileId($file->getId());
	}

	public function deleteByFolder(Node $folder) {
		$tracks = $this->gatherTrackFiles($folder, true);
		foreach ($tracks as $track) {
			$this->deleteByFileId($track->getId());
		}
	}

	// delete folder tracks only if it's not accessible to user anymore
	public function safeDeleteByFolderIdUserId($folderId, $userId) {
		$userFolder = $this->root->getUserFolder($userId);
		$folders = $userFolder->getById($folderId);
		if (is_array($folders) and count($folders) === 1) {
			$folder = array_shift($folders);
			$tracks = $this->gatherTrackFiles($folder, true);
			foreach ($tracks as $track) {
				$this->deleteByFileIdUserId($track->getId(), $userId);
			}
		}
	}

	private function gatherTrackFiles($folder, $recursive) {
		$notes = [];
		$nodes = $folder->getDirectoryListing();
		foreach ($nodes as $node) {
			if ($node->getType() === FileInfo::TYPE_FOLDER and $recursive) {
				try {
					$notes = array_merge($notes, $this->gatherTrackFiles($node, $recursive));
				} catch (\OCP\Files\StorageNotAvailableException|\Exception $e) {
					$msg = 'WARNING: Could not access ' . $node->getName();
					echo($msg . "\n");
					$this->logger->error($msg);
				}
				continue;
			}
			if ($this->isTrack($node)) {
				$notes[] = $node;
			}
		}
		return $notes;
	}

	private function isTrack($file) {
		if ($file->getType() !== \OCP\Files\FileInfo::TYPE_FILE) {
			return false;
		}
		if (!in_array($file->getMimetype(), self::TRACK_MIME_TYPES)) {
			return false;
		}
		return true;
	}

	private function dbRowToTrack($row, $folder, $userFolder, $defaultMap, $ignoredPaths) {
		// avoid tracks that are not in "this map's" folder
		$files = $folder->getById(intval($row['file_id']));
		if (empty($files)) {
			if ($defaultMap) {
				$this->deleteTrackFromDB($row['id']);
			}
			return null;
		}
		$file = array_shift($files);
		if ($file === null || $file->getType() !== \OCP\Files\FileInfo::TYPE_FILE) {
			if ($defaultMap) {
				$this->deleteTrackFromDB($row['id']);
			}
			return null;
		}

		$path = $userFolder->getRelativePath($file->getPath());
		$isIgnored = false;
		foreach ($ignoredPaths as $ignoredPath) {
			if (str_starts_with($path, $ignoredPath)) {
				$isIgnored = true;
				break;
			}
		}
		if ($isIgnored) {
			return null;
		}

		return [
			'id' => intval($row['id']),
			'file_id' => intval($row['file_id']),
			'color' => $row['color'],
			'metadata' => $row['metadata'],
			'etag' => $row['etag'],
			'path' => $path,
			'isShareable' => $file->isShareable(),
			'isDeletable' => $file->isDeletable(),
			'isUpdateable' => $file->isUpdateable(),
			'isReadable' => $file->isReadable(),
			'mtime' => $file->getMTime(),
			'file_name' => $file->getName(),
			'file_path' => $userFolder->getRelativePath($file->getPath()),
		];
	}


	/**
	 * @param string $userId
	 */
	public function getTracksFromDB($userId, $folder = null, bool $respectNomediaAndNoimage = true, bool $hideTracksOnCustomMaps = false, bool $hideTracksInMapsFolder = true) {
		$ignoredPaths = $respectNomediaAndNoimage ? $this->getIgnoredPaths($userId, $folder, $hideTracksOnCustomMaps) : [];
		if ($hideTracksInMapsFolder) {
			$ignoredPaths[] = '/Maps';
		}
		$userFolder = $this->root->getUserFolder($userId);
		$tracks = [];
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->select('id', 'file_id', 'color', 'metadata', 'etag')
			->from('maps_tracks', 't')
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();

		if (is_null($folder)) {
			$folder = $userFolder;
		}
		$defaultMap = $folder->getId() === $userFolder->getId();

		// my-maps context
		while ($row = $req->fetch()) {
			$track = $this->dbRowToTrack($row, $folder, $userFolder, $defaultMap, $ignoredPaths);
			if (is_null($track)) {
				continue;
			}
			$tracks[] = $track;
		}
		$req->closeCursor();
		return $tracks;
	}

	/**
	 * @param $userId
	 * @param $folder
	 * @return array
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	private function getIgnoredPaths($userId, $folder = null, $hideImagesOnCustomMaps = true) {
		$ignoredPaths = [];
		$userFolder = $this->root->getUserFolder($userId);
		if (is_null($folder)) {
			$folder = $userFolder;
		}
		$ignoreFileMimetypes = [
			'application/x-nextcloud-noindex',
			'application/x-nextcloud-nomedia',
			'application/x-nextcloud-notrack',
		];
		if ($hideImagesOnCustomMaps) {
			$ignoreFileMimetypes[] = 'application/x-nextcloud-maps';
		}
		$func = function (string $i): SearchComparison {
			return new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'mimetype', $i);
		};
		$excludedNodes = $folder->search(new SearchQuery(
			new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_OR, array_map(
				$func,
				$ignoreFileMimetypes)
			),
			0,
			0,
			[]
		));
		foreach ($excludedNodes as $node) {
			$ignoredPaths[] = $userFolder->getRelativePath($node->getParent()->getPath());
		}
		return $ignoredPaths;
	}

	public function getTrackFromDB($id, $userId = null) {
		$track = null;
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->select('id', 'file_id', 'color', 'metadata', 'etag')
			->from('maps_tracks', 't')
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
			if ($userId !== '' and $userId !== null) {
				$userFolder = $this->root->getUserFolder($userId);
				$files = $userFolder->getById(intval($row['file_id']));
				if (empty($files)) {
					break;
				}
				$file = array_shift($files);
				if ($file === null) {
					break;
				}
				$track = $this->dbRowToTrack($row, $userFolder, $userFolder, true, []);
			} else {
				$track = [
					'id' => intval($row['id']),
					'file_id' => intval($row['file_id']),
					'color' => $row['color'],
					'metadata' => $row['metadata'],
					'etag' => $row['etag'],
					'path' => '',
					'isShareable' => false,
					'isDeletable' => false,
					'isUpdateable' => false,
					'isReadable' => false,
					'mtime' => 0,
					'file_name' => '',
					'file_path' => '',
				];
			}
			break;
		}
		$req->closeCursor();
		return $track;
	}

	public function getTrackByFileIDFromDB($fileId, $userId = null) {
		$track = null;
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->select('id', 'file_id', 'color', 'metadata', 'etag')
			->from('maps_tracks', 't')
			->where(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			);
		if ($userId !== null) {
			$qb->andWhere(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		}
		$req = $qb->execute();

		while ($row = $req->fetch()) {
			if ($userId !== '' and $userId !== null) {
				$userFolder = $this->root->getUserFolder($userId);
				$files = $userFolder->getById(intval($row['file_id']));
				if (empty($files)) {
					break;
				}
				$file = array_shift($files);
				if ($file === null) {
					break;
				}
				$track = $this->dbRowToTrack($row, $userFolder, $userFolder, true, []);
			} else {
				$track = [
					'id' => intval($row['id']),
					'file_id' => intval($row['file_id']),
					'color' => $row['color'],
					'metadata' => $row['metadata'],
					'etag' => $row['etag'],
					'path' => '',
					'isShareable' => false,
					'isDeletable' => false,
					'isUpdateable' => false,
					'isReadable' => false,
					'mtime' => 0,
					'file_name' => '',
					'file_path' => '',
				];
			}
			break;
		}
		$req->closeCursor();
		return $track;
	}

	public function addTrackToDB($userId, $fileId, $file) {
		$metadata = '';
		$etag = $file->getEtag();
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->insert('maps_tracks')
			->values([
				'user_id' => $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
				'file_id' => $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT),
				'metadata' => $qb->createNamedParameter($metadata, IQueryBuilder::PARAM_STR),
				'etag' => $qb->createNamedParameter($etag, IQueryBuilder::PARAM_STR)
			]);
		$req = $qb->execute();
		$trackId = $qb->getLastInsertId();
		return $trackId;
	}

	public function editTrackInDB($id, $color, $metadata, $etag) {
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->update('maps_tracks');
		if ($color !== null) {
			$qb->set('color', $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR));
		}
		if ($metadata !== null) {
			$qb->set('metadata', $qb->createNamedParameter($metadata, IQueryBuilder::PARAM_STR));
		}
		if ($etag !== null) {
			$qb->set('etag', $qb->createNamedParameter($etag, IQueryBuilder::PARAM_STR));
		}
		$qb->where(
			$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
		);
		$req = $qb->execute();
	}

	public function deleteByFileId($fileId) {
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->delete('maps_tracks')
			->where(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->execute();
	}

	public function deleteByFileIdUserId($fileId, $userId) {
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->delete('maps_tracks')
			->where(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();
		$qb = $this->dbconnection->getQueryBuilder();
	}

	public function deleteTrackFromDB($id) {
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->delete('maps_tracks')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->execute();
		$qb = $this->dbconnection->getQueryBuilder();
	}

	public function deleteAllTracksFromDB($userId) {
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->delete('maps_tracks')
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();
		$qb = $this->dbconnection->getQueryBuilder();
	}

	public function deleteTracksFromDB($ids, $userId) {
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->delete('maps_tracks')
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		if (count($ids) > 0) {
			$or = $qb->expr()->orx();
			foreach ($ids as $id) {
				$or->add($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
			}
			$qb->andWhere($or);
		} else {
			return;
		}
		$req = $qb->execute();
		$qb = $this->dbconnection->getQueryBuilder();
	}

	public function generateTrackMetadata($file) {
		$DISTANCE_BETWEEN_SHORT_POINTS = 300;
		$STOPPED_SPEED_THRESHOLD = 0.9;

		$name = $file->getName();
		$gpx_content = $file->getContent();

		$lat = '0';
		$lon = '0';
		$total_distance = 0;
		$total_duration = 0;
		$date_begin = null;
		$date_end = null;

		$distAccCumulEle = 0;
		$pos_elevation = 0;
		$neg_elevation = 0;
		$min_elevation = null;
		$max_elevation = null;

		$avg_speed = '???';
		$moving_time = 0;
		$moving_distance = 0;
		$stopped_distance = 0;
		$moving_max_speed = 0;
		$moving_avg_speed = 0;
		$stopped_time = 0;
		$north = null;
		$south = null;
		$east = null;
		$west = null;
		$trackNameList = [];
		$linkurl = '';
		$linktext = '';

		$pointsBySegment = [];
		$lastTime = null;

		try {
			$gpx = new \SimpleXMLElement($gpx_content);
		} catch (\Throwable $e) {
			$this->logger->error(
				'Exception in '.$name.' gpx parsing : '.$e->getMessage(),
				['app' => 'maps']
			);
			return null;
		}

		if (count($gpx->trk) === 0 and count($gpx->rte) === 0 and count($gpx->wpt) === 0) {
			$this->logger->error(
				'Nothing to parse in '.$name.' gpx file',
				['app' => 'maps']
			);
			return null;
		}

		// METADATA
		if (!empty($gpx->metadata) and !empty($gpx->metadata->link)) {
			$linkurl = $gpx->metadata->link['href'];
			if (!empty($gpx->metadata->link->text)) {
				$linktext = $gpx->metadata->link->text;
			}
		}

		// TRACKS
		foreach ($gpx->trk as $track) {
			$trackname = str_replace("\n", '', $track->name);
			if (empty($trackname)) {
				$trackname = '';
			}
			$trackname = str_replace('"', "'", $trackname);
			$trackNameList[] = sprintf('"%s"', $trackname);
			foreach ($track->trkseg as $segment) {
				$lastPoint = null;
				$lastTime = null;
				$pointIndex = 0;
				$pointsBySegment[] = $segment->trkpt;
				foreach ($segment->trkpt as $point) {
					if (empty($point['lat']) or empty($point['lon'])) {
						continue;
					}
					if (empty($point->ele)) {
						$pointele = null;
					} else {
						$pointele = floatval($point->ele);
					}
					if (empty($point->time)) {
						$pointtime = null;
					} else {
						$pointtime = new \DateTime($point->time);
					}
					if ($lastPoint !== null and (!empty($lastPoint->ele))) {
						$lastPointele = floatval($lastPoint->ele);
					} else {
						$lastPointele = null;
					}
					if ($lastPoint !== null and (!empty($lastPoint->time))) {
						$lastTime = new \DateTime($lastPoint->time);
					} else {
						$lastTime = null;
					}
					if ($lastPoint !== null) {
						$distToLast = distance($lastPoint, $point);
					} else {
						$distToLast = null;
					}
					$pointlat = floatval($point['lat']);
					$pointlon = floatval($point['lon']);
					if ($pointIndex === 0) {
						if ($lat === '0' and $lon === '0') {
							$lat = $pointlat;
							$lon = $pointlon;
						}
						if ($pointtime !== null and ($date_begin === null or $pointtime < $date_begin)) {
							$date_begin = $pointtime;
						}
						$downBegin = $pointele;
						if ($north === null) {
							$north = $pointlat;
							$south = $pointlat;
							$east = $pointlon;
							$west = $pointlon;
						}
					}

					if ($pointlat > $north) {
						$north = $pointlat;
					}
					if ($pointlat < $south) {
						$south = $pointlat;
					}
					if ($pointlon > $east) {
						$east = $pointlon;
					}
					if ($pointlon < $west) {
						$west = $pointlon;
					}
					if ($pointele !== null and ($min_elevation === null or $pointele < $min_elevation)) {
						$min_elevation = $pointele;
					}
					if ($pointele !== null and ($max_elevation === null or $pointele > $max_elevation)) {
						$max_elevation = $pointele;
					}
					if ($lastPoint !== null and $pointtime !== null and $lastTime !== null) {
						$t = abs($lastTime->getTimestamp() - $pointtime->getTimestamp());

						$speed = 0;
						if ($t > 0) {
							$speed = $distToLast / $t;
							$speed = $speed / 1000;
							$speed = $speed * 3600;
						}

						if ($speed <= $STOPPED_SPEED_THRESHOLD) {
							$stopped_time += $t;
							$stopped_distance += $distToLast;
						} else {
							$moving_time += $t;
							$moving_distance += $distToLast;
						}
					}
					if ($lastPoint !== null) {
						$total_distance += $distToLast;
					}

					$lastPoint = $point;
					$pointIndex += 1;
				}

				if ($lastTime !== null and ($date_end === null or $lastTime > $date_end)) {
					$date_end = $lastTime;
				}
			}

		}

		# ROUTES
		foreach ($gpx->rte as $route) {
			$routename = str_replace("\n", '', $route->name);
			if (empty($routename)) {
				$routename = '';
			}
			$routename = str_replace('"', "'", $routename);
			$trackNameList[] = sprintf('"%s"', $routename);

			$lastPoint = null;
			$lastTime = null;
			$pointIndex = 0;
			$pointsBySegment[] = $route->rtept;
			foreach ($route->rtept as $point) {
				if (empty($point['lat']) or empty($point['lon'])) {
					continue;
				}
				if (empty($point->ele)) {
					$pointele = null;
				} else {
					$pointele = floatval($point->ele);
				}
				if (empty($point->time)) {
					$pointtime = null;
				} else {
					$pointtime = new \DateTime($point->time);
				}
				if ($lastPoint !== null and (!empty($lastPoint->ele))) {
					$lastPointele = floatval($lastPoint->ele);
				} else {
					$lastPointele = null;
				}
				if ($lastPoint !== null and (!empty($lastPoint->time))) {
					$lastTime = new \DateTime($lastPoint->time);
				} else {
					$lastTime = null;
				}
				if ($lastPoint !== null) {
					$distToLast = distance($lastPoint, $point);
				} else {
					$distToLast = null;
				}
				$pointlat = floatval($point['lat']);
				$pointlon = floatval($point['lon']);
				if ($pointIndex === 0) {
					if ($lat === '0' and $lon === '0') {
						$lat = $pointlat;
						$lon = $pointlon;
					}
					if ($pointtime !== null and ($date_begin === null or $pointtime < $date_begin)) {
						$date_begin = $pointtime;
					}
					$downBegin = $pointele;
					if ($north === null) {
						$north = $pointlat;
						$south = $pointlat;
						$east = $pointlon;
						$west = $pointlon;
					}
				}

				if ($pointlat > $north) {
					$north = $pointlat;
				}
				if ($pointlat < $south) {
					$south = $pointlat;
				}
				if ($pointlon > $east) {
					$east = $pointlon;
				}
				if ($pointlon < $west) {
					$west = $pointlon;
				}
				if ($pointele !== null and ($min_elevation === null or $pointele < $min_elevation)) {
					$min_elevation = $pointele;
				}
				if ($pointele !== null and ($max_elevation === null or $pointele > $max_elevation)) {
					$max_elevation = $pointele;
				}
				if ($lastPoint !== null and $pointtime !== null and $lastTime !== null) {
					$t = abs($lastTime->getTimestamp() - $pointtime->getTimestamp());

					$speed = 0;
					if ($t > 0) {
						$speed = $distToLast / $t;
						$speed = $speed / 1000;
						$speed = $speed * 3600;
					}

					if ($speed <= $STOPPED_SPEED_THRESHOLD) {
						$stopped_time += $t;
						$stopped_distance += $distToLast;
					} else {
						$moving_time += $t;
						$moving_distance += $distToLast;
					}
				}
				if ($lastPoint !== null) {
					$total_distance += $distToLast;
				}

				$lastPoint = $point;
				$pointIndex += 1;
			}

			if ($lastTime !== null and ($date_end === null or $lastTime > $date_end)) {
				$date_end = $lastTime;
			}
		}

		# TOTAL STATS : duration, avg speed, avg_moving_speed
		if ($date_end !== null and $date_begin !== null) {
			$totsec = abs($date_end->getTimestamp() - $date_begin->getTimestamp());
			$total_duration = $totsec;
			if ($totsec === 0) {
				$avg_speed = 0;
			} else {
				$avg_speed = $total_distance / $totsec;
				$avg_speed = $avg_speed / 1000;
				$avg_speed = $avg_speed * 3600;
				$avg_speed = sprintf('%.2f', $avg_speed);
			}
		}

		// determination of real moving average speed from moving time
		$moving_avg_speed = 0;
		$moving_pace = 0;
		if ($moving_time > 0) {
			$moving_avg_speed = $total_distance / $moving_time;
			$moving_avg_speed = $moving_avg_speed / 1000;
			$moving_avg_speed = $moving_avg_speed * 3600;
			$moving_avg_speed = sprintf('%.2f', $moving_avg_speed);
			// pace in minutes/km
			$moving_pace = $moving_time / $total_distance;
			$moving_pace = $moving_pace / 60;
			$moving_pace = $moving_pace * 1000;
			$moving_pace = sprintf('%.2f', $moving_pace);
		}

		# WAYPOINTS
		foreach ($gpx->wpt as $waypoint) {
			$waypointlat = floatval($waypoint['lat']);
			$waypointlon = floatval($waypoint['lon']);

			if ($lat === '0' and $lon === '0') {
				$lat = $waypointlat;
				$lon = $waypointlon;
			}

			if ($north === null or $waypointlat > $north) {
				$north = $waypointlat;
			}
			if ($south === null or $waypointlat < $south) {
				$south = $waypointlat;
			}
			if ($east === null or $waypointlon > $east) {
				$east = $waypointlon;
			}
			if ($west === null or $waypointlon < $west) {
				$west = $waypointlon;
			}
		}

		$trackNameList = '[' . implode(',', $trackNameList) . ']';
		if ($north === null) {
			$north = 0;
		}
		if ($south === null) {
			$south = 0;
		}
		if ($east === null) {
			$east = 0;
		}
		if ($west === null) {
			$west = 0;
		}

		// we filter all segments by distance
		$distFilteredPointsBySegment = [];
		foreach ($pointsBySegment as $points) {
			$distFilteredPointsBySegment[] = $this->getDistanceFilteredPoints($points);
		}
		// and we get points with elevation and time for each segment
		$pointsWithElevationBySegment = [];
		$pointsWithTimeBySegment = [];
		foreach ($distFilteredPointsBySegment as $points) {
			$pointsWithTimeOneSegment = [];
			$pointsWithElevationOneSegment = [];
			foreach ($points as $point) {
				if (!empty($point->ele)) {
					$pointsWithElevationOneSegment[] = $point;
				}
				if (!empty($point->time)) {
					$pointsWithTimeOneSegment[] = $point;
				}
			}
			$pointsWithElevationBySegment[] = $pointsWithElevationOneSegment;
			$pointsWithTimeBySegment[] = $pointsWithTimeOneSegment;
		}
		// process elevation gain/loss
		$pos_elevation = 0;
		$neg_elevation = 0;
		foreach ($pointsWithElevationBySegment as $points) {
			$gainLoss = $this->getElevationGainLoss($points);
			$pos_elevation += $gainLoss[0];
			$neg_elevation += $gainLoss[1];
		}
		$pos_elevation = number_format($pos_elevation, 2, '.', '');
		$neg_elevation = number_format($neg_elevation, 2, '.', '');
		// process max speed from distance filtered points
		$maxSpeed = 0;
		foreach ($pointsWithTimeBySegment as $points) {
			$segmentMaxSpeed = $this->getMaxSpeed($points);
			if ($segmentMaxSpeed > $maxSpeed) {
				$maxSpeed = $segmentMaxSpeed;
			}
		}

		$result = sprintf('{"lat":%s, "lng":%s, "name": "%s", "distance": %.3f, "duration": %d, "begin": %d, "end": %d, "posel": %.2f, "negel": %.2f, "minel": %.2f, "maxel": %.2f, "maxspd": %.2f, "avgspd": %.2f, "movtime": %d, "stptime": %d, "movavgspd": %s, "n": %.8f, "s": %.8f, "e": %.8f, "w": %.8f, "trnl": %s, "lnkurl": "%s", "lnktxt": "%s", "movpace": %.2f}',
			$lat,
			$lon,
			str_replace('"', "'", $name),
			$total_distance,
			$total_duration,
			($date_begin !== null) ? $date_begin->getTimestamp() : -1,
			($date_end !== null) ? $date_end->getTimestamp() : -1,
			$pos_elevation,
			$neg_elevation,
			($min_elevation !== null) ? $min_elevation : -1000,
			($max_elevation !== null) ? $max_elevation : -1000,
			$maxSpeed,
			$avg_speed,
			$moving_time,
			$stopped_time,
			$moving_avg_speed,
			$north,
			$south,
			$east,
			$west,
			(strlen($trackNameList) < 200) ? $trackNameList : '[]',
			str_replace('"', "'", $linkurl),
			str_replace('"', "'", $linktext),
			$moving_pace
		);
		return $result;
	}

	private function getDistanceFilteredPoints($points) {
		$DISTANCE_THRESHOLD = 10;

		$distFilteredPoints = [];
		if (count($points) > 0) {
			array_push($distFilteredPoints, $points[0]);
			$lastPoint = $points[0];
			foreach ($points as $point) {
				if (distance($lastPoint, $point) >= $DISTANCE_THRESHOLD) {
					array_push($distFilteredPoints, $point);
					$lastPoint = $point;
				}
			}
		}

		return $distFilteredPoints;
	}

	private function getMaxSpeed($points) {
		$maxSpeed = 0;

		if (count($points) > 0) {
			$lastPoint = $points[0];
			$lastTime = new \DateTime($lastPoint->time);
			foreach ($points as $point) {
				$time = new \DateTime($point->time);
				$timeDelta = abs($lastTime->getTimestamp() - $time->getTimestamp());
				if ($timeDelta > 0) {
					$distance = distance($point, $lastPoint);
					$speed = $distance / $timeDelta;
					$speed = $speed / 1000;
					$speed = $speed * 3600;
					if ($speed > $maxSpeed) {
						$maxSpeed = $speed;
					}
				}
				$lastTime = $time;
				$lastPoint = $point;
			}
		}

		return $maxSpeed;
	}

	/**
	 * inspired by https://www.gpsvisualizer.com/tutorials/elevation_gain.html
	 */
	private function getElevationGainLoss($points) {
		$ELEVATION_THRESHOLD = 6;
		$gain = 0;
		$loss = 0;

		// then calculate elevation gain with elevation threshold
		if (count($points) > 0) {
			$validPoint = $points[0];
			foreach ($points as $point) {
				$deniv = floatval($point->ele) - floatval($validPoint->ele);
				if ($deniv >= $ELEVATION_THRESHOLD) {
					$gain += $deniv;
					$validPoint = $point;
				} elseif (-$deniv >= $ELEVATION_THRESHOLD) {
					$loss -= $deniv;
					$validPoint = $point;
				}
			}
		}

		return [$gain, $loss];
	}
}

/*
 * return distance between these two gpx points in meters
 */
function distance($p1, $p2) {

	$lat1 = (float)$p1['lat'];
	$long1 = (float)$p1['lon'];
	$lat2 = (float)$p2['lat'];
	$long2 = (float)$p2['lon'];

	if ($lat1 === $lat2 and $long1 === $long2) {
		return 0;
	}

	// Convert latitude and longitude to
	// spherical coordinates in radians.
	$degrees_to_radians = pi() / 180.0;

	// phi = 90 - latitude
	$phi1 = (90.0 - $lat1) * $degrees_to_radians;
	$phi2 = (90.0 - $lat2) * $degrees_to_radians;

	// theta = longitude
	$theta1 = $long1 * $degrees_to_radians;
	$theta2 = $long2 * $degrees_to_radians;

	// Compute spherical distance from spherical coordinates.

	// For two locations in spherical coordinates
	// (1, theta, phi) and (1, theta, phi)
	// cosine( arc length ) =
	//    sin phi sin phi' cos(theta-theta') + cos phi cos phi'
	// distance = rho * arc length

	$cos = (sin($phi1) * sin($phi2) * cos($theta1 - $theta2) +
		   cos($phi1) * cos($phi2));
	// why some cosinus are > than 1 ?
	if ($cos > 1.0) {
		$cos = 1.0;
	}
	$arc = acos($cos);

	// Remember to multiply arc by the radius of the earth
	// in your favorite set of units to get length.
	return $arc * 6371000;
}
