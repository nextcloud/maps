/**
 * ownCloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Sander Brand <brantje@gmail.com>
 * @copyright Sander Brand 2014
 */

(function ($, OC) {

	// initialize map when page ready
	$(document).ready(function(){
		marker = null;
		circle = null;
		firstRun = true;
		
		
		$('#app-navigation').hide();
		
		/*var map = L.map('map').setView([51.505, -0.09], 13);
		L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery © <a href="http://mapbox.com">Mapbox</a>',
			maxZoom: 18
		}).addTo(map);

		*/
		var attribution = '&copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>';

        var mapnik = L.tileLayer(
                'http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'
                , {attribution: attribution}
        )    
		var mapbox = L.tileLayer(
                'https://a.tiles.mapbox.com/v3/liedman.h9ekn0f1/{z}/{x}/{y}.png'
                , {attribution: attribution +' Tiles <a href="https://www.mapbox.com/about/maps/">MapBox</a>' }
        )
        var blackAndWhite = L.tileLayer(
                'http://{s}.www.toolserver.org/tiles/bw-mapnik/{z}/{x}/{y}.png'
                , {attribution: attribution}
        )
		
		var airial = L.tileLayer(
				'http://server.arcgisonline.com/ArcGIS/' 
           + 'rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'
				, {attribution: attribution+' Tiles © Esri', subdomains:"1234"}
        )
		
        var clouds = L.tileLayer('http://{s}.tile.openweathermap.org/map/clouds/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; <a href="http://openweathermap.org">OpenWeatherMap</a>'
            , opacity: 0.5
        })
        var wind = L.tileLayer('http://{s}.tile.openweathermap.org/map/wind/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; <a href="http://openweathermap.org">OpenWeatherMap</a>'
            , opacity: 0.5
        })
        var temperature = L.tileLayer('http://{s}.tile.openweathermap.org/map/temp/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; <a href="http://openweathermap.org">OpenWeatherMap</a>'
            , opacity: 0.5
        })     

		var seamarks = L.tileLayer('http://tiles.openseamap.org/seamark/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; <a href="http://openweathermap.org">OpenSeaMap</a>'
            , opacity: 1.0
        })     

		var none = L.tileLayer('http://{s}.tile.openweathermap.org/map/temp/{z}/{x}/{y}.png', {
            attribution: ''
            , opacity: 0.0
        })

        map = L.map('map', {
            center: new L.LatLng(39.73, -104.99), zoom: 10, layers: [mapbox,none]
        });

        var baseMaps = {
           "MapBox": mapbox, "Mapnik": mapnik, "Black and White": blackAndWhite, "Airial": airial
        };

        var overlayMaps = {
            "-None-": none,
            "Clouds": clouds,
            "Wind": wind,
            "Temperature": temperature,
			"Sea Markers": seamarks
        };

        //var control = L.control.layers(baseMaps, overlayMaps)
        var control = L.control.selectLayers(baseMaps, overlayMaps)
        control.addTo(map);		
		map.locate({setView: false, maxZoom: 16,watch: true,enableHighAccuracy: true});
		map.on('locationfound', onLocationFound);
		//map.addControl( new L.Control.Compass() );
		$('.leaflet-control-layers-overlays').removeProp('multiple');
	})
	function onLocationFound(e) {
		var radius = e.accuracy / 2;
		if(marker != null){
			map.removeLayer(marker);
		}
		if(circle != null){
			map.removeLayer(circle);
		}

		marker = L.marker(e.latlng).addTo(map);
			//.bindPopup("You are within " + radius + " meters from this point").openPopup();
		if(radius < 5000){
			circle = L.circle(e.latlng, radius).addTo(map);
		}

		if(firstRun){
			map.panTo(e.latlng);
			var maxZoom = 16;
			map.setZoom(14);
			firstRun = false;
		}
	}
})(jQuery, OC);