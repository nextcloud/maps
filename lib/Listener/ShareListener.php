<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH
 * SPDX-FileContributor: Carl Schwan
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Maps\Listener;

use OCA\Maps\Service\PhotofilesService;
use OCA\Maps\Service\TracksService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Share\Events\BeforeShareDeletedEvent;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Share\Events\ShareDeletedEvent;
use OCP\Share\IShare;

/**
 * @template-implements IEventListener<ShareDeletedEvent|BeforeShareDeletedEvent|ShareCreatedEvent>
 */
class ShareListener implements IEventListener {
	public function __construct(
		private readonly PhotofilesService $photofilesService,
		private readonly TracksService $tracksService,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof ShareDeletedEvent) {
			$this->handleShareDeleted($event);
		}

		if ($event instanceof BeforeShareDeletedEvent) {
			$this->handleBeforeShareDeleted($event);
		}

		if ($event instanceof ShareCreatedEvent) {
			$this->handleShareCreated($event);
		}
	}

	private function handleShareDeleted(ShareDeletedEvent $event): void {
		$share = $event->getShare();

		if ($share->getShareType() === IShare::TYPE_USER && $share->getNodeType() === 'file') {
			$targetUserId = $share->getSharedWith();
			$fileId = $share->getNodeId();
			$this->photofilesService->deleteByFileIdUserId($fileId, $targetUserId);
			$this->tracksService->safeDeleteByFileIdUserId($fileId, $targetUserId);
		}
	}

	private function handleBeforeShareDeleted(BeforeShareDeletedEvent $event): void {
		$share = $event->getShare();

		if ($share->getShareType() === IShare::TYPE_USER && $share->getNodeType() === 'folder') {
			$targetUserId = $share->getSharedWith();
			$dirId = $share->getNodeId();
			$this->photofilesService->deleteByFolderIdUserId($dirId, $targetUserId);
			$this->tracksService->safeDeleteByFolderIdUserId($dirId, $targetUserId);
		}
	}

	private function handleShareCreated(ShareCreatedEvent $event) {
		$share = $event->getShare();
		if ($share->getNodeType() === 'file') {
			/** @var File $folder */
			$file = $share->getNode();
			$this->photofilesService->addByFile($file);
			$this->tracksService->safeAddByFile($file);
		} elseif ($share->getNodeType() === 'folder') {
			/** @var Folder $folder */
			$folder = $share->getNode();
			$this->photofilesService->addByFolder($folder);
			$this->tracksService->safeAddByFolder($folder);
		}
	}
}
