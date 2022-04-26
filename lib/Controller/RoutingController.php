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
use OCP\IDateTimeZone;
use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\IServerContainer;
use OCP\IGroupManager;
use OCP\ILogger;

class RoutingController extends Controller {

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
                                IDateTimeZone $dateTimeZone,
                                $UserId){
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->dateTimeZone = $dateTimeZone;
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
    public function exportRoute($type, $coords, $name, $totDist, $totTime, $myMapId=null) {
        // create /Maps directory if necessary
        $userFolder = $this->userfolder;
		if (is_null($myMapId) || $myMapId === '') {
			if (!$userFolder->nodeExists('/Maps')) {
				$userFolder->newFolder('Maps');
			}
			if ($userFolder->nodeExists('/Maps')) {
				$mapsFolder = $userFolder->get('/Maps');
				if ($mapsFolder->getType() !== \OCP\Files\FileInfo::TYPE_FOLDER) {
					$response = new DataResponse('/Maps is not a directory', 400);
					return $response;
				}
				else if (!$mapsFolder->isCreatable()) {
					$response = new DataResponse('/Maps is not writeable', 400);
					return $response;
				}
			}
			else {
				$response = new DataResponse('Impossible to create /Maps', 400);
				return $response;
			}
		} else {
			$folders = $userFolder->getById($myMapId);
			if (!is_array($folders) or count($folders) === 0) {
				$response = new DataResponse('myMaps Folder not found', 404);
				return $response;
			}
			$mapsFolder = array_shift($folders);
			if (is_null($mapsFolder)) {
				$response = new DataResponse('myMaps Folder not found', 404);
				return $response;
			}
		}

        $filename = $name.'.gpx';
        if ($mapsFolder->nodeExists($filename)) {
            $mapsFolder->get($filename)->delete();
        }
        $file = $mapsFolder->newFile($filename);
        $fileHandler = $file->fopen('w');

        $dt = new \DateTime();
        $date = $dt->format('Y-m-d\TH:i:s\Z');

        $gpxHeader = '<?xml version="1.0" encoding="UTF-8" standalone="yes" ?>
<gpx version="1.1" creator="Nextcloud Maps '.$this->appVersion.'" xmlns="http://www.topografix.com/GPX/1/1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd">
  <metadata>
    <name>'.$name.'</name>
    <time>'.$date.'</time>
  </metadata>';
        fwrite($fileHandler, $gpxHeader."\n");

        if ($type === 'route') {
            fwrite($fileHandler, '  <rte>'."\n");
            fwrite($fileHandler, '    <name>'.$name.'</name>'."\n");
            foreach ($coords as $ll) {
                $line = '    <rtept lat="' . $ll['lat'] . '" lon="' . $ll['lng'] . '"></rtept>' . "\n";
                fwrite($fileHandler, $line);
            }
            fwrite($fileHandler, '  </rte>'."\n");
        } elseif ($type === 'track') {
            fwrite($fileHandler, '  <trk>'."\n");
            fwrite($fileHandler, '    <name>'.$name.'</name>'."\n");
            fwrite($fileHandler, '    <trkseg>'."\n");
            foreach ($coords as $ll) {
                $line = '      <trkpt lat="' . $ll['lat'] . '" lon="' . $ll['lng'] . '"></trkpt>' . "\n";
                fwrite($fileHandler, $line);
            }
            fwrite($fileHandler, '    </trkseg>'."\n");
            fwrite($fileHandler, '  </trk>'."\n");
        }
        fwrite($fileHandler, '</gpx>'."\n");
        fclose($fileHandler);
        $file->touch();
        return new DataResponse($userFolder->getRelativePath($file->getPath()));
    }

}
