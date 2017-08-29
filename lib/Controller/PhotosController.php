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

class PhotosController extends Controller {
	private $userId;
	private $geophotoService;
	private $logger;

	public function __construct($AppName, ILogger $logger, IRequest $request, GeophotoService $GeophotoService, $UserId){
		parent::__construct($AppName, $request);
		$this->logger = $logger;
		$this->userId = $UserId;
		$this->geophotoService = $GeophotoService;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */	
	public function getPhotosFromDb() {
		$result = $this->geophotoService->getAllFromDB($this->userId);
		return new DataResponse($result);
	}

}
