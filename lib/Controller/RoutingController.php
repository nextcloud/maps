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
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\IRequest;

class RoutingController extends Controller {
	private Folder $userfolder;
	private readonly string $appVersion;

	public function __construct(
		string $appName,
		IRequest $request,
		IRootFolder $rootFolder,
		IAppConfig $config,
		private readonly IL10N $l,
		private readonly TracksService $tracksService,
		private readonly ?string $userId,
	) {
		parent::__construct($appName, $request);
		$this->appVersion = $config->getAppValueString('maps', 'installed_version');
		if ($this->userId !== '' && $this->userId !== null) {
			// path of user files folder relative to DATA folder
			$this->userfolder = $rootFolder->getUserFolder($this->userId);
		}
	}

	/**
	 * @param 'route'|'track' $type
	 * @param $coords
	 * @param $totDist
	 * @param $totTime
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 */
	#[NoAdminRequired]
	public function exportRoute(string $type, $coords, string $name, $totDist, $totTime, ?int $myMapId = null): DataResponse {
		// create /Maps directory if necessary
		$userFolder = $this->userfolder;
		if (is_null($myMapId) || $myMapId === 0) {
			try {
				/** @var Folder $mapsFolder */
				$mapsFolder = $userFolder->get('Maps');
			} catch (NotFoundException) {
				try {
					$mapsFolder = $userFolder->newFolder('Maps');
				} catch (NotPermittedException) {
					return new DataResponse($this->l->t('Impossible to create /Maps directory'), 400);
				}
			}
		} else {
			$mapsFolder = $userFolder->getFirstNodeById($myMapId);
			if (!$mapsFolder instanceof Folder) {
				return new DataResponse('myMaps Folder not found', 404);
			}
		}

		$filename = $name . '.gpx';
		if ($mapsFolder->nodeExists($filename)) {
			$mapsFolder->get($filename)->delete();
		}
		if ($mapsFolder->nodeExists($filename . '.tmp')) {
			$mapsFolder->get($filename . '.tmp')->delete();
		}
		$file = $mapsFolder->newFile($filename . 'tmp');
		$fileHandler = $file->fopen('w');

		$dt = new \DateTime();
		$date = $dt->format('Y-m-d\TH:i:s\Z');

		$gpxHeader = '<?xml version="1.0" encoding="UTF-8" standalone="yes" ?>
<gpx version="1.1" creator="Nextcloud Maps ' . $this->appVersion . '" xmlns="http://www.topografix.com/GPX/1/1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd">
  <metadata>
    <name>' . $name . '</name>
    <time>' . $date . '</time>
  </metadata>';
		fwrite($fileHandler, $gpxHeader . "\n");

		if ($type === 'route') {
			fwrite($fileHandler, '  <rte>' . "\n");
			fwrite($fileHandler, '    <name>' . $name . '</name>' . "\n");
			foreach ($coords as $ll) {
				$line = '    <rtept lat="' . $ll['lat'] . '" lon="' . $ll['lng'] . '"></rtept>' . "\n";
				fwrite($fileHandler, $line);
			}
			fwrite($fileHandler, '  </rte>' . "\n");
		} elseif ($type === 'track') {
			fwrite($fileHandler, '  <trk>' . "\n");
			fwrite($fileHandler, '    <name>' . $name . '</name>' . "\n");
			fwrite($fileHandler, '    <trkseg>' . "\n");
			foreach ($coords as $ll) {
				$line = '      <trkpt lat="' . $ll['lat'] . '" lon="' . $ll['lng'] . '"></trkpt>' . "\n";
				fwrite($fileHandler, $line);
			}
			fwrite($fileHandler, '    </trkseg>' . "\n");
			fwrite($fileHandler, '  </trk>' . "\n");
		}
		fwrite($fileHandler, '</gpx>' . "\n");
		fclose($fileHandler);
		$file->touch();
		$file->move(substr((string)$file->getPath(), 0, -3));
		$track = $this->tracksService->getTrackByFileIDFromDB($file->getId(), $this->userId);
		return new DataResponse($track);
	}

}
