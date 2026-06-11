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
use OCA\Maps\Service\TracksService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class UserInstallScanJob extends QueuedJob {
	/**
	 * UserInstallScanJob constructor.
	 *
	 * A QueuedJob to scan user storage for photos and tracks
	 */
	public function __construct(
		ITimeFactory $timeFactory,
		private readonly IConfig $config,
		private readonly PhotofilesService $photofilesService,
		private readonly TracksService $tracksService,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);
	}

	public function run($argument): void {
		$userId = $argument['userId'];
		$this->logger->debug('Launch user install scan job for ' . $userId . ' cronjob executed');
		// scan photos and tracks for given user
		$this->rescanUserPhotos($userId);
		$this->rescanUserTracks($userId);
		$this->config->setUserValue($userId, 'maps', 'installScanDone', 'yes');
	}

	private function rescanUserPhotos(string $userId): void {
		//$this->output->info('======== User '.$userId.' ========'."\n");
		$c = 1;
		foreach ($this->photofilesService->rescan($userId) as $path) {
			//$this->output->info('['.$c.'] Photo "'.$path.'" added'."\n");
			$c++;
		}
	}

	private function rescanUserTracks(string $userId): void {
		//$this->output->info('======== User '.$userId.' ========'."\n");
		$c = 1;
		foreach ($this->tracksService->rescan($userId) as $path) {
			//$this->output->info('['.$c.'] Track "'.$path.'" added'."\n");
			$c++;
		}
	}

}
