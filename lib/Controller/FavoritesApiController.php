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
use OCP\App\IAppManager;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IServerContainer;
use OCP\IUserManager;
use OCP\Share\IManager;

class FavoritesApiController extends ApiController {
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
	private $l;
	private $favoritesService;
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
		FavoritesService $favoritesService,
		$UserId) {
		parent::__construct($AppName, $request,
			'PUT, POST, GET, DELETE, PATCH, OPTIONS',
			'Authorization, Content-Type, Accept',
			1728000);
		$this->favoritesService = $favoritesService;
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
			$this->userfolder = $serverContainer->getUserFolder($UserId);
		}
		$this->shareManager = $shareManager;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @param $apiversion
	 * @param int $pruneBefore
	 * @return DataResponse
	 */
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @param $apiversion
	 * @param $name
	 * @param $lat
	 * @param $lng
	 * @param $category
	 * @param $comment
	 * @param $extensions
	 * @return DataResponse
	 */
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @param $id
	 * @param $name
	 * @param $lat
	 * @param $lng
	 * @param $category
	 * @param $comment
	 * @param $extensions
	 * @return DataResponse
	 */
	public function editFavorite($id, $name, $lat, $lng, $category, $comment, $extensions): DataResponse {
		$favorite = $this->favoritesService->getFavoriteFromDB($id, $this->userId);
		if ($favorite !== null) {
			if (($lat === null || is_numeric($lat)) &&
				($lng === null || is_numeric($lng))
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

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @param $id
	 * @return DataResponse
	 */
	public function deleteFavorite($id): DataResponse {
		$favorite = $this->favoritesService->getFavoriteFromDB($id, $this->userId);
		if ($favorite !== null) {
			$this->favoritesService->deleteFavoriteFromDB($id);
			return new DataResponse('DELETED');
		} else {
			return new DataResponse($this->l->t('No such favorite'), 400);
		}
	}

}
