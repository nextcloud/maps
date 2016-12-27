<?php
/**
 * ownCloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 * @copyright Vinzenz Rosenkranz 2015, 2016
 */

namespace OCA\Maps\Controller;

use OCA\Maps\Db\ApiKey;
use OCA\Maps\Db\ApiKeyMapper;
use \OCP\IRequest;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\ApiController;


class ApiKeyController extends ApiController {

	private $userId;
	private $apiKeyMapper;

	public function __construct($appName, IRequest $request, ApiKeyMapper $apiKeyMapper, $userId) {
		parent::__construct($appName, $request);
		$this->apiKeyMapper = $apiKeyMapper;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param $key string
	 * @param $id int
	 * @return JSONResponse
	 */
	public function updateKey($key, $id) {

		$apikey = new ApiKey();
		$apikey->setId($id);
		$apikey->setApiKey($key);

		/* Only save apiKey if it exists in db */
		try {
			$this->apiKeyMapper->find($id);
			return new JSONResponse($this->apiKeyMapper->update($apikey));
		} catch(\OCP\AppFramework\Db\DoesNotExistException $e) {
			return new JSONResponse([
				'error' => $e->getMessage()
			]);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param $key string
	 * @return JSONResponse
	 */
	public function addKey($key){
		$apikey = new ApiKey();
		$apikey->setApiKey($key);
		$apikey->setUserId($this->userId);

		/* @var $apikey ApiKey */
		$apikey = $this->apiKeyMapper->insert($apikey);

		$response = array('id'=> $apikey->getId());
		return new JSONResponse($response);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return JSONResponse
	 */
	public function getKey(){
		$apikey = new ApiKey();
		try {
			$apikey = $this->apiKeyMapper->findByUser($this->userId);
		} catch(\OCP\AppFramework\Db\DoesNotExistException $e) {
			$apikey->setUserId($this->userId);
		}
		return new JSONResponse($apikey);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param $id int
	 * @return JSONResponse
	 */
	public function removeApiKey($id){
		$apikey = $this->apiKeyMapper->find($id);
		if($apikey->userId == $this->userId) {
			$this->apiKeyMapper->delete($apikey);
		}
		return new JSONResponse();
	}

}
