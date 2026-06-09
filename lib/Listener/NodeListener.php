<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH
 * SPDX-FileContributor: Carl Schwan
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Maps\Listener;

use OCA\Files_Trashbin\Events\NodeRestoredEvent;
use OCA\Maps\Service\PhotofilesService;
use OCA\Maps\Service\TracksService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeTouchedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Lock\ILockingProvider;

/**
 * @template-implements IEventListener<NodeWrittenEvent|BeforeNodeDeletedEvent|NodeTouchedEvent|NodeRenamedEvent|NodeRestoredEvent>
 */
class NodeListener implements IEventListener {
	public function __construct(
		private readonly ILockingProvider $lockingProvider,
		private readonly PhotofilesService $photofilesService,
		private readonly TracksService $tracksService,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof NodeWrittenEvent) {
			$this->handleWritten($event);
		}

		if ($event instanceof BeforeNodeDeletedEvent) {
			$this->handleDeleted($event);
		}

		// this one is triggered when restoring a version of a file
		// and NOT when it's created so we can use it for updating coordinates in DB
		if ($event instanceof NodeTouchedEvent) {
			$this->handleTouch($event);
		}

		// move file: delete then add it again in DB to be sure it's there for all users with access to target file
		if ($event instanceof NodeRenamedEvent) {
			$this->handleRenamed($event);
		}

		if ($event instanceof NodeRestoredEvent) {
			$this->handleRestored($event);
		}
	}

	private function isUserNode(\OCP\Files\Node $node): bool {
		//return $node->getStorage()->instanceOfStorage("\OCP\Files\IHomeStorage")
		$owner = $node->getStorage()->getOwner('');
		if (!$owner) {
			return false;
		}
		return str_starts_with($node->getPath(), '/' . $owner . '/');
	}

	private function handleWritten(NodeWrittenEvent $event): void {
		$node = $event->getNode();

		if ($node instanceof File && $this->isUserNode($node) && $node->getSize()) {
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
	}

	private function handleDeleted(BeforeNodeDeletedEvent $event): void {
		$node = $event->getNode();

		if ($this->isUserNode($node)) {
			if ($node instanceof Folder) {
				$this->photofilesService->deleteByFolder($node);
				$this->tracksService->deleteByFolder($node);
			}

			if ($node instanceof File) {
				$this->photofilesService->deleteByFile($node);
				$this->tracksService->deleteByFile($node);
			}
		}
	}

	private function handleTouch(NodeTouchedEvent $event): void {
		$node = $event->getNode();

		if ($this->isUserNode($node) and $node instanceof File) {
			$this->photofilesService->updateByFile($node);
			// nothing to update on tracks, metadata will be regenerated when getting content if etag has changed
		}
	}

	private function handleRenamed(NodeRenamedEvent $event): void {
		$target = $event->getTarget();
		$source = $event->getSource();

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
	}

	private function handleRestored(NodeRestoredEvent $event): void {
		$node = $event->getTarget();
		if ($this->isUserNode($node)) {
			if ($node instanceof Folder) {
				$this->photofilesService->addByFolder($node);
				$this->tracksService->safeAddByFolder($node);
			}

			if ($node instanceof File) {
				$this->photofilesService->addByFile($node);
				$this->tracksService->safeAddByFile($node);
			}
		}
	}
}
