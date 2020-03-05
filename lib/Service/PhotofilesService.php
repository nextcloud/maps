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

use lsolesen\pel\PelEntryTime;
use OCP\Files\FileInfo;
use OCP\IL10N;
use OCP\Files\IRootFolder;
use OCP\Files\Storage\IStorage;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\ILogger;
use OCP\Share\IManager;
use OCP\Files\Mount\IMountPoint;
use \OCP\BackgroundJob\IJobList;

use OCA\Maps\DB\Geophoto;
use OCA\Maps\DB\GeophotoMapper;
use OCA\Maps\BackgroundJob\AddPhotoJob;
use OCA\Maps\BackgroundJob\UpdatePhotoByFileJob;

require_once __DIR__ . '/../../vendor/autoload.php';
use lsolesen\pel\PelDataWindow;
use lsolesen\pel\PelException;
use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelExif;
use lsolesen\pel\PelTiff;
use lsolesen\pel\PelTag;
use lsolesen\pel\PelEntryAscii;
use lsolesen\pel\PelEntryRational;
use lsolesen\pel\PelIfd;
use lsolesen\pel\Pel;

class PhotofilesService {

    const PHOTO_MIME_TYPES = ['image/jpeg', 'image/tiff'];

    private $l10n;
    private $root;
    private $photoMapper;
    private $shareManager;
    private $logger;
    private $jobList;

    public function __construct (ILogger $logger, IRootFolder $root, IL10N $l10n,
                                GeophotoMapper $photoMapper, IManager $shareManager, IJobList $jobList) {
        $this->root = $root;
        $this->l10n = $l10n;
        $this->photoMapper = $photoMapper;
        $this->shareManager = $shareManager;
        $this->logger = $logger;
        $this->jobList = $jobList;
    }

    public function rescan($userId){
        $userFolder = $this->root->getUserFolder($userId);
        $photos = $this->gatherPhotoFiles($userFolder, true);
        $this->photoMapper->deleteAll($userId);
        foreach($photos as $photo) {
            $this->addPhoto($photo, $userId);
            yield $photo->getPath();
        }
    }

    // add the file for its owner and users that have access
    // check if it's already in DB before adding
    public function addByFile(Node $file) {
        $ownerId = $file->getOwner()->getUID();
        $userFolder = $this->root->getUserFolder($ownerId);
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
        }
        else {
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
        $userFolder = $this->root->getUserFolder($userId);
        $folders = $userFolder->getById($folderId);
		if (empty($folders)) {
			return;
		}
		$folder = array_shift($folders);
        if ($folder !== null) {
            $photos = $this->gatherPhotoFiles($folder, true);
            foreach($photos as $photo) {
                $this->addPhoto($photo, $userId);
            }
        }
    }

