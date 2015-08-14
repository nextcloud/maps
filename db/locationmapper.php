<?php
namespace OCA\Maps\Db;

use OCP\AppFramework\Db\Mapper;
use OCP\IDBConnection;

class LocationMapper extends Mapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'maps_locations', '\OCA\Maps\Db\Location');
	}

	/**
	 * @param int $id
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 * @return Location
	 */
	public function find($id) {
		$sql = 'SELECT * FROM `*PREFIX*maps_locations` '.
			'WHERE `id` = ?';
		return $this->findEntity($sql, [$id]);
	}

	/**
	 * @param string $deviceHash
	 * @param string $from
	 * @param string $until
	 * @param int $limit
	 * @param int $offset
	 * @return Location[]
	 */
	public function findBetween($deviceHash, $from, $until, $limit=null, $offset=null) {
		$sql = 'SELECT * FROM `*PREFIX*maps_locations` '.
			'WHERE `device_hash` = ?'.
			'AND `timestamp` BETWEEN ? and ?';
		return $this->findEntities($sql, [$deviceHash, $from, $until], $limit, $offset);
	}

	/**
	 * @param int $limit
	 * @param int $offset
	 * @return Location[]
	 */
	public function findAll($limit=null, $offset=null) {
		$sql = 'SELECT * FROM `*PREFIX*maps_locations`';
		return $this->findEntities($sql, $limit, $offset);
	}
}