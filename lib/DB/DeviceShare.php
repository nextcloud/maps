<?php

declare(strict_types=1);

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

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getToken()
 * @method int getDeviceId()
 * @method int getTimestampFrom()
 * @method int getTimestampTo()
 * @method void setToken(string $token)
 * @method void setDeviceId(int $deviceId)
 * @method void setTimestampFrom(int $timestampFrom)
 * @method void setTimestampTo(int $timestampTo)
 */
class DeviceShare extends Entity {
	public $token;

	public $deviceId;

	public $timestampFrom;

	public $timestampTo;

	public function __construct() {
		$this->addType('token', Types::STRING);
		$this->addType('deviceId', Types::INTEGER);
		$this->addType('timestampFrom', Types::INTEGER);
		$this->addType('timestampTo', Types::INTEGER);
	}
}
