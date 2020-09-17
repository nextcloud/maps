<?php

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Arthur Crepin-Leblond <arthur@marmottus.net>
 * @copyright Arthur Crepin-Leblond 2020
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

use OCA\Maps\Service\PhotofilesService;

class RescanPhotosJobLess extends Command {

    protected $userManager;

    protected $output;

    protected $encryptionManager;

    private $photofilesService;

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
    protected function configure() {
        $this->setName('maps:scan-photos-jobless')
            ->setDescription('Rescan photos GPS exif data without creating a background job')
            ->addArgument(
                'user_id',
                InputArgument::OPTIONAL,
                'Rescan photos GPS exif data for the given user'
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
                $this->rescanUserPhotos($user->getUID());
            });
        } else {
            $user = $this->userManager->get($userId);
            if ($user !== null) {
                $this->rescanUserPhotos($userId);
            }
        }
        return 0;
    }

    private function rescanUserPhotos($userId) {
        echo '======== User '.$userId.' ========'."\n";
        $c = 1;
        foreach ($this->photofilesService->rescanJobless($userId) as $path) {
            echo '['.$c.'] Photo "'.$path.'" added'."\n";
            $c++;
        }
        $this->config->setUserValue($userId, 'maps', 'installScanDone', 'yes');
    }
}
