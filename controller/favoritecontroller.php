<?php
/**
 * ownCloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 * @copyright Vinzenz Rosenkranz 2015
 */

namespace OCA\Maps\Controller;

use OCA\Maps\Db\Favorite;
use OCA\Maps\Db\FavoriteMapper;
use \OCP\IRequest;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\ApiController;


class FavoriteController extends ApiController {

	private $userId;
	private $favoriteMapper;

	public function __construct($appName, IRequest $request, FavoriteMapper $favoriteMapper, $userId) {
		parent::__construct($appName, $request);
		$this->favoriteMapper = $favoriteMapper;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param $name string
	 * @param $id int
	 * @return JSONResponse
	 */
	public function updateFavorite($name, $id) {

		$favorite = new Favorite();
		$favorite->setId($id);
		$favorite->setName($name);
		$favorite->setTimestamp(time());

		/* Only save favorite if it exists in db */
		try {
			$this->favoriteMapper->find($id);
			return new JSONResponse($this->favoriteMapper->update($favorite));
		} catch(\OCP\AppFramework\Db\DoesNotExistException $e) {
			return new JSONResponse([
				'error' => $e->getMessage()
			]);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param $lat float
	 * @param $lng float
	 * @return JSONResponse
	 */
	public function addFavorite($lat, $lng, $name = null){
		$favorite = new Favorite();
		$favorite->setName($name);
		$favorite->setTimestamp(time());
		$favorite->setUserId($this->userId);
		$favorite->setLat($lat);
		$favorite->setLng($lng);

		/* @var $favorite Favorite */
		$favorite = $this->favoriteMapper->insert($favorite);

		$response = array('id'=> $favorite->getId());
		return new JSONResponse($response);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return JSONResponse
	 */
	public function getFavorites(){
		$favorites = $this->favoriteMapper->findAll();
		return new JSONResponse($favorites);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param $id int
	 * @return JSONResponse
	 */
	public function removeFavorite($id){
		$favorite = $this->favoriteMapper->find($id);
		if($favorite->userId == $this->userId) {
			$this->favoriteMapper->delete($favorite);
		}
		return new JSONResponse();
	}

}
