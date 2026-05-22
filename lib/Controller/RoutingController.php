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

use OCA\Maps\Service\TracksService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IRequest;

class RoutingController extends Controller {
	private ?Folder $userfolder = null;

	private readonly string $appVersion;

	public function __construct(
		string $appName,
		IRequest $request,
		IAppConfig $appConfig,
		private readonly IL10N $l,
		private readonly TracksService $tracksService,
		IRootFolder $rootFolder,
		private readonly ?string $userId,
	) {
		parent::__construct($appName, $request);
		$this->appVersion = $appConfig->getValueString('maps', 'installed_version');
		// IConfig object
		if ($this->userId !== '' && $this->userId !== null) {
			// path of user files folder relative to DATA folder
			$this->userfolder = $rootFolder->getUserFolder($userId);
		}
	}

	/**
	 * @param $type
	 * @param $coords
	 * @param $name
	 * @param $totDist
	 * @param $totTime
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 */
	#[NoAdminRequired]
	public function exportRoute($type, $coords, $name, $totDist, $totTime, $myMapId = null): DataResponse {
		// create /Maps directory if necessary
		$userFolder = $this->userfolder;
		if (is_null($myMapId) || $myMapId === '') {
			if (!$userFolder->nodeExists('/Maps')) {
				$userFolder->newFolder('Maps');
			}

			if ($userFolder->nodeExists('/Maps')) {
				$mapsFolder = $userFolder->get('/Maps');
				if (!$mapsFolder instanceof Folder) {
					return new DataResponse($this->l->t('/Maps is not a directory'), 400);
				}

				if (!$mapsFolder->isCreatable()) {
					return new DataResponse($this->l->t('/Maps directory is not writeable'), 400);
				}
			} else {
				return new DataResponse($this->l->t('Impossible to create /Maps directory'), 400);
			}
		} else {
			$folder = $userFolder->getFirstNodeById($myMapId);
			if (!$folder instanceof Folder) {
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