    // add all photos of a folder taking care of shared accesses
    public function addByFolder($folder) {
        $photos = $this->gatherPhotoFiles($folder, true);
        foreach($photos as $photo) {
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
                if ($this->photoMapper->findByFileId($ownerId, $file->getId()) === null) {
                    // TODO insert for all users having access to this file, not just the owner
                    $this->insertPhoto($file, $ownerId, $exif);
                }
                else {
                    $this->updatePhoto($file, $exif);
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
            foreach($photos as $photo) {
                $this->photoMapper->deleteByFileIdUserId($photo->getId(), $userId);
            }
        }
    }

    public function setPhotosFilesCoords($userId, $paths, $lats, $lngs, $directory) {
        if ($directory === 'true') {
            return $this->setDirectoriesCoords($userId, $paths, $lats, $lngs);
        }
        else {
            return $this->setFilesCoords($userId, $paths, $lats, $lngs);
        }
    }

    private function setDirectoriesCoords($userId, $paths, $lats, $lngs) {
        $lat = $lats[0] ?? 0;
        $lng = $lngs[0] ?? 0;
        $userFolder = $this->root->getUserFolder($userId);
        $nbDone = 0;
        foreach ($paths as $dirPath) {
            $cleanDirPath = str_replace(array('../', '..\\'), '',  $dirPath);
            if ($userFolder->nodeExists($cleanDirPath)) {
                $dir = $userFolder->get($cleanDirPath);
                if ($dir->getType() === FileInfo::TYPE_FOLDER) {
                    $nodes = $dir->getDirectoryListing();
                    foreach($nodes as $node) {
                        if ($this->isPhoto($node) and $node->isUpdateable()) {
                            $this->setExifCoords($node, $lat, $lng);
                            $this->updateByFileNow($node);
                            $nbDone++;
                        }
                    }
                }
            }
        }
        return $nbDone;
    }

    private function setFilesCoords($userId, $paths, $lats, $lngs) {
        $userFolder = $this->root->getUserFolder($userId);
        $nbDone = 0;

        foreach ($paths as $i => $path) {
            $cleanpath = str_replace(array('../', '..\\'), '',  $path);
            if ($userFolder->nodeExists($cleanpath)) {
                $file = $userFolder->get($cleanpath);
                if ($this->isPhoto($file) and $file->isUpdateable()) {
                    $lat = (count($lats) > $i) ? $lats[$i] : $lats[0];
                    $lng = (count($lngs) > $i) ? $lngs[$i] : $lngs[0];
                    $this->setExifCoords($file, $lat, $lng);
                    $this->updateByFileNow($file);
                    $nbDone++;
                }
            }
        }
        return $nbDone;
    }

    public function resetPhotosFilesCoords($userId, $paths) {
        $userFolder = $this->root->getUserFolder($userId);
        $nbDone = 0;

        foreach ($paths as $i => $path) {
            $cleanpath = str_replace(array('../', '..\\'), '',  $path);
            if ($userFolder->nodeExists($cleanpath)) {
                $file = $userFolder->get($cleanpath);
                if ($this->isPhoto($file) and $file->isUpdateable()) {
                    $this->resetExifCoords($file);
                    $this->photoMapper->updateByFileId($file->getId(), null, null);
                    $nbDone++;
                }
            }
        }
        return $nbDone;
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
            if ($this->photoMapper->findByFileId($userId, $photo->getId()) === null) {
                $this->insertPhoto($photo, $userId, $exif);
            }
        }
    }


    private function insertPhoto($photo, $userId, $exif) {
        $photoEntity = new Geophoto();
        $photoEntity->setFileId($photo->getId());
        $photoEntity->setLat(
            is_numeric($exif->lat)&&!is_nan($exif->lat) ? $exif->lat : null
        );
        $photoEntity->setLng(
            is_numeric($exif->lng)&&!is_nan($exif->lng) ? $exif->lng : null
        );
        $photoEntity->setUserId($userId);
        // alternative should be file creation date
        $photoEntity->setDateTaken($exif->dateTaken ?? $photo->getMTime());
        $this->photoMapper->insert($photoEntity);
    }

    private function updatePhoto($file, $exif) {
        $lat = is_numeric($exif->lat)&&!is_nan($exif->lat) ? $exif->lat : null;
        $lng = is_numeric($exif->lng)&&!is_nan($exif->lng) ? $exif->lng : null;
        $this->photoMapper->updateByFileId($file->getId(), $lat, $lng);
    }

    private function normalizePath($node) {
        return str_replace("files","", $node->getInternalPath());
    }

    public function getPhotosByFolder($userId, $path) {
        $userFolder = $this->root->getUserFolder($userId);
        $folder = $userFolder->get($path);
        return $this->getPhotosListForFolder($folder);
    }

    private function getPhotosListForFolder($folder) {
        $FilesList = $this->gatherPhotoFiles($folder, false);
        $notes = [];
        foreach($FilesList as $File) {
            $file_object = new \stdClass();
            $file_object->fileId = $File->getId();
            $file_object->path = $this->normalizePath($File);
            $notes[] = $file_object;
        }
        return $notes;
    }

