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

    public function __construct($AppName, ILogger $logger, IRequest $request, GeophotoService $GeophotoService,
                                PhotofilesService $photofilesService, $UserId){
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->userId = $UserId;
        $this->geophotoService = $GeophotoService;
        $this->photofilesService = $photofilesService;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getPhotosFromDb() {
        $result = $this->geophotoService->getAllFromDB($this->userId);
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
    public function placePhotos($paths, $lats, $lngs, $directory=false) {
        $result = $this->photofilesService->setPhotosFilesCoords($this->userId, $paths, $lats, $lngs, $directory);
        return new DataResponse($result);
    }

    /**
     * @NoAdminRequired
     */
    public function resetPhotosCoords($paths) {
        $result = $this->photofilesService->resetPhotosFilesCoords($this->userId, $paths);
        return new DataResponse($result);
    }

}
