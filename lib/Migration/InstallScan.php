<?php
/**
 * @copyright Copyright (c) 2019 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Maps\Migration;

use OCA\Maps\BackgroundJob\LaunchUsersInstallScanJob;
use OCP\BackgroundJob\IJobList;
use OCP\Encryption\IManager;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Class InstallScan
 *
 * @package OCA\Maps\Migration
 */
class InstallScan implements IRepairStep {

	public function __construct(
		private IConfig $config,
		private IUserManager $userManager,
		private IJobList $jobList,
		private IManager $encryptionManager
	) {
		$this->config = $config;
		$this->jobList = $jobList;
		$this->encryptionManager = $encryptionManager;
		$this->userManager = $userManager;
	}

	/**
	 * Returns the step's name
	 *
	 * @return string
	 * @since 9.1.0
	 */
	public function getName() {
		return 'Scan photos and tracks in users storages';
	}

	/**
	 * @param IOutput $output
	 */
	public function run(IOutput $output) {
		if (!$this->shouldRun()) {
			return;
		}

		if ($this->encryptionManager->isEnabled()) {
			$output->warning('Encryption is enabled. Installation photos/tracks scan aborted.');
			return 1;
		}

		// set the install scan flag for existing users
		// future users won't have any value and won't be bothered by "media scan" warning
		$this->userManager->callForSeenUsers(function (IUser $user) {
			$this->config->setUserValue($user->getUID(), 'maps', 'installScanDone', 'no');
		});
		// create the job which will create a job by user
		$this->jobList->add(LaunchUsersInstallScanJob::class, []);
	}

	protected function shouldRun() {
		$appVersion = $this->config->getAppValue('maps', 'installed_version', '0.0.0');
		return version_compare($appVersion, '0.0.10', '<');
	}

}
