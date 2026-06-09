<?php

/**
 * Nextcloud - Maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Maps\Controller;

use OCA\Maps\Service\DevicesService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;

class DevicesApiController extends ApiController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IL10N $l,
		private readonly DevicesService $devicesService,
		private readonly ?string $userId,
	) {
		parent::__construct($appName, $request, 'PUT, POST, GET, DELETE, PATCH, OPTIONS');
	}

	/**
	 * @CORS
	 * @param $apiversion
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getDevices($apiversion): DataResponse {
		$now = new \DateTime();

		$devices = $this->devicesService->getDevicesFromDB($this->userId);

		$etag = md5(json_encode($devices));
		if ($this->request->getHeader('If-None-Match') === '"' . $etag . '"') {
			return new DataResponse([], Http::STATUS_NOT_MODIFIED);
		}
		return (new DataResponse($devices))
			->setLastModified($now)
			->setETag($etag);
	}

	/**
	 * @CORS
	 * @param $id
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getDevicePoints($id, int $pruneBefore = 0): DataResponse {
		$points = $this->devicesService->getDevicePointsFromDB($this->userId, $id, $pruneBefore);
		return new DataResponse($points);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @param $apiversion
	 * @param $lat
	 * @param $lng
	 * @param $timestamp
	 * @param $user_agent
	 * @param $altitude
	 * @param $battery
	 * @param $accuracy
	 */
	public function addDevicePoint($apiversion, $lat, $lng, $timestamp = null, $user_agent = null, $altitude = null, $battery = null, $accuracy = null): DataResponse {
		if (is_numeric($lat) and is_numeric($lng)) {
			$timestamp = $this->normalizeOptionalNumber($timestamp);
			$altitude = $this->normalizeOptionalNumber($altitude);
			$battery = $this->normalizeOptionalNumber($battery);
			$accuracy = $this->normalizeOptionalNumber($accuracy);
			$ts = $timestamp;
			if ($timestamp === null) {
				$ts = (new \DateTime())->getTimestamp();
			}
			$ua = $user_agent;
			if ($user_agent === null) {
				$ua = $_SERVER['HTTP_USER_AGENT'];
			}
			$deviceId = $this->devicesService->getOrCreateDeviceFromDB($this->userId, $ua);
			$pointId = $this->devicesService->addPointToDB($deviceId, $lat, $lng, $ts, $altitude, $battery, $accuracy);
			return new DataResponse([
				'deviceId' => $deviceId,
				'pointId' => $pointId
			]);
		} else {
			return new DataResponse($this->l->t('Invalid values'), 400);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @param $id
	 * @param $color
	 */
	public function editDevice($id, $color): DataResponse {
		$device = $this->devicesService->getDeviceFromDB($id, $this->userId);
		if ($device !== null) {
			if (is_string($color) && strlen($color) > 0) {
				$this->devicesService->editDeviceInDB($id, $color, null);
				$editedDevice = $this->devicesService->getDeviceFromDB($id, $this->userId);
				return new DataResponse($editedDevice);
			} else {
				return new DataResponse($this->l->t('Invalid values'), 400);
			}
		} else {
			return new DataResponse($this->l->t('No such device'), 400);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @param $id
	 */
	public function deleteDevice($id): DataResponse {
		$device = $this->devicesService->getDeviceFromDB($id, $this->userId);
		if ($device !== null) {
			$this->devicesService->deleteDeviceFromDB($id);
			return new DataResponse('DELETED');
		} else {
			return new DataResponse($this->l->t('No such device'), 400);
		}
	}

	/**
	 * @param $value
	 * @return float|int|string|null
	 */
	private function normalizeOptionalNumber($value) {
		if (!is_numeric($value)) {
			return null;
		}
		return $value;
	}

}
