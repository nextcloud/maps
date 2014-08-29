<?php
/**
 * ownCloud - passman
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Sander Brand <brantje@gmail.com>
 * @copyright Sander Brand 2014
 */
namespace OCA\Maps\Db;

use \OCP\IDb;
use \OCP\DB\insertid;

class LocationManager {
	private $userid;
	private $db;
	public function __construct($db) {
		$this -> db = $db;
	}

	public function save($location) {
		$sql = "INSERT INTO `*PREFIX*maps_locations` (device_hash,lat,lng,timestamp,hdop,altitude,speed) VALUES(?,?,?,?,?,?,?)";
		$query = $this -> db -> prepareQuery($sql);
		$query -> bindParam(1, $location['device_hash'], \PDO::PARAM_STR);
		$query -> bindParam(2, $location['lat'], \PDO::PARAM_STR);
		$query -> bindParam(3, $location['lng'], \PDO::PARAM_STR);
		$query -> bindParam(4, $location['timestamp'], \PDO::PARAM_STR);
		$query -> bindParam(5, $location['hdop'], \PDO::PARAM_STR);
		$query -> bindParam(6, $location['altitude'], \PDO::PARAM_STR);
		$query -> bindParam(7, $location['speed'], \PDO::PARAM_STR);
		$result = $query -> execute();
		$location['id'] = $this -> db -> getInsertId('`*PREFIX*maps_locations`');
		return $location;
	}

	public function addDevice($name, $hash, $user) {
		$sql = "INSERT INTO `*PREFIX*maps_location_track_users` (user_id,name,hash,created) VALUES(?,?,?,?)";
		$query = $this -> db -> prepareQuery($sql);
		$query -> bindParam(1, $user, \PDO::PARAM_STR);
		$query -> bindParam(2, $name, \PDO::PARAM_STR);
		$query -> bindParam(3, $hash, \PDO::PARAM_STR);
		$query -> bindParam(4, time(), \PDO::PARAM_STR);
		$result = $query -> execute();
		return $this -> db -> getInsertId('`*PREFIX*maps_locations`');
	}
	
	public function loadHistory($deviceId,$limit)
	{
		$sql = "SELECT `d`.`id`,`d`.`name`,`hash`,`l`.* from *PREFIX*maps_location_track_users d join *PREFIX*maps_locations l on d.hash = l.device_hash  where d.id = ? order by l.timestamp DESC LIMIT ?";
		$query = $this -> db -> prepareQuery($sql);
		$query -> bindParam(1, $deviceId, \PDO::PARAM_INT);
		$query -> bindParam(2, $limit, \PDO::PARAM_INT);
		$result = $query -> execute();
		$rows = array();
		while ($row = $result -> fetchRow()) {
			$rows[] = $row;
		}
		return $rows;
		
	}
	
	public function loadAll($userId)
	{
		$sql = "SELECT * from  `*PREFIX*maps_location_track_users` where `user_id`=?";
		$query = $this -> db -> prepareQuery($sql);
		$query -> bindParam(1, $userId, \PDO::PARAM_STR); 
		$result = $query -> execute();
		$rows = array();
		while ($row = $result -> fetchRow()) {
			$rows[] = $row;
		}
		return $rows;
	}
	public function remove($id,$userId)
	{
		$sql = "DELETE from `*PREFIX*maps_location_track_users` where `user_id`=? and id=?";
		$query = $this -> db -> prepareQuery($sql);
		$query -> bindParam(1, $userId, \PDO::PARAM_STR); 
		$query -> bindParam(2, $id, \PDO::PARAM_STR); 
		$result = $query -> execute();
	}
	

}
