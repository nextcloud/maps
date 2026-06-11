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

use OCA\Maps\AppFramework\Db\Attribute\Column;
use OCA\Maps\AppFramework\Db\Attribute\Entity;
use OCP\DB\Types;

#[Entity(name: 'maps_device_shares')]
class DeviceShare {
	#[Column(name: 'id', type: Types::STRING)]
	public int $id;

	#[Column(name: 'token', type: Types::STRING)]
	public string $token;

	#[Column(name: 'device_id', type: Types::INTEGER)]
	public int $deviceId;

	#[Column(name: 'timestamp_from', type: Types::INTEGER)]
	public int $timestampFrom;

	#[Column(name: 'timestamp_to', type: Types::INTEGER)]
	public int $timestampTo;
}
