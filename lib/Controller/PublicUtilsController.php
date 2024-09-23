<?php
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

use OCP\AppFramework\Http\DataResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\GenericFileException;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IInitialStateService;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Lock\LockedException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;

class PublicUtilsController extends PublicPageController {

	protected IRootFolder $root;

	public function __construct(
		string $appName,
		IRequest $request,
		ISession $session,
		IURLGenerator $urlGenerator,
		IConfig $config,
		IInitialStateService $initialStateService,
		IUserManager $userManager,
		ShareManager $shareManager,
		IRootFolder $root,
		IEventDispatcher $eventDispatcher
	) {
		parent::__construct($appName, $request, $session, $urlGenerator, $eventDispatcher, $config, $initialStateService, $shareManager, $userManager);
		$this->root = $root;
	}

	/**
	 * Validate the permissions of the share
	 *
	 * @return bool
	 */
	private function validateShare(\OCP\Share\IShare $share) {
		// If the owner is disabled no access to the link is granted
		$owner = $this->userManager->get($share->getShareOwner());
		if ($owner === null || !$owner->isEnabled()) {
			return false;
		}

		// If the initiator of the share is disabled no access is granted
		$initiator = $this->userManager->get($share->getSharedBy());
		if ($initiator === null || !$initiator->isEnabled()) {
			return false;
		}

		return $share->getNode()->isReadable() && $share->getNode()->isShareable();
	}

	/**
	 * @return \OCP\Share\IShare
	 * @throws NotFoundException
	 */
	private function getShare() {
		// Check whether share exists
		try {
			$share = $this->shareManager->getShareByToken($this->getToken());
		} catch (ShareNotFound $e) {
			// The share does not exists, we do not emit an ShareLinkAccessedEvent
			throw new NotFoundException();
		}

		if (!$this->validateShare($share)) {
			throw new NotFoundException();
		}
		return $share;
	}

	/**
	 * @return \OCP\Files\File|\OCP\Files\Folder
	 * @throws NotFoundException
	 */
	private function getShareNode() {
		\OC_User::setIncognitoMode(true);

		$share = $this->getShare();

		return $share->getNode();
	}

	/**
	 * Save options values to the DB for current user
	 *
	 * @PublicPage
	 * @param $options
	 * @param null $myMapId
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws GenericFileException
	 * @throws InvalidPathException
	 * @throws NotPermittedException
	 */
	public function saveOptionValue($options, $myMapId = null): DataResponse {
		$share = $this->getShare();
		$permissions = $share->getPermissions();
		$folder = $this->getShareNode();
		$isCreatable = ($permissions & (1 << 2)) && $folder->isCreatable();

		try {
			$file = $folder->get('.index.maps');
		} catch (NotFoundException $e) {
			if ($isCreatable) {
				$file = $folder->newFile('.index.maps', $content = '{}');
			} else {
				throw new NotFoundException();
			}
		}
		$isUpdateable = ($permissions & (1 << 1)) && $file->isUpdateable();
		if (!$isUpdateable) {
			throw new NotPermittedException();
		}

		try {
			$ov = json_decode($file->getContent(), true, 512);
			foreach ($options as $key => $value) {
				$ov[$key] = $value;
			}
			$file->putContent(json_encode($ov, JSON_PRETTY_PRINT));
		} catch (LockedException $e) {
			return new DataResponse('File is locked', 500);
		}
		return new DataResponse(['done' => 1]);
	}

	/**
	 * get options values from the config for current user
	 *
	 * @PublicPage
	 * @return DataResponse
	 * @throws InvalidPathException
	 * @throws LockedException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getOptionsValues(): DataResponse {
		$ov = [];

		$share = $this->getShare();
		$permissions = $share->getPermissions();
		$folder = $this->getShareNode();
		$isCreatable = ($permissions & (1 << 2)) && $folder->isCreatable();
		try {
			$file = $folder->get('.index.maps');
		} catch (NotFoundException $e) {
			if ($isCreatable) {
				$file = $folder->newFile('.index.maps', $content = '{}');
			} else {
				throw new NotFoundException();
			}
		}
		$ov = json_decode($file->getContent(), true, 512);

		// Maps content can be read mostly from the folder
		$ov['isReadable'] = ($permissions & (1 << 0)) && $folder->isReadable();
		//Saving maps information in the file
		$ov['isUpdateable'] = ($permissions & (1 << 1)) && $file->isUpdateable();
		$ov['isCreatable'] = ($permissions & (1 << 2)) && $folder->isCreatable();
		//We can delete the map by deleting the folder or the .index.maps file
		$ov['isDeletable'] = ($permissions & (1 << 3)) && ($folder->isDeletable() || $file->isDeletable());
		// Share map by sharing the folder
		$ov['isShareable'] = ($permissions & (1 << 4)) && $folder->isShareable();


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
			$v = $this->config->getAppValue('maps', $k);
			$ov[$k] = $v;
		}
		return new DataResponse(['values' => $ov]);
	}


	/**
	 * get content of mapbox traffic style
	 * @PublicPage
	 *
	 * @return DataResponse
	 */
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
