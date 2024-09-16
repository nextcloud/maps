<?php

/**
 * @copyright Copyright (c) 2019, Paul Schwörer <hello@paulschwoerer.de>
 *
 * @author Paul Schwörer <hello@paulschwoerer.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Maps\Controller;

use OCA\Maps\DB\FavoriteShareMapper;
use OCA\Maps\Service\FavoritesService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\PublicShareController;
use OCP\IRequest;
use OCP\ISession;

class PublicFavoritesApiController extends PublicShareController {
	/* @var FavoritesService */
	private $favoritesService;

	/* @var FavoriteShareMapper */
	private $favoriteShareMapper;

	public function __construct(
		$appName,
		IRequest $request,
		ISession $session,
		FavoritesService $favoritesService,
		FavoriteShareMapper $favoriteShareMapper
	) {
		parent::__construct($appName, $request, $session);

		$this->favoriteShareMapper = $favoriteShareMapper;
		$this->favoritesService = $favoritesService;
	}

	public function getPasswordHash(): string {
		return '';
	}

	/**
	 * @return bool
	 */
	protected function isPasswordProtected(): bool {
		return false;
	}

	/**
	 * @return bool
	 */
	public function isValidToken(): bool {
		try {
			$this->favoriteShareMapper->findByToken($this->getToken());
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function canEdit(): bool {
		try {
			$share = $this->favoriteShareMapper->findByToken($this->getToken());
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return false;
		}

		return $share->getAllowEdits();
	}

	/**
	 * @PublicPage
	 *
	 * @return DataResponse
	 */
	public function getFavorites(): DataResponse {
		try {
			$share = $this->favoriteShareMapper->findByToken($this->getToken());
		} catch (DoesNotExistException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (MultipleObjectsReturnedException $e) {
			return new DataResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$favorites = $this->favoritesService->getFavoritesFromDB($share->getOwner(), 0, $share->getCategory(), false, false, true);

		return new DataResponse([
			'share' => $share,
			'favorites' => $favorites
		]);
	}

	//  /**
	//   * @PublicPage
	//   *
	//   * @param $lat
	//   * @param $lng
	//   * @param $name
	//   * @param $comment
	//   * @param $extensions
	//   * @return DataResponse
	//   */
	//  public function addFavorite($lat, $lng, $name, $comment, $extensions)
	//  {
	//    if (!$this->canEdit()) {
	//      return new DataResponse('Not authorized to add favorite', Http::STATUS_UNAUTHORIZED);
	//    }
	//
	//    try {
	//      $share = $this->favoriteShareMapper->findByToken($this->getToken());
	//    } catch (DoesNotExistException $e) {
	//      return new DataResponse([], Http::STATUS_NOT_FOUND);
	//    } catch (MultipleObjectsReturnedException $e) {
	//      return new DataResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
	//    }
	//
	//    $category = $share->getCategory();
	//
	//
	//    if (is_numeric($lat) && is_numeric($lng)) {
	//      $favoriteId = $this->favoritesService->addFavoriteToDB($this->userId, $name, $lat, $lng, $category, $comment, $extensions);
	//      $favorite = $this->favoritesService->getFavoriteFromDB($favoriteId);
	//      return new DataResponse($favorite);
	//    } else {
	//      return new DataResponse('invalid values', 400);
	//    }
	//  }

	//  /**
	//   * @PublicPage
	//   *
	//   * @param $id
	//   * @param $lat
	//   * @param $lng
	//   * @param $name
	//   * @param $comment
	//   * @param $extensions
	//   * @return DataResponse
	//   */
	//  public function editFavorite($id, $lat, $lng, $name, $comment, $extensions)
	//  {
	//    if (!$this->canEdit()) {
	//      return new DataResponse('Not authorized to edit favorite', Http::STATUS_UNAUTHORIZED);
	//    }
	//
	//    try {
	//      $share = $this->favoriteShareMapper->findByToken($this->getToken());
	//    } catch (DoesNotExistException $e) {
	//      return new DataResponse([], Http::STATUS_NOT_FOUND);
	//    } catch (MultipleObjectsReturnedException $e) {
	//      return new DataResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
	//    }
	//
	//    $favorite = $this->favoritesService->getFavoriteFromDB($id, $share->getOwner(), $share->getCategory());
	//
	//    if ($favorite !== null) {
	//      if (($lat === null || is_numeric($lat)) &&
	//        ($lng === null || is_numeric($lng))
	//      ) {
	//        $this->favoritesService->editFavoriteInDB($id, $name, $lat, $lng, $favorite['category'], $comment, $extensions);
	//        $editedFavorite = $this->favoritesService->getFavoriteFromDB($id);
	//
	//        return new DataResponse($editedFavorite);
	//      } else {
	//        return new DataResponse('invalid values', 400);
	//      }
	//    } else {
	//      return new DataResponse('no such favorite', 400);
	//    }
	//  }

	//  /**
	//   * @PublicPage
	//   *
	//   * @param $id
	//   * @return DataResponse
	//   */
	//  public function deleteFavorite($id)
	//  {
	//    if (!$this->canEdit()) {
	//      return new DataResponse('Not authorized to delete favorite', Http::STATUS_UNAUTHORIZED);
	//    }
	//
	//    try {
	//      $share = $this->favoriteShareMapper->findByToken($this->getToken());
	//    } catch (DoesNotExistException $e) {
	//      return new DataResponse([], Http::STATUS_NOT_FOUND);
	//    } catch (MultipleObjectsReturnedException $e) {
	//      return new DataResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
	//    }
	//
	//    $favorite = $this->favoritesService->getFavoriteFromDB($id, $share->getOwner(), $share->getCategory());
	//
	//    if ($favorite !== null) {
	//      $this->favoritesService->deleteFavoriteFromDB($id);
	//      return new DataResponse('deleted');
	//    } else {
	//      return new DataResponse('no such favorite', 400);
	//    }
	//  }
}
