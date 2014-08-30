<?php
/**
 * ownCloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Sander Brand <brantje@gmail.com>
 * @copyright Sander Brand 2014
 */

namespace OCA\Maps\Controller;

use \OCP\IRequest;
use \OCP\AppFramework\Http\TemplateResponse;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\ApiController;


class LocationController extends ApiController {

	private $userId;
	private $cacheManager;
	private $locationManager;
	public function __construct($appName, IRequest $request,$locationManager,$userId) {
		parent::__construct($appName, $request);
		$this->locationManager = $locationManager;
		$this->userId = $userId;
	}

	/**
	 * CAUTION: the @Stuff turn off security checks, for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @PublicPage
	 */
	public function update() {
		$params = array('user' => $this -> userId);
		$location['lat'] = $this->params('lat');
		$location['lng'] = $this->params('lon');
		$location['timestamp'] = strtotime($this->params('timestamp'));
		$location['hdop'] = $this->params('hdop');
		$location['altitude'] = $this->params('altitude');
		$location['speed'] = $this->params('speed');
		$location['device_hash'] = $this->params('hash');
		
		/**
		 * @TODO check if hash exists
		 */
		
		$this->locationManager->save($location);
	}

	/**
	 *  @NoAdminRequired
	 */	
	public function addDevice(){
		$deviceName = $this->params('name');
		$hash = uniqid();
		$deviceId = $this->locationManager->addDevice($deviceName,$hash,$this->userId);
		$response = array('id'=> $deviceId,'hash'=>$hash);
		return new JSONResponse($response);			
	}

	/**
	 * @NoAdminRequired
	 */		
	public function loadDevices(){
		$response = $this->locationManager->loadAll($this->userId);
		return new JSONResponse($response);			
	}
	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */		
	public function loadLocations(){
		$deviceIds = explode(',',$this->params('devices'));
		$limit = ($this->params('limit')) ? (int) $this->params('limit') : 500;
		$response = array();
		foreach($deviceIds as $device){
			$response[$device] = $this->locationManager->loadHistory($device,$limit);
		}
		return new JSONResponse($response);			
	}
	/**
	 * @NoAdminRequired
	 */		
	public function removeDevice(){
		$deviceId = $this->params('deviceId');
		$response = $this->locationManager->remove($deviceId,$this->userId);
		return new JSONResponse($response);			
	}

}
