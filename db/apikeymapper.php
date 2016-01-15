<?php
namespace OCA\Maps\Db;

use OCP\AppFramework\Db\Mapper;
use OCP\IDb;

class ApiKeyMapper extends Mapper {

	public function __construct(IDB $db) {
		parent::__construct($db, 'maps_apikeys', '\OCA\Maps\Db\ApiKey');
	}

	/**
	 * @param int $id
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @return ApiKey
	 */
	public function find($id) {
		$sql = 'SELECT * FROM `*PREFIX*maps_apikeys` '.
			'WHERE `id` = ?';
		return $this->findEntity($sql, [$id]);
	}

	/**
	 * @param string $uid
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @return ApiKey
	 */
	public function findByUser($uid) {
		$sql = 'SELECT * FROM `*PREFIX*maps_apikeys` '.
			'WHERE `user_id` = ?';
		return $this->findEntity($sql, [$uid]);
	}

	/**
	 * @param int $limit
	 * @param int $offset
	 * @return ApiKey[]
	 */
	public function findAll($limit=null, $offset=null) {
		$sql = 'SELECT * FROM `*PREFIX*maps_apikeys`';
		return $this->findEntities($sql, $limit, $offset);
	}
}
