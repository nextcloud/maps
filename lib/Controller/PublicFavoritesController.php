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
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;

class PublicFavoritesController extends PublicPageController {
	private ?string $defaultFavoritsJSON;

	public function __construct(
		string $appName,
		IRequest $request,
		ISession $session,
		IURLGenerator $urlGenerator,
		IConfig $config,
		IInitialStateService $initialStateService,
		IManager $shareManager,
		IUserManager $userManager,
		private IL10N $l,
		private FavoritesService $favoritesService,
		private FavoriteShareMapper $favoriteShareMapper,
		IEventDispatcher $eventDispatcher,
	) {
		parent::__construct($appName, $request, $session, $urlGenerator, $eventDispatcher, $config, $initialStateService, $shareManager, $userManager);
		$this->userManager = $userManager;
		$this->shareManager = $shareManager;
		$this->defaultFavoritsJSON = json_encode([
			'type' => 'FeatureCollection',
			'features' => []
		], JSON_PRETTY_PRINT);
	}

	/**
	 * Validate the permissions of the share
	 */
	private function validateShare(IShare $share): bool {
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
	 * @throws NotFoundException
	 */
	private function getShare(): IShare {
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
	 * @return \OCP\Files\File|Folder
	 * @throws NotFoundException
	 */
	private function getShareNode() {
		\OC_User::setIncognitoMode(true);

		$share = $this->getShare();

		return $share->getNode();
	}

	/**
	 * @throws NotPermittedException
	 */
	private function getJSONFavoritesFile(Folder $folder, bool $isCreatable): Node {
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
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	#[PublicPage]
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
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\Files\InvalidPathException
	 */
	#[PublicPage]
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
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\Files\InvalidPathException
	 */
	#[PublicPage]
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
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\Files\InvalidPathException
	 */
	#[PublicPage]
	public function editFavorite(int $id, ?string $name, ?float $lat, ?float $lng, ?string $category, ?string $comment, ?string $extensions): DataResponse {
		$share = $this->getShare();
		$permissions = $share->getPermissions();
		$folder = $this->getShareNode();
		$isCreatable = ($permissions & (1 << 2)) && $folder->isCreatable();
		$file = $this->getJSONFavoritesFile($folder, $isCreatable);
		$isUpdateable = ($permissions & (1 << 1)) && $file->isUpdateable();
		if (!$isUpdateable) {
			throw new NotPermittedException();
		}
		$favorite = $this->favoritesService->getFavoriteFromJSON($file, $id);
		if ($favorite === null) {
			return new DataResponse($this->l->t('no such favorite'), 400);
		}
		$this->favoritesService->editFavoriteInJSON($file, $id, $name, $lat, $lng, $category, $comment, $extensions);
		$editedFavorite = $this->favoritesService->getFavoriteFromJSON($file, $id);
		$editedFavorite['isDeletable'] = ($permissions & (1 << 3)) && $editedFavorite['isDeletable'];
		return new DataResponse($editedFavorite);
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\Files\InvalidPathException
	 */
	#[PublicPage]
	public function renameCategories(array $categories, string $newName): DataResponse {
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

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\Files\InvalidPathException
	 */
	#[PublicPage]
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
	 * @param array $ids
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\Files\InvalidPathException
	 */
	#[PublicPage]
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
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	#[PublicPage]
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
