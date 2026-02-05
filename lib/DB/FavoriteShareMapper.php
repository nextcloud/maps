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
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use OCP\Security\ISecureRandom;

/** @template-extends QBMapper<FavoriteShare> */
class FavoriteShareMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private ISecureRandom $secureRandom,
		private IRootFolder $root,
	) {
		parent::__construct($db, 'maps_favorite_shares');
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByToken(string $token): FavoriteShare {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	public function create(string $owner, string $category): FavoriteShare {
		$token = $this->secureRandom->generate(
			Constants::TOKEN_LENGTH,
			ISecureRandom::CHAR_HUMAN_READABLE
		);

		$newShare = new FavoriteShare();
		$newShare->setToken($token);
		$newShare->setCategory($category);
		$newShare->setOwner($owner);

		return $this->insert($newShare);
	}

	/**
	 * @return FavoriteShare[]
	 */
	public function findAllByOwner(string $owner): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('owner', $qb->createNamedParameter($owner, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function findAllByMapId(string $userId, int $mapId): array {
		$userFolder = $this->root->getUserFolder($userId);
		$folder = $userFolder->getFirstNodeById($mapId);
		$shares = [];
		if (!$folder instanceof Folder) {
			return $shares;
		}
		return $this->findAllByFolder($folder);
	}

	/**
	 * @throws NotFoundException
	 */
	public function findAllByFolder(Folder $folder, bool $isCreatable = true): array {
		try {
			/** @var File $file */
			$file = $folder->get('.favorite_shares.json');
		} catch (NotFoundException $e) {
			if ($isCreatable) {
				$folder->newFile('.favorite_shares.json', '[]');
				return [];
			} else {
				throw new NotFoundException();
			}
		}
		return json_decode($file->getContent(), true);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByOwnerAndCategory(string $owner, string $category): FavoriteShare {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('category', $qb->createNamedParameter($category, IQueryBuilder::PARAM_STR))
			)->andWhere(
				$qb->expr()->eq('owner', $qb->createNamedParameter($owner, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param $category
	 * @return mixed|null
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function findByMapIdAndCategory(string $userId, int $mapId, $category) {
		$shares = $this->findAllByMapId($userId, $mapId);
		foreach ($shares as $share) {
			if ($share->category === $category) {
				return $share;
			}
		}
		return null;
	}

	public function removeByMapIdAndCategory(string $userId, int $mapId, $category) {
		$userFolder = $this->root->getUserFolder($userId);
		$folder = $userFolder->getFirstNodeById($mapId);
		$shares = [];
		$deleted = null;
		if (!$folder instanceof Folder) {
			return $deleted;
		}
		try {
			$file = $folder->get('.favorite_shares.json');
		} catch (NotFoundException $e) {
			$file = $folder->newFile('.favorite_shares.json', $content = '[]');
		}
		$data = json_decode($file->getContent(), true);
		foreach ($data as $share) {
			$c = $share['category'];
			if ($c === $category) {
				$deleted = $share;
			} else {
				$shares[] = $share;
			}
		}
		$file->putContent(json_encode($shares, JSON_PRETTY_PRINT));
		return $deleted;
	}

	public function findOrCreateByOwnerAndCategory(string $owner, string $category): ?FavoriteShare {
		/* @var ?FavoriteShare $entity */
		$entity = null;

		try {
			$entity = $this->findByOwnerAndCategory($owner, $category);
		} catch (DoesNotExistException $e) {
			$entity = $this->create($owner, $category);
		} catch (MultipleObjectsReturnedException $e) {
		}

		return $entity;
	}

	public function removeByOwnerAndCategory(string $owner, string $category): bool {
		try {
			$entity = $this->findByOwnerAndCategory($owner, $category);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return false;
		}

		$this->delete($entity);

		return true;
	}
}
