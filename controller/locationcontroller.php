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

use OCA\Maps\Db\Device;
use OCA\Maps\Db\DeviceMapper;
use OCA\Maps\Db\Location;
use OCA\Maps\Db\LocationMapper;
use \OCP\IRequest;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\ApiController;


class LocationController extends ApiController {

	private $userId;
	private $locationMapper;
	private $deviceMapper;

	public function __construct($appName, IRequest $request, LocationMapper $locationMapper, DeviceMapper $deviceMapper, $userId) {
		parent::__construct($appName, $request);
		$this->locationMapper = $locationMapper;
		$this->deviceMapper = $deviceMapper;
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
	 * @return JSONResponse
	 */
	public function update($lat, $lon, $timestamp, $hdop, $altitude, $speed, $hash) {

		$location = new Location();
		$location->lat = $lat;
		$location->lng = $lon;
		if((string)(float)$timestamp === $timestamp) {
			if(strtotime(date('d-m-Y H:i:s',$timestamp)) === (int)$timestamp) {
				$location->timestamp = (int)$timestamp;
			} elseif(strtotime(date('d-m-Y H:i:s',$timestamp/1000)) === (int)floor($timestamp/1000)) {
				$location->timestamp = (int)floor($timestamp/1000);
			}
		} else {
			$location->timestamp = strtotime($timestamp);
		}
		$location->hdop = $hdop;
		$location->altitude = $altitude;
		$location->speed = $speed;
		$location->deviceHash = $hash;

		/* Only save location if hash exists in db */
		try {
			$this->deviceMapper->findByHash($hash);
			return new JSONResponse($this->locationMapper->insert($location));
		} catch(\OCP\AppFramework\Db\DoesNotExistException $e) {
			return new JSONResponse([
				'error' => $e->getMessage()
			]);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param $name string
	 * @return JSONResponse
	 */
	public function addDevice($name){
		$device = new Device();
		$device->name = $name;
		$device->hash = uniqid();
		$device->created = time();
		$device->userId = $this->userId;

		/* @var $device Device */
		$device = $this->deviceMapper->insert($device);

		$response = array('id'=> $device->getId(),'hash'=>$device->hash);
		return new JSONResponse($response);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return JSONResponse
	 */
	public function loadDevices(){
		$response = $this->deviceMapper->findAll($this->userId);
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
		foreach($deviceIds as $deviceId){
			$hash = $this->deviceMapper->findById($deviceId)->hash;
			$response[$deviceId] = $this->locationMapper->findBetween($hash, $from, $till, $limit);
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
		/* @var $device Device */
		$device = $this->deviceMapper->findById($deviceId);
		if($device->userId == $this->userId) {
			$this->deviceMapper->delete($device);
		}
		return new JSONResponse();
	}

}