    private function gatherPhotoFiles ($folder, $recursive) {
        $notes = [];
        $nodes = $folder->getDirectoryListing();
        foreach ($nodes as $node) {
            if ($node->getType() === FileInfo::TYPE_FOLDER AND $recursive) {
                // we don't explore external storages for which previews are disabled
                if ($node->isMounted()) {
                    $options = $node->getMountPoint()->getOptions();
                    if (!(array_key_exists('previews', $options) && $options['previews'])) {
                        continue;
                    }
                }
                $notes = array_merge($notes, $this->gatherPhotoFiles($node, $recursive));
                continue;
            }
            if ($this->isPhoto($node)) {
                $notes[] = $node;
            }
        }
        return $notes;
    }

    private function isPhoto($file) {
        if ($file->getType() !== \OCP\Files\FileInfo::TYPE_FILE) return false;
        if (!in_array($file->getMimetype(), self::PHOTO_MIME_TYPES)) return false;
        return true;
    }

    private function hasValidExifGeoTags($exif) {
        if (!isset($exif["GPSLatitude"]) OR !isset($exif["GPSLongitude"])) {
            return false;
        }
        if (count($exif["GPSLatitude"]) !== 3 OR count($exif["GPSLongitude"]) !== 3) {
            return false;
        }
        //Check photos are on the earth
        if ($exif["GPSLatitude"][0] >= 90 OR $exif["GPSLongitude"][0] >= 180) {
            return false;
        }
        //Check photos are not on NULL island, remove if they should be.
        if ($exif["GPSLatitude"][0] === 0 AND
            $exif["GPSLatitude"][1] === 0 AND
            $exif["GPSLongitude"][0] === 0 AND
            $exif["GPSLongitude"][1] === 0
        ){
            return false;
        }
        return true;
    }

    private function getExif($file) {
        $path = $file->getStorage()->getLocalFile($file->getInternalPath());
        $has_info = false;

        $exif = @exif_read_data($path);

        if (!$this->hasValidExifGeoTags($exif)) {
            try {
                $exif = $this->getExifPelBackup($file);
            } catch (PelException $exception) {
                $exif = [];
            }

        }

        if ($this->hasValidExifGeoTags($exif)){
            //Check if there is exif infor
            $LatM = 1; $LongM = 1;
            if ($exif["GPSLatitudeRef"] === 'S'){
                $LatM = -1;
            }
            if ($exif["GPSLongitudeRef"] === 'W'){
                $LongM = -1;
            }
            //get the GPS data
            $gps['LatDegree'] = $exif["GPSLatitude"][0];
            $gps['LatMinute'] = $exif["GPSLatitude"][1];
            $gps['LatgSeconds'] = $exif["GPSLatitude"][2];
            $gps['LongDegree'] = $exif["GPSLongitude"][0];
            $gps['LongMinute'] = $exif["GPSLongitude"][1];
            $gps['LongSeconds'] = $exif["GPSLongitude"][2];

            //convert strings to numbers
            foreach ($gps as $key => $value){
                $pos = strpos($value, '/');
                if ($pos !== false){
                    $temp = explode('/',$value);
                    $gps[$key] = $temp[0] / $temp[1];
                }
            }
            $file_object = new \stdClass();
            //calculate the decimal degree
            $file_object->lat = $LatM * ($gps['LatDegree'] + ($gps['LatMinute'] / 60) + ($gps['LatgSeconds'] / 3600));
            $file_object->lng = $LongM * ($gps['LongDegree'] + ($gps['LongMinute'] / 60) + ($gps['LongSeconds'] / 3600));
            $has_info = true;
        } else {
            $file_object = new \stdClass();
            $file_object->lat = null;
            $file_object->lng = null;
        }

        if (isset($exif["DateTimeOriginal"])) {
            $file_object->dateTaken = strtotime($exif["DateTimeOriginal"]);
            $has_info = true;
        }

        if ($has_info) {
            return $file_object;
        } else {
            return null;
        }
    }

