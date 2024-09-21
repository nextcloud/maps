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
use OCP\AppFramework\Http\DataResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IDateTimeZone;
use OCP\IGroupManager;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IServerContainer;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;

class PublicFavoritesController extends PublicPageController {

	private string $appVersion;
	private IL10N $l;
	private FavoritesService $favoritesService;
	private IDateTimeZone $dateTimeZone;
	private ?string $defaultFavoritsJSON;
	protected $appName;
	protected $groupManager;

	/* @var FavoriteShareMapper */
	private $favoriteShareMapper;

	public function __construct(
		string $appName,
		IRequest $request,
		ISession $session,
		IURLGenerator $urlGenerator,
		IServerContainer $serverContainer,
		IConfig $config,
		IInitialStateService $initialStateService,
		IManager $shareManager,
		IAppManager $appManager,
		IUserManager $userManager,
		IGroupManager $groupManager,
		IL10N $l,
		FavoritesService $favoritesService,
		IDateTimeZone $dateTimeZone,
		FavoriteShareMapper $favoriteShareMapper,
		IEventDispatcher $eventDispatcher,
	) {
		parent::__construct($appName, $request, $session, $urlGenerator, $eventDispatcher, $config, $initialStateService, $shareManager, $userManager);
		$this->favoritesService = $favoritesService;
		$this->dateTimeZone = $dateTimeZone;
		$this->appName = $appName;
		$this->appVersion = $config->getAppValue('maps', 'installed_version');
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->l = $l;
		$this->config = $config;
		$this->shareManager = $shareManager;
		$this->favoriteShareMapper = $favoriteShareMapper;
		$this->defaultFavoritsJSON = json_encode([
			'type' => 'FeatureCollection',
			'features' => []
		], JSON_PRETTY_PRINT);
	}

	/**
	 * Validate the permissions of the share
	 *
	 * @return bool
	 */
	private function validateShare(\OCP\Share\IShare $share) {
		// If the owner is disabled no access to the link is granted
		$owner = $this->userManager->get($share->getShareOwner());
		if ($owner === null || !$owner->isEnabled()) {
			return false;
		}

		// If the initiator of the share is disabled no access is granted
		$initiator = $this->userManager->get($share->getSharedBy());
		if ($initiator === null || !$initiator->isEnabled()) {
			return false;
		}

		return $share->getNode()->isReadable() && $share->getNode()->isShareable();
	}

	/**
	 * @return \OCP\Share\IShare
	 * @throws NotFoundException
	 */
	private function getShare() {
		// Check whether share exists
		try {
			$share = $this->shareManager->getShareByToken($this->getToken());
		} catch (ShareNotFound $e) {
			// The share does not exists, we do not emit an ShareLinkAccessedEvent
			throw new NotFoundException();
		}

		if (!$this->validateShare($share)) {
			throw new NotFoundException();
		}
		return $share;
	}

	/**
	 * @return \OCP\Files\File|\OCP\Files\Folder
	 * @throws NotFoundException
	 */
	private function getShareNode() {
		\OC_User::setIncognitoMode(true);

		$share = $this->getShare();

		return $share->getNode();
	}

	/**
	 * @param Folder $folder
	 * @param $isCreatable
	 * @return mixed
	 * @throws NotPermittedException
	 */
	private function getJSONFavoritesFile(\OCP\Files\Folder $folder, $isCreatable): \OCP\Files\Node {
		try {
			$file = $folder->get('.favorites.json');
		} catch (NotFoundException $e) {
			if ($isCreatable) {
				$file = $folder->newFile('.favorites.json', $content = $this->defaultFavoritsJSON);
			} else {
				throw new NotPermittedException();
			}

		}
		return $file;
	}

