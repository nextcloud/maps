<?php



\OCP\Util::addScript('maps', '3rdparty/leaflet/leaflet');
\OCP\Util::addScript('maps', '3rdparty/jstorage/jstorage');
\OCP\Util::addScript('maps', '3rdparty/overpass/OverPassLayer');
\OCP\Util::addScript('maps', '3rdparty/leaflet/plugins/leaflet-routing-machine.min');

\OCP\Util::addScript('maps', '3rdparty/leaflet/lib/Control.Geocoder');
\OCP\Util::addScript('maps', '3rdparty/leaflet/lib/leaflet.iconlabel');

\OCP\Util::addScript('maps', '3rdparty/leaflet/plugins/leaflet-hash');
\OCP\Util::addScript('maps', '3rdparty/leaflet/plugins/leaflet.polylineDecorator');
\OCP\Util::addScript('maps', '3rdparty/leaflet/plugins/leaflet.active-layers.min');
\OCP\Util::addScript('maps', '3rdparty/leaflet/plugins/leaflet.select-layers.min');
\OCP\Util::addScript('maps', '3rdparty/leaflet/plugins/leaflet-compass.min');
\OCP\Util::addScript('maps', '3rdparty/leaflet-gps/leaflet-gps.min');
\OCP\Util::addStyle('maps', 'leaflet-gps/css/leaflet-gps.min');

\OCP\Util::addStyle('maps', 'awsome-markers/leaflet.awesome-markers');
\OCP\Util::addScript('maps', '3rdparty/leaflet/plugins/leaflet.awesome-markers.min');
\OCP\Util::addScript('maps', '3rdparty/leaflet/plugins/Leaflet.MakiMarkers');
\OCP\Util::addScript('maps', 'script');

\OCP\Util::addStyle('maps', 'leaflet/leaflet');
\OCP\Util::addStyle('maps', 'leaflet/leaflet-compass.min');

\OCP\Util::addStyle('maps', 'leaflet/leaflet-routing-machine');
\OCP\Util::addStyle('maps', 'style');
\OCP\Util::addStyle('maps', 'font-awesome.min');


?>
 
<div id="app">
	<div id="app-navigation">
		<div id="searchContainer">
				
		</div>	<br />
		<ul class="with-icon">
			<li>
				<a class='contactLayer icon-contacts-dark' data-layer="contacts">Contacts</a>
			</li>
			<li>
				<a class='main-cat-layer icon-info toggle-children' data-layer="amenity">Amenity</a>
				<ul id="amenity-items" class="hidden"></ul>
			</li>
			<li>
				<a class='main-cat-layer icon-toggle toggle-children' data-layer="tourism">Tourism</a>
				<ul id="tourism-items" class="hidden"></ul>
			</li>
			<li>
				<a class='main-cat-layer icon-home toggle-children' data-layer="shop">Shops</a>
				<ul id="shop-items" class="hidden"></ul>
			</li>
			<li>
				<a class="toggle-children">My devices</a>
				<ul id="deviceList" class="hidden">
				<?php foreach($_['devices'] as $entry){ ?>
 					 <li><a class="device" data-deviceId="<?php p($entry['id']); ?>"><?php p($entry['name']); ?><span class="keepDeviceCentered"></span></span></a></li>
				<?php
				}
				?>
				</ul>
			</li>
		</ul>
		
		<div id="loadingContacts" style="display:none">
			<div id="progressBar"><div></div></div>
			<div id="cCounter"></div>
		</div>
		<div id="app-settings">
			<div id="app-settings-header">
				<button class="settings-button" data-apps-slide-toggle="#app-settings-content"></button>
			</div>
			<div id="app-settings-content">
					<a id="tracking-settings">Location tracking settings</a>
			</div>
		</div>
	  
  	</div>
	<div id="app-content">
		<div id="map">
		</div>
	</div>
</div>

<div id="trackingSettingsPopup" style="display: none;">
	<h2>Add an new device</h2>
	<form id="addtracking">Name: <input type="text" name="deviceName"><button class="" type="button">+</button></form>
	<table id="trackingDevices">
		<thead><th>Name</th><th>Hash</th></thead>
		<tbody>
			
		</tbody>
	</table>
</div>
