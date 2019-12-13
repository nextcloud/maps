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

use OCP\AppFramework\Db\Entity;

class FavoriteShare extends Entity {
  public $owner;
  public $token;
  public $category;
  public $allowEdits = false; // TODO

  public function __construct() {
    $this->addType('owner', 'string');
    $this->addType('token', 'string');
    $this->addType('category', 'string');
    $this->addType('allowEdits', 'boolean');
  }
}
