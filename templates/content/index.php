<?php
/**
 * Nextcloud - Maps
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 * @author Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 * @copyright Vinzenz Rosenkranz 2017
 */

style('maps', '../node_modules/leaflet/dist/leaflet');
script('maps', '../node_modules/leaflet/dist/leaflet');
script('maps', '../node_modules/opening_hours/opening_hours');
?>
<div id="search">
    <form id="search-form">
        <input type="text" placehoder="Search..." id="search-term" />
        <button type="button" id="search-submit">Search!</button>
    </form>
</div>
<div id="map"></div>
