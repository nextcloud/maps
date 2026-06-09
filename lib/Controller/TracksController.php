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
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IRequest;

use function OCA\Maps\Helper\remove_utf8_bom;

class TracksController extends Controller {

	private $userfolder;
	protected $appName;

	public function __construct(
		string $appName,
		IRequest $request,
		private IL10N $l,
		private TracksService $tracksService,
		IRootFolder $rootFolder,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
		if ($userId !== '' && $userId !== null) {
			$this->userfolder = $rootFolder->getUserFolder($userId);
		}
	}

	/**
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	#[NoAdminRequired]
	public function getTracks($myMapId = null): DataResponse {
		if (is_null($myMapId) || $myMapId === '') {
			$tracks = $this->tracksService->getTracksFromDB($this->userId, $this->userfolder, true, false, true);
		} else {
			$folders = $this->userfolder->getById($myMapId);
			$folder = array_shift($folders);
			if ($folder instanceof Folder) {
				$tracks = $this->tracksService->getTracksFromDB($this->userId, $folder, true, false, false);
			}
		}
		return new DataResponse($tracks);
	}

	#[NoAdminRequired]
	public function getTrackContentByFileId(int $id): DataResponse {
		$track = $this->tracksService->getTrackByFileIDFromDB($id, $this->userId);
		$res = is_null($track) ? null : $this->userfolder->getById($track['file_id']);
		if (is_array($res) and count($res) > 0) {
			$trackFile = $res[0];
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
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	#[NoAdminRequired]
	public function getTrackFileContent(int $id): DataResponse {
		$track = $this->tracksService->getTrackFromDB($id);
		$res = is_null($track) ? null : $this->userfolder->getById($track['file_id']);
		if (is_array($res) and count($res) > 0) {
			$trackFile = $res[0];
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

	#[NoAdminRequired]
	public function editTrack(int $id, ?string $color, ?string $metadata, ?string $etag): DataResponse {
		$track = $this->tracksService->getTrackFromDB($id, $this->userId);
		if ($track !== null) {
			$this->tracksService->editTrackInDB($id, $color, $metadata, $etag);
			return new DataResponse('EDITED');
		} else {
			return new DataResponse($this->l->t('No such track'), 400);
		}
	}

	#[NoAdminRequired]
	public function deleteTrack(int $id): DataResponse {
		$track = $this->tracksService->getTrackFromDB($id, $this->userId);
		if ($track !== null) {
			$this->tracksService->deleteTrackFromDB($id);
			return new DataResponse('DELETED');
		} else {
			return new DataResponse($this->l->t('No such track'), 400);
		}
	}

}
