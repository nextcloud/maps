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
use OCP\Files\FileInfo;
use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchComparison;
use OCP\IL10N;
use OCP\Files\IRootFolder;
use OCP\Files\Storage\IStorage;
use OCP\Files\Folder;
use OCP\IPreview;
use OCP\ILogger;


use OCA\Maps\Service\PhotofilesService;
use OCA\Maps\DB\Geophoto;
use OCA\Maps\DB\GeophotoMapper;
use OCA\Maps\Service\TracksService;
use OCA\Maps\Service\DevicesService;

class GeophotoService {

    private $l10n;
    private $root;
    private $photoMapper;
    private $logger;
    private $preview;
    private $tracksService;
    private $timeorderedPointSets;
    private $devicesService;

    public function __construct (ILogger $logger,
                                 IRootFolder $root,
                                 IL10N $l10n,
                                 GeophotoMapper $photoMapper,
                                 IPreview $preview,
                                 TracksService $tracksService,
                                 DevicesService $devicesService,
                                 $userId) {
        $this->root = $root;
        $this->l10n = $l10n;
        $this->photoMapper = $photoMapper;
        $this->logger = $logger;
        $this->preview = $preview;
        $this->tracksService = $tracksService;
        $this->timeorderedPointSets = null;
        $this->userId = $userId;
        $this->devicesService = $devicesService;

    }

	/**
	 * @param string $userId
	 * @param $folder=null
	 * @param bool $respectNomediaAndNoimage=true
	 * @param bool $hideImagesOnCustomMaps=true
	 * @return array
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
     public function getAllFromDB(string $userId, $folder=null, bool $respectNomediaAndNoimage=true, bool $hideImagesOnCustomMaps=true): array {
		$ignoredPaths = $respectNomediaAndNoimage ? $this->getIgnoredPaths($userId, $folder, $hideImagesOnCustomMaps) : [];
        $photoEntities = $this->photoMapper->findAll($userId);
		$userFolder = $this->getFolderForUser($userId);
        if (is_null($folder)) {
            $folder = $this->getFolderForUser($userId);
        }
        $filesById = [];
        $cache = $folder->getStorage()->getCache();
        $previewEnableMimetypes = $this->getPreviewEnabledMimetypes();
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
				$path = $userFolder->getRelativePath( $file->getPath());
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
        shuffle($filesById);
        return $filesById;
    }

	/**
	 * @param string $userId
	 * @param $folder=null
	 * @param bool $respectNomediaAndNoimage
	 * @param bool $hideImagesOnCustomMaps
	 * @return array with geodatas of all nonLocalizedPhotos
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
    public function getNonLocalizedFromDB (string $userId, $folder=null, bool $respectNomediaAndNoimage=true, bool $hideImagesOnCustomMaps=true): array {
		$ignoredPaths = $respectNomediaAndNoimage ? $this->getIgnoredPaths($userId, $folder, $hideImagesOnCustomMaps) : [];
        $foo = $this->loadTimeorderedPointSets($userId, $folder, $respectNomediaAndNoimage, $hideImagesOnCustomMaps);
        $photoEntities = $this->photoMapper->findAllNonLocalized($userId);
		$userFolder = $this->getFolderForUser($userId);
		if (is_null($folder)) {
			$folder = $userFolder;
		}
        $filesById = [];
        $cache = $folder->getStorage()->getCache();
        $previewEnableMimetypes = $this->getPreviewEnabledMimetypes();
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
				$path = $userFolder->getRelativePath( $file->getPath());
				$isIgnored = false;
				foreach ($ignoredPaths as $ignoredPath) {
					if (str_starts_with($path, $ignoredPath)) {
						$isIgnored = true;
						break;
					}
				}
				if (!$isIgnored) {
					$isRoot = $file === $userFolder;

					$date = $photoEntity->getDateTaken() ?? \time();
					$locations = $this->getLocationGuesses($date);
					foreach ($locations as $location) {
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
						$file_object->hasPreview = in_array($cacheEntry->getMimeType(), $previewEnableMimetypes);
						$filesById[] = $file_object;
					}
				}
            }

        }
        shuffle($filesById);
        return $filesById;
    }

	/**
	 * @param $userId
	 * @param $folder
	 * @return array
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	private function getIgnoredPaths($userId, $folder=null, $hideImagesOnCustomMaps){
		$ignoredPaths = [];
		$folder = $this->getFolderForUser($userId);
		if (is_null($folder)) {
			$folder = $this->getFolderForUser($userId);
		}
		$ignoreMarkerFiles = [
			'.nomedia',
			'.noimage',
			'.noindex',
		];
		if ($hideImagesOnCustomMaps) {
			$ignoreMarkerFiles[] = '.maps';
		}
		$func = function(string $i): SearchComparison {
			return new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'name', $i);
		};
		$excludedNodes = $folder->search(new SearchQuery(
			new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_AND, [
				new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'mimetype', 'application/octet-stream'),
				new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_OR, array_map(
					$func,
					$ignoreMarkerFiles)
				),
			]),
			0,
			0,
			[]
		));
		foreach($excludedNodes as $node) {
			$ignoredPaths[] = $folder->getRelativePath($node->getParent()->getPath());
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
        foreach (($this->timeorderedPointSets ?? []) as $timeordedPointSet) {
            $location = $this->getLocationFromSequenceOfPoints($dateTaken,$timeordedPointSet);
            if (!is_null($location)) {
                $locations[] = $location;
            }
        }
        return $locations;

    }

    /*
     * Timeorderd Point sets is an Array of Arrays with time => location as key=>value pair, which are orderd by the key.
     * This function loads this Arrays from all Track files of the user.
     */
    private function loadTimeorderedPointSets(string $userId, $folder=null, bool $respectNomediaAndNoimage=true, bool $hideImagesOnCustomMaps=true) {
        $userFolder = $this->getFolderForUser($userId);
        foreach ($this->tracksService->getTracksFromDB($userId, $folder, $respectNomediaAndNoimage, $hideImagesOnCustomMaps) as $gpxfile) {
            $res = $userFolder->getById($gpxfile['file_id']);
            if (is_array($res) and count($res) > 0) {
                $file = array_shift($res);
                if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
                    foreach ($this->getTracksFromGPX($file->getContent()) as $track) {
                        $this->timeorderedPointSets[] = $this->getTimeorderdPointsFromTrack($track);
                    }
                }
            }
        }
        foreach ($this->devicesService->getDevicesFromDB($userId) as $device) {
            $device_points = $this->devicesService->getDevicePointsFromDB($userId, $device['id']);
            $points = [];
            foreach ($device_points as $pt) {
                $points[$pt['timestamp']] = [$pt['lat'], $pt['lng']];
            }
            $foo = ksort($points);
            $this->timeorderedPointSets[] = $points;
        }
        return null;
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
                $points[strtotime($pt->time)] = [(string) $pt["lat"],(string) $pt["lon"]];
            }
        }
        foreach ($track->trkpt as $pt) {
            $points[strtotime($pt->time)] = [(string) $pt["lat"],(string) $pt["lon"]];
        }

        $foo = ksort($points);
        return $points;
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
        if ($start > $dateTaken OR $end < $dateTaken) {
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
        if (!is_null($smaller) AND !is_null($bigger)) {
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
        return str_replace("files","", $path);
    }

    /**
     * @param string $userId the user id
     * @return Folder
     */
    private function getFolderForUser ($userId) {
		return $this->root->getUserFolder($userId);
    }

}
