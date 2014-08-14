<?php
\OCP\Util::addScript('maps', '3rdparty/leaflet/leaflet');
\OCP\Util::addScript('maps', '3rdparty/leaflet/plugins/leaflet-routing-machine.min');

\OCP\Util::addScript('maps', '3rdparty/leaflet/lib/Control.Geocoder');
\OCP\Util::addScript('maps', '3rdparty/leaflet/lib/leaflet.iconlabel');

\OCP\Util::addScript('maps', '3rdparty/leaflet/plugins/leaflet.active-layers.min');
\OCP\Util::addScript('maps', '3rdparty/leaflet/plugins/leaflet.select-layers.min');
\OCP\Util::addScript('maps', '3rdparty/leaflet/plugins/leaflet-compass.min');



\OCP\Util::addScript('maps', 'script');

\OCP\Util::addStyle('maps', 'leaflet/leaflet');
\OCP\Util::addStyle('maps', 'leaflet/leaflet-compass.min');

\OCP\Util::addStyle('maps', 'leaflet/leaflet-routing-machine');
\OCP\Util::addStyle('maps', 'style');


?>
 
<div id="app">
	<div id="app-navigation">
		<div id="searchContainer">
				
		</div>	
		<div id="app-settings">
			<div id="app-settings-header">
				<button class="settings-button" data-apps-slide-toggle="#app-settings-content"></button>
			</div>
			<div id="app-settings-content">
	
			</div>
		</div>
  	</div>
	<div id="app-content">
		<div id="map">
		</div>
	</div>
</div>