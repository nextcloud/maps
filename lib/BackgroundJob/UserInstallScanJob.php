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
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;

use OCP\IConfig;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class UserInstallScanJob extends QueuedJob {

	private IJobList $jobList;
	private IConfig $config;
	private IUserManager $userManager;
	private PhotofilesService $photofilesService;
	private TracksService $tracksService;

	/**
	 * UserInstallScanJob constructor.
	 *
	 * A QueuedJob to scan user storage for photos and tracks
	 *
	 * @param IJobList $jobList
	 */
	public function __construct(ITimeFactory $timeFactory, IJobList $jobList,
		IUserManager $userManager,
		IConfig $config,
		PhotofilesService $photofilesService,
		TracksService $tracksService) {
		parent::__construct($timeFactory);
		$this->config = $config;
		$this->jobList = $jobList;
		$this->userManager = $userManager;
		$this->photofilesService = $photofilesService;
		$this->tracksService = $tracksService;
	}

	public function run($argument) {
		$userId = $argument['userId'];
		\OCP\Server::get(LoggerInterface::class)->debug('Launch user install scan job for '.$userId.' cronjob executed');
		// scan photos and tracks for given user
		$this->rescanUserPhotos($userId);
		$this->rescanUserTracks($userId);
		$this->config->setUserValue($userId, 'maps', 'installScanDone', 'yes');
	}

	private function rescanUserPhotos($userId) {
		//$this->output->info('======== User '.$userId.' ========'."\n");
		$c = 1;
		foreach ($this->photofilesService->rescan($userId) as $path) {
			//$this->output->info('['.$c.'] Photo "'.$path.'" added'."\n");
			$c++;
		}
	}

	private function rescanUserTracks($userId) {
		//$this->output->info('======== User '.$userId.' ========'."\n");
		$c = 1;
		foreach ($this->tracksService->rescan($userId) as $path) {
			//$this->output->info('['.$c.'] Track "'.$path.'" added'."\n");
			$c++;
		}
	}

}
