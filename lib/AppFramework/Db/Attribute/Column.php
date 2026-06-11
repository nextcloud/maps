<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH
 * SPDX-FileContributor: Carl Schwan
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Maps\AppFramework\Db\Attribute;

use Attribute;
use OCP\AppFramework\Attribute\Consumable;

/**
 * Attribute for mapping a property in an entity to a database column.
 *
 * ```php
 * #[Entity]
 * #[Table(name: 'my_entity']
 * final class MyEntity {
 *     #[Column(name: 'my_column', type: Types::String, default: '')]
 *     public string $myColumn = '';
 * }
 * ```
 *
 * @since 35.0.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
#[Consumable(since: '35.0.0')]
final readonly class Column {
	public function __construct(
		public string $name,
		public string $type,
		public ?int $length = null,
		public bool $nullable = false,
		public mixed $default = null,
	) {
	}
}
