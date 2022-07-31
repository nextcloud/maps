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

use OCP\Files\IRootFolder;
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
	private $root;

    public function __construct($AppName,
                                ILogger $logger,
                                IRequest $request,
                                GeophotoService $GeophotoService,
                                PhotofilesService $photofilesService,
								IRootFolder $root,
                                $UserId) {
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->userId = $UserId;
        $this->geophotoService = $GeophotoService;
        $this->photofilesService = $photofilesService;
		$this->root = $root;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
	 * @return DataResponse
     */
    public function getPhotosFromDb($myMapId=null): DataResponse {
		$userFolder = $this->root->getUserFolder($this->userId);
        if (is_null($myMapId) || $myMapId === "") {
            $result = $this->geophotoService->getAllFromDB($this->userId, $userFolder);
        } else {
            $folders = $userFolder->getById($myMapId);
            $folder = array_shift($folders);
            $result = $this->geophotoService->getAllFromDB($this->userId, $folder, true, false);
        }
        return new DataResponse($result);
    }

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return DataResponse
	 */
    public function getNonLocalizedPhotosFromDb(): DataResponse {
        $result = $this->geophotoService->getNonLocalizedFromDB($this->userId);
        return new DataResponse($result);
    }


	/**
	 * @NoAdminRequired
	 * @param $paths
	 * @param $lats
	 * @param $lngs
	 * @param bool $directory
	 * @return DataResponse
	 */
    public function placePhotos($paths, $lats, $lngs, bool $directory=false, $myMapId=null, $relative=false): DataResponse {
		$userFolder = $this->root->getUserFolder($this->userId);
        if (!is_null($myMapId) and $myMapId !== '') {
            // forbid folder placement in my-maps
            if ($directory === 'true') {
                return 0;
            }
            $folders = $userFolder->getById($myMapId);
            $folder = array_shift($folders);
            // photo's path is relative to this map's folder => get full path, don't copy
            if ($relative === 'true') {
                foreach ($paths as $key => $path) {
                    $photoFile = $folder->get($path);
                    $paths[$key] = $userFolder->getRelativePath($photoFile->getPath());
                }
            } else {
                // here the photo path is good, copy it in this map's folder if it's not already there
                foreach ($paths as $key => $path) {
                    $photoFile = $userFolder->get($path);
                    // is the photo in this map's folder?
                    if (!$folder->getById($photoFile->getId())) {
                        $copiedFile = $photoFile->copy($folder->getPath() . '/' . $photoFile->getName());
                        $paths[$key] = $userFolder->getRelativePath($copiedFile->getPath());
                    }
                }
            }
        }
        $result = $this->photofilesService->setPhotosFilesCoords($this->userId, $paths, $lats, $lngs, $directory);
        return new DataResponse($result);
    }

	/**
	 * @NoAdminRequired
	 * @param $paths
	 * @return DataResponse
	 */
    public function resetPhotosCoords($paths, $myMapId=null): DataResponse {
		$userFolder = $this->root->getUserFolder($this->userId);
        $result = [];
        if (sizeof($paths) > 0) {
			$result = $this->photofilesService->resetPhotosFilesCoords($this->userId, $paths);
        }
		if (!is_null($myMapId) and $myMapId !== '') {
			foreach ($paths as $key => $path) {
				$folders = $userFolder->getById($myMapId);
				$folder = array_shift($folders);
				$photoFile = $userFolder->get($path);
				if ($folder->isSubNode($photoFile)) {
					$photoFile->delete();
					unset($paths[$key]);
				}
			}
		}
        return new DataResponse($result);
    }

}
