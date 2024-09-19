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

use OCA\Maps\BackgroundJob\AddPhotoJob;
use OCA\Maps\BackgroundJob\UpdatePhotoByFileJob;
use OCA\Maps\DB\Geophoto;
use OCA\Maps\DB\GeophotoMapper;
use OCA\Maps\Helper\ExifDataInvalidException;
use OCA\Maps\Helper\ExifDataNoLocationException;
use OCA\Maps\Helper\ExifGeoData;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\BackgroundJob\IJobList;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\ICacheFactory;
use OCP\IL10N;
use OCP\Share\IManager;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../../vendor/autoload.php';
use lsolesen\pel\PelDataWindow;
use lsolesen\pel\PelEntryAscii;
use lsolesen\pel\PelEntryRational;
use lsolesen\pel\PelExif;
use lsolesen\pel\PelIfd;
use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelTag;
use lsolesen\pel\PelTiff;

class PhotofilesService {

	public const PHOTO_MIME_TYPES = ['image/jpeg', 'image/tiff'];

	private $l10n;
	private $root;
	private $photoMapper;
	private $shareManager;
	private $jobList;
	private ICacheFactory $cacheFactory;
	private \OCP\ICache $photosCache;
	private \OCP\ICache $backgroundJobCache;

	public function __construct(
		private LoggerInterface $logger,
		ICacheFactory $cacheFactory,
		IRootFolder $root,
		IL10N $l10n,
		GeophotoMapper $photoMapper,
		IManager $shareManager,
		IJobList $jobList) {
		$this->root = $root;
		$this->l10n = $l10n;
		$this->photoMapper = $photoMapper;
		$this->shareManager = $shareManager;
		$this->jobList = $jobList;
		$this->cacheFactory = $cacheFactory;
		$this->photosCache = $this->cacheFactory->createDistributed('maps:photos');
		$this->backgroundJobCache = $this->cacheFactory->createDistributed('maps:background-jobs');
	}

	public function rescan($userId, $inBackground = true) {
		$this->photosCache->clear($userId);
		$userFolder = $this->root->getUserFolder($userId);
		$photos = $this->gatherPhotoFiles($userFolder, true);
		$this->photoMapper->deleteAll($userId);
		foreach ($photos as $photo) {
			if ($inBackground) {
				$this->addPhoto($photo, $userId);
			} else {
				$this->addPhotoNow($photo, $userId);
			}
			yield $photo->getPath();
		}
	}

	// add the file for its owner and users that have access
	// check if it's already in DB before adding
	public function addByFile(Node $file) {
		$ownerId = $file->getOwner()->getUID();
		if ($this->isPhoto($file)) {
			$this->addPhoto($file, $ownerId);
			// is the file accessible to other users ?
			$accesses = $this->shareManager->getAccessList($file);
			foreach ($accesses['users'] as $uid) {
				if ($uid !== $ownerId) {
					$this->addPhoto($file, $uid);
				}
			}
			return true;
		} else {
			return false;
		}
	}

	public function addByFileIdUserId($fileId, $userId) {
		$userFolder = $this->root->getUserFolder($userId);
		$files = $userFolder->getById($fileId);
		if (empty($files)) {
			return;
		}
		$file = array_shift($files);
		if ($file !== null and $this->isPhoto($file)) {
			$this->addPhoto($file, $userId);
		}
	}

	public function addByFolderIdUserId($folderId, $userId) {
		$folders = $this->root->getById($folderId);
		if (empty($folders)) {
			return;
		}
		$folder = array_shift($folders);
		if ($folder !== null) {
			$photos = $this->gatherPhotoFiles($folder, true);
			foreach ($photos as $photo) {
				$this->addPhoto($photo, $userId);
			}
		}
	}

	// add all photos of a folder taking care of shared accesses
	public function addByFolder($folder) {
		$photos = $this->gatherPhotoFiles($folder, true);
		foreach ($photos as $photo) {
			$this->addByFile($photo);
		}
	}

