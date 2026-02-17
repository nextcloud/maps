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

use OCA\Maps\DB\DeviceShareMapper;
use OCA\Maps\Service\DevicesService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IAppConfig;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IRequest;

class DevicesController extends Controller {
	private ?Folder $userFolder = null;
	private string $appVersion;

	public function __construct(
		string $appName,
		IRequest $request,
		IAppConfig $appConfig,
		private IL10N $l,
		private DevicesService $devicesService,
		private DeviceShareMapper $deviceShareMapper,
		private IDateTimeZone $dateTimeZone,
		private IRootFolder $root,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
		$this->appVersion = $appConfig->getValueString('maps', 'installed_version');
		if ($userId !== '' && $userId !== null) {
			// path of user files folder relative to DATA folder
			$this->userFolder = $this->root->getUserFolder($userId);
		}
	}

	/**
	 * @param ?string[] $tokens
	 */
	#[NoAdminRequired]
	public function getDevices(?array $tokens = null, ?int $myMapId = null): DataResponse {
		if (is_null($tokens)) {
			$tokens = [];
		}
		if (is_null($myMapId)) {
			$devices = $this->devicesService->getDevicesFromDB($this->userId);
			$deviceIds = array_column($devices, 'id');
			$shares = $this->deviceShareMapper->findByDeviceIds($deviceIds);
			foreach ($shares as $s) {
				$devices[$s->getDeviceId()]['shares'][] = $s;
			}
		} else {
			$devices = [];
			$userFolder = $this->root->getUserFolder($this->userId);
			$folder = $userFolder->getFirstNodeById($myMapId);
			if (!$folder instanceof Folder) {
				return new DataResponse($this->l->t('Map not Found'), 404);
			}
			$shares = $this->devicesService->getSharedDevicesFromFolder($folder);
			$st = array_column($shares, 'token');
			$tokens = array_merge($tokens, $st);
		}
		$td = $this->devicesService->getDevicesByTokens($tokens);
		$devices = $td + $devices;
		return new DataResponse(array_values($devices));
	}

	/**
	 * @param string[] $tokens
	 */
	#[NoAdminRequired]
	public function getDevicesByTokens(array $tokens): DataResponse {
		$devices = $this->devicesService->getDevicesByTokens($tokens);
		return new DataResponse(array_values($devices));
	}

	#[NoAdminRequired]
	public function getDevicePoints(int $id, ?int $pruneBefore = 0, ?int $limit = 10000, ?int $offset = 0, ?array $tokens = null): DataResponse {
		if (is_null($tokens)) {
			$points = $this->devicesService->getDevicePointsFromDB($this->userId, $id, $pruneBefore, $limit, $offset);
		} else {
			$points = $this->devicesService->getDevicePointsByTokens($tokens, $pruneBefore, $limit, $offset);
		}
		return new DataResponse($points);
	}

	/**
	 * @param $lat
	 * @param $lng
	 * @param null $timestamp
	 * @param null $user_agent
	 * @param null $altitude
	 * @param null $battery
	 * @param null $accuracy
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function addDevicePoint($lat, $lng, $timestamp = null, $user_agent = null, $altitude = null, $battery = null, $accuracy = null): DataResponse {
		if (is_numeric($lat) and is_numeric($lng)) {
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
			return new DataResponse('Invalid values', 400);
		}
	}

	#[NoAdminRequired]
	public function editDevice(int $id, string $color, string $name): DataResponse {
		$device = $this->devicesService->getDeviceFromDB($id, $this->userId);
		if ($device !== null) {
			if (strlen($color) > 0 || strlen($name) > 0) {
				$this->devicesService->editDeviceInDB($id, $color, $name);
				$editedDevice = $this->devicesService->getDeviceFromDB($id, $this->userId);
				return new DataResponse($editedDevice);
			} else {
				return new DataResponse($this->l->t('Invalid values'), 400);
			}
		} else {
			return new DataResponse($this->l->t('No such device'), 400);
		}
	}

	#[NoAdminRequired]
	public function deleteDevice(int $id): DataResponse {
		$device = $this->devicesService->getDeviceFromDB($id, $this->userId);
		if ($device !== null) {
			$this->devicesService->deleteDeviceFromDB($id);
			$this->deviceShareMapper->removeAllByDeviceId($id);
			return new DataResponse('DELETED');
		} else {
			return new DataResponse($this->l->t('No such device'), 400);
		}
	}

	/**
	 * @param ?array $deviceIdList
	 * @param int $begin
	 * @param int $end
	 * @param bool $all=false
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 */
	#[NoAdminRequired]
	public function exportDevices($deviceIdList, $begin, $end, bool $all = false): DataResponse {
		// sorry about ugly deviceIdList management:
		// when an empty list is passed in http request, we get null here
		if ($deviceIdList === null or (is_array($deviceIdList) and count($deviceIdList) === 0)) {
			return new DataResponse($this->l->t('No device to export'), 400);
		}

		// create /Maps directory if necessary
		$userFolder = $this->userFolder;
		if (!$userFolder->nodeExists('/Maps')) {
			$userFolder->newFolder('Maps');
		}
		if ($userFolder->nodeExists('/Maps')) {
			$mapsFolder = $userFolder->get('/Maps');
			if (!$mapsFolder instanceof Folder) {
				return new DataResponse($this->l->t('/Maps is not a directory'), 400);
			} elseif (!$mapsFolder->isCreatable()) {
				return new DataResponse($this->l->t('/Maps directory is not writeable'), 400);
			}
		} else {
			return new DataResponse($this->l->t('Impossible to create /Maps directory'), 400);
		}

		$nbDevices = $this->devicesService->countPoints($this->userId, $deviceIdList, $begin, $end);
		if ($nbDevices === 0) {
			return new DataResponse($this->l->t('Nothing to export'), 400);
		}

		// generate export file name
		$prefix = $all ? '' : 'filtered-';
		$tz = $this->dateTimeZone->getTimeZone();
		$now = new \DateTime('now', $tz);
		$dateStr = $now->format('Y-m-d H:i:s (P)');
		$filename = $dateStr . ' ' . $prefix . 'devices.gpx';

		if ($mapsFolder->nodeExists($filename)) {
			$mapsFolder->get($filename)->delete();
		}
		$file = $mapsFolder->newFile($filename);
		$handler = $file->fopen('w');

		$this->devicesService->exportDevices($this->userId, $handler, $deviceIdList, $begin, $end, $this->appVersion, $filename);

		fclose($handler);
		$file->touch();
		return new DataResponse('/Maps/' . $filename);
	}

