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
use OCA\Maps\AppFramework\Db\Attribute\Id;
use OCP\DB\Types;

#[Entity(name: 'maps_favorite_shares')]
class FavoriteShare {
	#[Id]
	#[Column(name: 'id', type: Types::INTEGER)]
	public int $id;

	#[Column(name: 'owner', type: Types::STRING, nullable: false, length: 64)]
	public string $owner;

	#[Column(name: 'token', type: Types::STRING, nullable: false, length: 64)]
	public string $token;

	#[Column(name: 'category', type: Types::STRING, nullable: false, length: 64)]
	public string $category;
}
