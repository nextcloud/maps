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

	protected IUserManager $userManager;
	protected OutputInterface $output;
	protected IManager $encryptionManager;
	protected PhotofilesService $photofilesService;
	protected IConfig $config;

	public function __construct(IUserManager $userManager,
		IManager $encryptionManager,
		PhotofilesService $photofilesService,
		IConfig $config) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->encryptionManager = $encryptionManager;
		$this->photofilesService = $photofilesService;
		$this->config = $config;
	}

	/**
	 * @return void
	 */
	protected function configure() {
		$this->setName('maps:scan-photos')
			->setDescription('Rescan photos GPS exif data')
			->addArgument(
				'user_id',
				InputArgument::OPTIONAL,
				'Rescan photos GPS exif data for the given user'
			)
			->addOption(
				'now',
				null,
				InputOption::VALUE_NONE,
				'Dot the rescan now and not as background jobs. Doing it now might run out of memory.'
			);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		if ($this->encryptionManager->isEnabled()) {
			$output->writeln('Encryption is enabled. Aborted.');
			return 1;
		}
		$this->output = $output;
		$userId = $input->getArgument('user_id');
		$inBackground = !($input->getOption('now') ?? true);
		if ($inBackground) {
			echo "Extracting coordinates from photo is performed in a BackgroundJob \n";
		}
		if ($userId === null) {
			$this->userManager->callForSeenUsers(function (IUser $user) use ($inBackground) {
				$this->rescanUserPhotos($user->getUID(), $inBackground);
			});
		} else {
			$user = $this->userManager->get($userId);
			if ($user !== null) {
				$this->rescanUserPhotos($userId, $inBackground);
			}
		}
		return 0;
	}

	/**
	 * @param string $userId
	 * @param bool $inBackground
	 * @return void
	 * @throws \OCP\PreConditionNotMetException
	 */
	private function rescanUserPhotos(string $userId, bool $inBackground = true) {
		echo '======== User '.$userId.' ========'."\n";
		$c = 1;
		foreach ($this->photofilesService->rescan($userId, $inBackground) as $path) {
			echo '['.$c.'] Photo "'.$path.'" added'."\n";
			$c++;
		}
		$this->config->setUserValue($userId, 'maps', 'installScanDone', 'yes');
	}
}
