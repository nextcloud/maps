import * as network from './network'
/* import {
	// showSuccess,
	// showError,
	// showWarning
} from '@nextcloud/dialogs' */

const optionsController = {
	nbRouters: 0,
	optionValues: {},
	enabledFavoriteCategories: [],
	disabledContactGroups: [],
	enabledTracks: [],
	enabledDevices: [],
	enabledDeviceLines: [],
	saveOptionValues(optionValues) {
		network.saveOptionValues(optionValues)
	},

	restoreOptions(successCB) {
		network.getOptionValues()
			.then((response) => {
				this.handleOptionValues(response.data)
				successCB()
			})
	},

	handleOptionValues(response) {
		console.debug(this)
		const optionsValues = response.values
		this.optionValues = optionsValues

		/* // check if install scan was done
		if (optionsValues.hasOwnProperty('installScanDone') && optionsValues.installScanDone === 'no') {
			showWarning(
				t('maps', 'Media scan was not done yet. Wait a few minutes/hours and reload this page to see your photos/tracks.')
			)
		}

		// set tilelayer before showing photo layer because it needs a max zoom value
		if (optionsValues.hasOwnProperty('displaySlider') && optionsValues.displaySlider === 'true') {
			$('#timeRangeSlider').show();
			$('#display-slider').prop('checked', true);
		}

		//detect Webgl
		var canvas = document.createElement('canvas');
		var experimental = false;
		var gl;

		try { gl = canvas.getContext("webgl"); }
		catch (x) { gl = null; }

		if (gl == null) {
			try { gl = canvas.getContext("experimental-webgl"); experimental = true; }
			catch (x) { gl = null; }
		}

		if (optionsValues.hasOwnProperty('mapboxAPIKEY') && optionsValues.mapboxAPIKEY !== '' && gl != null) {
			// change "button" layers
			delete mapController.baseLayers['OpenStreetMap'];
			delete mapController.baseLayers['ESRI Aerial'];
			mapController.defaultStreetLayer = 'Mapbox vector streets';
			mapController.defaultSatelliteLayer = 'Mapbox satellite';
			// remove dark, esri topo and openTopoMap
			// Mapbox outdoors and dark are good enough
			mapController.controlLayers.removeLayer(mapController.baseLayers['ESRI Topo']);
			mapController.controlLayers.removeLayer(mapController.baseLayers['OpenTopoMap']);
			mapController.controlLayers.removeLayer(mapController.baseLayers['Dark']);
			delete mapController.baseLayers['ESRI Topo'];
			delete mapController.baseLayers['OpenTopoMap'];
			delete mapController.baseLayers['Dark'];

			// add mapbox-gl tile servers
			var attrib = '<a href="https://www.mapbox.com/about/maps/">© Mapbox</a> '+
				'<a href="https://www.openstreetmap.org/copyright">© OpenStreetMap</a> '+
				'<a href="https://www.mapbox.com/map-feedback/">'+t('maps', 'Improve this map')+'</a>';
			var attribSat = attrib + '<a href="https://www.digitalglobe.com/">© DigitalGlobe</a>'

			mapController.baseLayers['Mapbox vector streets'] = L.mapboxGL({
				accessToken: optionsValues.mapboxAPIKEY,
				style: 'mapbox://styles/mapbox/streets-v8',
				minZoom: 1,
				maxZoom: 22,
				attribution: attrib
			});
			//mapController.controlLayers.addBaseLayer(mapController.baseLayers['Mapbox vector streets'], 'Mapbox vector streets');

			mapController.baseLayers['Topographic'] = L.mapboxGL({
				accessToken: optionsValues.mapboxAPIKEY,
				style: 'mapbox://styles/mapbox/outdoors-v11',
				minZoom: 1,
				maxZoom: 22,
				attribution: attrib
			});
			mapController.controlLayers.addBaseLayer(mapController.baseLayers['Topographic'], 'Topographic');

			mapController.baseLayers['Dark'] = L.mapboxGL({
				accessToken: optionsValues.mapboxAPIKEY,
				style: 'mapbox://styles/mapbox/dark-v8',
				minZoom: 1,
				maxZoom: 22,
				attribution: attrib
			});
			mapController.controlLayers.addBaseLayer(mapController.baseLayers['Dark'], 'Dark');

			mapController.baseLayers['Mapbox satellite'] = L.mapboxGL({
				accessToken: optionsValues.mapboxAPIKEY,
				style: 'mapbox://styles/mapbox/satellite-streets-v9',
				minZoom: 1,
				maxZoom: 22,
				attribution: attribSat
			});
			//mapController.controlLayers.addBaseLayer(mapController.baseLayers['Mapbox satellite'], 'Mapbox satellite');
		}
		if (optionsValues.hasOwnProperty('tileLayer')) {
			mapController.changeTileLayer(optionsValues.tileLayer);
		}
		else {
			mapController.changeTileLayer(mapController.defaultStreetLayer);
		}
		if (optionsValues.hasOwnProperty('mapBounds')) {
			var nsew = optionsValues.mapBounds.split(';');
			if (nsew.length === 4) {
				var n = parseFloat(nsew[0]);
				var s = parseFloat(nsew[1]);
				var e = parseFloat(nsew[2]);
				var w = parseFloat(nsew[3]);
				if (n && s && e && w) {
					mapController.map.fitBounds([
						[n, e],
						[s, w]
					]);
				}
			}
		}
		if (!optionsValues.hasOwnProperty('photosLayer') || optionsValues.photosLayer === 'true') {
			photosController.toggleLayer();
		}
		if (!optionsValues.hasOwnProperty('contactGroupListShow') || optionsValues.contactGroupListShow === 'true') {
			contactsController.toggleGroupList();
		}
		if (optionsValues.hasOwnProperty('disabledContactGroups')
			&& optionsValues.disabledContactGroups
			&& optionsValues.disabledContactGroups !== '')
		{
			that.disabledContactGroups = optionsValues.disabledContactGroups.split('|');
		}
		if (!optionsValues.hasOwnProperty('contactLayer') || optionsValues.contactLayer === 'true') {
			contactsController.toggleLayer();
		}
		if (optionsValues.hasOwnProperty('locControlEnabled') && optionsValues.locControlEnabled === 'true') {
			mapController.locControl.start();
		}
		if (!optionsValues.hasOwnProperty('favoriteCategoryListShow') || optionsValues.favoriteCategoryListShow === 'true') {
			favoritesController.toggleCategoryList();
		}
		if (optionsValues.hasOwnProperty('enabledFavoriteCategories')
			&& optionsValues.enabledFavoriteCategories
			&& optionsValues.enabledFavoriteCategories !== '')
		{
			that.enabledFavoriteCategories = optionsValues.enabledFavoriteCategories.split('|');
		}
		if (!optionsValues.hasOwnProperty('favoritesEnabled') || optionsValues.favoritesEnabled === 'true') {
			favoritesController.toggleFavorites();
		}
		if (!optionsValues.hasOwnProperty('trackListShow') || optionsValues.trackListShow === 'true') {
			tracksController.toggleTrackList();
		}
		if (optionsValues.hasOwnProperty('enabledTracks')
			&& optionsValues.enabledTracks
			&& optionsValues.enabledTracks !== '')
		{
			that.enabledTracks = optionsValues.enabledTracks.split('|').map(function (x) {
				return parseInt(x);
			});
		}
		if (optionsValues.hasOwnProperty('tracksSortOrder') && optionsValues.tracksSortOrder !== '') {
			tracksController.sortOrder = optionsValues.tracksSortOrder;
		}
		else {
			tracksController.sortOrder = 'date';
		}
		if (getUrlParameter('track') || !optionsValues.hasOwnProperty('tracksEnabled') || optionsValues.tracksEnabled === 'true') {
			tracksController.toggleTracks();
		}
		if (!optionsValues.hasOwnProperty('deviceListShow') || optionsValues.deviceListShow === 'true') {
			devicesController.toggleDeviceList();
		}
		if (optionsValues.hasOwnProperty('enabledDevices')
			&& optionsValues.enabledDevices
			&& optionsValues.enabledDevices !== '')
		{
			that.enabledDevices = optionsValues.enabledDevices.split('|').map(function (x) {
				return parseInt(x);
			});
		}
		if (optionsValues.hasOwnProperty('enabledDeviceLines')
			&& optionsValues.enabledDeviceLines
			&& optionsValues.enabledDeviceLines !== '')
		{
			that.enabledDeviceLines = optionsValues.enabledDeviceLines.split('|').map(function (x) {
				return parseInt(x);
			});
		}
		if (!optionsValues.hasOwnProperty('devicesEnabled') || optionsValues.devicesEnabled === 'true') {
			devicesController.toggleDevices();
		}
		if (optionsValues.hasOwnProperty('trackMe') && optionsValues.trackMe === 'true') {
			$('#track-me').prop('checked', true);
			devicesController.launchTrackLoop();
		}

		// save tile layer when changed
		// do it after restore, otherwise restoring triggers save
		mapController.map.on('baselayerchange ', function(e) {
			optionsController.saveOptionValues({tileLayer: e.name});
			// make sure map is clean
			for (var ol in mapController.baseOverlays) {
				mapController.map.removeLayer(mapController.baseOverlays[ol]);
			}
			for (var tl in mapController.baseLayers) {
				if (tl !== e.name) {
					mapController.map.removeLayer(mapController.baseLayers[tl]);
				}
			}
			if (e.name === 'Watercolor') {
				mapController.map.addLayer(mapController.baseOverlays['Roads and labels']);
			}
			mapController.layerChanged(e.name);
		});

		// routing
		that.nbRouters = 0;
		if (optionsValues.hasOwnProperty('osrmCarURL') && optionsValues.osrmCarURL !== '') {
			that.nbRouters++;
		}
		if (optionsValues.hasOwnProperty('osrmBikeURL') && optionsValues.osrmBikeURL !== '') {
			that.nbRouters++;
		}
		if (optionsValues.hasOwnProperty('osrmFootURL') && optionsValues.osrmFootURL !== '') {
			that.nbRouters++;
		}
		if (optionsValues.hasOwnProperty('mapboxAPIKEY') && optionsValues.mapboxAPIKEY !== '') {
			that.nbRouters++;
		}
		if ((optionsValues.hasOwnProperty('graphhopperURL') && optionsValues.graphhopperURL !== '') ||
			(optionsValues.hasOwnProperty('graphhopperAPIKEY') && optionsValues.graphhopperAPIKEY !== '') ){
			that.nbRouters++;
		}
		if (that.nbRouters === 0 && !OC.isUserAdmin()) {
			// disable routing and hide it to the user
			// search bar
			$('#route-submit').hide();
			$('#search-submit').css('right', '10px');
			// context menu: remove routing related items
			mapController.map.contextmenu.removeItem(mapController.map.contextmenu._items[mapController.map.contextmenu._items.length-1].el);
			mapController.map.contextmenu.removeItem(mapController.map.contextmenu._items[mapController.map.contextmenu._items.length-1].el);
			mapController.map.contextmenu.removeItem(mapController.map.contextmenu._items[mapController.map.contextmenu._items.length-1].el);
			mapController.map.contextmenu.removeItem(mapController.map.contextmenu._items[mapController.map.contextmenu._items.length-1].el);
			// and we don't init routingController
		}
		else {
			routingController.initRoutingControl(mapController.map, optionsValues);
		} */
	},
}

export default optionsController
