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
use OCA\Maps\AppFramework\Db\Repository;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use OCP\Security\ISecureRandom;

/**
 * @template-extends Repository<FavoriteShare>
 */
class FavoriteShareRepository extends Repository {
	public function __construct(
		IDBConnection $db,
		private readonly ISecureRandom $secureRandom,
		private readonly IRootFolder $root,
	) {
		parent::__construct($db, FavoriteShare::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByToken(string $token): FavoriteShare {
		return $this->findOneBy(['token' => $token]);
	}

	public function create(string $owner, string $category): FavoriteShare {
		$token = $this->secureRandom->generate(
			Constants::TOKEN_LENGTH,
			ISecureRandom::CHAR_HUMAN_READABLE
		);

		$newShare = new FavoriteShare();
		$newShare->token = $token;
		$newShare->category = $category;
		$newShare->owner = $owner;

		return $this->insert($newShare);
	}

	/**
	 * @return \Generator<FavoriteShare>
	 */
	public function findAllByOwner(string $owner): \Generator {
		return $this->findBy(['owner' => $owner]);
	}

	/**
	 * @param $userId
	 * @param $mapId
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function findAllByMapId(string $userId, int $mapId): array {
		$userFolder = $this->root->getUserFolder($userId);
		$folder = $userFolder->getFirstNodeById($mapId);
		if (!$folder instanceof Folder) {
			return [];
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
		} catch (NotFoundException) {
			if ($isCreatable) {
				$file = $folder->newFile('.favorite_shares.json', $content = '[]');
				return [];
			} else {
				throw new NotFoundException();
			}
		}
		return json_decode((string)$file->getContent(), true);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByOwnerAndCategory(string $owner, string $category): FavoriteShare {
		return $this->findOneBy([
			'owner' => $owner,
			'category' => $category,
		]);
	}

	/**
	 * @param $userId
	 * @param $mapId
	 * @param $category
	 * @return mixed|null
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function findByMapIdAndCategory(string $userId, int $mapId, string $category) {
		$shares = $this->findAllByMapId($userId, $mapId);
		foreach ($shares as $share) {
			if ($share->category === $category) {
				return $share;
			}
		}
		return null;
	}

	public function removeByMapIdAndCategory(string $userId, int $mapId, string $category): ?array {
		$userFolder = $this->root->getUserFolder($userId);
		$folder = $userFolder->getFirstNodeById($mapId);
		$shares = [];
		$deleted = null;
		if (!$folder instanceof Folder) {
			return null;
		}
		try {
			$file = $folder->get('.favorite_shares.json');
		} catch (NotFoundException) {
			$file = $folder->newFile('.favorite_shares.json', $content = '[]');
		}
		$data = json_decode((string)$file->getContent(), true);
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

	public function findOrCreateByOwnerAndCategory(string $owner, string $category): FavoriteShare {
		try {
			return $this->findByOwnerAndCategory($owner, $category);
		} catch (DoesNotExistException) {
			return $this->create($owner, $category);
		}
	}

	public function removeByOwnerAndCategory(string $owner, string $category): bool {
		return $this->deleteBy([
			'owner' => $owner,
			'category' => $category,
		]) > 0;
	}
}
