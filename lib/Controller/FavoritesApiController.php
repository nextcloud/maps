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

use OCA\Maps\Service\FavoritesService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;

class FavoritesApiController extends ApiController {
	public function __construct(
		string $appName,
		IRequest $request,
		private IL10N $l,
		private FavoritesService $favoritesService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request,
			'PUT, POST, GET, DELETE, PATCH, OPTIONS',
			'Authorization, Content-Type, Accept',
			1728000);
	}

	/**
	 * @param $apiversion
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function getFavorites($apiversion, int $pruneBefore = 0): DataResponse {
		$now = new \DateTime();

		$favorites = $this->favoritesService->getFavoritesFromDB($this->userId, $pruneBefore);

		$etag = md5(json_encode($favorites));
		if ($this->request->getHeader('If-None-Match') === '"' . $etag . '"') {
			return new DataResponse([], Http::STATUS_NOT_MODIFIED);
		}
		return (new DataResponse($favorites))
			->setLastModified($now)
			->setETag($etag);
	}

	/**
	 * @param $apiversion
	 * @param $name
	 * @param $lat
	 * @param $lng
	 * @param $category
	 * @param $comment
	 * @param $extensions
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function addFavorite($apiversion, $name, $lat, $lng, $category, $comment, $extensions): DataResponse {
		if (is_numeric($lat) && is_numeric($lng)) {
			$favoriteId = $this->favoritesService->addFavoriteToDB($this->userId, $name, $lat, $lng, $category, $comment, $extensions);
			$favorite = $this->favoritesService->getFavoriteFromDB($favoriteId);
			return new DataResponse($favorite);
		} else {
			return new DataResponse($this->l->t('Invalid values'), 400);
		}
	}

	/**
	 * @param $name
	 * @param $lat
	 * @param $lng
	 * @param $category
	 * @param $comment
	 * @param $extensions
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function editFavorite(int $id, $name, $lat, $lng, $category, $comment, $extensions): DataResponse {
		$favorite = $this->favoritesService->getFavoriteFromDB($id, $this->userId);
		if ($favorite !== null) {
			if (($lat === null || is_numeric($lat))
				&& ($lng === null || is_numeric($lng))
			) {
				$this->favoritesService->editFavoriteInDB($id, $name, $lat, $lng, $category, $comment, $extensions);
				$editedFavorite = $this->favoritesService->getFavoriteFromDB($id);
				return new DataResponse($editedFavorite);
			} else {
				return new DataResponse($this->l->t('Invalid values'), 400);
			}
		} else {
			return new DataResponse($this->l->t('No such favorite'), 400);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function deleteFavorite(int $id): DataResponse {
		$favorite = $this->favoritesService->getFavoriteFromDB($id, $this->userId);
		if ($favorite !== null) {
			$this->favoritesService->deleteFavoriteFromDB($id);
			return new DataResponse('DELETED');
		} else {
			return new DataResponse($this->l->t('No such favorite'), 400);
		}
	}

}