	/**
	 * @PublicPage
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getFavorites(): DataResponse {
		$share = $this->getShare();
		$permissions = $share->getPermissions();
		$folder = $this->getShareNode();
		$isCreatable = ($permissions & (1 << 2)) && $folder->isCreatable();

		$file = $this->getJSONFavoritesFile($folder, $isCreatable);
		$isReadable = (bool)($permissions & (1 << 0));
		if ($isReadable) {
			$favorites = $this->favoritesService->getFavoritesFromJSON($file);
			$favorites = array_map(function ($favorite) use ($permissions) {
				$favorite['isCreatable'] = ($permissions & (1 << 2)) && $favorite['isCreatable'];
				$favorite['isUpdateable'] = ($permissions & (1 << 1)) && $favorite['isUpdateable'];
				$favorite['isDeletable'] = ($permissions & (1 << 3)) && $favorite['isDeletable'];
				return $favorite;
			}, $favorites);
		} else {
			throw new NotPermittedException();
		}
		return new DataResponse($favorites);
	}

	/**
	 * @PublicPage
	 * @param string|null $name
	 * @param float $lat
	 * @param float $lng
	 * @param string|null $category
	 * @param string|null $comment
	 * @param string|null $extensions
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function addFavorite(?string $name, float $lat, float $lng, ?string $category, ?string $comment, ?string $extensions): DataResponse {
		if (is_numeric($lat) && is_numeric($lng)) {
			$share = $this->getShare();
			$permissions = $share->getPermissions();
			$folder = $this->getShareNode();
			$isCreatable = ($permissions & (1 << 2)) && $folder->isCreatable();
			$file = $this->getJSONFavoritesFile($folder, $isCreatable);
			$isUpdateable = ($permissions & (1 << 1)) && $file->isUpdateable();
			if ($isUpdateable) {
				$favoriteId = $this->favoritesService->addFavoriteToJSON($file, $name, $lat, $lng, $category, $comment, $extensions);
				$favorite = $this->favoritesService->getFavoriteFromJSON($file, $favoriteId);
				$favorite['isDeletable'] = ($permissions & (1 << 3)) && $favorite['isDeletable'];
			} else {
				throw new NotPermittedException();
			}
			return new DataResponse($favorite);
		} else {
			return new DataResponse($this->l->t('Invalid values'), 400);
		}
	}

	/**
	 * @PublicPage
	 * @param array $favorites
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function addFavorites(array $favorites): DataResponse {
		$share = $this->getShare();
		$permissions = $share->getPermissions();
		$folder = $this->getShareNode();
		$isCreatable = ($permissions & (1 << 2)) && $folder->isCreatable();
		$file = $this->getJSONFavoritesFile($folder, $isCreatable);
		$isUpdateable = ($permissions & (1 << 1)) && $file->isUpdateable();
		if ($isUpdateable) {
			$favoriteIds = $this->favoritesService->addFavoritesToJSON($file, $favorites);
			$favoritesAfter = [];
			foreach ($this->favoritesService->getFavoritesFromJSON($file) as $favorite) {
				if (in_array($favorite['id'], $favoriteIds)) {
					$favorite['isDeletable'] = ($permissions & (1 << 3)) && $favorite['isDeletable'];
					$favoritesAfter[] = $favorite;
				}
			};
			return new DataResponse($favoritesAfter);
		} else {
			throw new NotPermittedException();
		}
	}

	/**
	 * @PublicPage
	 * @param int $id
	 * @param string|null $name
	 * @param float $lat
	 * @param float $lng
	 * @param string|null $category
	 * @param string|null $comment
	 * @param string|null $extensions
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function editFavorite(int $id, ?string $name, float $lat, float $lng, ?string $category, ?string $comment, ?string $extensions): DataResponse {
		$share = $this->getShare();
		$permissions = $share->getPermissions();
		$folder = $this->getShareNode();
		$isCreatable = ($permissions & (1 << 2)) && $folder->isCreatable();
		$file = $this->getJSONFavoritesFile($folder, $isCreatable);
		$isUpdateable = ($permissions & (1 << 1)) && $file->isUpdateable();
		if ($isUpdateable) {
			$favorite = $this->favoritesService->getFavoriteFromJSON($file, $id);
			if ($favorite !== null) {
				if (($lat === null || is_numeric($lat)) &&
					($lng === null || is_numeric($lng))
				) {
					$this->favoritesService->editFavoriteInJSON($file, $id, $name, $lat, $lng, $category, $comment, $extensions);
					$editedFavorite = $this->favoritesService->getFavoriteFromJSON($file, $id);
					$editedFavorite['isDeletable'] = ($permissions & (1 << 3)) && $editedFavorite['isDeletable'];
					return new DataResponse($editedFavorite);
				} else {
					return new DataResponse($this->l->t('invalid values'), 400);
				}
			} else {
				return new DataResponse($this->l->t('no such favorite'), 400);
			}
		} else {
			throw new NotPermittedException();
		}
	}

	/**
	 * @PublicPage
	 * @param array $categories
	 * @param string $newName
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function renameCategories(array $categories, string $newName): DataResponse {
		if (is_array($categories)) {
			$share = $this->getShare();
			$permissions = $share->getPermissions();
			$folder = $this->getShareNode();
			$isCreatable = ($permissions & (1 << 2)) && $folder->isCreatable();
			$file = $this->getJSONFavoritesFile($folder, $isCreatable);
			$isUpdateable = ($permissions & (1 << 1)) && $file->isUpdateable();
			if ($isUpdateable) {
				foreach ($categories as $cat) {
					$this->favoritesService->renameCategoryInJSON($file, $cat, $newName);
				}
			} else {
				throw new NotPermittedException();
			}
			return new DataResponse('RENAMED');
		}
		throw new NotFoundException();
	}

	/**
	 * @PublicPage
	 * @param int $id
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function deleteFavorite(int $id): DataResponse {
		$share = $this->getShare();
		$permissions = $share->getPermissions();
		$folder = $this->getShareNode();
		$isCreatable = ($permissions & (1 << 2)) && $folder->isCreatable();
		$file = $this->getJSONFavoritesFile($folder, $isCreatable);
		$isDeleteable = ($permissions & (1 << 3)) && $file->isUpdateable();
		if ($isDeleteable) {
			if ($this->favoritesService->deleteFavoriteFromJSON($file, $id) > 0) {
				return new DataResponse('DELETED');
			}
			return new DataResponse($this->l->t('no such favorite'), 400);
		} else {
			throw new NotPermittedException();
		}

	}

	/**
	 * @PublicPage
	 * @param array $ids
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function deleteFavorites(array $ids): DataResponse {
		$share = $this->getShare();
		$permissions = $share->getPermissions();
		$folder = $this->getShareNode();
		$isCreatable = ($permissions & (1 << 2)) && $folder->isCreatable();
		$file = $this->getJSONFavoritesFile($folder, $isCreatable);
		$isDeleteable = ($permissions & (1 << 3)) && $file->isUpdateable();
		if ($isDeleteable) {
			$this->favoritesService->deleteFavoritesFromJSON($file, $ids);
		} else {
			throw new NotPermittedException();
		}
		return new DataResponse('DELETED');
	}

	/**
	 * @PublicPage
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getSharedCategories(): DataResponse {
		$share = $this->getShare();
		$permissions = $share->getPermissions();
		$folder = $this->getShareNode();
		$isCreatable = ($permissions & (1 << 2)) && $folder->isCreatable();
		$isReadable = ($permissions & (1 << 0));
		if ($isReadable) {
			$categories = $this->favoriteShareMapper->findAllByFolder($folder, $isCreatable);
		} else {
			throw new NotPermittedException();
		}
		return new DataResponse($categories);
	}
}