	public function updateByFile(Node $file) {
		$this->jobList->add(UpdatePhotoByFileJob::class, ['fileId' => $file->getId(), 'userId' => $file->getOwner()->getUID()]);
	}

	public function updateByFileNow(Node $file) {
		if ($this->isPhoto($file)) {
			$exif = $this->getExif($file);
			if (!is_null($exif)) {
				$ownerId = $file->getOwner()->getUID();
				// in case there is no entry for this file yet (normally there is because non-localized photos are added)
				try {
					$this->photoMapper->findByFileIdUserId($file->getId(), $ownerId);
					$this->updatePhoto($file, $exif);
					$this->photosCache->clear($ownerId);
				} catch (DoesNotExistException $exception) {
					$this->insertPhoto($file, $ownerId, $exif);
				}
			}
		}
	}

	public function deleteByFile(Node $file) {
		$this->photoMapper->deleteByFileId($file->getId());
	}

	// delete photo only if it's not accessible to user anymore
	// it might have been shared multiple times by different users
	public function deleteByFileIdUserId($fileId, $userId) {
		$userFolder = $this->root->getUserFolder($userId);
		$files = $userFolder->getById($fileId);
		if (!is_array($files) or count($files) === 0) {
			$this->photoMapper->deleteByFileIdUserId($fileId, $userId);
			$this->photosCache->clear($userId);
		}
	}


	public function deleteByFolder(Node $folder) {
		$photos = $this->gatherPhotoFiles($folder, true);
		foreach ($photos as $photo) {
			$this->photoMapper->deleteByFileId($photo->getId());
		}
	}

	// delete folder photos only if it's not accessible to user anymore
	public function deleteByFolderIdUserId($folderId, $userId) {
		$userFolder = $this->root->getUserFolder($userId);
		$folders = $userFolder->getById($folderId);
		if (is_array($folders) and count($folders) === 1) {
			$folder = array_shift($folders);
			$photos = $this->gatherPhotoFiles($folder, true);
			foreach ($photos as $photo) {
				$this->photoMapper->deleteByFileIdUserId($photo->getId(), $userId);
			}
			$this->photosCache->clear($userId);
		}
	}

	/**
	 * @param $userId
	 * @return array
	 */
	public function getBackgroundJobStatus($userId): array {
		$add_counter = 0;
		$addJobsRunning = false;

		foreach ($this->jobList->getJobsIterator(AddPhotoJob::class, null, 0) as $job) {
			if ($job->getArgument()['userId'] === $userId) {
				$add_counter += 1;
			}
			$addJobsRunning = true;
		}
		$update_counter = 0;
		$updateJobsRunning = false;

		foreach ($this->jobList->getJobsIterator(UpdatePhotoByFileJob::class, null, 0) as $job) {
			if ($job->getArgument()['userId'] === $userId) {
				$update_counter += 1;
			}
			$updateJobsRunning = true;
		}
		$recentlyAdded = $this->backgroundJobCache->get('recentlyAdded:'.$userId) ?? 0;
		$recentlyUpdated = $this->backgroundJobCache->get('$recentlyUpdated:'.$userId) ?? 0;
		return [
			'addJobsRunning' => $addJobsRunning,
			'addJobsRemainingForUser' => $add_counter,
			'recentlyAdded' => $recentlyAdded,
			'updateJobsRunning' => $updateJobsRunning,
			'updateJobsRemainingForUser' => $update_counter,
			'recentlyUpdated' => $recentlyUpdated
		];
	}

	public function setPhotosFilesCoords($userId, $paths, $lats, $lngs, $directory) {
		if ($directory) {
			return $this->setDirectoriesCoords($userId, $paths, $lats, $lngs);
		} else {
			return $this->setFilesCoords($userId, $paths, $lats, $lngs);
		}
	}

