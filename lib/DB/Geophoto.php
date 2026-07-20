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

use OCA\Maps\AppFramework\Db\Attribute\Column;
use OCA\Maps\AppFramework\Db\Attribute\Entity;
use OCA\Maps\AppFramework\Db\Attribute\Id;
use OCP\DB\Types;

#[Entity(name: 'maps_photos')]
class Geophoto {
	#[Id]
	#[Column(name: 'id', type: Types::BIGINT, nullable: false)]
	public ?int $id = null;

	#[Column(name: 'file_id', type: Types::INTEGER, nullable: false)]
	public int $fileId;

	#[Column(name: 'lat', type: Types::FLOAT, nullable: true)]
	public ?float $lat = null;

	#[Column(name: 'lng', type: Types::FLOAT, nullable: true)]
	public ?float $lng = null;

	#[Column(name: 'date_taken', type: Types::DATETIME, nullable: true)]
	public ?\DateTime $dateTaken = null;

	#[Column(name: 'user_id', type: Types::STRING, nullable: false)]
	public string $userId;
}
