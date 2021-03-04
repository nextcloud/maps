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

use OCP\IDateTimeZone;

use OCA\Maps\Service\DevicesService;

//use function \OCA\Maps\Service\endswith;

class DevicesController extends Controller {

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
    private $trans;
    private $logger;
    private $devicesService;
    private $dateTimeZone;
    protected $appName;

    public function __construct($AppName,
                                IRequest $request,
                                IServerContainer $serverContainer,
                                IConfig $config,
                                IManager $shareManager,
                                IAppManager $appManager,
                                IUserManager $userManager,
                                IGroupManager $groupManager,
                                IL10N $trans,
                                ILogger $logger,
                                DevicesService $devicesService,
                                IDateTimeZone $dateTimeZone,
                                $UserId){
        parent::__construct($AppName, $request);
        $this->devicesService = $devicesService;
        $this->dateTimeZone = $dateTimeZone;
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
        if ($UserId !== '' and $UserId !== null and $serverContainer !== null){
            // path of user files folder relative to DATA folder
            $this->userfolder = $serverContainer->getUserFolder($UserId);
        }
        $this->shareManager = $shareManager;
    }

    /**
     * @NoAdminRequired
     */
    public function getDevices() {
        $devices = $this->devicesService->getDevicesFromDB($this->userId);
        return new DataResponse($devices);
    }

    /**
     * @NoAdminRequired
     */
    public function getDevicePoints($id, $pruneBefore=0) {
        $points = $this->devicesService->getDevicePointsFromDB($this->userId, $id, $pruneBefore);
        return new DataResponse($points);
    }

    /**
     * @NoAdminRequired
     */
    public function addDevicePoint($lat, $lng, $timestamp=null, $user_agent=null, $altitude=null, $battery=null, $accuracy=null) {
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
     */
    public function editDevice($id, $color, $name) {
        $device = $this->devicesService->getDeviceFromDB($id, $this->userId);
        if ($device !== null) {
            if ((is_string($color) && strlen($color) > 0) ||
                (is_string($name) && strlen($name) > 0)
            ) {
                $this->devicesService->editDeviceInDB($id, $color, $name);
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

    /**
     * @NoAdminRequired
     */
    public function exportDevices($deviceIdList=null, $begin, $end, $all=false) {
        // sorry about ugly deviceIdList management:
        // when an empty list is passed in http request, we get null here
        if ($deviceIdList === null or (is_array($deviceIdList) and count($deviceIdList) === 0)) {
            return new DataResponse('No device to export', 400);
        }

        // create /Maps directory if necessary
        $userFolder = $this->userfolder;
        if (!$userFolder->nodeExists('/Maps')) {
            $userFolder->newFolder('Maps');
        }
        if ($userFolder->nodeExists('/Maps')) {
            $mapsFolder = $userFolder->get('/Maps');
            if ($mapsFolder->getType() !== \OCP\Files\FileInfo::TYPE_FOLDER) {
                return new DataResponse('/Maps is not a directory', 400);
            }
            else if (!$mapsFolder->isCreatable()) {
                return new DataResponse('/Maps is not writeable', 400);
            }
        }
        else {
            return new DataResponse('Impossible to create /Maps', 400);
        }

        $nbDevices = $this->devicesService->countPoints($this->userId, $deviceIdList, $begin, $end);
        if ($nbDevices === 0) {
            return new DataResponse('Nothing to export', 400);
        }

        // generate export file name
        $prefix = $all ? '' : 'filtered-';
        $tz = $this->dateTimeZone->getTimeZone();
        $now = new \DateTime('now', $tz);
        $dateStr = $now->format('Y-m-d H:i:s (P)');
        $filename = $dateStr.' '.$prefix.'devices.gpx';

        if ($mapsFolder->nodeExists($filename)) {
            $mapsFolder->get($filename)->delete();
        }
        $file = $mapsFolder->newFile($filename);
        $handler = $file->fopen('w');

        $this->devicesService->exportDevices($this->userId, $handler, $deviceIdList, $begin, $end, $this->appVersion, $filename);

        fclose($handler);
        $file->touch();
        return new DataResponse('/Maps/'.$filename);
    }

    /**
     * @NoAdminRequired
     */
    public function importDevices($path) {
        $userFolder = $this->userfolder;
        $cleanpath = str_replace(array('../', '..\\'), '',  $path);

        if ($userFolder->nodeExists($cleanpath)){
            $file = $userFolder->get($cleanpath);
            if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                $file->isReadable()){
                $lowerFileName = strtolower($file->getName());
                if ($this->endswith($lowerFileName, '.gpx') or $this->endswith($lowerFileName, '.kml') or $this->endswith($lowerFileName, '.kmz')) {
                    $nbImported = $this->devicesService->importDevices($this->userId, $file);
                    return new DataResponse($nbImported);
                }
                else {
                    // invalid extension
                    return new DataResponse('Invalid file extension', 400);
                }
            }
            else {
                // directory or not readable
                return new DataResponse('Impossible to read the file', 400);
            }
        }
        else {
            // does not exist
            return new DataResponse('File does not exist', 400);
        }
    }

    private function endswith($string, $test) {
        $strlen = strlen($string);
        $testlen = strlen($test);
        if ($testlen > $strlen) return false;
        return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
    }

}
