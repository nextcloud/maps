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

use OCP\IDBConnection;

class CacheManager {
	private $db;
	public function __construct(IDBConnection $db) {
		$this -> db = $db;
	}

	/**
	 * List items in a folder
	 */
	
	
	public function insert($hash,$raw){
		$serialized = serialize($raw);
		$sql = "INSERT INTO `*PREFIX*maps_adress_cache` (adres_hash,serialized) VALUES(?,?)";
		$query = $this -> db -> prepare($sql);
		$query -> bindParam(1, $hash, \PDO::PARAM_STR);
		$query -> bindParam(2, $serialized, \PDO::PARAM_STR);
		$result = $query -> execute();
	}
	
	public function check($hash){
		$sql = 'SELECT * from `*PREFIX*maps_adress_cache` where adres_hash=?';
		$query = $this -> db -> prepare($sql);
		$query -> bindParam(1, $hash, \PDO::PARAM_STR);
		$query -> execute();
		$result = $query->fetch();
		return unserialize($result['serialized']);
	} 
}