	/**
	 * @param $path
	 * @return DataResponse
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	#[NoAdminRequired]
	public function importDevices($path): DataResponse {
		$userFolder = $this->userFolder;
		$cleanpath = str_replace(['../', '..\\'], '', $path);

		if ($userFolder->nodeExists($cleanpath)) {
			$file = $userFolder->get($cleanpath);
			if ($file instanceof File && $file->isReadable()) {
				$lowerFileName = strtolower($file->getName());
				if (str_ends_with($lowerFileName, '.gpx') || str_ends_with($lowerFileName, '.kml') || str_ends_with($lowerFileName, '.kmz')) {
					$nbImported = $this->devicesService->importDevices($this->userId, $file);
					return new DataResponse($nbImported);
				} else {
					// invalid extension
					return new DataResponse($this->l->t('Invalid file extension'), 400);
				}
			} else {
				// directory or not readable
				return new DataResponse($this->l->t('Impossible to read the file'), 400);
			}
		} else {
			// does not exist
			return new DataResponse($this->l->t('File does not exist'), 400);
		}
	}

	/**
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	#[NoAdminRequired]
	public function getSharedDevices(?int $myMapId = null): DataResponse {
		if (is_null($myMapId)) {
			$sharedDevices = [];
		} else {
			$folder = $this->userFolder->getFirstNodeById($myMapId);
			if ($folder instanceof Folder) {
				$sharedDevices = $this->devicesService->getSharedDevicesFromFolder($folder);
			}
		}

		return new DataResponse($sharedDevices);
	}

	#[NoAdminRequired]
	public function shareDevice(int $id, int $timestampFrom, int $timestampTo): DataResponse {
		$device = $this->devicesService->getDeviceFromDB($id, $this->userId);
		if ($device === null) {
			return new DataResponse($this->l->t('No such device'), 400);
		}

		$share = $this->deviceShareMapper->create($id, $timestampFrom, $timestampTo);
		return new DataResponse($share);
	}

	/**
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	#[NoAdminRequired]
	public function removeDeviceShare(string $token): DataResponse {
		try {
			$share = $this->deviceShareMapper->findByToken($token);
		} catch (DoesNotExistException) {
			throw new NotFoundException();
		}
		$device = $this->devicesService->getDeviceFromDB($share->getDeviceId(), $this->userId);
		if ($device !== null) {
			return new DataResponse($this->deviceShareMapper->removeById($share->getId()));
		} else {
			throw new NotFoundException();
		}
	}

	/**
	 * @throws NotFoundException
	 */
	#[NoAdminRequired]
	public function addSharedDeviceToMap(string $token, int $targetMapId): DataResponse {
		try {
			$share = $this->deviceShareMapper->findByToken($token);
		} catch (DoesNotExistException $e) {
			return new DataResponse($this->l->t('Share not Found'), 404);
		}
		$folder = $this->userFolder->getFirstNodeById($targetMapId);
		if (!$folder instanceof Folder) {
			return new DataResponse($this->l->t('Map not Found'), 404);
		}
		try {
			/** @var File $file */
			$file = $folder->get('.device_shares.json');
		} catch (\OCP\Files\NotFoundException $e) {
			$file = $folder->newFile('.device_shares.json', '[]');
		}
		$data = json_decode($file->getContent(), true);
		foreach ($data as $s) {
			if ($s->token == $share->getToken()) {
				return new DataResponse($this->l->t('Share was already on map'));
			}
		}
		$data[] = $share;
		$file->putContent(json_encode($data, JSON_PRETTY_PRINT));
		return new DataResponse('Done');
	}

	public function removeSharedDeviceFromMap(string $token, int $myMapId): DataResponse {
		$folder = $this->userFolder->getFirstNodeById($myMapId);
		if (!$folder instanceof Folder) {
			return new DataResponse($this->l->t('Map not Found'), 404);
		}
		try {
			/** @var File $file */
			$file = $folder->get('.device_shares.json');
		} catch (\OCP\Files\NotFoundException $e) {
			$file = $folder->newFile('.device_shares.json', '[]');
		}
		$data = json_decode($file->getContent(), true);
		$shares = [];
		$deleted = null;
		foreach ($data as $share) {
			$t = $share['token'];
			if ($t === $token) {
				$deleted = $share;
			} else {
				$shares[] = $share;
			}
		}
		$file->putContent(json_encode($shares, JSON_PRETTY_PRINT));
		if (is_null($deleted)) {
			return new DataResponse('Failed', 500);
		}
		return new DataResponse('Done');
	}
}
