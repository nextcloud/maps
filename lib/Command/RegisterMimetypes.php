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

use OCA\Maps\Service\MimetypeService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RegisterMimetypes extends Command {

	protected MimetypeService $mimetypeService;

	public function __construct(MimetypeService $mimetypeService) {
		parent::__construct();
		$this->mimetypeService = $mimetypeService;
	}

	/**
	 * @return void
	 */
	protected function configure() {
		$this->setName('maps:register-mimetypes')
			->setDescription('Registers the maps mimetypes for existing and new files.');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->writeln('Register mimetypes for existing files');
		$this->mimetypeService->registerForExistingFiles();
		$output->writeln('Register mimetypes for new files');
		$this->mimetypeService->registerForNewFiles();
		return 0;
	}
}
