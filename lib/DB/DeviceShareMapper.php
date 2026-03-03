<?php

/**
 * @copyright Copyright (c) 2019, Paul Schwörer <hello@paulschwoerer.de>
 *
 * @author Paul Schwörer <hello@paulschwoerer.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Maps\DB;

use OC\Share\Constants;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use OCP\Security\ISecureRandom;

/** @template-extends QBMapper<DeviceShare> */
class DeviceShareMapper extends QBMapper {
	/* @var ISecureRandom */
	private $secureRandom;
	private $root;

	public function __construct(IDBConnection $db, ISecureRandom $secureRandom, IRootFolder $root) {
		parent::__construct($db, 'maps_device_shares');

		$this->secureRandom = $secureRandom;
		$this->root = $root;
	}

	/**
	 * @param string $token
	 * @return DeviceShare|null
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByToken($token) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param string[] $token
	 * @return DeviceShare[]|null
	 * @throws DoesNotExistException
	 */
	public function findByTokens($tokens) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->in('token', $qb->createNamedParameter($tokens, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param $deviceId
	 * @param $timestampFrom
	 * @param $timestampTo
	 * @return DeviceShare
	 */
	public function create($deviceId, $timestampFrom, $timestampTo): Entity {
		$token = $this->secureRandom->generate(
			Constants::TOKEN_LENGTH,
			ISecureRandom::CHAR_HUMAN_READABLE
		);

		$newShare = new DeviceShare();
		$newShare->setToken($token);
		$newShare->setDeviceId($deviceId);
		$newShare->setTimestampFrom($timestampFrom);
		$newShare->setTimestampTo($timestampTo);

		return $this->insert($newShare);
	}


	/**
	 * @param $deviceId
	 * @return DeviceShare[]
	 * @throws DoesNotExistException
	 */
	public function findByDeviceId($deviceId) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('device_id', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param $deviceIds
	 * @return DeviceShare[]
	 */
	public function findByDeviceIds($deviceIds) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->in('device_id', $qb->createNamedParameter($deviceIds, IQueryBuilder::PARAM_INT_ARRAY))
			);

		return $this->findEntities($qb);
	}

	/*
	public function findByMapIdAndDeviceId($userId, $mapId, $deviceId) {
		$shares = $this->findAllByMapId($userId, $mapId);
		foreach ($shares as $share) {
			if ($share->deviceId === $deviceId) {
				return $share;
			}
		}
		return null;
	}


	public function removeByMapIdAndDeviceId($userId, $mapId, $deviceId) {
		$userFolder = $this->root->getUserFolder($userId);
		$folders = $userFolder->getById($mapId);
		$shares = [];
		$deleted = null;
		if (empty($folders)) {
			return $deleted;
		}
		$folder = array_shift($folders);
		if ($folder === null) {
			return $deleted;
		}
		try {
			$file=$folder->get(".device_shares.json");
		} catch (NotFoundException $e) {
			$file=$folder->newFile(".device_shares.json", $content = '[]');
		}
		$data = json_decode($file->getContent(),true);
		foreach ($data as $share) {
			$c = $share["deviceId"];
			if($c === $deviceId) {
				$deleted = $share;
			} else {
				$shares[] = $share;
			}
		}
		$file->putContent(json_encode($shares, JSON_PRETTY_PRINT));
		return $deleted;
	}
	*/

	/**
	 * @param $id
	 * @return DeviceShare|null
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function findById($id) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param $id
	 * @return bool
	 */
	public function removeById($id) {
		try {
			$entity = $this->findById($id);
			$this->delete($entity);
		} catch (DoesNotExistException) {
			return false;
		}
		return true;
	}

	/**
	 * @param $deviceId
	 * @return bool
	 */
	public function removeAllByDeviceId($deviceId) {
		try {
			$entities = $this->findByDeviceId($deviceId);
			foreach ($entities as $entity) {
				$this->delete($entity);
			}
		} catch (DoesNotExistException) {
			return false;
		}
		return true;
	}
}
