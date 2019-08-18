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

function remove_utf8_bom($text) {
    $bom = pack('H*','EFBBBF');
    $text = preg_replace("/^$bom/", '', $text);
    return $text;
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
            $res = $this->userfolder->getById($track['file_id']);
            if (is_array($res) and count($res) > 0) {
                $trackFile = $res[0];
                if ($trackFile->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
                    $track['mtime'] = $trackFile->getMTime();
                    $track['file_name'] = $trackFile->getName();
                    $track['file_path'] = \preg_replace("/^\/".$this->userId."\/files/", '', $trackFile->getPath());
                    array_push($existingTracks, $track);
                }
                else {
                    $this->deleteTrack($track['id']);
                }
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
        $res = $this->userfolder->getById($track['file_id']);
        if (is_array($res) and count($res) > 0) {
            $trackFile = $res[0];
            if ($trackFile->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
                $trackContent = remove_utf8_bom($trackFile->getContent());
                // compute metadata if necessary
                // first time we get it OR the file changed
                if (!$track['metadata'] || $track['etag'] !== $trackFile->getEtag()) {
                    $metadata = $this->tracksService->generateTrackMetadata($trackFile);
                    $this->tracksService->editTrackInDB($track['id'], null, $metadata, $trackFile->getEtag());
                }
                else {
                    $metadata = $track['metadata'];
                }
                return new DataResponse([
                    'metadata'=>$metadata,
                    'content'=>$trackContent
                ]);
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
    public function editTrack($id, $color, $metadata, $etag) {
        $track = $this->tracksService->getTrackFromDB($id, $this->userId);
        if ($track !== null) {
            $this->tracksService->editTrackInDB($id, $color, $metadata, $etag);
            return new DataResponse('EDITED');
        }
        else {
            return new DataResponse('no such track', 400);
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

}
