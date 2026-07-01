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
use OCP\IDBConnection;
use OCP\Security\ISecureRandom;

/**
 * @template-extends Repository<DeviceShare>
 */
class DeviceShareRepository extends Repository {
	public function __construct(
		IDBConnection $db,
		private readonly ISecureRandom $secureRandom,
	) {
		parent::__construct($db, DeviceShare::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByToken(string $token): ?DeviceShare {
		return $this->findOneBy(['token' => $token]);
	}

	public function create(int $deviceId, int $timestampFrom, int $timestampTo): DeviceShare {
		$token = $this->secureRandom->generate(
			Constants::TOKEN_LENGTH,
			ISecureRandom::CHAR_HUMAN_READABLE
		);

		$newShare = new DeviceShare();
		$newShare->token = $token;
		$newShare->deviceId = $deviceId;
		$newShare->timestampFrom = $timestampFrom;
		$newShare->timestampTo = $timestampTo;

		return $this->insert($newShare);
	}

	/**
	 * @param list<int> $deviceIds
	 * @return \Generator<DeviceShare>
	 */
	public function findByDeviceIds(array $deviceIds): \Generator {
		return $this->findBy(['deviceId' => $deviceIds]);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findById(int $id): ?DeviceShare {
		return $this->findOneBy(['id' => $id]);
	}

	public function removeById(int $id): bool {
		return $this->deleteBy(['id' => $id]) === 1;
	}

	public function removeAllByDeviceId(int $deviceId): bool {
		return $this->deleteBy(['deviceId' => $deviceId]) > 0;
	}
}
