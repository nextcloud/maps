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
style('maps', '../node_modules/leaflet.locatecontrol/dist/L.Control.Locate.min');
style('maps', '../node_modules/leaflet-easybutton/src/easy-button');
style('maps', '../node_modules/leaflet.markercluster/dist/MarkerCluster');
style('maps', '../node_modules/leaflet.markercluster/dist/MarkerCluster.Default');
style('maps', '../node_modules/leaflet-routing-machine/dist/leaflet-routing-machine');
style('maps', '../node_modules/leaflet-control-geocoder/dist/Control.Geocoder');
style('maps', '../node_modules/leaflet-mouse-position/src/L.Control.MousePosition');
style('maps', '../node_modules/leaflet-contextmenu/dist/leaflet.contextmenu.min');
style('maps', '../node_modules/leaflet.elevation/dist/Leaflet.Elevation-0.0.2');
style('maps', '../node_modules/mapbox-gl/dist/mapbox-gl');
style('maps', 'fontawesome/css/all.min');
script('viewer', 'viewer');
script('maps', '../node_modules/leaflet/dist/leaflet');
script('maps', '../node_modules/leaflet.markercluster/dist/leaflet.markercluster');
script('maps', '../node_modules/leaflet.featuregroup.subgroup/dist/leaflet.featuregroup.subgroup');
script('maps', '../node_modules/opening_hours/opening_hours');
script('maps', '../node_modules/leaflet.locatecontrol/dist/L.Control.Locate.min');
script('maps', '../node_modules/leaflet-easybutton/src/easy-button');
script('maps', '../node_modules/leaflet-routing-machine/dist/leaflet-routing-machine.min');
script('maps', '../js/external/lrm-graphhopper-1.2.0.min');
script('maps', '../node_modules/leaflet-control-geocoder/dist/Control.Geocoder.min');
script('maps', '../node_modules/leaflet-mouse-position/src/L.Control.MousePosition');
script('maps', '../node_modules/leaflet-contextmenu/dist/leaflet.contextmenu.min');
script('maps', '../node_modules/d3/d3.min');
script('maps', '../node_modules/leaflet.elevation/dist/Leaflet.Elevation-0.0.2.min');
script('maps', '../node_modules/mapbox-gl/dist/mapbox-gl');
script('maps', '../node_modules/mapbox-gl-leaflet/leaflet-mapbox-gl');
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
