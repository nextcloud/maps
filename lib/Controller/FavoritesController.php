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

function endswith($string, $test) {
    $strlen = strlen($string);
    $testlen = strlen($test);
    if ($testlen > $strlen) return false;
    return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
}

class FavoritesController extends Controller {

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
        parent::__construct($AppName, $request);
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
     */
    public function getFavorites() {
        $favorites = $this->favoritesService->getFavoritesFromDB($this->userId);
        return new DataResponse($favorites);
    }

    /**
     * @NoAdminRequired
     */
    public function addFavorite($name, $lat, $lng, $category, $comment, $extensions) {
        if ($name && strlen($name) > 0
            && is_numeric($lat)
            && is_numeric($lng)
        ) {
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
     */
    public function editFavorite($id, $name, $lat, $lng, $category, $comment, $extensions) {
        $favorite = $this->favoritesService->getFavoriteFromDB($id, $this->userId);
        if ($favorite !== null) {
            if ($name && strlen($name) > 0
                && ($lat === null || is_numeric($lat))
                && ($lng === null || is_numeric($lng))
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

    /**
     * @NoAdminRequired
     */
    public function exportAllFavorites() {
        return $this->exportFavorites([], null, null, true);
    }

    /**
     * @NoAdminRequired
     */
    public function exportFavorites($categoryList, $begin, $end, $all=false) {
        // create /Maps directory if necessary
        $userFolder = $this->userfolder;
        if (!$userFolder->nodeExists('/Maps')) {
            $userFolder->newFolder('Maps');
        }
        if ($userFolder->nodeExists('/Maps')) {
            $mapsFolder = $userFolder->get('/Maps');
            if ($mapsFolder->getType() !== \OCP\Files\FileInfo::TYPE_FOLDER) {
                $response = new DataResponse(['message'=>'/Maps is not a directory'], 400);
                return $response;
            }
            else if (!$mapsFolder->isCreatable()) {
                $response = new DataResponse(['message'=>'/Maps is not writeable'], 400);
                return $response;
            }
        }
        else {
            $response = new DataResponse(['message'=>'Impossible to create /Maps'], 400);
            return $response;
        }

        // generate export file name
        $prefix = $all ? '' : 'filtered-';
        $now = new \DateTime();
        $dateStr = $now->format('Y-m-d H:i:s');
        $filename = $dateStr.'-'.$prefix.'favorites.gpx';

        if ($mapsFolder->nodeExists($filename)) {
            $mapsFolder->get($filename)->delete();
        }
        $file = $mapsFolder->newFile($filename);
        $handler = $file->fopen('w');

        $this->favoritesService->exportFavorites($this->userId, $handler, $categoryList, $begin, $end, $this->appVersion);

        fclose($handler);
        $file->touch();
        return new DataResponse('/Maps/'.$filename);
    }

    /**
     * @NoAdminRequired
     */
    public function importFavorites($path) {
        $userFolder = $this->userfolder;
        $cleanpath = str_replace(array('../', '..\\'), '',  $path);

        if ($userFolder->nodeExists($cleanpath)){
            $file = $userFolder->get($cleanpath);
            if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                $file->isReadable()){
                if (endswith($file->getName(), '.gpx') or endswith($file->getName(), '.GPX')) {
                    $nbImported = $this->favoritesService->importFavorites($this->userId, $file);
                    return new DataResponse($nbImported);
                }
                else {
                    // invalid extension
                    return new DataResponse('Invalid file extension', 400);
                }
            }
            else {
                // directory or not readable
                return new DataResponse('Impossible to read the file', 400);
            }
        }
        else {
            // does not exist
            return new DataResponse('File does not exist', 400);
        }
    }

}
