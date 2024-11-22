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

namespace OCA\Maps\Hooks;

use OC\Files\Filesystem;
use OCA\Maps\Service\PhotofilesService;
use OCA\Maps\Service\TracksService;
use OCP\Files\FileInfo;
use OCP\Files\IHomeStorage;
use OCP\Files\IRootFolder;
use OCP\Lock\ILockingProvider;
use OCP\Share;
use OCP\Util;

/**
 * Handles files events
 */
class FileHooks {

	private $photofilesService;
	private $tracksService;

	private $root;

	private ILockingProvider $lockingProvider;

	public function __construct(IRootFolder $root, PhotofilesService $photofilesService, TracksService $tracksService,
		$appName, ILockingProvider $lockingProvider) {
		$this->photofilesService = $photofilesService;
		$this->tracksService = $tracksService;
		$this->root = $root;
		$this->lockingProvider = $lockingProvider;
	}

	public function register() {
		$fileWriteCallback = function (\OCP\Files\Node $node) {
			if ($this->isUserNode($node) && $node->getSize() > 0) {
				$path = $node->getPath();
				if (!$this->lockingProvider->isLocked($path, ILockingProvider::LOCK_SHARED)
					and !$this->lockingProvider->isLocked($path, ILockingProvider::LOCK_EXCLUSIVE)
				) {
					$isPhoto = $this->photofilesService->addByFile($node);
					if (!$isPhoto) {
						$this->tracksService->safeAddByFile($node);
					}
				}
			}
		};
		$this->root->listen('\OC\Files', 'postWrite', $fileWriteCallback);

		$fileDeletionCallback = function (\OCP\Files\Node $node) {
			if ($this->isUserNode($node)) {
				if ($node->getType() === FileInfo::TYPE_FOLDER) {
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
		$this->root->listen('\OC\Files', 'postTouch', function (\OCP\Files\Node $node) {
			if ($this->isUserNode($node) and $node->getType() === FileInfo::TYPE_FILE) {
				$this->photofilesService->updateByFile($node);
				// nothing to update on tracks, metadata will be regenerated when getting content if etag has changed
			}
		});

		// move file: delete then add it again in DB to be sure it's there for all users with access to target file
		$this->root->listen('\OC\Files', 'postRename', function (\OCP\Files\Node $source, \OCP\Files\Node $target) {
			if ($this->isUserNode($target)) {
				if ($target->getType() === FileInfo::TYPE_FILE) {
					// if moved (parents are different) => update DB with access list
					if ($source->getParent()->getId() !== $target->getParent()->getId()) {
						// we renamed therefore target and source are identical
						$this->photofilesService->deleteByFile($target);
						$this->photofilesService->addByFile($target);
						// tracks: nothing to do here because we use fileID
					}
				} elseif ($target->getType() === FileInfo::TYPE_FOLDER) {
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
		Util::connectHook(\OCP\Share::class, 'post_shared', $this, 'postShare');
		Util::connectHook(\OCP\Share::class, 'post_unshare', $this, 'postUnShare');
		Util::connectHook(\OCP\Share::class, 'pre_unshare', $this, 'preUnShare');
	}

	public function postShare($params) {
		if ($params['itemType'] === 'file') {
			//$targetFilePath = $params['itemTarget'];
			//$sourceUserId = $params['uidOwner'];
			$fileId = $params['fileSource']; // or itemSource
			$files = $this->root->getById($fileId);
			if (empty($files)) {
				return;
			}
			$file = array_shift($files);
			$this->photofilesService->addByFile($file, );
			$this->tracksService->safeAddByFile($file);
		} elseif ($params['itemType'] === 'folder') {
			$dirId = $params['fileSource']; // or itemSource
			$folders = $this->root->getById($dirId);
			if (empty($folders)) {
				return;
			}
			$folder = array_shift($folders);
			$this->photofilesService->addByFolder($folder);
			$this->tracksService->safeAddByFolder($folder);
		}
	}

	public function postUnShare($params) {
		if ($params['shareType'] === Share::SHARE_TYPE_USER) {
			if ($params['itemType'] === 'file') {
				$targetUserId = $params['shareWith'];
				$fileId = $params['fileSource']; // or itemSource
				$this->photofilesService->deleteByFileIdUserId($fileId, $targetUserId);
				$this->tracksService->safeDeleteByFileIdUserId($fileId, $targetUserId);
			}
		}
	}

	public function preUnShare($params) {
		if ($params['shareType'] === Share::SHARE_TYPE_USER) {
			if ($params['itemType'] === 'folder') {
				$targetUserId = $params['shareWith'];
				$dirId = $params['fileSource']; // or itemSource
				$this->photofilesService->deleteByFolderIdUserId($dirId, $targetUserId);
				$this->tracksService->safeDeleteByFolderIdUserId($dirId, $targetUserId);
			}
		}
	}

	public function restore($params) {
		$node = $this->getNodeForPath($params['filePath']);
		if ($this->isUserNode($node)) {
			if ($node->getType() === FileInfo::TYPE_FOLDER) {
				$this->photofilesService->addByFolder($node);
				$this->tracksService->safeAddByFolder($node);
			} else {
				$this->photofilesService->addByFile($node);
				$this->tracksService->safeAddByFile($node);
			}
		}
	}

	private function getNodeForPath($path) {
		$user = \OC::$server->getUserSession()->getUser();
		$fullPath = Filesystem::normalizePath('/' . $user->getUID() . '/files/' . $path);
		return $this->root->get($fullPath);
	}

	private function isUserNode(\OCP\Files\Node $node): bool {
		return $node->getStorage()->instanceOfStorage(IHomeStorage::class);
	}

}
