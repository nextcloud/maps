<?php

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Maps\Command;

use OCA\Maps\Service\TracksService;
use OCP\Encryption\IManager;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RescanTracks extends Command {

	protected OutputInterface $output;

	public function __construct(
		private readonly IUserManager $userManager,
		private readonly IManager $encryptionManager,
		protected TracksService $tracksService,
		private readonly IConfig $config,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('maps:scan-tracks')
			->setDescription('Rescan track files')
			->addArgument(
				'user_id',
				InputArgument::OPTIONAL,
				'Rescan track files for the given user'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		if ($this->encryptionManager->isEnabled()) {
			$output->writeln('Encryption is enabled. Aborted.');
			return 1;
		}
		$this->output = $output;
		$userId = $input->getArgument('user_id');
		if ($userId === null) {
			$this->userManager->callForSeenUsers(function (IUser $user): void {
				$this->rescanUserTracks($user->getUID());
			});
		} else {
			$user = $this->userManager->get($userId);
			if ($user !== null) {
				$this->rescanUserTracks($userId);
			}
		}
		return 0;
	}

	private function rescanUserTracks(string $userId): void {
		echo '======== User ' . $userId . ' ========' . "\n";
		$c = 1;
		foreach ($this->tracksService->rescan($userId) as $path) {
			echo '[' . $c . '] Track "' . $path . '" added' . "\n";
			$c++;
		}
		$this->config->setUserValue($userId, 'maps', 'installScanDone', 'yes');
	}
}
