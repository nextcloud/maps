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

use OCP\Encryption\IManager;
use OCP\Files\NotFoundException;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OCP\IConfig;

use OCA\Maps\Service\TracksService;

class RescanTracks extends Command {

    protected $userManager;

    protected $output;

    protected $encryptionManager;

    private $tracksService;

    public function __construct(IUserManager $userManager,
                                IManager $encryptionManager,
                                TracksService $tracksService,
                                IConfig $config) {
        parent::__construct();
        $this->userManager = $userManager;
        $this->encryptionManager = $encryptionManager;
        $this->tracksService = $tracksService;
        $this->config = $config;
    }
    protected function configure() {
        $this->setName('maps:scan-tracks')
            ->setDescription('Rescan track files')
            ->addArgument(
                'user_id',
                InputArgument::OPTIONAL,
                'Rescan track files for the given user'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        if ($this->encryptionManager->isEnabled()) {
            $output->writeln('Encryption is enabled. Aborted.');
            return 1;
        }
        $this->output = $output;
        $userId = $input->getArgument('user_id');
        if ($userId === null) {
            $this->userManager->callForSeenUsers(function (IUser $user) {
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

    private function rescanUserTracks($userId) {
        echo '======== User '.$userId.' ========'."\n";
        $c = 1;
        foreach ($this->tracksService->rescan($userId) as $path) {
            echo '['.$c.'] Track "'.$path.'" added'."\n";
            $c++;
        }
        $this->config->setUserValue($userId, 'maps', 'installScanDone', 'yes');
    }
}
