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

use OCA\Maps\Service\DevicesService;

class DevicesApiController extends ApiController {

    private $userId;
    private $userfolder;
    private $config;
    private $appVersion;
    private $shareManager;
    private $userManager;
    private $groupManager;
    private $dbconnection;
    private $dbtype;
    private $dbdblquotes;
    private $defaultDeviceId;
    private $trans;
    private $logger;
    private $devicesService;
    protected $appName;

    public function __construct($AppName, IRequest $request, $UserId,
                                $userfolder, $config, $shareManager,
                                IAppManager $appManager, $userManager,
                                $groupManager, IL10N $trans, $logger, DevicesService $devicesService){
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
        $this->trans = $trans;
        $this->dbtype = $config->getSystemValue('dbtype');
        // IConfig object
        $this->config = $config;
        $this->dbconnection = \OC::$server->getDatabaseConnection();
        if ($UserId !== '' and $userfolder !== null){
            // path of user files folder relative to DATA folder
            $this->userfolder = $userfolder;
        }
        $this->shareManager = $shareManager;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function getDevices($apiversion) {
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
     */
    public function getDevicePoints($id, $pruneBefore=0) {
        $points = $this->devicesService->getDevicePointsFromDB($this->userId, $id, $pruneBefore);
        return new DataResponse($points);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function addDevicePoint($apiversion, $lat, $lng, $timestamp=null, $user_agent=null, $altitude=null, $battery=null, $accuracy=null) {
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
            return new DataResponse('invalid values', 400);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function editDevice($id, $color) {
        $device = $this->devicesService->getDeviceFromDB($id, $this->userId);
        if ($device !== null) {
            if (is_string($color) && strlen($color) > 0) {
                $this->devicesService->editDeviceInDB($id, $color, null);
                $editedDevice = $this->devicesService->getDeviceFromDB($id, $this->userId);
                return new DataResponse($editedDevice);
            }
            else {
                return new DataResponse('invalid values', 400);
            }
        }
        else {
            return new DataResponse('no such device', 400);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function deleteDevice($id) {
        $device = $this->devicesService->getDeviceFromDB($id, $this->userId);
        if ($device !== null) {
            $this->devicesService->deleteDeviceFromDB($id);
            return new DataResponse('DELETED');
        }
        else {
            return new DataResponse('no such device', 400);
        }
    }

    private function normalizeOptionalNumber($value) {
        if (!is_numeric($value)) {
            return null;
        }
        return $value;
    }

}