	private function setDirectoriesCoords($userId, $paths, $lats, $lngs) {
		$lat = $lats[0] ?? 0;
		$lng = $lngs[0] ?? 0;
		$userFolder = $this->root->getUserFolder($userId);
		$done = [];
		foreach ($paths as $dirPath) {
			$cleanDirPath = str_replace(['../', '..\\'], '', $dirPath);
			if ($userFolder->nodeExists($cleanDirPath)) {
				$dir = $userFolder->get($cleanDirPath);
				if ($dir->getType() === FileInfo::TYPE_FOLDER) {
					$nodes = $dir->getDirectoryListing();
					foreach ($nodes as $node) {
						if ($this->isPhoto($node) && $node->isUpdateable()) {
							$photo = $this->photoMapper->findByFileIdUserId($node->getId(), $userId);
							$done[] = [
								'path' => preg_replace('/^files/', '', $node->getInternalPath()),
								'lat' => $lat,
								'lng' => $lng,
								'oldLat' => $photo ? $photo->getLat() : null,
								'oldLng' => $photo ? $photo->getLng() : null,
							];
							$this->setExifCoords($node, $lat, $lng);
							$this->updateByFileNow($node);
						}
					}
				}
			}
		}
		return $done;
	}

	private function setFilesCoords($userId, $paths, $lats, $lngs) {
		$userFolder = $this->root->getUserFolder($userId);
		$done = [];

		foreach ($paths as $i => $path) {
			$cleanpath = str_replace(['../', '..\\'], '', $path);
			if ($userFolder->nodeExists($cleanpath)) {
				$file = $userFolder->get($cleanpath);
				if ($this->isPhoto($file) && $file->isUpdateable()) {
					$lat = (count($lats) > $i) ? $lats[$i] : $lats[0];
					$lng = (count($lngs) > $i) ? $lngs[$i] : $lngs[0];
					$photo = $this->photoMapper->findByFileIdUserId($file->getId(), $userId);
					$done[] = [
						'path' => preg_replace('/^files/', '', $file->getInternalPath()),
						'lat' => $lat,
						'lng' => $lng,
						'oldLat' => $photo ? $photo->getLat() : null,
						'oldLng' => $photo ? $photo->getLng() : null,
					];
					$this->setExifCoords($file, $lat, $lng);
					$this->updateByFileNow($file);
				}
			}
		}
		return $done;
	}

	public function resetPhotosFilesCoords($userId, $paths) {
		$userFolder = $this->root->getUserFolder($userId);
		$done = [];

		foreach ($paths as $i => $path) {
			$cleanpath = str_replace(['../', '..\\'], '', $path);
			if ($userFolder->nodeExists($cleanpath)) {
				$file = $userFolder->get($cleanpath);
				if ($this->isPhoto($file) && $file->isUpdateable()) {
					$photo = $this->photoMapper->findByFileIdUserId($file->getId(), $userId);
					$done[] = [
						'path' => preg_replace('/^files/', '', $file->getInternalPath()),
						'lat' => null,
						'lng' => null,
						'oldLat' => $photo ? $photo->getLat() : null,
						'oldLng' => $photo ? $photo->getLng() : null,
					];
					$this->resetExifCoords($file);
					$this->photoMapper->updateByFileId($file->getId(), null, null);
				}
			}
		}
		return $done;
	}

	// avoid adding photo if it already exists in the DB
	private function addPhoto($photo, $userId) {
		$this->jobList->add(AddPhotoJob::class, ['photoId' => $photo->getId(), 'userId' => $userId]);
	}

	public function addPhotoNow($photo, $userId) {
		$exif = $this->getExif($photo);
		if (!is_null($exif)) {
			// filehooks are triggered several times (2 times for file creation)
			// so we need to be sure it's not inserted several times
			// by checking if it already exists in DB
			// OR by using file_id in primary key
			try {
				$this->photoMapper->findByFileIdUserId($photo->getId(), $userId);
			} catch (DoesNotExistException $exception) {
				$this->insertPhoto($photo, $userId, $exif);
			}
			$this->photosCache->clear($userId);
		}
	}


