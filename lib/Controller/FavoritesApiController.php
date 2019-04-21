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

use OCA\Maps\Service\FavoritesService;

class FavoritesApiController extends ApiController {

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
    private $defaultDeviceId;
    private $trans;
    private $logger;
    private $favoritesService;
    protected $appName;

    public function __construct($AppName, IRequest $request, $UserId,
                                $userfolder, $config, $shareManager,
                                IAppManager $appManager, $userManager,
                                $groupManager, IL10N $trans, $logger, FavoritesService $favoritesService){
        parent::__construct($AppName, $request,
                            'PUT, POST, GET, DELETE, PATCH, OPTIONS',
                            'Authorization, Content-Type, Accept',
                            1728000);
        $this->favoritesService = $favoritesService;
        $this->logger = $logger;
        $this->appName = $AppName;
        $this->appVersion = $config->getAppValue('maps', 'installed_version');
        $this->userId = $UserId;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->trans = $trans;
        $this->dbtype = $config->getSystemValue('dbtype');
        // IConfig object
        $this->config = $config;
        $this->dbconnection = \OC::$server->getDatabaseConnection();
        if ($UserId !== '' and $userfolder !== null){
            // path of user files folder relative to DATA folder
            $this->userfolder = $userfolder;
        }
        $this->shareManager = $shareManager;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function getFavorites($apiversion, $pruneBefore=0) {
        $now = new \DateTime();

        $favorites = $this->favoritesService->getFavoritesFromDB($this->userId, $pruneBefore);

        $etag = md5(json_encode($favorites));
        if ($this->request->getHeader('If-None-Match') === '"'.$etag.'"') {
            return new DataResponse([], Http::STATUS_NOT_MODIFIED);
        }
        return (new DataResponse($favorites))
            ->setLastModified($now)
            ->setETag($etag);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function addFavorite($apiversion, $name, $lat, $lng, $category, $comment, $extensions) {
        if (is_numeric($lat) && is_numeric($lng)) {
            $favoriteId = $this->favoritesService->addFavoriteToDB($this->userId, $name, $lat, $lng, $category, $comment, $extensions);
            $favorite = $this->favoritesService->getFavoriteFromDB($favoriteId);
            return new DataResponse($favorite);
        }
        else {
            return new DataResponse('invalid values', 400);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function editFavorite($id, $name, $lat, $lng, $category, $comment, $extensions) {
        $favorite = $this->favoritesService->getFavoriteFromDB($id, $this->userId);
        if ($favorite !== null) {
            if (($lat === null || is_numeric($lat)) &&
                ($lng === null || is_numeric($lng))
            ) {
                $this->favoritesService->editFavoriteInDB($id, $name, $lat, $lng, $category, $comment, $extensions);
                $editedFavorite = $this->favoritesService->getFavoriteFromDB($id);
                return new DataResponse($editedFavorite);
            }
            else {
                return new DataResponse('invalid values', 400);
            }
        }
        else {
            return new DataResponse('no such favorite', 400);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function deleteFavorite($id) {
        $favorite = $this->favoritesService->getFavoriteFromDB($id, $this->userId);
        if ($favorite !== null) {
            $this->favoritesService->deleteFavoriteFromDB($id);
            return new DataResponse('DELETED');
        }
        else {
            return new DataResponse('no such favorite', 400);
        }
    }

}
