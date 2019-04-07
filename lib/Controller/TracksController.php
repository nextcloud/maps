<?php
/**
 * Nextcloud - Maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Maps\Controller;

use OCP\App\IAppManager;

use OCP\IURLGenerator;
use OCP\IConfig;
use \OCP\IL10N;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\ApiController;
use OCP\Constants;
use OCP\Share;

use OCA\Maps\Service\TracksService;

function endswith($string, $test) {
    $strlen = strlen($string);
    $testlen = strlen($test);
    if ($testlen > $strlen) return false;
    return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
}

class TracksController extends Controller {

    private $userId;
    private $userfolder;
    private $config;
    private $appVersion;
    private $shareManager;
    private $userManager;
    private $groupManager;
    private $dbconnection;
    private $dbtype;
    private $dbdblquotes;
    private $trans;
    private $logger;
    private $tracksService;
    protected $appName;

    public function __construct($AppName, IRequest $request, $UserId,
                                $userfolder, $config, $shareManager,
                                IAppManager $appManager, $userManager,
                                $groupManager, IL10N $trans, $logger, TracksService $tracksService){
        parent::__construct($AppName, $request);
        $this->tracksService = $tracksService;
        $this->logger = $logger;
        $this->appName = $AppName;
        $this->appVersion = $config->getAppValue('maps', 'installed_version');
        $this->userId = $UserId;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->trans = $trans;
        $this->dbtype = $config->getSystemValue('dbtype');
        $this->config = $config;
        $this->dbconnection = \OC::$server->getDatabaseConnection();
        if ($UserId !== '' and $userfolder !== null){
            $this->userfolder = $userfolder;
        }
        $this->shareManager = $shareManager;
    }

    /**
     * @NoAdminRequired
     */
    public function getTracks() {
        $tracks = $this->tracksService->getTracksFromDB($this->userId);
        $existingTracks = [];
        foreach ($tracks as $track) {
            if ($this->userfolder->nodeExists($track['file_path'])
                and $this->userfolder->get($track['file_path'])->getType() === \OCP\Files\FileInfo::TYPE_FILE
            ) {
                array_push($existingTracks, $track);
            }
            else {
                $this->deleteTrack($track['id']);
            }
        }
        return new DataResponse($existingTracks);
    }

    /**
     * @NoAdminRequired
     */
    public function getTrackFileContent($id) {
        $track = $this->tracksService->getTrackFromDB($id);
        if ($this->userfolder->nodeExists($track['file_path'])) {
            $trackFile = $this->userfolder->get($track['file_path']);
            if ($trackFile->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
                $trackContent = $trackFile->getContent();
                return new DataResponse($trackContent);
            }
            else {
                return new DataResponse('bad file type', 400);
            }
        }
        else {
            return new DataResponse('file not found', 400);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function addTrack($path) {
        if ($path && strlen($path) > 0) {
            $cleanpath = str_replace(array('../', '..\\'), '',  $path);
            if ($this->userfolder->nodeExists($cleanpath)) {
                $trackFile = $this->userfolder->get($cleanpath);
                if ($trackFile->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
                    $trackFileId = $trackFile->getId();
                    $trackId = $this->tracksService->addTrackToDB($this->userId, $cleanpath, $trackFileId);
                    $track = $this->tracksService->getTrackFromDB($trackId);
                    return new DataResponse($track);
                }
                else {
                    return new DataResponse('bad file type', 400);
                }
            }
            else {
                return new DataResponse('file not found', 400);
            }
        }
        else {
            return new DataResponse('invalid value', 400);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function deleteTrack($id) {
        $track = $this->tracksService->getTrackFromDB($id, $this->userId);
        if ($track !== null) {
            $this->tracksService->deleteTrackFromDB($id);
            return new DataResponse('DELETED');
        }
        else {
            return new DataResponse('no such track', 400);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function deleteTracks($ids) {
        $this->tracksService->deleteTracksFromDB($ids, $this->userId);
        return new DataResponse('DELETED');
    }

}
