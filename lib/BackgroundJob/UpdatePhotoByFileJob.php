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
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\ICache;

use OCP\ICacheFactory;

class UpdatePhotoByFileJob extends QueuedJob {
	private ICache $backgroundJobCache;

	/**
	 * UserInstallScanJob constructor.
	 *
	 * A QueuedJob to scan user storage for photos and tracks
	 */
	public function __construct(
		ITimeFactory $timeFactory,
		private IRootFolder $root,
		private PhotofilesService $photofilesService,
		private ICacheFactory $cacheFactory,
	) {
		parent::__construct($timeFactory);
		$this->backgroundJobCache = $this->cacheFactory->createDistributed('maps:background-jobs');
	}

	public function run($argument): void {
		$userFolder = $this->root->getUserFolder($argument['userId']);
		$file = $userFolder->getFirstNodeById($argument['fileId']);
		if (!$file instanceof File) {
			return;
		}
		$this->photofilesService->updateByFileNow($file);

		$counter = $this->backgroundJobCache->get('recentlyUpdated:' . $argument['userId']) ?? 0;
		$this->backgroundJobCache->set('recentlyUpdated:' . $argument['userId'], (int)$counter + 1, 60 * 60 * 3);
	}
}
