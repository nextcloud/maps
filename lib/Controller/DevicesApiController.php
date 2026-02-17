<?php

declare(strict_types=1);

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
use OCP\AppFramework\Http\Attribute\CORS;
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
		parent::__construct($appName, $request,
			'PUT, POST, GET, DELETE, PATCH, OPTIONS',
			'Authorization, Content-Type, Accept',
			1728000);
	}

	/**
	 * @param $apiversion
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
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

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function getDevicePoints(int $id, int $pruneBefore = 0): DataResponse {
		$points = $this->devicesService->getDevicePointsFromDB($this->userId, $id, $pruneBefore);
		return new DataResponse($points);
	}

	/**
	 * @param $apiversion
	 * @param $lat
	 * @param $lng
	 * @param $timestamp
	 * @param $user_agent
	 * @param $altitude
	 * @param $battery
	 * @param $accuracy
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function addDevicePoint($apiversion, $lat, $lng, $timestamp = null, $user_agent = null, $altitude = null, $battery = null, $accuracy = null): DataResponse {
		if (is_numeric($lat) && is_numeric($lng)) {
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
		}

		return new DataResponse($this->l->t('Invalid values'), 400);
	}

	/**
	 * @param $id
	 * @param $color
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function editDevice(int $id, $color): DataResponse {
		$device = $this->devicesService->getDeviceFromDB($id, $this->userId);
		if ($device !== null) {
			if (is_string($color) && $color !== '') {
				$this->devicesService->editDeviceInDB($id, $color, null);
				$editedDevice = $this->devicesService->getDeviceFromDB($id, $this->userId);
				return new DataResponse($editedDevice);
			}

			return new DataResponse($this->l->t('Invalid values'), 400);
		}

		return new DataResponse($this->l->t('No such device'), 400);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function deleteDevice(int $id): DataResponse {
		$device = $this->devicesService->getDeviceFromDB($id, $this->userId);
		if ($device !== null) {
			$this->devicesService->deleteDeviceFromDB($id);
			return new DataResponse('DELETED');
		}

		return new DataResponse($this->l->t('No such device'), 400);
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
