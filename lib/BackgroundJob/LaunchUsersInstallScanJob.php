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

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class LaunchUsersInstallScanJob extends QueuedJob {

	/**
	 * LaunchUsersInstallScanJob constructor.
	 *
	 * A QueuedJob to launch a scan job for each user
	 *
	 * @param IJobList $jobList
	 */
	public function __construct(
		ITimeFactory $timeFactory,
		private IJobList $jobList,
		private IUserManager $userManager,
	) {
		parent::__construct($timeFactory);
	}

	public function run($argument) {
		\OCP\Server::get(LoggerInterface::class)->debug('Launch users install scan jobs cronjob executed');
		$this->userManager->callForSeenUsers(function (IUser $user) {
			$this->jobList->add(UserInstallScanJob::class, ['userId' => $user->getUID()]);
		});
	}
}
