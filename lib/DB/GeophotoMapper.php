<?php

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Piotr Bator <prbator@gmail.com>
 * @copyright Piotr Bator 2017
 */

namespace OCA\Maps\DB;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Geophoto> */
class GeophotoMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'maps_photos');
	}

	/**
	 * @param $id
	 * @return mixed|\OCP\AppFramework\Db\Entity
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function find($id) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param $fileId
	 * @param $userId
	 * @return mixed|\OCP\AppFramework\Db\Entity
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function findByFileIdUserId($fileId, $userId) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)->andWhere(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param $fileId
	 * @return mixed|\OCP\AppFramework\Db\Entity
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function findByFileId($fileId) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param $userId
	 * @param $limit
	 * @param $offset
	 * @return array|\OCP\AppFramework\Db\Entity[]
	 * @throws \OCP\DB\Exception
	 */
	public function findAll($userId, $limit = null, $offset = null) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)->andWhere(
				$qb->expr()->isNotNull('lat')
			)->andWhere(
				$qb->expr()->isNotNull('lng')
			)->orderBy('date_taken', 'ASC');
		if (!is_null($offset)) {
			$qb->setFirstResult($offset);
		}
		if (!is_null($limit)) {
			$qb->setMaxResults($limit);
		}
		return $this->findEntities($qb);
	}

	/**
	 * @param $userId
	 * @param $limit
	 * @param $offset
	 * @return array|\OCP\AppFramework\Db\Entity[]
	 * @throws \OCP\DB\Exception
	 */
	public function findAllNonLocalized($userId, $limit = null, $offset = null) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)->andWhere(
				$qb->expr()->orX(
					$qb->expr()->isNull('lat'),
					$qb->expr()->isNull('lng')
				)
			)->orderBy('date_taken', 'DESC');
		if (!is_null($offset)) {
			$qb->setFirstResult($offset);
		}
		if (!is_null($limit)) {
			$qb->setMaxResults($limit);
		}
		return array_reverse($this->findEntities($qb));
	}

	/**
	 * @param $fileId
	 * @return int
	 * @throws \OCP\DB\Exception
	 */
	public function deleteByFileId($fileId) {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_STR))
			);

		return $qb->executeStatement();
	}

	/**
	 * @param $fileId
	 * @param $userId
	 * @return int
	 * @throws \OCP\DB\Exception
	 */
	public function deleteByFileIdUserId($fileId, $userId) {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)->andWhere(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_STR))
			);
		return $qb->executeStatement();
	}

	/**
	 * @param $userId
	 * @return int
	 * @throws \OCP\DB\Exception
	 */
	public function deleteAll($userId) {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		return $qb->executeStatement();
	}

	/**
	 * @param $fileId
	 * @param $lat
	 * @param $lng
	 * @return int
	 * @throws \OCP\DB\Exception
	 */
	public function updateByFileId($fileId, $lat, $lng) {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('lat', $qb->createNamedParameter($lat))
			->set('lng', $qb->createNamedParameter($lng))
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId)));

		return $qb->executeStatement();
	}

}
