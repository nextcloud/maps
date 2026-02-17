<?php

declare(strict_types=1);

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
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\StorageNotAvailableException;
use OCP\ICache;
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

	private $root;


	private $shareManager;

	private $jobList;

	private readonly ICacheFactory $cacheFactory;

	private readonly ICache $photosCache;

	private readonly ICache $backgroundJobCache;

	public function __construct(
		private readonly LoggerInterface $logger,
		ICacheFactory $cacheFactory,
		IRootFolder $root,
		IL10N $l10n,
		private readonly GeophotoMapper $photoMapper,
		IManager $shareManager,
		IJobList $jobList,
	) {
		$this->root = $root;
		$this->shareManager = $shareManager;
		$this->jobList = $jobList;
		$this->cacheFactory = $cacheFactory;
		$this->photosCache = $this->cacheFactory->createDistributed('maps:photos');
		$this->backgroundJobCache = $this->cacheFactory->createDistributed('maps:background-jobs');
	}

	/**
	 * @psalm-return \Generator<int, mixed, mixed, void>
	 */
	public function rescan($userId, $inBackground = true, $pathToScan = null): \Generator {
		$this->photosCache->clear($userId);
		$userFolder = $this->root->getUserFolder($userId);
		if ($pathToScan === null) {
			$folder = $userFolder;
			$this->photoMapper->deleteAll($userId);
		} else {
			$folder = $userFolder->get($pathToScan);
		}

		$photos = $this->gatherPhotoFiles($folder, true);
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
	public function addByFile(Node $file): bool {
		if ($this->isPhoto($file)) {
			$ownerId = $file->getOwner()->getUID();
			$this->addPhoto($file, $ownerId);
			// is the file accessible to other users ?
			$accesses = $this->shareManager->getAccessList($file);
			if (array_key_exists('users', $accesses)) {
				foreach ($accesses['users'] as $uid) {
					if ($uid !== $ownerId) {
						$this->addPhoto($file, $uid);
					}
				}
			}

			return true;
		}

		return false;
	}

	public function addByFileIdUserId(int $fileId, string $userId): void {
		$userFolder = $this->root->getUserFolder($userId);
		$file = $userFolder->getFirstNodeById($fileId);
		if ($file instanceof File && $this->isPhoto($file)) {
			$this->addPhoto($file, $userId);
		}
	}

	public function addByFolderIdUserId(int $folderId, string $userId): void {
		$folder = $this->root->getFirstNodeById($folderId);
		if ($folder instanceof Folder) {
			$photos = $this->gatherPhotoFiles($folder, true);
			foreach ($photos as $photo) {
				$this->addPhoto($photo, $userId);
			}
		}
	}

	// add all photos of a folder taking care of shared accesses
	public function addByFolder(Folder $folder): void {
		$photos = $this->gatherPhotoFiles($folder, true);
		foreach ($photos as $photo) {
			$this->addByFile($photo);
		}
	}

	public function updateByFile(Node $file): void {
		$this->jobList->add(UpdatePhotoByFileJob::class, ['fileId' => $file->getId(), 'userId' => $file->getOwner()->getUID()]);
	}

	public function updateByFileNow(File $file): void {
		if ($this->isPhoto($file)) {
			$exif = $this->getExif($file);
			if (!is_null($exif)) {
				$ownerId = $file->getOwner()->getUID();
				// in case there is no entry for this file yet (normally there is because non-localized photos are added)
				try {
					$this->photoMapper->findByFileIdUserId($file->getId(), $ownerId);
					$this->updatePhoto($file, $exif);
					$this->photosCache->clear($ownerId);
				} catch (DoesNotExistException) {
					$this->insertPhoto($file, $ownerId, $exif);
				}
			}
		}
	}

	public function deleteByFile(Node $file): void {
		$this->photoMapper->deleteByFileId($file->getId());
	}

	// delete photo only if it's not accessible to user anymore
	// it might have been shared multiple times by different users
	public function deleteByFileIdUserId(int $fileId, string $userId): void {
		$userFolder = $this->root->getUserFolder($userId);
		$file = $userFolder->getFirstNodeById($fileId);
		if ($file !== null) {
			$this->photoMapper->deleteByFileIdUserId($fileId, $userId);
			$this->photosCache->clear($userId);
		}
	}


	public function deleteByFolder(Folder $folder): void {
		$photos = $this->gatherPhotoFiles($folder, true);
		foreach ($photos as $photo) {
			$this->photoMapper->deleteByFileId($photo->getId());
		}
	}

	// delete folder photos only if it's not accessible to user anymore
	public function deleteByFolderIdUserId($folderId, $userId): void {
		$userFolder = $this->root->getUserFolder($userId);
		$folders = $userFolder->getById($folderId);
		if (is_array($folders) && count($folders) === 1) {
			$folder = array_shift($folders);
			$photos = $this->gatherPhotoFiles($folder, true);
			foreach ($photos as $photo) {
				$this->photoMapper->deleteByFileIdUserId($photo->getId(), $userId);
			}

			$this->photosCache->clear($userId);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getBackgroundJobStatus(string $userId): array {
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

		$recentlyAdded = $this->backgroundJobCache->get('recentlyAdded:' . $userId) ?? 0;
		$recentlyUpdated = $this->backgroundJobCache->get('$recentlyUpdated:' . $userId) ?? 0;
		return [
			'addJobsRunning' => $addJobsRunning,
			'addJobsRemainingForUser' => $add_counter,
			'recentlyAdded' => $recentlyAdded,
			'updateJobsRunning' => $updateJobsRunning,
			'updateJobsRemainingForUser' => $update_counter,
			'recentlyUpdated' => $recentlyUpdated
		];
	}

	public function setPhotosFilesCoords(string $userId, $paths, $lats, $lngs, $directory): array {
		if ($directory) {
			return $this->setDirectoriesCoords($userId, $paths, $lats, $lngs);
		}

		return $this->setFilesCoords($userId, $paths, $lats, $lngs);
	}

	private function setDirectoriesCoords(string $userId, $paths, $lats, $lngs): array {
		$lat = $lats[0] ?? 0;
		$lng = $lngs[0] ?? 0;
		$userFolder = $this->root->getUserFolder($userId);
		$done = [];
		foreach ($paths as $dirPath) {
			$cleanDirPath = str_replace(['../', '..\\'], '', $dirPath);
			if ($userFolder->nodeExists($cleanDirPath)) {
				$dir = $userFolder->get($cleanDirPath);
				if ($dir instanceof Folder) {
					$nodes = $dir->getDirectoryListing();
					foreach ($nodes as $node) {
						if ($this->isPhoto($node) && $node->isUpdateable()) {
							$photo = $this->photoMapper->findByFileIdUserId($node->getId(), $userId);
							$done[] = [
								'path' => preg_replace('/^files/', '', (string)$node->getInternalPath()),
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

	/**
	 * @return array{path: (array | string | null), lat: mixed, lng: mixed, oldLat: mixed, oldLng: mixed}[]
	 */
	private function setFilesCoords(string $userId, $paths, array $lats, array $lngs): array {
		$userFolder = $this->root->getUserFolder($userId);
		$done = [];

		foreach ($paths as $i => $path) {
			$cleanpath = str_replace(['../', '..\\'], '', $path);
			if ($userFolder->nodeExists($cleanpath)) {
				$file = $userFolder->get($cleanpath);
				if ($file instanceof File && $this->isPhoto($file) && $file->isUpdateable()) {
					$lat = (count($lats) > $i) ? $lats[$i] : $lats[0];
					$lng = (count($lngs) > $i) ? $lngs[$i] : $lngs[0];
					try {
						$photo = $this->photoMapper->findByFileIdUserId($file->getId(), $userId);
					} catch (DoesNotExistException) {
						$photo = null;
					}

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

	/**
	 * @return array{path: (array | string | null), lat: null, lng: null, oldLat: mixed, oldLng: mixed}[]
	 */
	public function resetPhotosFilesCoords($userId, $paths): array {
		$userFolder = $this->root->getUserFolder($userId);
		$done = [];

		foreach ($paths as $path) {
			$cleanpath = str_replace(['../', '..\\'], '', $path);
			if ($userFolder->nodeExists($cleanpath)) {
				$file = $userFolder->get($cleanpath);
				if ($this->isPhoto($file) && $file->isUpdateable()) {
					$photo = $this->photoMapper->findByFileIdUserId($file->getId(), $userId);
					$done[] = [
						'path' => preg_replace('/^files/', '', (string)$file->getInternalPath()),
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
	private function addPhoto($photo, string $userId): void {
		$this->jobList->add(AddPhotoJob::class, ['photoId' => $photo->getId(), 'userId' => $userId]);
	}

	public function addPhotoNow($photo, $userId): void {
		$exif = $this->getExif($photo);
		if (!is_null($exif)) {
			// filehooks are triggered several times (2 times for file creation)
			// so we need to be sure it's not inserted several times
			// by checking if it already exists in DB
			// OR by using file_id in primary key
			try {
				$this->photoMapper->findByFileIdUserId($photo->getId(), $userId);
			} catch (DoesNotExistException) {
				$this->insertPhoto($photo, $userId, $exif);
			}

			$this->photosCache->clear($userId);
		}
	}


	private function insertPhoto($photo, $userId, ExifGeoData $exif): void {
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

	private function updatePhoto(File $file, ExifGeoData $exif): void {
		$lat = is_numeric($exif->lat) && !is_nan($exif->lat) ? $exif->lat : null;
		$lng = is_numeric($exif->lng) && !is_nan($exif->lng) ? $exif->lng : null;
		$this->photoMapper->updateByFileId($file->getId(), $lat, $lng);
	}

	private function normalizePath($node): string|array {
		return str_replace('files', '', $node->getInternalPath());
	}

	public function getPhotosByFolder(string $userId, string $path): array {
		$userFolder = $this->root->getUserFolder($userId);
		$folder = $userFolder->get($path);
		if ($folder instanceof Folder) {
			return $this->getPhotosListForFolder($folder);
		}

		return [];
	}

	/**
	 * @return \stdClass[]
	 */
	private function getPhotosListForFolder(Folder $folder): array {
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

	private function gatherPhotoFiles(Folder $folder, bool $recursive): array {
		$notes = [];
		$nodes = $folder->getDirectoryListing();
		foreach ($nodes as $node) {
			if ($node instanceof Folder && $recursive) {
				// we don't explore external storages for which previews are disabled
				if ($node->isMounted()) {
					$options = $node->getMountPoint()->getOptions();
					if (!(isset($options['previews']) && $options['previews'])) {
						continue;
					}
				}

				try {
					$notes = array_merge($notes, $this->gatherPhotoFiles($node, $recursive));
				} catch (StorageNotAvailableException|\Exception) {
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

	/**
	 * @psalm-assert $file instanceof File
	 */
	private function isPhoto(Node $file): bool {
		if ($file instanceof File) {
			return false;
		}

		return in_array($file->getMimetype(), self::PHOTO_MIME_TYPES);
	}

	/**
	 * Get exif geo Data object
	 * returns with null in any validation or Critical errors
	 *
	 * @param $file
	 */
	private function getExif($file) : ?ExifGeoData {
		$path = $file->getStorage()->getLocalFile($file->getInternalPath());
		try {
			$exif_geo_data = ExifGeoData::get($path);
			$exif_geo_data->validate(true);
		} catch (ExifDataInvalidException $e) {
			$exif_geo_data = null;
			$this->logger->notice($e->getMessage(), ['code' => $e->getCode(), 'path' => $path]);
		} catch (ExifDataNoLocationException $e) {
			$this->logger->notice($e->getMessage(), ['code' => $e->getCode(), 'path' => $path]);
		} catch (\Throwable $f) {
			$exif_geo_data = null;
			$this->logger->error($f->getMessage(), ['code' => $f->getCode(), 'path' => $path]);
		}

		return $exif_geo_data;
	}

	private function resetExifCoords($file): void {
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

	private function setExifCoords(File $file, $lat, $lng): void {
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

	private function setGeolocation(PelIfd $pelSubIfdGps, $latitudeDegreeDecimal, $longitudeDegreeDecimal): void {
		$latitudeRef = ($latitudeDegreeDecimal >= 0) ? 'N' : 'S';
		$latitudeDegreeMinuteSecond
			= $this->degreeDecimalToDegreeMinuteSecond(abs($latitudeDegreeDecimal));
		$longitudeRef = ($longitudeDegreeDecimal >= 0) ? 'E' : 'W';
		$longitudeDegreeMinuteSecond
			= $this->degreeDecimalToDegreeMinuteSecond(abs($longitudeDegreeDecimal));

		$pelSubIfdGps->addEntry(new PelEntryAscii(
			PelTag::GPS_LATITUDE_REF,
			$latitudeRef
		));
		$pelSubIfdGps->addEntry(new PelEntryRational(
			PelTag::GPS_LATITUDE,
			[$latitudeDegreeMinuteSecond['degree'], 1],
			[$latitudeDegreeMinuteSecond['minute'], 1],
			[round($latitudeDegreeMinuteSecond['second'] * 1000), 1000]
		));
		$pelSubIfdGps->addEntry(new PelEntryAscii(
			PelTag::GPS_LONGITUDE_REF,
			$longitudeRef
		));
		$pelSubIfdGps->addEntry(new PelEntryRational(
			PelTag::GPS_LONGITUDE,
			[$longitudeDegreeMinuteSecond['degree'], 1],
			[$longitudeDegreeMinuteSecond['minute'], 1],
			[round($longitudeDegreeMinuteSecond['second'] * 1000), 1000]
		));
	}

	/**
	 * @return array{degree: float, minute: float, second: float}
	 */
	private function degreeDecimalToDegreeMinuteSecond(float|int $degreeDecimal): array {
		$degree = floor($degreeDecimal);
		$remainder = $degreeDecimal - $degree;
		$minute = floor($remainder * 60);
		$remainder = ($remainder * 60) - $minute;
		$second = $remainder * 60;
		return ['degree' => $degree, 'minute' => $minute, 'second' => $second];
	}
}
