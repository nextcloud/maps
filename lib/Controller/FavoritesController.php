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
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IDateTimeZone;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IServerContainer;
use OCP\IUserManager;
use OCP\Share\IManager;

class FavoritesController extends Controller {

	private string $userId;
	private \OCP\Files\Folder $userFolder;
	private IConfig $config;
	private string $appVersion;
	private IManager $shareManager;
	private IUserManager $userManager;
	private IGroupManager $groupManager;
	private string $dbtype;
	private IL10N $l;
	private FavoritesService $favoritesService;
	private IDateTimeZone $dateTimeZone;
	private ?string $defaultFavoritsJSON;
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
		IL10N $l,
		FavoritesService $favoritesService,
		IDateTimeZone $dateTimeZone,
		FavoriteShareMapper $favoriteShareMapper,
		$UserId) {
		parent::__construct($AppName, $request);
		$this->favoritesService = $favoritesService;
		$this->dateTimeZone = $dateTimeZone;
		$this->appName = $AppName;
		$this->appVersion = $config->getAppValue('maps', 'installed_version');
		$this->userId = $UserId;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->l = $l;
		$this->dbtype = $config->getSystemValue('dbtype');
		// IConfig object
		$this->config = $config;
		if ($UserId !== '' and $UserId !== null and $serverContainer !== null) {
			// path of user files folder relative to DATA folder
			$this->userFolder = $serverContainer->getUserFolder($UserId);
		}
		$this->shareManager = $shareManager;
		$this->favoriteShareMapper = $favoriteShareMapper;
		$this->defaultFavoritsJSON = json_encode([
			'type' => 'FeatureCollection',
			'features' => []
		], JSON_PRETTY_PRINT);
	}

	/**
	 * @param \OCP\Files\Folder $folder
	 * @return mixed
	 * @throws \OCP\Files\NotPermittedException
	 */
	private function getJSONFavoritesFile(\OCP\Files\Folder $folder): \OCP\Files\Node {
		try {
			$file = $folder->get('.favorites.json');
		} catch (NotFoundException $e) {
			$file = $folder->newFile('.favorites.json', $content = $this->defaultFavoritsJSON);
		}
		return $file;
	}

	/**
	 * @throws \OCP\Files\NotPermittedException
	 */
	#[NoAdminRequired]
	public function getFavorites(?int $myMapId = null): DataResponse {
		if (is_null($myMapId) || $myMapId === '') {
			$favorites = $this->favoritesService->getFavoritesFromDB($this->userId);
		} else {
			$folder = $this->userFolder->getFirstNodeById($myMapId);
			if (!$folder instanceof Folder) {
				throw new NotFoundException("Folder with id $myMapId does not exist");
			}
			$file = $this->getJSONFavoritesFile($folder);
			$favorites = $this->favoritesService->getFavoritesFromJSON($file);
		}
		return new DataResponse($favorites);
	}

	/**
	 * @param string|null $name
	 * @param float $lat
	 * @param float $lng
	 * @param string|null $category
	 * @param string|null $comment
	 * @param string|null $extensions
	 * @param int|null $myMapId
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotPermittedException
	 */
	#[NoAdminRequired]
	public function addFavorite(?string $name, ?float $lat, ?float $lng, ?string $category, ?string $comment, ?string $extensions, ?int $myMapId = null): DataResponse {
		if (!is_numeric($lat) || !is_numeric($lng)) {
			return new DataResponse($this->l->t('Invalid values'), 400);
		}
		if (is_null($myMapId)) {
			$favoriteId = $this->favoritesService->addFavoriteToDB($this->userId, $name, $lat, $lng, $category, $comment, $extensions);
			$favorite = $this->favoritesService->getFavoriteFromDB($favoriteId);
			return new DataResponse($favorite);
		} else {
			$folders = $this->userFolder->getById($myMapId);
			if (!empty($folders) && $this->userFolder->getId() === $myMapId) {
				$folders[] = $this->userFolder;
			}
			$folder = array_shift($folders);
			if (!($folder instanceof Folder)) {
				return new DataResponse('Map not found', 404);
			}
			$file = $this->getJSONFavoritesFile($folder);
			$favoriteId = $this->favoritesService->addFavoriteToJSON($file, $name, $lat, $lng, $category, $comment, $extensions);
			$favorite = $this->favoritesService->getFavoriteFromJSON($file, $favoriteId);
			return new DataResponse($favorite);
		}
	}

	/**
	 * @param array $favorites
	 * @param int|null $myMapId
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotPermittedException
	 */
	#[NoAdminRequired]
	public function addFavorites(array $favorites, ?int $myMapId = null): DataResponse {
		if (is_null($myMapId)) {
			$favoritesAfter = [];
			foreach ($favorites as $favorite) {
				if (is_numeric($favorite->lat) && is_numeric($favorite->lng)) {
					$favoriteId = $this->favoritesService->addFavoriteToDB($this->userId, $favorite->name, $favorite->lat, $favorite->lng, $favorite->category, $favorite->comment, $favorite->extensions);
					$favoritesAfter[] = $this->favoritesService->getFavoriteFromDB($favoriteId);
				} else {
					return new DataResponse('invalid values', 400);
				}
			}
			return new DataResponse($favoritesAfter);
		} else {
			$folders = $this->userFolder->getById($myMapId);
			if (!empty($folders) && $this->userFolder->getId() === $myMapId) {
				$folders[] = $this->userFolder;
			}
			$folder = array_shift($folders);
			if (!$folder instanceof Folder) {
				return new DataResponse('Map not found', 404);
			}
			$file = $this->getJSONFavoritesFile($folder);
			$favoriteIds = $this->favoritesService->addFavoritesToJSON($file, $favorites);
			$favoritesAfter = [];
			foreach ($this->favoritesService->getFavoritesFromJSON($file) as $favorite) {
				if (in_array($favorite['id'], $favoriteIds)) {
					$favoritesAfter[] = $favorite;
				}
			};
			return new DataResponse($favoritesAfter);
		}
	}

	/**
	 * @throws \OCP\Files\NotPermittedException
	 */
	#[NoAdminRequired]
	public function editFavorite(int $id, ?string $name, ?float $lat, ?float $lng, ?string $category, ?string $comment, ?string $extensions, ?int $myMapId = null): DataResponse {
		if (is_null($myMapId)) {
			$favorite = $this->favoritesService->getFavoriteFromDB($id, $this->userId);
			if ($favorite !== null) {
				$this->favoritesService->editFavoriteInDB($id, $name, $lat, $lng, $category, $comment, $extensions);
				$editedFavorite = $this->favoritesService->getFavoriteFromDB($id);
				return new DataResponse($editedFavorite);
			}
			return new DataResponse($this->l->t('no such favorite'), 400);
		}

		$folder = $this->userFolder->getFirstNodeById($myMapId);
		if (!$folder instanceof Folder) {
			return new DataResponse('Map not found', 404);
		}
		$file = $this->getJSONFavoritesFile($folder);
		$favorite = $this->favoritesService->getFavoriteFromJSON($file, $id, $this->userId);
		if ($favorite === null) {
			return new DataResponse($this->l->t('no such favorite'), 400);
		}
		$this->favoritesService->editFavoriteInJSON($file, $id, $name, $lat, $lng, $category, $comment, $extensions);
		$editedFavorite = $this->favoritesService->getFavoriteFromJSON($file, $id);
		return new DataResponse($editedFavorite);
	}

	/**
	 * @throws \OCP\DB\Exception
	 * @throws \OCP\Files\NotPermittedException
	 */
	#[NoAdminRequired]
	public function renameCategories(array $categories, string $newName, ?int $myMapId = null): DataResponse {
		if (is_array($categories)) {
			foreach ($categories as $cat) {
				if (is_null($myMapId)) {
					$this->favoritesService->renameCategoryInDB($this->userId, $cat, $newName);

					// Rename share if one exists
					try {
						$share = $this->favoriteShareMapper->findByOwnerAndCategory($this->userId, $cat);
						$share->setCategory($newName);
						$this->favoriteShareMapper->update($share);
					} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
					}
				} else {
					$folder = $this->userFolder->getFirstNodeById($myMapId);
					if (!$folder instanceof Folder) {
						return new DataResponse('Map not found', 404);
					}
					$file = $this->getJSONFavoritesFile($folder);
					$this->favoritesService->renameCategoryInJSON($file, $cat, $newName);
				}
			}
		}
		return new DataResponse('RENAMED');
	}

	/**
	 * @param int $id
	 * @param int|null $myMapId
	 * @return DataResponse
	 * @throws \OCP\Files\NotPermittedException
	 */
	#[NoAdminRequired]
	public function deleteFavorite(int $id, ?int $myMapId = null): DataResponse {
		if (is_null($myMapId)) {
			$favorite = $this->favoritesService->getFavoriteFromDB($id, $this->userId);
			if ($favorite !== null) {
				$this->favoritesService->deleteFavoriteFromDB($id);
				return new DataResponse('DELETED');
			}
			return new DataResponse($this->l->t('no such favorite'), 400);
		}
		$folder = $this->userFolder->getFirstNodeById($myMapId);
		if (!$folder instanceof Folder) {
			return new DataResponse('Map not found', 404);
		}
		$file = $this->getJSONFavoritesFile($folder);
		if ($this->favoritesService->deleteFavoriteFromJSON($file, $id) > 0) {
			return new DataResponse('DELETED');
		}
		return new DataResponse($this->l->t('no such favorite'), 400);
	}

	/**
	 * @param array $ids
	 * @param int|null $myMapId
	 * @return DataResponse
	 * @throws \OCP\Files\NotPermittedException
	 */
	#[NoAdminRequired]
	public function deleteFavorites(array $ids, ?int $myMapId = null): DataResponse {
		if (is_null($myMapId)) {
			$this->favoritesService->deleteFavoritesFromDB($ids, $this->userId);
		} else {
			$folder = $this->userFolder->getFirstNodeById($myMapId);
			if (!$folder instanceof Folder) {
				return new DataResponse('Map not found', 404);
			}
			$file = $this->getJSONFavoritesFile($folder);
			$this->favoritesService->deleteFavoritesFromJSON($file, $ids);
		}
		return new DataResponse('DELETED');
	}

	/**
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	#[NoAdminRequired]
	public function getSharedCategories(?int $myMapId = null): DataResponse {
		if (is_null($myMapId)) {
			$categories = $this->favoriteShareMapper->findAllByOwner($this->userId);
		} else {
			$categories = $this->favoriteShareMapper->findAllByMapId($this->userId, $myMapId);
		}

		return new DataResponse($categories);
	}

	#[NoAdminRequired]
	public function shareCategory(string $category): DataResponse {
		if ($this->favoritesService->countFavorites($this->userId, [$category], null, null) === 0) {
			return new DataResponse($this->l->t('Unknown category'), Http::STATUS_BAD_REQUEST);
		}

		$share = $this->favoriteShareMapper->findOrCreateByOwnerAndCategory($this->userId, $category);

		if ($share === null) {
			return new DataResponse($this->l->t('Error sharing favorite'), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse($share);
	}

	#[NoAdminRequired]
	public function unShareCategory(string $category): DataResponse {
		if ($this->favoritesService->countFavorites($this->userId, [$category], null, null) === 0) {
			return new DataResponse($this->l->t('Unknown category'), Http::STATUS_BAD_REQUEST);
		}

		$didExist = $this->favoriteShareMapper->removeByOwnerAndCategory($this->userId, $category);

		return new DataResponse([
			'did_exist' => $didExist
		]);
	}

	/**
	 * @param string $category
	 * @param int $targetMapId
	 * @param int|null $myMapId
	 * @return DataResponse
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	#[NoAdminRequired]
	public function addShareCategoryToMap(string $category, int $targetMapId, ?int $myMapId = null): DataResponse {
		if (is_null($myMapId) || $myMapId === '') {
			$share = $this->favoriteShareMapper->findByOwnerAndCategory($this->userId, $category);
		} else {
			$share = $this->favoriteShareMapper->findByMapIdAndCategory($this->userId, $myMapId, $category);
		}
		$folders = $this->userFolder->getById($targetMapId);
		$folder = array_shift($folders);
		if (!($folder instanceof Folder)) {
			return new DataResponse($this->l->t('Map not Found'), 404);
		}
		try {
			$file = $folder->get('.favorite_shares.json');
		} catch (NotFoundException $e) {
			$file = $folder->newFile('.favorite_shares.json', $content = '[]');
		}
		$data = json_decode($file->getContent(), true);
		foreach ($data as $s) {
			if ($s->token === $share->token) {
				return new DataResponse($this->l->t('Share was already on map'));
			}
		}
		$share->id = count($data);
		$data[] = $share;
		$file->putContent(json_encode($data, JSON_PRETTY_PRINT));
		return new DataResponse('Done');
	}

	#[NoAdminRequired]
	public function removeShareCategoryFromMap(string $category, int $myMapId): DataResponse {
		$d = $this->favoriteShareMapper->removeByMapIdAndCategory($this->userId, $myMapId, $category);
		if (is_null($d)) {
			return new DataResponse('Failed', 500);
		}
		return new DataResponse('Done');
	}

	/**
	 * @param array|null $categoryList
	 * @param int|null $begin
	 * @param int|null $end
	 * @param bool $all
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 */
	#[NoAdminRequired]
	public function exportFavorites(?array $categoryList = null, ?int $begin = null, ?int $end = null, bool $all = false): DataResponse {
		// sorry about ugly categoryList management:
		// when an empty list is passed in http request, we get null here
		if ($categoryList === null or (is_array($categoryList) and count($categoryList) === 0)) {
			$response = new DataResponse($this->l->t('Nothing to export'), 400);
			return $response;
		}

		// create /Maps directory if necessary
		$userFolder = $this->userFolder;
		if (!$userFolder->nodeExists('/Maps')) {
			$userFolder->newFolder('Maps');
		}
		if ($userFolder->nodeExists('/Maps')) {
			$mapsFolder = $userFolder->get('/Maps');
			if (!($mapsFolder instanceof Folder)) {
				$response = new DataResponse($this->l->t('/Maps is not a directory'), 400);
				return $response;
			} elseif (!$mapsFolder->isCreatable()) {
				$response = new DataResponse($this->l->t('/Maps directory is not writeable'), 400);
				return $response;
			}
		} else {
			$response = new DataResponse($this->l->t('Impossible to create /Maps directory'), 400);
			return $response;
		}

		$nbFavorites = $this->favoritesService->countFavorites($this->userId, $categoryList, $begin, $end);
		if ($nbFavorites === 0) {
			$response = new DataResponse($this->l->t('Nothing to export'), 400);
			return $response;
		}

		// generate export file name
		$prefix = $all ? '' : 'filtered-';
		$tz = $this->dateTimeZone->getTimeZone();
		$now = new \DateTime('now', $tz);
		$dateStr = $now->format('Y-m-d H:i:s (P)');
		$filename = $dateStr . ' ' . $prefix . 'favorites.gpx';

		if ($mapsFolder->nodeExists($filename)) {
			$mapsFolder->get($filename)->delete();
		}
		$file = $mapsFolder->newFile($filename);
		$handler = $file->fopen('w');

		$this->favoritesService->exportFavorites($this->userId, $handler, $categoryList, $begin, $end, $this->appVersion);

		fclose($handler);
		$file->touch();
		return new DataResponse('/Maps/' . $filename);
	}

	/**
	 * @throws NotFoundException
	 * @throws \OCP\Files\InvalidPathException
	 */
	#[NoAdminRequired]
	public function importFavorites(string $path): DataResponse {
		$userFolder = $this->userFolder;
		$cleanPath = str_replace(['../', '..\\'], '', $path);

		if (!$userFolder->nodeExists($cleanPath)) {
			return new DataResponse($this->l->t('File does not exist'), 400);
		}

		$file = $userFolder->get($cleanPath);
		if (!$file instanceof File || !$file->isReadable()) {
			// directory or not readable
			return new DataResponse($this->l->t('Impossible to read the file'), 400);
		}

		$lowerFileName = strtolower($file->getName());
		if (str_ends_with($lowerFileName, '.gpx')
			|| str_ends_with($lowerFileName, '.kml')
			|| str_ends_with($lowerFileName, '.kmz')
			|| str_ends_with($lowerFileName, '.json')
			|| str_ends_with($lowerFileName, '.geojson')) {
			$result = $this->favoritesService->importFavorites($this->userId, $file);
			return new DataResponse($result);
		} else {
			// invalid extension
			return new DataResponse($this->l->t('Invalid file extension'), 400);
		}
	}
}
