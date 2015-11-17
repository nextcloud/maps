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
	 * @param $lat int
	 * @param $lng int
	 * @param $timestamp string
	 * @param $name string
	 * @param $userId int
	 * @param $id int
	 * @return JSONResponse
	 */
	public function update($lat, $lng, $timestamp, $name, $userId, $id) {

		$favorite = new Favorite();
		$favorite->setLat($lat);
		$favorite->setLng($lng);
		if((string)(float)$timestamp === $timestamp) {
			if(strtotime(date('d-m-Y H:i:s',$timestamp)) === (int)$timestamp) {
				$favorite->setTimestamp((int)$timestamp);
			} elseif(strtotime(date('d-m-Y H:i:s',$timestamp/1000)) === (int)floor($timestamp/1000)) {
				$favorite->setTimestamp((int)floor($timestamp/1000));
			}
		} else {
			$favorite->timestamp = strtotime($timestamp);
		}
		$favorite->setName($name);
		$favorite->setUserId($userId);
		$favorite->setId($id);

		/* Only save favorite if it exists in db */
		try {
			$this->favoriteMapper->find($id);
			return new JSONResponse($this->favoriteMapper->insert($favorite));
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
	public function addFavorite($lat, $lng){
		$favorite = new Favorite();
		$favorite->setName("empty");
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
