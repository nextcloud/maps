<?php

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Piotr Bator <prbator@gmail.com>
 * @copyright Piotr Bator 2017
 */

namespace OCA\Maps\Service;

use OC\Files\Search\SearchBinaryOperator;
use OC\Files\Search\SearchComparison;
use OC\Files\Search\SearchQuery;
use OC\User\NoUserException;
use OCA\Maps\DB\GeophotoMapper;
use OCP\DB\Exception;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchComparison;
use OCP\ICacheFactory;
use OCP\IL10N;
use OCP\IPreview;

class GeophotoService {

	private $l10n;
	private $root;
	private $photoMapper;
	private $preview;
	private $tracksService;
	private $timeorderedPointSets;
	private $devicesService;
	private $cacheFactory;
	private $userId;
	private \OCP\ICache $photosCache;
	private \OCP\ICache $timeOrderedPointSetsCache;
	private \OCP\ICache $backgroundJobCache;

	public function __construct(
		IRootFolder $root,
		IL10N $l10n,
		GeophotoMapper $photoMapper,
		IPreview $preview,
		TracksService $tracksService,
		DevicesService $devicesService,
		ICacheFactory $cacheFactory,
		$userId) {
		$this->root = $root;
		$this->l10n = $l10n;
		$this->photoMapper = $photoMapper;
		$this->preview = $preview;
		$this->tracksService = $tracksService;
		$this->timeorderedPointSets = null;
		$this->userId = $userId;
		$this->devicesService = $devicesService;
		$this->cacheFactory = $cacheFactory;
		$this->photosCache = $this->cacheFactory->createDistributed('maps:photos');
		$this->timeOrderedPointSetsCache = $this->cacheFactory->createDistributed('maps:time-ordered-point-sets');
		$this->backgroundJobCache = $this->cacheFactory->createDistributed('maps:background-jobs');
	}

	/**
	 * @param string $userId
	 * @return bool
	 */
	public function clearCache(string $userId = ''): bool {
		$a = $this->photosCache->clear($userId);
		$b = $this->timeOrderedPointSetsCache->clear($userId);
		$c = $this->backgroundJobCache->clear('recentlyAdded:'.$userId);
		$d = $this->backgroundJobCache->clear('recentlyUpdated:'.$userId);
		return $a and $b and $c and $d;
	}

