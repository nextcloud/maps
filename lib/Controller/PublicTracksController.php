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
use OCP\AppFramework\Http\DataResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IServerContainer;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Share;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;

use function OCA\Maps\Helper\remove_utf8_bom;

class PublicTracksController extends PublicPageController {

	protected IConfig $config;
	protected ShareManager $shareManager;
	protected IUserManager $userManager;
	protected IL10N $l;
	protected TracksService $tracksService;
	protected $appName;
	protected IRootFolder $root;

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
		IServerContainer $serverContainer,
		protected IGroupManager $groupManager,
		IL10N $l,
		TracksService $tracksService,
		IRootFolder $root) {
		parent::__construct($appName, $request, $session, $urlGenerator, $eventDispatcher, $config, $initialStateService, $shareManager, $userManager);
		$this->tracksService = $tracksService;
		$this->l = $l;
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
	 * @PublicPage
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function getTracks(): DataResponse {
		$share = $this->getShare();
		$hideDownload = (bool)$share->getHideDownload();
		$permissions = $share->getPermissions();
		$folder = $this->getShareNode();
		$isReadable = (bool)($permissions & (1 << 0));
		if ($isReadable) {
			$owner = $share->getShareOwner();
			$pre_path = $this->root->getUserFolder($owner)->getPath();
			$tracks = $this->tracksService->getTracksFromDB($owner, $folder, true, false, false);
			$new_tracks = array_map(function ($track) use ($folder, $permissions, $pre_path, $hideDownload) {
				$track['isCreatable'] = ($permissions & (1 << 2)) && $track['isCreatable'];
				$track['isUpdateable'] = ($permissions & (1 << 1)) && $track['isUpdateable'];
				$track['isDeletable'] = ($permissions & (1 << 3)) && $track['isDeletable'];
				$track['path'] = $folder->getRelativePath($pre_path.$track['path']);
				$track['filename'] = $track['path'];
				$track['hideDownload'] = $hideDownload;
				return $track;
			}, $tracks);
		} else {
			throw new NotPermittedException();
		}
		return new DataResponse($new_tracks);
	}

	/**
	 * @PublicPage
	 * @param $id
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function getTrackContentByFileId($id) {
		$share = $this->getShare();
		$permissions = $share->getPermissions();
		$folder = $this->getShareNode();
		$isReadable = (bool)($permissions & (1 << 0));
		if (!$isReadable) {
			throw new NotPermittedException();
		}
		$owner = $share->getShareOwner();
		$track = $this->tracksService->getTrackByFileIDFromDB($id, $owner);
		$res = is_null($track) ? null : $folder->getById($track['file_id']);
		if (is_array($res) and count($res) > 0) {
			$trackFile = array_shift($res);
			if ($trackFile->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
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
			} else {
				return new DataResponse($this->l->t('Bad file type'), 400);
			}
		} else {
			return new DataResponse($this->l->t('File not found'), 400);
		}
	}

	/**
	 * @PublicPage
	 * @param $id
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function getTrackFileContent($id): DataResponse {
		$track = $this->tracksService->getTrackFromDB($id);
		$res = is_null($track) ? null : $this->getShareNode()->getById($track['file_id']);
		if (is_array($res) and count($res) > 0) {
			$trackFile = array_shift($res);
			if ($trackFile->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
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
			} else {
				return new DataResponse($this->l->t('Bad file type'), 400);
			}
		} else {
			return new DataResponse($this->l->t('File not found'), 400);
		}
	}

	/**
	 * @PublicPage
	 * @param $id
	 * @param $color
	 * @param $metadata
	 * @param $etag
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function editTrack($id, $color, $metadata, $etag): DataResponse {
		$share = $this->getShare();
		$permissions = $share->getPermissions();
		$folder = $this->getShareNode();
		$isUpdateable = (bool)($permissions & (1 << 1));
		if ($isUpdateable) {
			$owner = $share->getShareOwner();
			$track = $this->tracksService->getTrackFromDB($id, $owner);
			if ($track !== null) {
				$this->tracksService->editTrackInDB($id, $color, $metadata, $etag);
				return new DataResponse('EDITED');
			} else {
				return new DataResponse($this->l->t('No such track'), 400);
			}
		} else {
			throw new NotPermittedException();
		}
	}

	/**
	 * @NoAdminRequired
	 * @param $id
	 * @return DataResponse
	 */
	public function deleteTrack($id): DataResponse {
		$share = $this->getShare();
		$permissions = $share->getPermissions();
		$folder = $this->getShareNode();
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
