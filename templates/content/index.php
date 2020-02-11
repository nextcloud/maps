<?php
/**
 * Nextcloud - Maps
 *
 * @author Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 * @author Gary Kim <gary@garykim.dev
 *
 * @copyright Vinzenz Rosenkranz 2017
 * @copyright Copyright (c) 2020, Gary Kim <gary@garykim.dev>
 *
 * @license GNU AGPL version 3 or any later version
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
 */

script('viewer', 'viewer');
style('maps', 'style');
?>
<div id="search">
    <form id="search-form">
        <input type="text" placeholder="<?php p($l->t('Search…')); ?>" id="search-term" />
        <input type="submit" id="search-submit" value="" class="icon-search">
        <button id="route-submit" class=""><i class="fas fa-route" aria-hidden="true"></i></button>
    </form>
</div>
<div id="map"></div>
<div id="timeRangeSlider">
</div>
<?php if (!empty($_['geourl'])): ?>
	<input type="hidden" value=<?php p($_['geourl']);?> id="geourl">
<?php endif; ?>
