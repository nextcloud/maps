<?php

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Piotr Bator <prbator@gmail.com>
 * @copyright Piotr Bator 2017
 */

namespace OCA\Maps\Controller;

use OCA\Maps\Service\GeophotoService;
use OCA\Maps\Service\PhotofilesService;
use OCP\AppFramework\Http\DataResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;

use OCP\IInitialStateService;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;

class PublicPhotosController extends PublicPageController {

	public function __construct(
		string $appName,
		IRequest $request,
		ISession $session,
		IURLGenerator $urlGenerator,
		IEventDispatcher $eventDispatcher,
		IConfig $config,
		IInitialStateService $initialStateService,
		ShareManager $shareManager,
		IUserManager $userManager,
		protected GeophotoService $geophotoService,
		protected PhotofilesService $photofilesService,
		protected IRootFolder $root,
	) {
		parent::__construct($appName, $request, $session, $urlGenerator, $eventDispatcher, $config, $initialStateService, $shareManager, $userManager);
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
	 * @PublicPage
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function getPhotos(): DataResponse {
		$share = $this->getShare();
		$permissions = $share->getPermissions();
		$folder = $this->getShareNode();
		$isReadable = (bool)($permissions & (1 << 0));
		if ($isReadable) {
			$owner = $share->getShareOwner();
			$pre_path = $this->root->getUserFolder($owner)->getPath();
			$result = $this->geophotoService->getAll($owner, $folder, true, false, false);
			$photos = array_map(function ($photo) use ($folder, $permissions, $pre_path) {
				$photo_object = (object)$photo;
				$photo_object->isCreatable = ($permissions & (1 << 2)) && $photo['isCreatable'];
				$photo_object->isUpdateable = ($permissions & (1 << 1)) && $photo['isUpdateable'];
				$photo_object->isDeletable = ($permissions & (1 << 3)) && $photo['isDeletable'];
				$photo_object->path = $folder->getRelativePath($pre_path.$photo_object->path);
				$photo_object->filename = $photo_object->path;
				return $photo_object;
			}, $result);
		} else {
			throw new NotPermittedException();
		}

		return new DataResponse($photos);
	}

	/**
	 * @PublicPage
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OC\User\NoUserException
	 */
	public function getNonLocalizedPhotos(?string $timezone = null, int $limit = 250, int $offset = 0): DataResponse {
		$share = $this->getShare();
		$permissions = $share->getPermissions();
		$folder = $this->getShareNode();
		$isReadable = (bool)($permissions & (1 << 0));
		if ($isReadable) {
			$owner = $share->getShareOwner();
			$pre_path = $this->root->getUserFolder($owner)->getPath();
			$result = $this->geophotoService->getNonLocalized($owner, $folder, true, false, false, $timezone, $limit, $offset);
			$photos = array_map(function ($photo) use ($folder, $permissions, $pre_path) {
				$photo_object = (object)$photo;
				$photo_object->isCreatable = ($permissions & (1 << 2)) && $photo['isCreatable'];
				$photo_object->isUpdateable = ($permissions & (1 << 1)) && $photo['isUpdateable'];
				$photo_object->isDeletable = ($permissions & (1 << 3)) && $photo['isDeletable'];
				$photo_object->path = $folder->getRelativePath($pre_path.$photo['path']);
				$photo_object->filename = $photo_object->path;
				return $photo_object;
			}, $result);
		} else {
			throw new NotPermittedException();
		}

		return new DataResponse($photos);
	}

	/**
	 * @PublicPage
	 * @return DataResponse
	 */
	public function clearCache(): DataResponse {
		$result = $this->geophotoService->clearCache();
		if ($result) {
			return new DataResponse('Cache cleared');
		} else {
			return new DataResponse('Failed to clear Cache', 400);
		}
	}

}