	private function insertPhoto($photo, $userId, $exif) {
		$photoEntity = new Geophoto();
		$photoEntity->setFileId($photo->getId());
		$photoEntity->setLat(
			is_numeric($exif->lat) && !is_nan($exif->lat) ? $exif->lat : null
		);
		$photoEntity->setLng(
			is_numeric($exif->lng) && !is_nan($exif->lng) ? $exif->lng : null
		);
		$photoEntity->setUserId($userId);
		// alternative should be file creation date
		$photoEntity->setDateTaken($exif->dateTaken ?? $photo->getMTime());
		$this->photoMapper->insert($photoEntity);

		$this->photosCache->clear($userId);
	}

	private function updatePhoto($file, $exif) {
		$lat = is_numeric($exif->lat) && !is_nan($exif->lat) ? $exif->lat : null;
		$lng = is_numeric($exif->lng) && !is_nan($exif->lng) ? $exif->lng : null;
		$this->photoMapper->updateByFileId($file->getId(), $lat, $lng);
	}

	private function normalizePath($node) {
		return str_replace('files', '', $node->getInternalPath());
	}

	public function getPhotosByFolder($userId, $path) {
		$userFolder = $this->root->getUserFolder($userId);
		$folder = $userFolder->get($path);
		return $this->getPhotosListForFolder($folder);
	}

	private function getPhotosListForFolder($folder) {
		$FilesList = $this->gatherPhotoFiles($folder, false);
		$notes = [];
		foreach ($FilesList as $File) {
			$file_object = new \stdClass();
			$file_object->fileId = $File->getId();
			$file_object->path = $this->normalizePath($File);
			$notes[] = $file_object;
		}
		return $notes;
	}

	private function gatherPhotoFiles($folder, $recursive) {
		$notes = [];
		$nodes = $folder->getDirectoryListing();
		foreach ($nodes as $node) {
			if ($node->getType() === FileInfo::TYPE_FOLDER and $recursive) {
				// we don't explore external storages for which previews are disabled
				if ($node->isMounted()) {
					$options = $node->getMountPoint()->getOptions();
					if (!(isset($options['previews']) && $options['previews'])) {
						continue;
					}
				}
				try {
					$notes = array_merge($notes, $this->gatherPhotoFiles($node, $recursive));
				} catch (\OCP\Files\StorageNotAvailableException|\Exception $e) {
					$msg = 'WARNING: Could not access ' . $node->getName();
					echo($msg . "\n");
					$this->logger->error($msg);
				}
				continue;
			}
			if ($this->isPhoto($node)) {
				$notes[] = $node;
			}
		}
		return $notes;
	}

	private function isPhoto($file) {
		if ($file->getType() !== \OCP\Files\FileInfo::TYPE_FILE) {
			return false;
		}
		if (!in_array($file->getMimetype(), self::PHOTO_MIME_TYPES)) {
			return false;
		}
		return true;
	}

	/**
	 * Get exif geo Data object
	 * returns with null in any validation or Critical errors
	 *
	 * @param $file
	 * @return ExifGeoData|null
	 */
	private function getExif($file) : ?ExifGeoData {
		$path = $file->getStorage()->getLocalFile($file->getInternalPath());
		try {
			$exif_geo_data = ExifGeoData::get($path);
			$exif_geo_data->validate(true);
		} catch (ExifDataInvalidException $e) {
			$exif_geo_data = null;
			$this->logger->notice($e->getMessage(), ['code' => $e->getCode(),'path' => $path]);
		} catch (ExifDataNoLocationException $e) {
			$this->logger->notice($e->getMessage(), ['code' => $e->getCode(),'path' => $path]);
		} catch (\Throwable $f) {
			$exif_geo_data = null;
			$this->logger->error($f->getMessage(), ['code' => $f->getCode(),'path' => $path]);
		}
		return $exif_geo_data;
	}

