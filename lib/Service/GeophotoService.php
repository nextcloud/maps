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
     * @return array with all notes in the current directory
     */
    public function getAllFromDB ($userId) {
        $photoEntities = $this->photoMapper->findAll($userId);
        $userFolder = $this->getFolderForUser($userId);
        $filesById = [];
        $foldersById = [];
        foreach ($photoEntities as $photoEntity) {
            $path = \OC\Files\Filesystem::getPath($photoEntity->getFileId());
            $photoFile = \OC\Files\Filesystem::getFileInfo($path);
            $photoFolder = $userFolder->get($path)->getParent();
            $file_object = new \stdClass();
            $file_object->fileId = $photoEntity->getFileId();
            $file_object->lat = $photoEntity->getLat();
            $file_object->lng = $photoEntity->getLng();
            $file_object->folderId = $photoFolder->getId();
            $file_object->path = $this->normalizePath($photoFile);
            $filesById[] = $file_object;
            $folder_object = new \stdClass();
            $folder_object->id = $photoFolder->getId();
            $folder_object->name = $photoFolder->getName();
            $folder_object->path = $this->normalizePath($photoFolder);
            /*$folder_object->filesList = $this->getPhotosListForFolder($photoFolder);*/
            $foldersById[$photoFolder->getId()] = $folder_object;
        }
        return [$filesById, $foldersById];
    }

    private function normalizePath($node) {
        return str_replace("files","", $node->getInternalPath());
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
