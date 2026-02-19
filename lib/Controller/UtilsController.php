<?php

declare(strict_types=1);

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
 * @copyright Benstone Zhang <benstonezhang@gmail.com> 2023
 */
namespace OCA\Maps\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\AppFramework\Http\DataResponse;


use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;

use OCP\Files\NotFoundException;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Lock\LockedException;

class UtilsController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IAppConfig $appConfig,
		private readonly IConfig $config,
		private readonly IRootFolder $root,
		private readonly string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Save options values to the DB for current user
	 *
	 * @param $options
	 * @throws \OCP\PreConditionNotMetException
	 */
	#[NoAdminRequired]
	public function saveOptionValue($options, ?int $myMapId = null): DataResponse {
		if (is_null($myMapId)) {
			foreach ($options as $key => $value) {
				$this->config->setUserValue($this->userId, 'maps', $key, $value);
			}
		} else {
			$userFolder = $this->root->getUserFolder($this->userId);
			$folder = $userFolder->getFirstNodeById($myMapId);
			if (!$folder instanceof Folder) {
				throw new NotFoundException('Could find map with mapid: ' . $myMapId);
			}

			try {
				/** @var File $file */
				$file = $folder->get('.index.maps');
			} catch (NotFoundException) {
				$file = $folder->newFile('.index.maps', $content = '{}');
			}

			try {
				$ov = json_decode((string)$file->getContent(), true, 512);
				foreach ($options as $key => $value) {
					$ov[$key] = $value;
				}

				$file->putContent(json_encode($ov, JSON_PRETTY_PRINT));
			} catch (LockedException) {
				return new DataResponse('File is locked', 500);
			}
		}

		return new DataResponse(['done' => 1]);
	}

	/**
	 * Get options values from the config for current user
	 */
	#[NoAdminRequired]
	public function getOptionsValues($myMapId = null): DataResponse {
		$ov = [];

		if (is_null($myMapId) || $myMapId === '') {
			// get all user values
			$keys = $this->config->getUserKeys($this->userId, 'maps');
			foreach ($keys as $key) {
				$value = $this->config->getUserValue($this->userId, 'maps', $key);
				$ov[$key] = $value;
			}

			$ov['isCreatable'] = true;
			$ov['isDeletable'] = false;
			$ov['isReadable'] = true;
			$ov['isUpdateable'] = true;
			$ov['isShareable'] = true;
		} else {
			$userFolder = $this->root->getUserFolder($this->userId);
			$folder = $userFolder->getFirstNodeById($myMapId);
			if (!$folder instanceof Folder) {
				throw new NotFoundException('Could find map with mapid: ' . $myMapId);
			}

			try {
				/** @var File $file */
				$file = $folder->get('.index.maps');
			} catch (NotFoundException) {
				$file = $folder->newFile('.index.maps', $content = '{}');
			}

			$ov = json_decode((string)$file->getContent(), true, 512);
			$ov['isCreatable'] = $folder->isCreatable();
			//We can delete the map by deleting the folder or the .index.maps file
			$ov['isDeletable'] = $folder->isDeletable() || $file->isDeletable();
			// Maps content can be read mostly from the folder
			$ov['isReadable'] = $folder->isReadable();
			//Saving maps information in the file
			$ov['isUpdateable'] = $file->isUpdateable();
			// Share map by sharing the folder
			$ov['isShareable'] = $folder->isShareable();
		}

		// get routing-specific admin settings values
		$settingsKeys = [
			'osrmCarURL',
			'osrmBikeURL',
			'osrmFootURL',
			'osrmDEMO',
			'graphhopperAPIKEY',
			'mapboxAPIKEY',
			'maplibreStreetStyleURL',
			'maplibreStreetStyleAuth',
			'graphhopperURL'
		];
		foreach ($settingsKeys as $k) {
			$v = $this->appConfig->getValueString('maps', $k);
			$ov[$k] = $v;
		}

		return new DataResponse(['values' => $ov]);
	}

	/**
	 * set routing settings
	 *
	 * @param $values
	 */
	public function setRoutingSettings($values): DataResponse {
		$acceptedKeys = [
			'osrmCarURL',
			'osrmBikeURL',
			'osrmFootURL',
			'osrmDEMO',
			'graphhopperAPIKEY',
			'mapboxAPIKEY',
			'maplibreStreetStyleURL',
			'maplibreStreetStyleAuth',
			'graphhopperURL'
		];
		foreach ($values as $k => $v) {
			if (in_array($k, $acceptedKeys)) {
				$this->appConfig->setValueString('maps', $k, $v);
			}
		}

		$response = new DataResponse('DONE');
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
			->addAllowedConnectDomain('*');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * Get content of mapbox traffic style
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getTrafficStyle(): DataResponse {
		$style = [
			'version' => 8,
			'name' => 'Mapbox Traffic tileset v1',
			'sources' => [
				'mapbox-traffic' => [
					'url' => 'mapbox://mapbox.mapbox-traffic-v1',
					'type' => 'vector'
				]
			],
			'layers' => [
				[
					'id' => 'traffic',
					'source' => 'mapbox-traffic',
					'source-layer' => 'traffic',
					'type' => 'line',
					'paint' => [
						'line-width' => 2.0,
						'line-color' => [
							'case',
							[
								'==',
								'low',
								[
									'get',
									'congestion'
								]
							],
							'#00ff00',
							[
								'==',
								'moderate',
								[
									'get',
									'congestion'
								]
							],
							'#ffad00',
							[
								'==',
								'heavy',
								[
									'get',
									'congestion'
								]
							],
							'#ff0000',
							[
								'==',
								'severe',
								[
									'get',
									'congestion'
								]
							],
							'#b43b71',
							'#000000'
						]
					]
				]
			]
		];
		return new DataResponse($style);
	}
}
