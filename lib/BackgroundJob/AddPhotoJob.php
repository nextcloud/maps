<?php

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Maps\BackgroundJob;

use OCA\Maps\Service\PhotofilesService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\Files\IRootFolder;
use OCP\ICache;

use OCP\ICacheFactory;

class AddPhotoJob extends QueuedJob {
	private PhotofilesService $photofilesService;
	private IRootFolder $root;
	private ICacheFactory $cacheFactory;
	private ICache $backgroundJobCache;

	/**
	 * UserInstallScanJob constructor.
	 *
	 * A QueuedJob to scan user storage for photos and tracks
	 */
	public function __construct(
		ITimeFactory $timeFactory,
		IRootFolder $root,
		PhotofilesService $photofilesService,
		ICacheFactory $cacheFactory,
	) {
		parent::__construct($timeFactory);
		$this->photofilesService = $photofilesService;
		$this->root = $root;
		$this->cacheFactory = $cacheFactory;
		$this->backgroundJobCache = $this->cacheFactory->createDistributed('maps:background-jobs');
	}

	public function run($argument): void {
		$userFolder = $this->root->getUserFolder($argument['userId']);
		$files = $userFolder->getById($argument['photoId']);
		if (empty($files)) {
			return;
		}
		$file = array_shift($files);
		$this->photofilesService->addPhotoNow($file, $argument['userId']);

		$counter = $this->backgroundJobCache->get('recentlyAdded:' . $argument['userId']) ?? 0;
		$this->backgroundJobCache->set('recentlyAdded:' . $argument['userId'], (int)$counter + 1, 60 * 60 * 3);
	}
}
