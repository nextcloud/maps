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

use OCA\Maps\Db\LocationManager;
use \OCP\IRequest;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\ApiController;


class LocationController extends ApiController {

	private $userId;
	private $locationManager;
	public function __construct($appName, IRequest $request, LocationManager $locationManager, $userId) {
		parent::__construct($appName, $request);
		$this->locationManager = $locationManager;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param $lat int
	 * @param $lon int
	 * @param $timestamp string
	 * @param $hdop string
	 * @param $altitude int
	 * @param $speed int
	 * @param $hash string
	 */
	public function update($lat, $lon, $timestamp, $hdop, $altitude, $speed, $hash) {
		$location['lat'] = $lat;
		$location['lng'] = $lon;
		if((string)(float)$timestamp === $timestamp) {
			if(strtotime(date('d-m-Y H:i:s',$timestamp)) === (int)$timestamp) {
				$location['timestamp'] = (int)$timestamp;
			} elseif(strtotime(date('d-m-Y H:i:s',$timestamp/1000)) === (int)floor($timestamp/1000)) {
				$location['timestamp'] = (int)floor($timestamp/1000);
			}
		} else {
			$location['timestamp'] = strtotime($timestamp);
		}
		$location['hdop'] = $hdop;
		$location['altitude'] = $altitude;
		$location['speed'] = $speed;
		$location['device_hash'] = $hash;

		/* Only save location if hash exists in db */
		if ( $this->locationManager->checkHash($location['device_hash']) ){
			$this->locationManager->save($location);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param $name string
	 * @return JSONResponse
	 */
	public function addDevice($name){
		$deviceName = $name;
		$hash = uniqid();
		$deviceId = $this->locationManager->addDevice($deviceName,$hash,$this->userId);
		$response = array('id'=> $deviceId,'hash'=>$hash);
		return new JSONResponse($response);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return JSONResponse
	 */
	public function loadDevices(){
		$response = $this->locationManager->loadAll($this->userId);
		return new JSONResponse($response);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param $devices string comma separated list of device ids
	 * @param $from string
	 * @param $till string
	 * @param $limit int
	 * @return JSONResponse
	 */
	public function loadLocations($devices, $from, $till, $limit){
		$deviceIds = explode(',',$devices);
		$from = ($from) ? strtotime($from) : null;
		$till = ($till != '') ? strtotime($till) : strtotime('now');
		$limit = ($limit != '') ? (int) $limit : 2000;
		$response = array();
		foreach($deviceIds as $device){
			$response[$device] = $this->locationManager->loadHistory($device,$from,$till,$limit);
		}
		return new JSONResponse($response);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param $deviceId string
	 * @return JSONResponse
	 */
	public function removeDevice($deviceId){
		$this->locationManager->remove($deviceId,$this->userId);
		return new JSONResponse();
	}

}