	private function resetExifCoords($file) {
		$data = new PelDataWindow($file->getContent());
		$pelJpeg = new PelJpeg($data);

		$pelExif = $pelJpeg->getExif();
		if ($pelExif === null) {
			$pelExif = new PelExif();
			$pelJpeg->setExif($pelExif);
		}

		$pelTiff = $pelExif->getTiff();
		if ($pelTiff === null) {
			$pelTiff = new PelTiff();
			$pelExif->setTiff($pelTiff);
		}

		$pelIfd0 = $pelTiff->getIfd();
		if ($pelIfd0 === null) {
			$pelIfd0 = new PelIfd(PelIfd::IFD0);
			$pelTiff->setIfd($pelIfd0);
		}

		$pelSubIfdGps = new PelIfd(PelIfd::GPS);
		$pelIfd0->addSubIfd($pelSubIfdGps);

		$file->putContent($pelJpeg->getBytes());
	}

	private function setExifCoords($file, $lat, $lng) {
		$data = new PelDataWindow($file->getContent());
		$pelJpeg = new PelJpeg($data);

		$pelExif = $pelJpeg->getExif();
		if ($pelExif === null) {
			$pelExif = new PelExif();
			$pelJpeg->setExif($pelExif);
		}

		$pelTiff = $pelExif->getTiff();
		if ($pelTiff === null) {
			$pelTiff = new PelTiff();
			$pelExif->setTiff($pelTiff);
		}

		$pelIfd0 = $pelTiff->getIfd();
		if ($pelIfd0 === null) {
			$pelIfd0 = new PelIfd(PelIfd::IFD0);
			$pelTiff->setIfd($pelIfd0);
		}

		$pelSubIfdGps = new PelIfd(PelIfd::GPS);
		$pelIfd0->addSubIfd($pelSubIfdGps);

		$this->setGeolocation($pelSubIfdGps, $lat, $lng);

		$file->putContent($pelJpeg->getBytes());
	}

	private function setGeolocation($pelSubIfdGps, $latitudeDegreeDecimal, $longitudeDegreeDecimal) {
		$latitudeRef = ($latitudeDegreeDecimal >= 0) ? 'N' : 'S';
		$latitudeDegreeMinuteSecond
			= $this->degreeDecimalToDegreeMinuteSecond(abs($latitudeDegreeDecimal));
		$longitudeRef = ($longitudeDegreeDecimal >= 0) ? 'E' : 'W';
		$longitudeDegreeMinuteSecond
			= $this->degreeDecimalToDegreeMinuteSecond(abs($longitudeDegreeDecimal));

		$pelSubIfdGps->addEntry(new PelEntryAscii(
			PelTag::GPS_LATITUDE_REF, $latitudeRef));
		$pelSubIfdGps->addEntry(new PelEntryRational(
			PelTag::GPS_LATITUDE,
			[$latitudeDegreeMinuteSecond['degree'], 1],
			[$latitudeDegreeMinuteSecond['minute'], 1],
			[round($latitudeDegreeMinuteSecond['second'] * 1000), 1000]));
		$pelSubIfdGps->addEntry(new PelEntryAscii(
			PelTag::GPS_LONGITUDE_REF, $longitudeRef));
		$pelSubIfdGps->addEntry(new PelEntryRational(
			PelTag::GPS_LONGITUDE,
			[$longitudeDegreeMinuteSecond['degree'], 1],
			[$longitudeDegreeMinuteSecond['minute'], 1],
			[round($longitudeDegreeMinuteSecond['second'] * 1000), 1000]));
	}

	private function degreeDecimalToDegreeMinuteSecond($degreeDecimal) {
		$degree = floor($degreeDecimal);
		$remainder = $degreeDecimal - $degree;
		$minute = floor($remainder * 60);
		$remainder = ($remainder * 60) - $minute;
		$second = $remainder * 60;
		return ['degree' => $degree, 'minute' => $minute, 'second' => $second];
	}

}
