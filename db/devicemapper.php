<?php
namespace OCA\Maps\Db;

use OCP\AppFramework\Db\Mapper;
use OCP\IDBConnection;

class DeviceMapper extends Mapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'maps_location_track_users', '\OCA\Maps\Db\Device');
	}

	/**
	 * @param string $hash
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 * @return Device
	 */
	public function findByHash($hash) {
		$sql = 'SELECT * FROM `*PREFIX*maps_location_track_users` '.
			'WHERE `hash` = ?';
		return $this->findEntity($sql, [$hash]);
	}

	/**
	 * @param int $id
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 * @return Device
	 */
	public function findById($id) {
		$sql = 'SELECT * FROM `*PREFIX*maps_location_track_users` '.
			'WHERE `id` = ?';
		return $this->findEntity($sql, [$id]);
	}

	/**
	 * @param string $userId
	 * @param int $limit
	 * @param int $offset
	 * @return Device[]
	 */
	public function findAll($userId, $limit=null, $offset=null) {
		$sql = 'SELECT * FROM `*PREFIX*maps_location_track_users`'.
			'WHERE `user_id` = ?';
		return $this->findEntities($sql, [$userId], $limit, $offset);
	}

}