	/**
	 * @param string $userId
	 * @param ?Folder $folder =null
	 * @param bool $respectNomediaAndNoimage =true
	 * @param bool $hideImagesOnCustomMaps =true
	 * @param bool $hideImagesInMapsFolder
	 * @return array
	 * @throws Exception
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getAll(string $userId, $folder = null, bool $respectNomediaAndNoimage = true, bool $hideImagesOnCustomMaps = false, bool $hideImagesInMapsFolder = true): array {
		$userFolder = $this->getFolderForUser($userId);
		if (is_null($folder)) {
			$folder = $userFolder;
		}
		$key = $userId . ':' . $userFolder->getRelativePath($folder->getPath()) . ':' . (string)$respectNomediaAndNoimage . ':' . (string)$hideImagesOnCustomMaps . ':' . (string)$hideImagesInMapsFolder;
		$filesById = $this->photosCache->get($key);
		if ($filesById === null) {
			$ignoredPaths = $respectNomediaAndNoimage ? $this->getIgnoredPaths($userId, $folder, $hideImagesOnCustomMaps) : [];
			if ($hideImagesInMapsFolder) {
				$ignoredPaths[] = '/Maps';
			}
			$photoEntities = $this->photoMapper->findAll($userId);

			$filesById = [];
			$cache = $folder->getStorage()->getCache();
			$previewEnableMimetypes = $this->getPreviewEnabledMimetypes();
			foreach ($photoEntities as $photoEntity) {
				$cacheEntry = $cache->get($photoEntity->getFileId());
				if ($cacheEntry) {
					// this path is relative to owner's storage
					//$path = $cacheEntry->getPath();
					//but we want it relative to current user's storage
					$files = $folder->getById($photoEntity->getFileId());
					if (empty($files)) {
						continue;
					}
					$file = array_shift($files);
					if ($file === null) {
						continue;
					}
					$path = $userFolder->getRelativePath($file->getPath());
					$isIgnored = false;
					foreach ($ignoredPaths as $ignoredPath) {
						if (str_starts_with($path, $ignoredPath)) {
							$isIgnored = true;
							break;
						}
					}
					if (!$isIgnored) {
						$isRoot = $file === $userFolder;

						$file_object = new \stdClass();
						$file_object->fileId = $photoEntity->getFileId();
						$file_object->fileid = $file_object->fileId;
						$file_object->lat = $photoEntity->getLat();
						$file_object->lng = $photoEntity->getLng();
						$file_object->dateTaken = $photoEntity->getDateTaken() ?? \time();
						$file_object->basename = $isRoot ? '' : $file->getName();
						$file_object->filename = $this->normalizePath($path);
						$file_object->etag = $cacheEntry->getEtag();
						//Not working for NC21 as Viewer requires String representation of permissions
						//                $file_object->permissions = $file->getPermissions();
						$file_object->type = $file->getType();
						$file_object->mime = $file->getMimetype();
						$file_object->lastmod = $file->getMTime();
						$file_object->size = $file->getSize();
						$file_object->path = $path;
						$file_object->isReadable = $file->isReadable();
						$file_object->isUpdateable = $file->isUpdateable();
						$file_object->isShareable = $file->isShareable();
						$file_object->isDeletable = $file->isDeletable();
						$file_object->hasPreview = in_array($cacheEntry->getMimeType(), $previewEnableMimetypes);
						$filesById[] = $file_object;
					}
				}
			}
			$this->photosCache->set($key, $filesById, 60 * 60 * 24);
		}
		return $filesById;
	}

	/**
	 * @param string $userId
	 * @param ?Folder $folder =null
	 * @param bool $respectNomediaAndNoimage
	 * @param bool $hideImagesOnCustomMaps
	 * @param bool $hideImagesInMapsFolder
	 * @param string|null $timezone locale time zone used by images
	 * @param int $limit
	 * @param int $offset
	 * @return array with geodatas of all nonLocalizedPhotos
	 * @throws Exception
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getNonLocalized(string $userId, $folder = null, bool $respectNomediaAndNoimage = true, bool $hideImagesOnCustomMaps = false, bool $hideImagesInMapsFolder = true, ?string $timezone = null, int $limit = 250, int $offset = 0): array {
		$userFolder = $this->getFolderForUser($userId);
		if (is_null($folder)) {
			$folder = $userFolder;
		}

		$ignoredPaths = $respectNomediaAndNoimage ? $this->getIgnoredPaths($userId, $folder, $hideImagesOnCustomMaps) : [];
		if ($hideImagesInMapsFolder) {
			$ignoredPaths[] = '/Maps';
		}
		$this->loadTimeorderedPointSets($userId, $folder, $respectNomediaAndNoimage, $hideImagesOnCustomMaps, $hideImagesInMapsFolder);
		$photoEntities = $this->photoMapper->findAllNonLocalized($userId, $limit, $offset);
		$suggestionsBySource = [];
		$cache = $folder->getStorage()->getCache();
		$previewEnableMimetypes = $this->getPreviewEnabledMimetypes();
		if (!is_null($timezone)) {
			$tz = new \DateTimeZone($timezone);
		} else {
			$tz = new \DateTimeZone(\date_default_timezone_get());
		}
		foreach ($photoEntities as $photoEntity) {
			$cacheEntry = $cache->get($photoEntity->getFileId());
			if ($cacheEntry) {
				// this path is relative to owner's storage
				//$path = $cacheEntry->getPath();
				// but we want it relative to current user's storage
				$files = $folder->getById($photoEntity->getFileId());
				if (empty($files)) {
					continue;
				}
				$file = array_shift($files);
				if ($file === null) {
					continue;
				}
				$path = $userFolder->getRelativePath($file->getPath());
				$isIgnored = false;
				foreach ($ignoredPaths as $ignoredPath) {
					if (str_starts_with($path, $ignoredPath)) {
						$isIgnored = true;
						break;
					}
				}
				if (!$isIgnored) {
					$isRoot = $file === $userFolder;

					//Unfortunately Exif stores the local and not the UTC time. There is no way to get the timezone, therefore it has to be given by the user.
					$date = $photoEntity->getDateTaken() ?? \time();

					$dateWithTimezone = new \DateTime(gmdate('Y-m-d H:i:s', $date), $tz);
					$locations = $this->getLocationGuesses($dateWithTimezone->getTimestamp());
					foreach ($locations as $key => $location) {
						$file_object = new \stdClass();
						$file_object->fileId = $photoEntity->getFileId();
						$file_object->fileid = $file_object->fileId;
						$file_object->path = $this->normalizePath($path);
						$file_object->hasPreview = in_array($cacheEntry->getMimeType(), $previewEnableMimetypes);
						$file_object->lat = $location[0];
						$file_object->lng = $location[1];
						$file_object->dateTaken = $date;
						$file_object->basename = $isRoot ? '' : $file->getName();
						$file_object->filename = $this->normalizePath($path);
						$file_object->etag = $cacheEntry->getEtag();
						//Not working for NC21 as Viewer requires String representation of permissions
						//                $file_object->permissions = $file->getPermissions();
						$file_object->type = $file->getType();
						$file_object->mime = $file->getMimetype();
						$file_object->lastmod = $file->getMTime();
						$file_object->size = $file->getSize();
						$file_object->path = $path;
						$file_object->isReadable = $file->isReadable();
						$file_object->isUpdateable = $file->isUpdateable();
						$file_object->isShareable = $file->isShareable();
						$file_object->isDeletable = $file->isDeletable();
						$file_object->hasPreview = in_array($cacheEntry->getMimeType(), $previewEnableMimetypes);
						$file_object->trackOrDeviceId = $key;
						if (!array_key_exists($key, $suggestionsBySource)) {
							$suggestionsBySource[$key] = [];
						}
						$suggestionsBySource[$key][] = $file_object;
					}
				}
			}
		}
		return $suggestionsBySource;
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
		$userFolder = $this->getFolderForUser($userId);
		if (is_null($folder)) {
			$folder = $userFolder;
		}
		$ignoreFileMimetypes = [
			'application/x-nextcloud-noindex',
			'application/x-nextcloud-nomedia',
			'application/x-nextcloud-noimage',
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

	/**
	 * returns a array of locations for a given date
	 *
	 * @param $dateTaken int
	 * @return array
	 */
	private function getLocationGuesses(int $dateTaken): array {
		$locations = [];
		foreach (($this->timeorderedPointSets ?? []) as $key => $timeordedPointSet) {
			$location = $this->getLocationFromSequenceOfPoints($dateTaken, $timeordedPointSet);
			if (!is_null($location)) {
				$locations[$key] = $location;
			}
		}
		return $locations;

	}

