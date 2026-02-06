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
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Share;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;

use OCP\Share\IShare;
use function OCA\Maps\Helper\remove_utf8_bom;

class PublicTracksController extends PublicPageController {
	public function __construct(
		string $appName,
		IRequest $request,
		IEventDispatcher $eventDispatcher,
		IConfig $config,
		IInitialStateService $initialStateService,
		IURLGenerator $urlGenerator,
		ShareManager $shareManager,
		IUserManager $userManager,
		ISession $session,
		protected IGroupManager $groupManager,
		protected IL10N $l,
		protected TracksService $tracksService,
		protected IRootFolder $root,
	) {
		parent::__construct($appName, $request, $session, $urlGenerator, $eventDispatcher, $config, $initialStateService, $shareManager, $userManager);
	}

	/**
	 * Validate the permissions of the share
	 */
	private function validateShare(\OCP\Share\IShare $share): bool {
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
	 * @throws NotFoundException
	 */
	private function getShare(): IShare {
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
	 * @throws NotFoundException
	 */
	private function getShareNode(): Node {
		\OC_User::setIncognitoMode(true);

		$share = $this->getShare();

		return $share->getNode();
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	#[PublicPage]
	public function getTracks(): DataResponse {
		$share = $this->getShare();
		$hideDownload = (bool)$share->getHideDownload();
		$permissions = $share->getPermissions();
		$folder = $this->getShareNode();
		$isReadable = (bool)($permissions & (1 << 0));
		if (!$isReadable || !($folder instanceof Folder)) {
			throw new NotPermittedException();
		}

		$owner = $share->getShareOwner();
		$pre_path = $this->root->getUserFolder($owner)->getPath();
		$tracks = $this->tracksService->getTracksFromDB($owner, $folder, true, false, false);
		$newTracks = array_map(function ($track) use ($folder, $permissions, $pre_path, $hideDownload): array {
			$track['isCreatable'] = ($permissions & (1 << 2)) && $track['isCreatable'];
			$track['isUpdateable'] = ($permissions & (1 << 1)) && $track['isUpdateable'];
			$track['isDeletable'] = ($permissions & (1 << 3)) && $track['isDeletable'];
			$track['path'] = $folder->getRelativePath($pre_path . $track['path']);
			$track['filename'] = $track['path'];
			$track['hideDownload'] = $hideDownload;
			return $track;
		}, $tracks);
		return new DataResponse($newTracks);
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\Files\InvalidPathException
	 */
	#[PublicPage]
	public function getTrackContentByFileId(int $id): DataResponse {
		$share = $this->getShare();
		$permissions = $share->getPermissions();
		$folder = $this->getShareNode();
		$isReadable = (bool)($permissions & (1 << 0));
		if (!$isReadable || !($folder instanceof Folder)) {
			throw new NotPermittedException();
		}
		$owner = $share->getShareOwner();
		$track = $this->tracksService->getTrackByFileIDFromDB($id, $owner);
		$trackFile = is_null($track) ? null : $folder->getFirstNodeById($track['file_id']);
		if (!($trackFile instanceof File)) {
			return new DataResponse($this->l->t('File not found'), 400);
		}

		$trackContent = remove_utf8_bom($trackFile->getContent());
		// compute metadata if necessary
		// first time we get it OR the file changed
		if (!$track['metadata'] || $track['etag'] !== $trackFile->getEtag()) {
			$metadata = $this->tracksService->generateTrackMetadata($trackFile);
			$this->tracksService->editTrackInDB($track['id'], null, $metadata, $trackFile->getEtag());
		} else {
			$metadata = $track['metadata'];
		}
		return new DataResponse([
			'metadata' => $metadata,
			'content' => $trackContent
		]);
	}

	/**
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws \OCP\Files\InvalidPathException
	 */
	#[PublicPage]
	public function getTrackFileContent(int $id): DataResponse {
		$track = $this->tracksService->getTrackFromDB($id);
		$shareNode = $this->getShareNode();
		if (!($shareNode instanceof Folder)) {
			return new DataResponse($this->l->t('File not found'), 400);
		}

		$trackFile = is_null($track) ? null : $shareNode->getFirstNodeById($track['file_id']);
		if (!($trackFile instanceof File)) {
			return new DataResponse($this->l->t('File not found'), 400);
		}

		$trackContent = remove_utf8_bom($trackFile->getContent());
		// compute metadata if necessary
		// first time we get it OR the file changed
		if (!$track['metadata'] || $track['etag'] !== $trackFile->getEtag()) {
			$metadata = $this->tracksService->generateTrackMetadata($trackFile);
			$this->tracksService->editTrackInDB($track['id'], null, $metadata, $trackFile->getEtag());
		} else {
			$metadata = $track['metadata'];
		}
		return new DataResponse([
			'metadata' => $metadata,
			'content' => $trackContent
		]);
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	#[PublicPage]
	public function editTrack(int $id, string $color, string $metadata, string $etag): DataResponse {
		$share = $this->getShare();
		$permissions = $share->getPermissions();
		$isUpdateable = (bool)($permissions & (1 << 1));
		if (!$isUpdateable) {
			throw new NotPermittedException();
		}

		$owner = $share->getShareOwner();
		$track = $this->tracksService->getTrackFromDB($id, $owner);
		if ($track !== null) {
			$this->tracksService->editTrackInDB($id, $color, $metadata, $etag);
			return new DataResponse('EDITED');
		} else {
			return new DataResponse($this->l->t('No such track'), 400);
		}
	}

	#[NoAdminRequired]
	public function deleteTrack(int $id): DataResponse {
		$share = $this->getShare();
		$permissions = $share->getPermissions();
		$isUpdateable = (bool)($permissions & (1 << 1));
		//It's allowed to delete a track from the share, if the share is updateable
		if ($isUpdateable) {
			$owner = $share->getShareOwner();
			$track = $this->tracksService->getTrackFromDB($id, $owner);
			if ($track !== null) {
				$this->tracksService->deleteTrackFromDB($id);
				return new DataResponse('DELETED');
			} else {
				return new DataResponse($this->l->t('No such track'), 400);
			}
		} else {
			throw new NotPermittedException();
		}
	}
}
