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
use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\IServerContainer;
use OCP\IGroupManager;
use OCP\ILogger;

use OCA\Maps\Service\TracksService;

/**
 * @param string $text
 * @return string
 */
function remove_utf8_bom(string $text): string {
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
    private $dbtype;
    private $dbdblquotes;
    private $l;
    private $logger;
    private $tracksService;
    protected $appName;

    public function __construct($AppName,
                                IRequest $request,
                                IServerContainer $serverContainer,
                                IConfig $config,
                                IManager $shareManager,
                                IAppManager $appManager,
                                IUserManager $userManager,
                                IGroupManager $groupManager,
                                IL10N $l,
                                ILogger $logger,
                                TracksService $tracksService,
                                $UserId){
        parent::__construct($AppName, $request);
        $this->tracksService = $tracksService;
        $this->logger = $logger;
        $this->appName = $AppName;
        $this->appVersion = $config->getAppValue('maps', 'installed_version');
        $this->userId = $UserId;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->l = $l;
        $this->dbtype = $config->getSystemValue('dbtype');
        $this->config = $config;
        if ($UserId !== '' and $UserId !== null and $serverContainer !== null){
            $this->userfolder = $serverContainer->getUserFolder($UserId);
        }
        $this->shareManager = $shareManager;
    }

	/**
	 * @NoAdminRequired
	 * @return DataResponse
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
    public function getTracks($myMapId=null): DataResponse {
        if (is_null($myMapId) || $myMapId === '') {
            $tracks = $this->tracksService->getTracksFromDB($this->userId, $this->userfolder, true, false, true);
        } else {
            $folders = $this->userfolder->getById($myMapId);
            $folder = array_shift($folders);
            $tracks = $this->tracksService->getTracksFromDB($this->userId, $folder, true, false, false);
        }
        return new DataResponse($tracks);
    }

	/**
	 * @NoAdminRequired
	 */
	public function getTrackContentByFileId($id) {
		$track = $this->tracksService->getTrackByFileIDFromDB($id, $this->userId);
		$res = is_null($track) ? null : $this->userfolder->getById($track['file_id']);
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
				return new DataResponse($this->l->t('Bad file type'), 400);
			}
		}
		else {
			return new DataResponse($this->l->t('File not found'), 400);
		}
	}

	/**
	 * @NoAdminRequired
	 * @param $id
	 * @return DataResponse
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
    public function getTrackFileContent($id): DataResponse {
        $track = $this->tracksService->getTrackFromDB($id);
        $res = is_null($track) ? null : $this->userfolder->getById($track['file_id']);
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
                return new DataResponse($this->l->t('Bad file type'), 400);
            }
        }
        else {
            return new DataResponse($this->l->t('File not found'), 400);
        }
    }

	/**
	 * @NoAdminRequired
	 * @param $id
	 * @param $color
	 * @param $metadata
	 * @param $etag
	 * @return DataResponse
	 */
    public function editTrack($id, $color, $metadata, $etag): DataResponse {
        $track = $this->tracksService->getTrackFromDB($id, $this->userId);
        if ($track !== null) {
            $this->tracksService->editTrackInDB($id, $color, $metadata, $etag);
            return new DataResponse('EDITED');
        }
        else {
            return new DataResponse($this->l->t('No such track'), 400);
        }
    }

	/**
	 * @NoAdminRequired
	 * @param $id
	 * @return DataResponse
	 */
    public function deleteTrack($id): DataResponse {
        $track = $this->tracksService->getTrackFromDB($id, $this->userId);
        if ($track !== null) {
            $this->tracksService->deleteTrackFromDB($id);
            return new DataResponse('DELETED');
        }
        else {
            return new DataResponse($this->l->t('No such track'), 400);
        }
    }

}
