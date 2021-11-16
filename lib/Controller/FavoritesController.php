<?php
/**
 * Nextcloud - Maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @author Paul Schwörer <hello@paulschwoerer.de>
 * @copyright Julien Veyssier 2019
 * @copyright Paul Schwörer 2019
 */

namespace OCA\Maps\Controller;

use OCA\Maps\DB\FavoriteShareMapper;
use OCA\Maps\Service\FavoritesService;
use OCP\App\IAppManager;
use OCP\Share\IManager;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IServerContainer;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IRequest;

class FavoritesController extends Controller {

    private $userId;
    private $userfolder;
    private $config;
    private $appVersion;
    private $shareManager;
    private $userManager;
    private $groupManager;
    private $dbtype;
    private $dbdblquotes;
    private $defaultDeviceId;
    private $trans;
    private $logger;
    private $favoritesService;
    private $dateTimeZone;
    protected $appName;

    /* @var FavoriteShareMapper */
    private $favoriteShareMapper;

    public function __construct($AppName,
                                IRequest $request,
                                IServerContainer $serverContainer,
                                IConfig $config,
                                IManager $shareManager,
                                IAppManager $appManager,
                                IUserManager $userManager,
                                IGroupManager $groupManager,
                                IL10N $trans,
                                ILogger $logger,
                                FavoritesService $favoritesService,
                                IDateTimeZone $dateTimeZone,
                                FavoriteShareMapper $favoriteShareMapper,
                                $UserId) {
        parent::__construct($AppName, $request);
        $this->favoritesService = $favoritesService;
        $this->dateTimeZone = $dateTimeZone;
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
        if ($UserId !== '' and $UserId !== null and $serverContainer !== null) {
            // path of user files folder relative to DATA folder
            $this->userfolder = $serverContainer->getUserFolder($UserId);
        }
        $this->shareManager = $shareManager;
        $this->favoriteShareMapper = $favoriteShareMapper;
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
        if (is_numeric($lat) && is_numeric($lng)) {
            $favoriteId = $this->favoritesService->addFavoriteToDB($this->userId, $name, $lat, $lng, $category, $comment, $extensions);
            $favorite = $this->favoritesService->getFavoriteFromDB($favoriteId);
            return new DataResponse($favorite);
        } else {
            return new DataResponse('invalid values', 400);
        }
    }

    /**
     * @NoAdminRequired
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
            } else {
                return new DataResponse('invalid values', 400);
            }
        } else {
            return new DataResponse('no such favorite', 400);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function renameCategories($categories, $newName) {
        if (is_array($categories)) {
            foreach ($categories as $cat) {
                $this->favoritesService->renameCategoryInDB($this->userId, $cat, $newName);

                // Rename share if one exists
                try {
                    $share = $this->favoriteShareMapper->findByOwnerAndCategory($this->userId, $cat);
                    $share->setCategory($newName);
                    $this->favoriteShareMapper->update($share);
                } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
                }
            }
        }
        return new DataResponse('RENAMED');
    }

    /**
     * @NoAdminRequired
     */
    public function deleteFavorite($id) {
        $favorite = $this->favoritesService->getFavoriteFromDB($id, $this->userId);
        if ($favorite !== null) {
            $this->favoritesService->deleteFavoriteFromDB($id);
            return new DataResponse('DELETED');
        } else {
            return new DataResponse('no such favorite', 400);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function deleteFavorites($ids) {
        $this->favoritesService->deleteFavoritesFromDB($ids, $this->userId);
        return new DataResponse('DELETED');
    }

    /**
     * @NoAdminRequired
     */
    public function getSharedCategories() {
        $categories = $this->favoriteShareMapper->findAllByOwner($this->userId);

        return new DataResponse($categories);
    }

    /**
     * @NoAdminRequired
     */
    public function shareCategory($category) {
        if ($this->favoritesService->countFavorites($this->userId, [$category], null, null) === 0) {
            return new DataResponse("Unknown category", Http::STATUS_BAD_REQUEST);
        }

        $share = $this->favoriteShareMapper->findOrCreateByOwnerAndCategory($this->userId, $category);

        if ($share === null) {
            return new DataResponse("Error sharing favorite", Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        return new DataResponse($share);
    }

    /**
     * @NoAdminRequired
     */
    public function unShareCategory($category) {
        if ($this->favoritesService->countFavorites($this->userId, [$category], null, null) === 0) {
            return new DataResponse("Unknown category", Http::STATUS_BAD_REQUEST);
        }

        $didExist = $this->favoriteShareMapper->removeByOwnerAndCategory($this->userId, $category);

        return new DataResponse([
            'did_exist' => $didExist
        ]);
    }

    /**
     * @NoAdminRequired
     */
    public function exportFavorites($begin, $end, $categoryList = null, $all = false) {
        // sorry about ugly categoryList management:
        // when an empty list is passed in http request, we get null here
        if ($categoryList === null or (is_array($categoryList) and count($categoryList) === 0)) {
            $response = new DataResponse('Nothing to export', 400);
            return $response;
        }

        // create /Maps directory if necessary
        $userFolder = $this->userfolder;
        if (!$userFolder->nodeExists('/Maps')) {
            $userFolder->newFolder('Maps');
        }
        if ($userFolder->nodeExists('/Maps')) {
            $mapsFolder = $userFolder->get('/Maps');
            if ($mapsFolder->getType() !== \OCP\Files\FileInfo::TYPE_FOLDER) {
                $response = new DataResponse('/Maps is not a directory', 400);
                return $response;
            }
            else if (!$mapsFolder->isCreatable()) {
                $response = new DataResponse('/Maps is not writeable', 400);
                return $response;
            }
        }
        else {
            $response = new DataResponse('Impossible to create /Maps', 400);
            return $response;
        }

        $nbFavorites = $this->favoritesService->countFavorites($this->userId, $categoryList, $begin, $end);
        if ($nbFavorites === 0) {
            $response = new DataResponse('Nothing to export', 400);
            return $response;
        }

        // generate export file name
        $prefix = $all ? '' : 'filtered-';
        $tz = $this->dateTimeZone->getTimeZone();
        $now = new \DateTime('now', $tz);
        $dateStr = $now->format('Y-m-d H:i:s (P)');
        $filename = $dateStr.' '.$prefix.'favorites.gpx';

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
        $cleanpath = str_replace(array('../', '..\\'), '',$path);

        if ($userFolder->nodeExists($cleanpath)){
            $file = $userFolder->get($cleanpath);
            if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                $file->isReadable()){
                $lowerFileName = strtolower($file->getName());
                if ($this->endswith($lowerFileName, '.gpx') or $this->endswith($lowerFileName, '.kml') or $this->endswith($lowerFileName, '.kmz') or $this->endswith($lowerFileName, '.json') or $this->endswith($lowerFileName, '.geojson')) {
                    $result = $this->favoritesService->importFavorites($this->userId, $file);
                    return new DataResponse($result);
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

    private function endswith($string, $test) {
        $strlen = strlen($string);
        $testlen = strlen($test);
        if ($testlen > $strlen) return false;
        return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
    }

}