    private function getExifPelBackup($file) {
        $data = new PelDataWindow($file->getContent());
        if (PelJpeg::isValid($data)) {
            $pelJpeg = new PelJpeg($data);

            $pelExif = $pelJpeg->getExif();
            if ($pelExif === null) {
                return null;
            }

            $pelTiff = $pelExif->getTiff();
        } elseif (PelTiff::isValid($data)) {
            $pelTiff = new PelTiff($data);
        } else {
            return null;
        }
        if (is_null($pelTiff)) {
            return null;
        }
        $pelIfd0 = $pelTiff->getIfd();
        if (is_null($pelIfd0)) {
            return null;
        }
        $pelIfdExif = $pelIfd0->getSubIfd(PelIfd::EXIF);

        if (is_null($pelIfdExif)) {
            return null;
        }
        $pelDateTimeOriginal = $pelIfdExif->getEntry(PelTag::DATE_TIME_ORIGINAL);
        if (is_null($pelDateTimeOriginal)) {
            return null;
        }
        $exif = [
            'DateTimeOriginal' => $pelDateTimeOriginal->getValue(PelEntryTime::EXIF_STRING),
        ];
        $pelIfdGPS = $pelIfd0->getSubIfd(PelIfd::GPS);
        if (!is_null($pelIfdGPS) && !is_null($pelIfdGPS->getEntry(PelTag::GPS_LATITUDE )) && !is_null( $pelIfdGPS->getEntry(PelTag::GPS_LONGITUDE))) {

            $lat = $pelIfdGPS->getEntry(PelTag::GPS_LATITUDE)->getValue();
            if ((int) $lat[0][1] !==0 && (int) $lat[1][1] !==0 && (int) $lat[2][1] !==0) {
                $exif['GPSLatitudeRef'] = $pelIfdGPS->getEntry(PelTag::GPS_LATITUDE_REF)->getValue();
                $exif['GPSLatitude'] = [
                    0 => (int) $lat[0][0]/ (int) $lat[0][1],
                    1 => (int) $lat[1][0]/ (int) $lat[1][1],
                    2 => (int) $lat[2][0]/ (int) $lat[2][1]
                ];
            }

            $lng = $pelIfdGPS->getEntry(PelTag::GPS_LONGITUDE)->getValue();
            if ((int) $lng[0][1] !==0 && (int) $lng[1][1] !==0 && (int) $lng[2][1] !==0) {
                $exif['GPSLongitudeRef'] = $pelIfdGPS->getEntry(PelTag::GPS_LONGITUDE_REF)->getValue();
                $exif['GPSLongitude'] = [
                    0 => (int)$lng[0][0] / (int)$lng[0][1],
                    1 => (int)$lng[1][0] / (int)$lng[1][1],
                    2 => (int)$lng[2][0] / (int)$lng[2][1]
                ];
            }
        }
        Pel::clearExceptions();
        return $exif;

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
        $longitudeRef= ($longitudeDegreeDecimal >= 0) ? 'E' : 'W';
        $longitudeDegreeMinuteSecond
            = $this->degreeDecimalToDegreeMinuteSecond(abs($longitudeDegreeDecimal));

        $pelSubIfdGps->addEntry(new PelEntryAscii(
            PelTag::GPS_LATITUDE_REF, $latitudeRef));
        $pelSubIfdGps->addEntry(new PelEntryRational(
            PelTag::GPS_LATITUDE,
            array($latitudeDegreeMinuteSecond['degree'], 1),
            array($latitudeDegreeMinuteSecond['minute'], 1),
            array(round($latitudeDegreeMinuteSecond['second'] * 1000), 1000)));
        $pelSubIfdGps->addEntry(new PelEntryAscii(
            PelTag::GPS_LONGITUDE_REF, $longitudeRef));
        $pelSubIfdGps->addEntry(new PelEntryRational(
            PelTag::GPS_LONGITUDE,
            array($longitudeDegreeMinuteSecond['degree'], 1),
            array($longitudeDegreeMinuteSecond['minute'], 1),
            array(round($longitudeDegreeMinuteSecond['second'] * 1000), 1000)));
    }

    private function degreeDecimalToDegreeMinuteSecond($degreeDecimal) {
        $degree = floor($degreeDecimal);
        $remainder = $degreeDecimal - $degree;
        $minute = floor($remainder * 60);
        $remainder = ($remainder * 60) - $minute;
        $second = $remainder * 60;
        return array('degree' => $degree, 'minute' => $minute, 'second' => $second);
    }

}