	/*
	 * Timeordered Point sets is an Array of Arrays with time => location as key=>value pair, which are orderd by the key.
	 * This function loads this Arrays from all Track files of the user.
	 */
	private function loadTimeorderedPointSets(string $userId, $folder = null, bool $respectNomediaAndNoimage = true, bool $hideTracksOnCustomMaps = false, bool $hideTracksInMapsFolder = true): void {
		$key = $userId . ':' . (string)$respectNomediaAndNoimage . ':' . (string)$hideTracksOnCustomMaps . ':' . (string)$hideTracksInMapsFolder;
		$this->timeorderedPointSets = $this->timeOrderedPointSetsCache->get($key);
		if (is_null($this->timeorderedPointSets)) {
			$userFolder = $this->getFolderForUser($userId);
			foreach ($this->tracksService->getTracksFromDB($userId, $folder, $respectNomediaAndNoimage, $hideTracksOnCustomMaps, $hideTracksInMapsFolder) as $gpxfile) {
				$res = $userFolder->getById($gpxfile['file_id']);
				if (is_array($res) and count($res) > 0) {
					$file = array_shift($res);
					if ($file instanceof File) {
						foreach ($this->getTracksFromGPX($file->getContent()) as $i => $track) {
							$this->timeorderedPointSets['track:' . $gpxfile['id'] . ':' . $i] = $this->getTimeorderdPointsFromTrack($track);
						}
					}
				}
			}
			foreach ($this->devicesService->getDevicesFromDB($userId) as $device) {
				$device_points = $this->devicesService->getDeviceTimePointsFromDb($userId, $device['id']);
				$this->timeorderedPointSets['device:' . $device['id']] = $device_points;
			}
			$this->timeOrderedPointSetsCache->set($key, $this->timeorderedPointSets);
		}
	}

