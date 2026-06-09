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

use OCA\Maps\Service\FavoritesService;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
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

	private \OCP\Files\Folder $userFolder;
	private readonly string $appVersion;
	private readonly IL10N $l;
	private readonly IDateTimeZone $dateTimeZone;
	private readonly ?string $defaultFavoritsJSON;
	protected $appName;

	public function __construct(
		$AppName,
		IRequest $request,
		IServerContainer $serverContainer,
		IConfig $config,
		IManager $shareManager,
		IAppManager $appManager,
		IUserManager $userManager,
		IGroupManager $groupManager,
		IL10N $l,
		private readonly FavoritesService $favoritesService,
		IDateTimeZone $dateTimeZone,
		private readonly \OCA\Maps\DB\FavoriteShareMapper $favoriteShareMapper,
		private readonly string $userId,
	) {
		parent::__construct($AppName, $request);
		$this->dateTimeZone = $dateTimeZone;
		$this->appName = $AppName;
		$this->appVersion = $config->getAppValue('maps', 'installed_version');
		$this->l = $l;
		if ($this->userId !== '' and $this->userId !== null and $serverContainer !== null) {
			// path of user files folder relative to DATA folder
			$this->userFolder = $serverContainer->getUserFolder($this->userId);
		}
		$this->defaultFavoritsJSON = json_encode([
			'type' => 'FeatureCollection',
			'features' => []
		], JSON_PRETTY_PRINT);
	}

	/**
	 * @throws \OCP\Files\NotPermittedException
	 */
	private function getJSONFavoritesFile(\OCP\Files\Folder $folder): \OCP\Files\Node {
		try {
			$file = $folder->get('.favorites.json');
		} catch (NotFoundException) {
			$file = $folder->newFile('.favorites.json', $content = $this->defaultFavoritsJSON);
		}
		return $file;
	}

	/**
	 * @NoAdminRequired
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function getFavorites(?int $myMapId = null): DataResponse {
		if (is_null($myMapId) || $myMapId === 0) {
			$favorites = $this->favoritesService->getFavoritesFromDB($this->userId);
		} else {
			$folders = $this->userFolder->getById($myMapId);
			$folder = array_shift($folders);
			$file = $this->getJSONFavoritesFile($folder);
			$favorites = $this->favoritesService->getFavoritesFromJSON($file);
		}
		return new DataResponse($favorites);
	}

	/**
	 * @NoAdminRequired
	 * @throws NotFoundException
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function addFavorite(?string $name, float $lat, float $lng, ?string $category, ?string $comment, ?string $extensions, ?int $myMapId = null): DataResponse {
		if (is_numeric($lng)) {
			if (is_null($myMapId) || $myMapId === 0) {
				$favoriteId = $this->favoritesService->addFavoriteToDB($this->userId, $name, $lat, $lng, $category, $comment, $extensions);
				$favorite = $this->favoritesService->getFavoriteFromDB($favoriteId);
				return new DataResponse($favorite);
			} else {
				$folders = $this->userFolder->getById($myMapId);
				if (!empty($folders) && $this->userFolder->getId() === $myMapId) {
					$folders[] = $this->userFolder;
				}
				$folder = array_shift($folders);
				if (is_null($folder)) {
					return new DataResponse('Map not found', 404);
				}
				$file = $this->getJSONFavoritesFile($folder);
				$favoriteId = $this->favoritesService->addFavoriteToJSON($file, $name, $lat, $lng, $category, $comment, $extensions);
				$favorite = $this->favoritesService->getFavoriteFromJSON($file, $favoriteId);
				return new DataResponse($favorite);
			}

		} else {
			return new DataResponse($this->l->t('Invalid values'), 400);
		}
	}

	/**
	 * @NoAdminRequired
	 * @throws NotFoundException
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function addFavorites(array $favorites, ?int $myMapId = null): DataResponse {
		if (is_null($myMapId) || $myMapId === 0) {
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
			if (is_null($folder)) {
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
	 * @NoAdminRequired
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function editFavorite(int $id, ?string $name, float $lat, float $lng, ?string $category, ?string $comment, ?string $extensions, ?int $myMapId = null): DataResponse {
		if (is_null($myMapId) || $myMapId === 0) {
			$favorite = $this->favoritesService->getFavoriteFromDB($id, $this->userId);
			if ($favorite !== null) {
				if (is_numeric($lng)
				) {
					$this->favoritesService->editFavoriteInDB($id, $name, $lat, $lng, $category, $comment, $extensions);
					$editedFavorite = $this->favoritesService->getFavoriteFromDB($id);
					return new DataResponse($editedFavorite);
				} else {
					return new DataResponse($this->l->t('invalid values'), 400);
				}
			} else {
				return new DataResponse($this->l->t('no such favorite'), 400);
			}
		} else {
			$folders = $this->userFolder->getById($myMapId);
			$folder = array_shift($folders);
			$file = $this->getJSONFavoritesFile($folder);
			$favorite = $this->favoritesService->getFavoriteFromJSON($file, $id);
			if ($favorite !== null) {
				if (is_numeric($lng)
				) {
					$this->favoritesService->editFavoriteInJSON($file, $id, $name, $lat, $lng, $category, $comment, $extensions);
					$editedFavorite = $this->favoritesService->getFavoriteFromJSON($file, $id);
					return new DataResponse($editedFavorite);
				} else {
					return new DataResponse($this->l->t('invalid values'), 400);
				}
			} else {
				return new DataResponse($this->l->t('no such favorite'), 400);
			}
		}
	}

	/**
	 * @NoAdminRequired
	 * @throws \OCP\DB\Exception
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function renameCategories(array $categories, string $newName, ?int $myMapId = null): DataResponse {
		if (is_array($categories)) {
			foreach ($categories as $cat) {
				if (is_null($myMapId) || $myMapId === 0) {
					$this->favoritesService->renameCategoryInDB($this->userId, $cat, $newName);

					// Rename share if one exists
					try {
						$share = $this->favoriteShareMapper->findByOwnerAndCategory($this->userId, $cat);
						$share->setCategory($newName);
						$this->favoriteShareMapper->update($share);
					} catch (DoesNotExistException|MultipleObjectsReturnedException) {
					}
				} else {
					$folders = $this->userFolder->getById($myMapId);
					$folder = array_shift($folders);
					$file = $this->getJSONFavoritesFile($folder);
					$this->favoritesService->renameCategoryInJSON($file, $cat, $newName);
				}
			}
		}
		return new DataResponse('RENAMED');
	}

	/**
	 * @NoAdminRequired
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function deleteFavorite(int $id, ?int $myMapId = null): DataResponse {
		if (is_null($myMapId) || $myMapId === 0) {
			$favorite = $this->favoritesService->getFavoriteFromDB($id, $this->userId);
			if ($favorite !== null) {
				$this->favoritesService->deleteFavoriteFromDB($id);
				return new DataResponse('DELETED');
			}
			return new DataResponse($this->l->t('no such favorite'), 400);
		}
		$folders = $this->userFolder->getById($myMapId);
		$folder = array_shift($folders);
		$file = $this->getJSONFavoritesFile($folder);
		if ($this->favoritesService->deleteFavoriteFromJSON($file, $id) > 0) {
			return new DataResponse('DELETED');
		}
		return new DataResponse($this->l->t('no such favorite'), 400);
	}

	/**
	 * @NoAdminRequired
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function deleteFavorites(array $ids, ?int $myMapId = null): DataResponse {
		if (is_null($myMapId) || $myMapId === 0) {
			$this->favoritesService->deleteFavoritesFromDB($ids, $this->userId);
		} else {
			$folders = $this->userFolder->getById($myMapId);
			$folder = array_shift($folders);
			$file = $this->getJSONFavoritesFile($folder);
			$this->favoritesService->deleteFavoritesFromJSON($file, $ids);
		}
		return new DataResponse('DELETED');
	}

	/**
	 * @NoAdminRequired
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function getSharedCategories(?int $myMapId = null): DataResponse {
		if (is_null($myMapId) || $myMapId === 0) {
			$categories = $this->favoriteShareMapper->findAllByOwner($this->userId);
		} else {
			$categories = $this->favoriteShareMapper->findAllByMapId($this->userId, $myMapId);
		}

		return new DataResponse($categories);
	}

	/**
	 * @NoAdminRequired
	 */
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

	/**
	 * @NoAdminRequired
	 */
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
	 * @NoAdminRequired
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function addShareCategoryToMap(string $category, int $targetMapId, ?int $myMapId = null): DataResponse {
		if (is_null($myMapId) || $myMapId === 0) {
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
		} catch (NotFoundException) {
			$file = $folder->newFile('.favorite_shares.json', $content = '[]');
		}
		$data = json_decode((string)$file->getContent(), true);
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

	/**
	 * @NoAdminRequired
	 */
	public function removeShareCategoryFromMap(string $category, int $myMapId): DataResponse {
		$d = $this->favoriteShareMapper->removeByMapIdAndCategory($this->userId, $myMapId, $category);
		if (is_null($d)) {
			return new DataResponse('Failed', 500);
		}
		return new DataResponse('Done');
	}

	/**
	 * @NoAdminRequired
	 * @throws NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function exportFavorites(?array $categoryList = null, ?int $begin = null, ?int $end = null, bool $all = false): DataResponse {
		// sorry about ugly categoryList management:
		// when an empty list is passed in http request, we get null here
		if ($categoryList === null or (is_array($categoryList) and count($categoryList) === 0)) {
			return new DataResponse($this->l->t('Nothing to export'), 400);
		}

		// create /Maps directory if necessary
		$userFolder = $this->userFolder;
		if (!$userFolder->nodeExists('/Maps')) {
			$userFolder->newFolder('Maps');
		}
		if ($userFolder->nodeExists('/Maps')) {
			$mapsFolder = $userFolder->get('/Maps');
			if (!($mapsFolder instanceof Folder)) {
				return new DataResponse($this->l->t('/Maps is not a directory'), 400);
			} elseif (!$mapsFolder->isCreatable()) {
				return new DataResponse($this->l->t('/Maps directory is not writeable'), 400);
			}
		} else {
			return new DataResponse($this->l->t('Impossible to create /Maps directory'), 400);
		}

		$nbFavorites = $this->favoritesService->countFavorites($this->userId, $categoryList, $begin, $end);
		if ($nbFavorites === 0) {
			return new DataResponse($this->l->t('Nothing to export'), 400);
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
	 * @NoAdminRequired
	 * @throws NotFoundException
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function importFavorites(string $path): DataResponse {
		$userFolder = $this->userFolder;
		$cleanpath = str_replace(['../', '..\\'], '', $path);

		if ($userFolder->nodeExists($cleanpath)) {
			$file = $userFolder->get($cleanpath);
			if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE
				and $file->isReadable()) {
				$lowerFileName = strtolower((string)$file->getName());
				if ($this->endswith($lowerFileName, '.gpx') or $this->endswith($lowerFileName, '.kml') or $this->endswith($lowerFileName, '.kmz') or $this->endswith($lowerFileName, '.json') or $this->endswith($lowerFileName, '.geojson')) {
					$result = $this->favoritesService->importFavorites($this->userId, $file);
					return new DataResponse($result);
				} else {
					// invalid extension
					return new DataResponse($this->l->t('Invalid file extension'), 400);
				}
			} else {
				// directory or not readable
				return new DataResponse($this->l->t('Impossible to read the file'), 400);
			}
		} else {
			// does not exist
			return new DataResponse($this->l->t('File does not exist'), 400);
		}
	}

	private function endswith(string $string, string $test): bool {
		$strlen = strlen($string);
		$testlen = strlen($test);
		if ($testlen > $strlen) {
			return false;
		}
		return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
	}

}
