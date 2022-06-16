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

use OCP\App\IAppManager;

use OCP\IURLGenerator;
use OCP\IConfig;
use \OCP\IL10N;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\ApiController;
use OCP\Constants;
use OCP\Share;
use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\IServerContainer;
use OCP\IGroupManager;
use OCP\ILogger;

use OCA\Maps\Service\DevicesService;

class DevicesApiController extends ApiController {

    private $userId;
    private $userfolder;
    private $config;
    private $appVersion;
    private $shareManager;
    private $userManager;
    private $groupManager;
    private $dbtype;
    private $dbdblquotes;
    private $defaultDeviceId;
    private $l;
    private $logger;
    private $devicesService;
    protected $appName;

    public function __construct($AppName,
                                IRequest $request,
                                IServerContainer $serverContainer,
                                IConfig $config,
                                IManager $shareManager,
                                IAppManager $appManager,
                                IUserManager $userManager,
                                IGroupManager $groupManager,
                                IL10N $l,
                                ILogger $logger,
                                DevicesService $devicesService,
                                $UserId){
        parent::__construct($AppName, $request,
                            'PUT, POST, GET, DELETE, PATCH, OPTIONS',
                            'Authorization, Content-Type, Accept',
                            1728000);
        $this->devicesService = $devicesService;
        $this->logger = $logger;
        $this->appName = $AppName;
        $this->appVersion = $config->getAppValue('maps', 'installed_version');
        $this->userId = $UserId;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->l = $l;
        $this->dbtype = $config->getSystemValue('dbtype');
        // IConfig object
        $this->config = $config;
        if ($UserId !== '' and $UserId !== null and $serverContainer !== null){
            // path of user files folder relative to DATA folder
            $this->userfolder = $serverContainer->getUserFolder($UserId);
        }
        $this->shareManager = $shareManager;
    }

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @param $apiversion
	 * @return DataResponse
	 */
    public function getDevices($apiversion): DataResponse {
        $now = new \DateTime();

        $devices = $this->devicesService->getDevicesFromDB($this->userId);

        $etag = md5(json_encode($devices));
        if ($this->request->getHeader('If-None-Match') === '"'.$etag.'"') {
            return new DataResponse([], Http::STATUS_NOT_MODIFIED);
        }
        return (new DataResponse($devices))
            ->setLastModified($now)
            ->setETag($etag);
    }

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @param $id
	 * @param int $pruneBefore
	 * @return DataResponse
	 */
    public function getDevicePoints($id, int $pruneBefore=0): DataResponse {
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
	 * @return DataResponse
	 */
    public function addDevicePoint($apiversion, $lat, $lng, $timestamp=null, $user_agent=null, $altitude=null, $battery=null, $accuracy=null): DataResponse {
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
                'deviceId'=>$deviceId,
                'pointId'=>$pointId
            ]);
        }
        else {
            return new DataResponse($this->l->t('Invalid values'), 400);
        }
    }

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @param $id
	 * @param $color
	 * @return DataResponse
	 */
    public function editDevice($id, $color): DataResponse {
        $device = $this->devicesService->getDeviceFromDB($id, $this->userId);
        if ($device !== null) {
            if (is_string($color) && strlen($color) > 0) {
                $this->devicesService->editDeviceInDB($id, $color, null);
                $editedDevice = $this->devicesService->getDeviceFromDB($id, $this->userId);
                return new DataResponse($editedDevice);
            }
            else {
                return new DataResponse($this->l->t('Invalid values'), 400);
            }
        }
        else {
            return new DataResponse($this->l->t('No such device'), 400);
        }
    }

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @param $id
	 * @return DataResponse
	 */
    public function deleteDevice($id): DataResponse {
        $device = $this->devicesService->getDeviceFromDB($id, $this->userId);
        if ($device !== null) {
            $this->devicesService->deleteDeviceFromDB($id);
            return new DataResponse('DELETED');
        }
        else {
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