	/*
	 * A GPX file can contain multiple tracks this function returns all Tracks from a given sting containing GPX encoded information.
	 * @param $content
	 * @return array
	 */
	private function getTracksFromGPX($content): array {
		$tracks = [];
		$gpx = simplexml_load_string($content);
		foreach ($gpx->trk as $trk) {
			$tracks[] = $trk;
		}
		return $tracks;
	}

	/*
	 * Loads all trackpoints from a given $track SimpleXMLObject. And stores them in a time=>location stuctured array which is sorted by the key.
	 * @param $track
	 * @return array
	 */
	private function getTimeorderdPointsFromTrack($track): array {
		$points = [];
		foreach ($track->trkseg as $seg) {
			foreach ($seg->trkpt as $pt) {
				$points[strtotime($pt->time)] = [(string)$pt['lat'],(string)$pt['lon']];
			}
		}
		foreach ($track->trkpt as $pt) {
			$points[strtotime($pt->time)] = [(string)$pt['lat'],(string)$pt['lon']];
		}

		$foo = ksort($points);
		return $points;
	}

	/**
	 * @param int $timeUTC
	 * @param float $lat
	 * @param float $lng
	 * @return void
	 */
	private function getLocalTime(int $timeUTC, float $lat, float $lng) {

	}

	/**
	 * @param $dateTaken int timestamp of the picture
	 * @param $points array sorted by keys timestamp => [lat, lng]
	 */
	private function getLocationFromSequenceOfPoints(int $dateTaken, array $points): ?array {
		$foo = end($points);
		$end = key($points);
		$foo = reset($points);
		$start = key($points);
		if ($start > $dateTaken or $end < $dateTaken) {
			return null;
		}
		$smaller = null;
		$bigger = null;
		foreach ($points as $time => $locations) {
			if ($time < $dateTaken) {
				$smaller = $time;
			} else {
				$bigger = $time;
				break;
			}
		}
		if (!is_null($smaller) and !is_null($bigger)) {
			$d = $bigger - $smaller;
			$t = ($dateTaken - $smaller) / $d;
			$latd = $points[$bigger][0] - $points[$smaller][0];
			$lngd = $points[$bigger][1] - $points[$smaller][1];
			return [$points[$smaller][0] + $t * $latd, $points[$smaller][1] + $t * $lngd];
		} else {
			return null;
		}
	}

	private function getPreviewEnabledMimetypes(): array {
		$enabledMimeTypes = [];
		foreach (PhotofilesService::PHOTO_MIME_TYPES as $mimeType) {
			if ($this->preview->isMimeSupported($mimeType)) {
				$enabledMimeTypes[] = $mimeType;
			}
		}
		return $enabledMimeTypes;
	}

	private function normalizePath($path) {
		return str_replace('files', '', $path);
	}

	/**
	 * @param string $userId the user id
	 * @return Folder
	 */
	private function getFolderForUser($userId) {
		return $this->root->getUserFolder($userId);
	}

}
