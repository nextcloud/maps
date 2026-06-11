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

use OCA\Maps\AppFramework\Db\Repository;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends Repository<Geophoto>
 */
class GeophotoRepository extends Repository {

	public function __construct(
		private readonly IDBConnection $db,
	) {
		parent::__construct($db, Geophoto::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function find(int $id): ?Geophoto {
		return $this->findOneBy(['id' => $id]);
	}

	/**
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function findByFileIdUserId(int $fileId, string $userId): Geophoto {
		return $this->findOneBy([
			'userId' => $userId,
			'fileId' => $fileId,
		]);
	}

	/**
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function findByFileId(int $fileId): ?Geophoto {
		return $this->findOneBy([
			'fileId' => $fileId,
		]);
	}

	/**
	 * @return list<Geophoto>
	 * @throws \OCP\DB\Exception
	 */
	public function findAll(string $userId, int $limit = null, int $offset = null): array {
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
	 * @return list<Geophoto>
	 * @throws \OCP\DB\Exception
	 */
	public function findAllNonLocalized(string $userId, int $limit = null, int $offset = null): array {
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
	 * @throws \OCP\DB\Exception
	 */
	public function deleteByFileId(int $fileId): int {
		return $this->deleteBy([
			'fileId' => $fileId,
		]);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function deleteByFileIdUserId(int $fileId, string $userId): int {
		return $this->deleteBy([
			'userId' => $userId,
			'fileId' => $fileId,
		]);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function deleteAll(string $userId): int {
		return $this->deleteBy([
			'userId' => $userId,
		]);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function updateByFileId(int $fileId, ?float $lat, ?float $lng): int {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('lat', $qb->createNamedParameter($lat))
			->set('lng', $qb->createNamedParameter($lng))
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId)));

		return $qb->executeStatement();
	}

}
