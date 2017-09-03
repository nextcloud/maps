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

use OCP\Files\FileInfo;
use OCP\IL10N;
use OCP\Files\IRootFolder;
use OCP\Files\Storage\IStorage;
use OCP\Files\Folder;
use OCP\ILogger;

use OCA\Maps\DB\Geophoto;
use OCA\Maps\DB\GeophotoMapper;

class GeophotoService {

    private $l10n;
    private $root;
    private $photoMapper;
    private $logger;

    public function __construct (ILogger $logger, IRootFolder $root, IL10N $l10n, GeophotoMapper $photoMapper) {
        $this->root = $root;
        $this->l10n = $l10n;
        $this->photoMapper = $photoMapper;
        $this->logger = $logger;
    }

    /**
     * @param string $userId
     * @return array with geodatas of all photos
     */
     public function getAllFromDB ($userId) {
        $photoEntities = $this->photoMapper->findAll($userId);
        $userFolder = $this->getFolderForUser($userId);
        $filesById = [];
        $cache = $userFolder->getStorage()->getCache();
        foreach ($photoEntities as $photoEntity) {
            $cacheEntry = $cache->get($photoEntity->getFileId());
            $path = $cacheEntry->getPath();
            $file_object = new \stdClass();
            $file_object->fileId = $photoEntity->getFileId();
            $file_object->lat = $photoEntity->getLat();
            $file_object->lng = $photoEntity->getLng();
            /* 30% longer
             * $file_object->folderId = $cache->getParentId($path); 
             */
            $file_object->path = $this->normalizePath($path);
            $filesById[] = $file_object;
        }
        return $filesById;
    }

    private function normalizePath($path) {
        return str_replace("files","", $path);
    }

    /**
     * @param string $userId the user id
     * @return Folder
     */
    private function getFolderForUser ($userId) {
        $path = '/' . $userId . '/files';
        if ($this->root->nodeExists($path)) {
            $folder = $this->root->get($path);
        } else {
            $folder = $this->root->newFolder($path);
        }
        return $folder;
    }

}
