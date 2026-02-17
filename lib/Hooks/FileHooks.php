<?php

declare(strict_types=1);

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Piotr Bator <prbator@gmail.com>
 * @copyright Piotr Bator 2017
 */
namespace OCA\Maps\Hooks;

use OC\Files\Filesystem;
use OCA\Maps\Service\PhotofilesService;
use OCA\Maps\Service\TracksService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IUserSession;
use OCP\Lock\ILockingProvider;
use OCP\Server;
use OCP\Share;
use OCP\Share\IShare;
use OCP\Util;
use function OCP\Log\logger;

/**
 * Handles files events
 */
class FileHooks {

	public function __construct(
		private readonly IRootFolder $root,
		private readonly PhotofilesService $photofilesService,
		private readonly TracksService $tracksService,
		private readonly ILockingProvider $lockingProvider,
	) {
	}

	public function register(): void {
		$fileWriteCallback = function (Node $node): void {
			//logger('maps')->debug("Hook postWrite");
			if ($node instanceof File && $this->isUserNode($node) && $node->getSize()) {
				$path = $node->getPath();
				if (!$this->lockingProvider->isLocked($path, ILockingProvider::LOCK_SHARED) && !$this->lockingProvider->isLocked($path, ILockingProvider::LOCK_EXCLUSIVE)
				) {
					$isPhoto = $this->photofilesService->addByFile($node);
					if (!$isPhoto) {
						$this->tracksService->safeAddByFile($node);
					}
				}
			}
		};
		$this->root->listen('\OC\Files', 'postWrite', $fileWriteCallback);

		$fileDeletionCallback = function (Node $node): void {
			//logger('maps')->debug("Hook preDelete");
			if ($this->isUserNode($node)) {
				if ($node instanceof Folder) {
					$this->photofilesService->deleteByFolder($node);
					$this->tracksService->deleteByFolder($node);
				} else {
					$this->photofilesService->deleteByFile($node);
					$this->tracksService->deleteByFile($node);
				}
			}
		};
		$this->root->listen('\OC\Files', 'preDelete', $fileDeletionCallback);

		// this one is triggered when restoring a version of a file
		// and NOT when it's created so we can use it for updating coordinates in DB
		$this->root->listen('\OC\Files', 'postTouch', function (Node $node): void {
			if ($this->isUserNode($node) && $node instanceof File) {
				$this->photofilesService->updateByFile($node);
				// nothing to update on tracks, metadata will be regenerated when getting content if etag has changed
			}
		});

		// move file: delete then add it again in DB to be sure it's there for all users with access to target file
		$this->root->listen('\OC\Files', 'postRename', function (Node $source, Node $target): void {
			if ($this->isUserNode($target)) {
				if ($target instanceof File) {
					// if moved (parents are different) => update DB with access list
					if ($source->getParent()->getId() !== $target->getParent()->getId()) {
						// we renamed therefore target and source are identical
						$this->photofilesService->deleteByFile($target);
						$this->photofilesService->addByFile($target);
						// tracks: nothing to do here because we use fileID
					}
				} elseif ($target instanceof Folder) {
					if ($source->getParent()->getId() !== $target->getParent()->getId()) {
						// we renamed therefore target and source have the same childs.
						$this->photofilesService->deleteByFolder($target);
						$this->photofilesService->addByFolder($target);
						// tracks: nothing to do here because we use fileID
					}
				}
			}
		});

		Util::connectHook('\OCA\Files_Trashbin\Trashbin', 'post_restore', $this, 'restore');

		// sharing hooks
		Util::connectHook(Share::class, 'post_shared', $this, 'postShare');
		Util::connectHook(Share::class, 'post_unshare', $this, 'postUnShare');
		Util::connectHook(Share::class, 'pre_unshare', $this, 'preUnShare');
	}

	/**
	 * @param array<string, mixed> $params
	 */
	public function postShare(array $params): void {
		//logger('maps')->debug("Hook postShare");
		if ($params['itemType'] === 'file') {
			//$targetFilePath = $params['itemTarget'];
			//$sourceUserId = $params['uidOwner'];
			$fileId = $params['fileSource']; // or itemSource
			$file = $this->root->getFirstNodeById($fileId);
			if (!$file instanceof File) {
				return;
			}

			$this->photofilesService->addByFile($file);
			$this->tracksService->safeAddByFile($file);
		} elseif ($params['itemType'] === 'folder') {
			$dirId = $params['fileSource']; // or itemSource
			$folder = $this->root->getFirstNodeById($dirId);
			if (!$folder instanceof Folder) {
				return;
			}

			$this->photofilesService->addByFolder($folder);
			$this->tracksService->safeAddByFolder($folder);
		}
	}

	/**
	 * @param array<string, mixed> $params
	 */
	public function postUnShare(array $params): void {
		//logger('maps')->debug("Hook postUnShare");
		if ($params['shareType'] === IShare::TYPE_USER && $params['itemType'] === 'file') {
			$targetUserId = $params['shareWith'];
			$fileId = $params['fileSource'];
			// or itemSource
			$this->photofilesService->deleteByFileIdUserId($fileId, $targetUserId);
			$this->tracksService->safeDeleteByFileIdUserId($fileId, $targetUserId);
		}
	}

	/**
	 * @param array<string, mixed> $params
	 */
	public function preUnShare(array $params): void {
		//logger('maps')->debug("Hook preUnShare");
		if ($params['shareType'] === IShare::TYPE_USER && $params['itemType'] === 'folder') {
			$targetUserId = $params['shareWith'];
			$dirId = $params['fileSource'];
			// or itemSource
			$this->photofilesService->deleteByFolderIdUserId($dirId, $targetUserId);
			$this->tracksService->safeDeleteByFolderIdUserId($dirId, $targetUserId);
		}
	}

	/**
	 * @param array<string, mixed> $params
	 */
	public function restore(array $params): void {
		$node = $this->getNodeForPath($params['filePath']);
		if ($this->isUserNode($node)) {
			if ($node instanceof Folder) {
				$this->photofilesService->addByFolder($node);
				$this->tracksService->safeAddByFolder($node);
			} elseif ($node instanceof File) {
				$this->photofilesService->addByFile($node);
				$this->tracksService->safeAddByFile($node);
			}
		}
	}

	private function getNodeForPath(string $path): Node {
		$user = Server::get(IUserSession::class)->getUser();
		$fullPath = Filesystem::normalizePath('/' . $user->getUID() . '/files/' . $path);
		return $this->root->get($fullPath);
	}

	private function isUserNode(Node $node): bool {
		//return $node->getStorage()->instanceOfStorage("\OCP\Files\IHomeStorage")
		$owner = $node->getStorage()->getOwner('');
		if (! $owner) {
			return false;
		}

		return str_starts_with($node->getPath(), '/' . $owner . '/');
	}

}
