<?php
/**
 * @copyright Copyright (c) 2024 Arne Hamann <git@arne.email>
 *
 * @author Arne Hamann <git@arne.email>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Maps\Helper;

/**
 * function remove_utf8_bom
 *
 * @param string $text
 * @return string
 */
function remove_utf8_bom(string $text): string {
	$bom = pack('H*', 'EFBBBF');
	$text = preg_replace("/^$bom/", '', $text);
	return $text;
}
