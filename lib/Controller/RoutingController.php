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

use OCA\Maps\Service\TracksService;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IDateTimeZone;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IServerContainer;
use OCP\IUserManager;
use OCP\Share\IManager;

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
	private $l;
	private $dateTimeZone;
	private $tracksService;
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
		IDateTimeZone $dateTimeZone,
		TracksService $tracksService,
		$UserId) {
		parent::__construct($AppName, $request);
		$this->dateTimeZone = $dateTimeZone;
		$this->appName = $AppName;
		$this->appVersion = $config->getAppValue('maps', 'installed_version');
		$this->userId = $UserId;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->l = $l;
		$this->dbtype = $config->getSystemValue('dbtype');
		// IConfig object
		$this->config = $config;
		if ($UserId !== '' and $UserId !== null and $serverContainer !== null) {
			// path of user files folder relative to DATA folder
			$this->userfolder = $serverContainer->getUserFolder($UserId);
		}
		$this->shareManager = $shareManager;
		$this->tracksService = $tracksService;
	}

	/**
	 * @NoAdminRequired
	 * @param $type
	 * @param $coords
	 * @param $name
	 * @param $totDist
	 * @param $totTime
	 * @return DataResponse
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function exportRoute($type, $coords, $name, $totDist, $totTime, $myMapId = null): DataResponse {
		// create /Maps directory if necessary
		$userFolder = $this->userfolder;
		if (is_null($myMapId) || $myMapId === '') {
			if (!$userFolder->nodeExists('/Maps')) {
				$userFolder->newFolder('Maps');
			}
			if ($userFolder->nodeExists('/Maps')) {
				$mapsFolder = $userFolder->get('/Maps');
				if ($mapsFolder->getType() !== \OCP\Files\FileInfo::TYPE_FOLDER) {
					$response = new DataResponse($this->l->t('/Maps is not a directory'), 400);
					return $response;
				} elseif (!$mapsFolder->isCreatable()) {
					$response = new DataResponse($this->l->t('/Maps directory is not writeable'), 400);
					return $response;
				}
			} else {
				$response = new DataResponse($this->l->t('Impossible to create /Maps directory'), 400);
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
		if ($mapsFolder->nodeExists($filename.'.tmp')) {
			$mapsFolder->get($filename.'.tmp')->delete();
		}
		$file = $mapsFolder->newFile($filename.'tmp');
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
		$file->move(substr($file->getPath(), 0, -3));
		$track = $this->tracksService->getTrackByFileIDFromDB($file->getId(), $this->userId);
		return new DataResponse($track);
	}

}
