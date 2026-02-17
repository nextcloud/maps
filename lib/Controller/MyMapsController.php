<?php

declare(strict_types=1);

/**
 * Nextcloud - Maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @author Paul Schwörer <hello@paulschwoerer.de>
 * @copyright Julien Veyssier 2019
 * @copyright Paul Schwörer 2019
 */
namespace OCA\Maps\Controller;

use OCA\Maps\Service\MyMapsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class MyMapsController extends Controller {



	public function __construct(
		string $AppName,
		IRequest $request,
		private readonly MyMapsService $myMapsService,
		private $userId,
	) {
		parent::__construct($AppName, $request);
	}

	/**
	 * @NoAdminRequired
	 */
	public function addMyMap(array $values): DataResponse {
		$newName = $values['newName'] ?? 'New Map';
		$myMap = $this->myMapsService->addMyMap($newName, $this->userId);
		if (is_string($myMap)) {
			new DataResponse($myMap, 400);
		}

		return new DataResponse($myMap);
	}

	/**
	 * @NoAdminRequired
	 */
	public function updateMyMap(int $id, array $values): DataResponse {
		$myMap = $this->myMapsService->updateMyMap($id, $values, $this->userId);
		return new DataResponse($myMap);
	}

	/**
	 * @NoAdminRequired
	 */
	public function deleteMyMap(int $id): DataResponse {
		$result = $this->myMapsService->deleteMyMap($id, $this->userId);
		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getMyMaps(): DataResponse {
		$myMaps = $this->myMapsService->getAllMyMaps($this->userId);
		return new DataResponse($myMaps);
	}
}
