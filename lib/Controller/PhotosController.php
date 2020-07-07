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

namespace OCA\Maps\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\ILogger;

use OCA\Maps\Service\GeophotoService;
use OCA\Maps\Service\PhotofilesService;

class PhotosController extends Controller {
    private $userId;
    private $geophotoService;
    private $photofilesService;
    private $logger;
    private $userfolder;

    public function __construct($AppName,
                                ILogger $logger,
                                IRequest $request,
                                GeophotoService $GeophotoService,
                                PhotofilesService $photofilesService,
                                $UserId, $userfolder) {
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->userId = $UserId;
        $this->userfolder = $userfolder;
        $this->geophotoService = $GeophotoService;
        $this->photofilesService = $photofilesService;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getPhotosFromDb($myMapId=null) {
        if (is_null($myMapId) || $myMapId === "") {
            $result = $this->geophotoService->getAllFromDB($this->userId, $this->userfolder);
        } else {
            $folders = $this->userfolder->getById($myMapId);
            $folder = array_shift($folders);
            $result = $this->geophotoService->getAllFromDB($this->userId, $folder);
        }
        return new DataResponse($result);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getNonLocalizedPhotosFromDb() {
        $result = $this->geophotoService->getNonLocalizedFromDB($this->userId);
        return new DataResponse($result);
    }


    /**
     * @NoAdminRequired
     */
    public function placePhotos($paths, $lats, $lngs, $directory=false, $myMapId=null, $relative=false) {
        if (!is_null($myMapId) and $myMapId !== '') {
            // forbid folder placement in my-maps
            if ($directory === 'true') {
                return 0;
            }
            $folders = $this->userfolder->getById($myMapId);
            $folder = array_shift($folders);
            // photo's path is relative to this map's folder => get full path, don't copy
            if ($relative === 'true') {
                foreach ($paths as $key => $path) {
                    $photoFile = $folder->get($path);
                    $paths[$key] = $this->userfolder->getRelativePath($photoFile->getPath());
                }
            } else {
                // here the photo path is good, copy it in this map's folder if it's not already there
                foreach ($paths as $key => $path) {
                    $photoFile = $this->userfolder->get($path);
                    // is the photo in this map's folder?
                    if (!$folder->getById($photoFile->getId())) {
                        $copiedFile = $photoFile->copy($folder->getPath() . '/' . $photoFile->getName());
                        $paths[$key] = $this->userfolder->getRelativePath($copiedFile->getPath());
                    }
                }
            }
        }
        $result = $this->photofilesService->setPhotosFilesCoords($this->userId, $paths, $lats, $lngs, $directory);
        return new DataResponse($result);
    }

    /**
     * @NoAdminRequired
     */
    public function resetPhotosCoords($paths, $myMapId=null) {
        $result = 0;
        if (!is_null($myMapId) and $myMapId !== '') {
            foreach ($paths as $key => $path) {
                $folders = $this->userfolder->getById($myMapId);
                $folder = array_shift($folders);
                $photoFile = $this->userfolder->get($path);
                if ($folder->isSubNode($photoFile)) {
                    $photoFile->delete();
                    unset($paths[$key]);
                    $result++;
                }
            }
        }
        if (sizeof($paths) > 0) {
            $result += $this->photofilesService->resetPhotosFilesCoords($this->userId, $paths);
        }
        return new DataResponse($result);
    }

}
