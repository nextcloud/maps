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

use \OCP\BackgroundJob\QueuedJob;
use \OCP\BackgroundJob\IJobList;
use \OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUserManager;
use OCP\IConfig;

use OCA\Maps\Service\PhotofilesService;
use OCA\Maps\Service\TracksService;

class UserInstallScanJob extends QueuedJob {

    private $jobList;

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

    public function run($arguments) {
        $userId = $arguments['userId'];
        \OC::$server->getLogger()->debug('Launch user install scan job for '.$userId.' cronjob executed');
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
