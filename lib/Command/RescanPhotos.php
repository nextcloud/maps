<?php

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Piotr Bator <prbator@gmail.com>
 * @copyright Piotr Bator 2017
 */

namespace OCA\Maps\Command;

use OCA\Maps\Service\PhotofilesService;
use OCP\Encryption\IManager;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RescanPhotos extends Command {
	public function __construct(
		private readonly IUserManager $userManager,
		private readonly IManager $encryptionManager,
		private readonly PhotofilesService $photofilesService,
		private readonly IConfig $config,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('maps:scan-photos')
			->setDescription('Rescan photos GPS exif data')
			->addArgument(
				'user_id',
				InputArgument::OPTIONAL,
				'Rescan photos GPS exif data for the given user'
			)
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Scan photos GPS exif data for the given path under user\'s files without wiping the database.'
			)
			->addOption(
				'now',
				null,
				InputOption::VALUE_NONE,
				'Dot the rescan now and not as background jobs. Doing it now might run out of memory.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		if ($this->encryptionManager->isEnabled()) {
			$output->writeln('Encryption is enabled. Aborted.');
			return 1;
		}
		$userId = $input->getArgument('user_id');
		$pathToScan = $input->getArgument('path');
		$inBackground = !($input->getOption('now') ?? true);
		if ($inBackground) {
			echo "Extracting coordinates from photo is performed in a BackgroundJob \n";
		}
		if ($userId === null) {
			$this->userManager->callForSeenUsers(function (IUser $user, ?string $pathToScan = null) use ($inBackground): void {
				$this->rescanUserPhotos($user->getUID(), $inBackground, $pathToScan);
			});
		} else {
			$user = $this->userManager->get($userId);
			if ($user !== null) {
				$this->rescanUserPhotos($userId, $inBackground, $pathToScan);
			}
		}
		return 0;
	}

	/**
	 * @param string $pathToScan
	 * @throws \OCP\PreConditionNotMetException
	 */
	private function rescanUserPhotos(string $userId, bool $inBackground = true, ?string $pathToScan = null): void {
		echo '======== User ' . $userId . ' ========' . "\n";
		$c = 1;
		foreach ($this->photofilesService->rescan($userId, $inBackground, $pathToScan) as $path) {
			echo '[' . $c . '] Photo "' . $path . '" added' . "\n";
			$c++;
		}
		if ($pathToScan === null) {
			$this->config->setUserValue($userId, 'maps', 'installScanDone', 'yes');
		}
	}
}
