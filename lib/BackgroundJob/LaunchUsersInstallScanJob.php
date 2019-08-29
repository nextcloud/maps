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
use OCP\IUser;

use \OCA\Maps\BackgroundJob\UserInstallScanJob;

class LaunchUsersInstallScanJob extends QueuedJob {

    private $jobList;

    /**
     * LaunchUsersInstallScanJob constructor.
     *
     * A QueuedJob to launch a scan job for each user
     *
     * @param IJobList $jobList
     */
    public function __construct(ITimeFactory $timeFactory, IJobList $jobList, IUserManager $userManager) {
        parent::__construct($timeFactory);
        $this->jobList = $jobList;
        $this->userManager = $userManager;
    }

    public function run($arguments) {
        \OC::$server->getLogger()->debug('Launch users install scan jobs cronjob executed');
        $this->userManager->callForSeenUsers(function (IUser $user) {
            $this->jobList->add(UserInstallScanJob::class, ['userId' => $user->getUID()]);
        });
    }
}
