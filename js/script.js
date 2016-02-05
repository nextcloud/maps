/**
 * ownCloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Sander Brand <brantje@gmail.com>
 * @copyright Sander Brand 2014
 */
/** Testing
 */

Array.prototype.clean = function(deleteValue) {
	for (var i = 0; i < this.length; i++) {
		if (this[i] == deleteValue) {
			this.splice(i, 1);
			i--;
		}
	}
	return this;
};
Array.prototype.unique = function() {
	var unique = [];
	for (var i = 0; i < this.length; i++) {
		if (unique.indexOf(this[i]) == -1) {
			unique.push(this[i]);
		}
	}
	return unique;
};

function debounce(func, wait, immediate) {
	var timeout;
	return function() {
		var context = this;
		var args = arguments;
		var later = function() {
			timeout = null;
			if(!immediate) func.apply(context, args);
		}
		var callNow = immediate && !timeout;
		clearTimeout(timeout);
		timeout = setTimeout(later, wait);
		if(callNow) func.apply(context, args);
	}
}

(function($, OC) {

	var normalMarkerImg = OC.filePath('maps', 'css', 'leaflet/images/marker-icon.png');
	var normalMarkerIcon = L.icon({
		iconUrl : normalMarkerImg,
		iconSize : [31, 37],
		iconAnchor : [15, 37],
		popupAnchor : [0, -37]
	});

	var favMarkerImg = OC.filePath('maps', 'img', 'icons/favMarker.png');
	var favMarkerIcon = L.icon({
		iconUrl : favMarkerImg,
		iconSize : [31, 37],
		iconAnchor : [15, 37],
		popupAnchor : [0, -37]
	});

	function addGeocodeMarker(latlng) {
		Maps.droppedPin = new L.marker(latlng);
		geocoder.reverse(latlng, 67108864, function(results) {
			var result = results[0];
			var popupHtml = '';
			var properties = result.properties;
			var address = properties;
			if(!geocodeSearch.apiKeySet()) address = properties.address; //address is stored depending on which geocoder is used
			popupHtml = Maps.getPoiPopupHTML(address).innerHTML;
			toolKit.addMarker(Maps.droppedPin, popupHtml, true);

		})
	}

	$.fn.clickToggle = function(func1, func2) {
		var funcs = [func1, func2];
		this.data('toggleclicked', 0);
		this.click(function() {
			var data = $(this).data();
			var tc = data.toggleclicked;
			$.proxy(funcs[tc], this)();
			data.toggleclicked = (tc + 1) % 2;
		});
		return this;
	};

	// initialize map when page ready
	$(document).ready(function() {
		marker = null;
		circle = null;
		firstRun = true;

		var attribution = '&copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>';

		var mapQuest = L.tileLayer('http://otile{s}.mqcdn.com/tiles/1.0.0/osm/{z}/{x}/{y}.png', {
			attribution : attribution,
			subdomains : "1234"
		})
		/*	var mapbox = L.tileLayer('https://a.tiles.mapbox.com/v3/liedman.h9ekn0f1/{z}/{x}/{y}.png', {
		 attribution : attribution + ' Tiles <a href="https://www.mapbox.com/about/maps/">MapBox</a>'
		 })
		 var blackAndWhite = L.tileLayer('http://{s}.www.toolserver.org/tiles/bw-mapnik/{z}/{x}/{y}.png', {
		 attribution : attribution
		 })

		 var airial = L.tileLayer('http://server.arcgisonline.com/ArcGIS/' + 'rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
		 attribution : attribution + ' Tiles © Esri',
		 subdomains : "1234"
		 })*/

		/*var clouds = L.tileLayer('http://{s}.tile.openweathermap.org/map/clouds/{z}/{x}/{y}.png', {
		 attribution : 'Map data &copy; <a href="http://openweathermap.org">OpenWeatherMap</a>',
		 opacity : 0.5
		 })
		 var wind = L.tileLayer('http://{s}.tile.openweathermap.org/map/wind/{z}/{x}/{y}.png', {
		 attribution : 'Map data &copy; <a href="http://openweathermap.org">OpenWeatherMap</a>',
		 opacity : 0.5
		 })
		 var temperature = L.tileLayer('http://{s}.tile.openweathermap.org/map/temp/{z}/{x}/{y}.png', {
		 attribution : 'Map data &copy; <a href="http://openweathermap.org">OpenWeatherMap</a>',
		 opacity : 0.5
		 })

		 var seamarks = L.tileLayer('http://tiles.openseamap.org/seamark/{z}/{x}/{y}.png', {
		 attribution : 'Map data &copy; <a href="http://openweathermap.org">OpenSeaMap</a>',
		 opacity : 1.0
		 })

		 var none = L.tileLayer('http://{s}.tile.openweathermap.org/map/temp/{z}/{x}/{y}.png', {
		 attribution : '',
		 opacity : 0.0
		 })*/

		var oldPosition = $.jStorage.get('location', {
			lat : 21.303210151521565,
			lng : 6.15234375
		});
		var oldZoom = $.jStorage.get('zoom', 3);

		map = L.map('map', {
			center : new L.LatLng(oldPosition.lat, oldPosition.lng),
			zoom : oldZoom,
			zoomControl : false,
			layers : [mapQuest]
		}),

		map.options.minZoom = 3;
		var hash = new L.Hash(map);
		/*var baseMaps = {
		 "MapBox" : mapbox,
		 "Mapnik" : mapnik,
		 "Black and White" : blackAndWhite,
		 "Airial" : airial
		 };*/

		//map.addControl(new L.Control.Compass());
		/*map.addControl(new L.Control.Zoom({
			position: 'bottomright'
		}));*/
		map.addControl(new L.Control.Gps({
			minZoom : 14,
			autoActive: 1,
			style : {
				radius : 16, //marker circle style
				weight : 3,
				color : '#0A00FF',
				fill : true
			},
			position : 'topleft',
		}));
		$('.leaflet-control-layers-overlays').removeProp('multiple');
		map.on('popupopen', function(e) {
			currentMarker = e.popup._source;
		});
		routing = L.Routing.control({
			waypoints : [],
			//geocoder : geocoder,
			routeWhileDragging: true,
			fitSelectedRoutes: true,
			show: false
		}).addTo(map);

		routing.getPlan().on('waypointschanged', function(e) {
			geocodeSearch.waypointsChanged(e);
		})

		apiKey = '';

		geocodeSearch.addGeocoder();

		// properly style as input field
		//$('#searchContainer').find('input').attr('type', 'text');

		map.on('zoomend', function(e) {
			var zoom = map.getZoom();
			if(!$.jStorage.get('pois') || zoom < 9) return false; //only show POIs on reasonable levels
			Maps.displayPoiIcons(zoom);
		})

		$.post(OC.generateUrl('/apps/maps/api/1.0/apikey/getKey'), null, function(data){
			if(data.id != null && data.apiKey != null) {
				apiKey = data.apiKey;
				document.getElementById('apiKey').value = apiKey;
				geocoder = L.Control.Geocoder.mapzen(apiKey);
			} else {
				geocoder = L.Control.Geocoder.nominatim();
			}
		});

		map.on('mousedown', function(e) {
			Maps.mouseDowntime = new Date().getTime();
		});

		map.on('mouseup', function(e) {
			if (e.originalEvent.target.className !== 'leaflet-tile leaflet-tile-loaded' && e.originalEvent.target.nodeName != 'svg')
				return;

			var curTime = new Date().getTime();
			if (Maps.droppedPin) {
				map.removeLayer(Maps.droppedPin);
				Maps.droppedPin = false;
			}
			if (/*(curTime - Maps.mouseDowntime) > 200 && */Maps.dragging === false) {//200 = 2 seconds
				addGeocodeMarker(e.latlng);
			}
		});

		map.on("dragstart", function() {
			Maps.dragging = true;
		})
		map.on("dragend zoomend", function(e) {
			Maps.saveCurrentLocation(e);
			Maps.dragging = false;
		})

		$(document).on('click', '.toggle-children', function(e) {
			var subCat = $(this).parent().find('ul');
			if (subCat.is(":visible")) {
				subCat.slideUp();
			} else {
				subCat.removeClass('hidden');
				subCat.slideDown();
			}
		})

		function convertLatLon(latD, lonD, latDir, lonDir){
			var lon = lonD[0] + lonD[1]/60 + lonD[2]/(60*60);
			var lat = latD[0] + latD[1]/60 + latD[2]/(60*60);
			if (latDir == "S") {
				lat = lat * -1;
			}
			if(lonDir == "W"){
				lon = lon * -1;
			}
			return {
				lat: lat,
				lon: lon
			};
		}
		$('.photoLayer').clickToggle(function() {
			OC.dialogs.filepicker("Select your photo", function addJpegFile(path){
				for(i=0; i<path.length; i++){
					var p = path[i];
					if(p.indexOf('/') === 0) p = p.substr(1);
					var separator = p.lastIndexOf('/');
					var dir = p.substr(0, separator);
					var file = p.substr(separator + 1);
					var picUrl = OC.generateUrl('apps/files/ajax/download.php?dir={dir}&files={file}', {
						dir: dir,
						file: file
					});
					new ImageInfo(
						picUrl,
						(function (element){
							return function (imageinfo){
								var exif = imageinfo.getAllFields().exif;
								var latD = exif.GPSLatitude;
								var lonD = exif.GPSLongitude;
								var latDir = exif.GPSLatitudeRef;
								var lonDir = exif.GPSLongitudeRef;
								var latlon = convertLatLon(latD, lonD, latDir, lonDir);
								var photoIcon = L.icon({
									iconUrl: picUrl,
									iconSize : [42, 49],
									iconAnchor : [21, 49],
									popupAnchor : [0, -49],
									className : 'photo-marker'
								});

								var markerHTML = '<h2>' + file + "</h2>";
								/*markerHTML += '<br />Latitude: ' + latlon.lat + " " + latDir;
								markerHTML += '<br />Longitude: ' + latlon.lon + " " + lonDir;
								markerHTML += '<br />Altitude: ' + exif.GPSAltitude + "m";*/
								var marker = L.marker([latlon.lat, latlon.lon], {
									icon : photoIcon
								});
								toolKit.addMarker(marker, markerHTML);
							};
						})(this)
					).readFileData();
				}
			}, true, ["image/jpeg", "image/png", "image/gif"], true);
		}, function() {
			//TODO
		});

		/* Favorites layer: Show by default, remember visibility */
		$('.favoriteLayer').click(function() {
			if($.jStorage.get('favorites')) {
				favorites.hide();
				$.jStorage.set('favorites', false);
				$('#favoriteMenu').removeClass('active').addClass('icon-star').removeClass('icon-starred');
			} else {
				favorites.show();
				$.jStorage.set('favorites', true);
				$('#favoriteMenu').addClass('active').addClass('icon-starred').removeClass('icon-star');
			}
		});
		if($.jStorage.get('favorites') === null) {
			favorites.show();
			$.jStorage.set('favorites', true);
			$('#favoriteMenu').addClass('active').addClass('icon-starred').removeClass('icon-star');
		}
		if($.jStorage.get('favorites')) {
			favorites.show();
			$('#favoriteMenu').addClass('active').addClass('icon-starred').removeClass('icon-star');
		} else {
			favorites.hide();
		}

		/* POI layer: Show by default, remember visibility */
		$('.poiLayer').click(function() {
			if($.jStorage.get('pois')) {
				Maps.hidePoiIcons();
				$.jStorage.set('pois', false);
				//$('#poiMenu').removeClass('active').addClass('icon-star').removeClass('icon-starred');
			} else {
				Maps.displayPoiIcons(map.getZoom());
				$.jStorage.set('pois', true);
				//$('#poiMenu').addClass('active').addClass('icon-starred').removeClass('icon-star');
			}
		});
		if($.jStorage.get('pois') === null) {
			Maps.displayPoiIcons(map.getZoom());
			$.jStorage.set('pois', true);
			//$('#poiMenu').addClass('active').addClass('icon-starred').removeClass('icon-star');
		}
		if($.jStorage.get('pois')) {
			Maps.displayPoiIcons(map.getZoom());
			//$('#poiMenu').addClass('active').addClass('icon-starred').removeClass('icon-star');
		} else {
			Maps.hidePoiIcons();
		}


		$('.contactLayer').clickToggle(function() {
			Maps.loadAdressBooks()
		}, function() {
			favorites.hide()
		});
		$(document).on('click', '.subLayer', function() {
			var layerGroup = $(this).attr('data-layerGroup');
			var layerValue = $(this).attr('data-layerValue');
			var isVisible = $(this).find('i').length;

			if (isVisible == 1) {
				$('.' + layerValue).css({
					'visibility' : 'hidden'
				})
				$(this).find('i').remove();
				Maps.updateLayers(false, false);
			} else {
				$('.' + layerValue).css({
					'visibility' : 'visible'
				})
				$(this).append('<i class="icon-toggle fright micon activeLayer"></i>');
				//if($('.'+layerValue).length==0){
				Maps.updateLayers(layerGroup, layerValue);
				/*} else{
				 Maps.updateLayers(false,false);
				 }*/

			}
		});

		$(document).on('click', '.device', function() {
			var isVisible = $(this).find('i').length;
			var dId = $(this).attr('data-deviceId')
			if (isVisible == 1) {
				$(this).find('i').remove();
				Maps.clearDevicePositions();
				Maps.clearDevicePosistionHistory(dId);
				var index = Maps.activeDevices.indexOf(dId);
				Maps.activeDevices.splice(index, 1);
				Maps.loadDevicesLastPosition();
			} else {
				Maps.activeDevices.push(dId);
				Maps.loadDevicesLastPosition();
				$(this).append('<i class="icon-toggle fright micon"></i>');
			}
		});

		$(document).on('click', '.keepDeviceCentered', function(e) {
			var isVisible = $(this).parent().find('i').length;
			var dId = $(this).parent().attr('data-deviceId')
			e.stopPropagation()
			if ($(this).hasClass('tracOn')) {
				$(this).removeClass('tracOn');
				Maps.traceDevice = null;
			} else {
				$('.keepDeviceCentered').removeClass('tracOn');
				$(this).addClass('tracOn');
				if (!isVisible) {
					Maps.activeDevices.push(dId);
					Maps.traceDevice = dId;
					Maps.loadDevicesLastPosition();
					$(this).parent().append('<i class="icon-toggle fright micon"></i>');
				} else {
					Maps.traceDevice = dId;
					Maps.loadDevicesLastPosition();
				}
			}

		});

		/**
		 * Setup datepickers
		 */
		$('.datetime').datetimepicker({
			dateFormat : 'dd-mm-yy',
			minDate : -900
		});

		$(document).on('click', '#setApiKey', function(e) {
			var value = document.getElementById('apiKey').value;
			if(value.length == 0) return;
			var formData = {
				key : value
			};
			$.post(OC.generateUrl('/apps/maps/api/1.0/apikey/addKey'), formData, function(data){
			});
		});

		$(document).on('click', '.deviceHistory', function(e) {
			var isVisible = $(this).parent().find('i').length;
			var dId = $(this).parent().attr('data-deviceId');
			var _this = this;
			e.stopPropagation();
			if (!isVisible) {
				$(".datetime").datepicker("disable");
				$('#showHistoryPopup').dialog({
					open : function() {
						$(".datetime").datepicker("enable");
						var currentDate = new Date();
						var month = ((currentDate.getMonth() * 1 + 1) < 10) ? '0' + (currentDate.getMonth() * 1 + 1) : (currentDate.getMonth() * 1 + 1);
						$('#deviceHistory [name="startDate"]').val(currentDate.getDate() + '-' + month + '-' + currentDate.getFullYear() + ' 00:00');
					},
					buttons : {
						"Cancel" : function() {
							$(this).dialog('destroy');
						},
						"Ok" : function() {
							var startDate = $('#deviceHistory [name="startDate"]').val();
							var endDate = $('#deviceHistory [name="endDate"]').val();
							var keepCenter = $('#deviceHistory [name="keepCenter"]').is(':checked');
							Maps.loadDevicePosistionHistory(dId, keepCenter, startDate, endDate);
							$(_this).parent().append('<i class="icon-toggle fright micon"></i>');
							$(this).dialog('destroy');
						}
					}
				});
			} else {
				Maps.clearDevicePosistionHistory(dId);
				$(this).parent().find('i').remove();
			}

		});

		/**
		 * Custom search function
		 */
		/*    searchItems = []*/
		searchTimeout = 0;

		/**
		 * setDestination on click
		 */
		map.locate({
			setView : false,
			watch : false
		})
		map.on('locationfound', function doRouteCalc(e) {
			currentlocation = [e.latitude, e.longitude];

		})
		$(document).on('click', '.setDestination', function() {

			var latlng = $(this).attr('data-latlng');
			var end = latlng.split(',');
			end[0] = end[0] * 1;
			end[1] = end[1] * 1;
			//map.removeLayer(routing);

			routing.setWaypoints([
				L.Routing.waypoint(L.latLng(currentlocation[0], currentlocation[1]), ""),
				L.Routing.waypoint(L.latLng(end[0], end[1]), "")
			]);

			map.closePopup();
		});
		/**
		 * Clear route
		 */
		$(document).on('click', '.clearroute', function() {
			routing.setWaypoints([]);
		});

	});
	// End document ready
	function onLocationFound(e) {
		var radius = e.accuracy / 2;
		if (marker != null) {
			map.removeLayer(marker);
		}
		if (circle != null) {
			map.removeLayer(circle);
		}

		marker = L.marker(e.latlng).addTo(map);
		//.bindPopup("You are within " + radius + " meters from this point").openPopup();
		if (radius < 5000) {
			circle = L.circle(e.latlng, radius).addTo(map);
		}

		if (firstRun) {
			map.panTo(e.latlng);
			var maxZoom = 16;
			map.setZoom(14);
			firstRun = false;
		}
	}

	geocodeSearch = {
		results : [],
		waypoints : [],
		markers : [],
		apiKeySet : function() {
			return apiKey != null && apiKey.length > 0;
		},
		addGeocoder : function() {
			var geocoderInputs = document.getElementsByClassName('geocoder');
			var elems = 0;
			if(geocoderInputs != null) elems = geocoderInputs.length;
			if(elems == 7) return;//only allow 5 via points
			var timeoutId;
			var searchCont = document.getElementById('search');
			var geocoderDiv = document.createElement('div');
			geocoderDiv.className = 'geocoder-container';
			var input = document.createElement('input');
			input.type = 'text';
			input.className = 'geocoder not-geocoded';
			var debounceTimeout = 500;
			if(!geocodeSearch.apiKeySet()) debounceTimeout = 1000;
			input.addEventListener('input', debounce(function() {
				geocodeSearch.performGeocode(input)
			}, debounceTimeout));
			input.addEventListener('blur', geocodeSearch.clearResults(input));
			var list = document.createElement('ul');
			list.className = 'geocoder-list';
			list.style.display = 'none';

			var btn = document.createElement('button');

			if(elems == 0) {
				geocoderDiv.id = 'geocoder-container-start';
				input.id = 'geocoder-start';
				list.id = 'geocoder-results-start';
				input.placeholder = 'Start address';

				btn.className = 'icon-add geocoder-button';
				btn.id = 'geocoder-add';
				btn.addEventListener('click', function() {
					geocodeSearch.addGeocoder();
				});

				geocoderDiv.appendChild(input);
				geocoderDiv.appendChild(list);
				geocoderDiv.appendChild(btn);
				searchCont.appendChild(geocoderDiv);
			} else if(elems == 1) {
				geocoderDiv.id = 'geocoder-container-end';
				input.id = 'geocoder-end';
				list.id = 'geocoder-results-end';
				input.placeholder = 'End address';

				btn.className = 'icon-close geocoder-button';
				btn.id = 'geocoder-remove-end';
				btn.addEventListener('click', function() {
					geocodeSearch.removeGeocoder(geocoderDiv);
				});

				geocoderDiv.appendChild(input);
				geocoderDiv.appendChild(list);
				geocoderDiv.appendChild(btn);
				searchCont.appendChild(geocoderDiv);
			} else {
				var id = elems - 2;
				geocoderDiv.id = 'geocoder-container-' + id;
				input.id = 'geocoder-' + id;
				list.id = 'geocoder-results-' + id;
				input.placeholder = 'Via address '/* + (id+1)*/;

				btn.className = 'icon-close geocoder-button';
				btn.id = 'geocoder-remove-' + id;
				btn.addEventListener('click', function() {
					geocodeSearch.removeGeocoder(geocoderDiv);
				});

				var children = searchCont.childNodes;
				var childLength = children.length;
				geocoderDiv.appendChild(input);
				geocoderDiv.appendChild(list);
				geocoderDiv.appendChild(btn);
				searchCont.insertBefore(geocoderDiv, children[childLength-1]);
			}
		},
		removeGeocoder : function(div) {
			var geocoderInputs = document.getElementsByClassName('geocoder');
			var elems = 0;
			if(geocoderInputs != null) elems = geocoderInputs.length;
			var isGeocoded = div.getElementsByClassName('is-geocoded');
			if(isGeocoded.length > 0) {
				geocodeSearch.removeMarker(isGeocoded[0].id)
			}
			//alert(div.getElementById('geocoder-end').toSource())
			if(div.id == 'geocoder-container-end') {
				var children = div.parentElement.childNodes;
				var childLength = children.length;
				var newEndDiv = children[childLength-2];
				var newInput = newEndDiv.getElementsByClassName('geocoder')[0];
				var newList = newEndDiv.getElementsByClassName('geocoder-list')[0];
				var newButton = newEndDiv.getElementsByClassName('geocoder-button')[0];
				newEndDiv.id = 'geocoder-container-end';
				newInput.id = 'geocoder-end';
				newList.id = 'geocoder-results-end';
				if(document.getElementsByClassName('geocoder').length - 1 > 1) {
					newInput.placeholder = 'End address';
				}
				newButton.id = 'geocoder-remove-end';
			}
			div.parentElement.removeChild(div);
		},
		removeMarker : function(id) {
			var remIndex = -1;
			for(var i=0; i<geocodeSearch.markers.length; ++i) {
				var curr = geocodeSearch.markers[i];
				if(curr[0] == id) {
					remIndex = i;
					map.removeLayer(curr[1][0]);
					break;
				}
			}
			if(remIndex >= 0) {
				geocodeSearch.markers.splice(remIndex, 1);
				geocodeSearch.computeRoute();
			}
		},
		clearResults : function(input) {
			geocodeSearch.results = [];
			var idParts = input.id.split('-');
			var id = idParts[0] + '-results-' + idParts[1];
			var list = document.getElementById(id);
			if(list == null) return;
			list.innerHTML = '';
			list.style.display = 'none';
		},
		performGeocode : function(input) {
			var query = input.value;
			geocodeSearch.clearResults(input);
			input.className = input.className.replace('is-geocoded', 'not-geocoded');
			geocodeSearch.removeMarker(input.id);
			if(query.length < 3) return;
			geocoder.geocode(query, function(data) {
				geocodeSearch.addResults(data);
				var formData = {
					name : query
				};
				$.post(OC.generateUrl('/apps/maps/api/1.0/favorite/getFavoritesByName'), formData, function(data){
					$.each(data, function(index, content) {
						content.center = L.latLng(content.lat, content.lng);
						content.bbox = null;
						content.type = 'favorite';
						geocodeSearch.addResults(data);
					});
					geocodeSearch.displayResults(input);
				});
			}, null);
			//TODO search for contacts
		},
		addResults : function(searchResults) {
			$.each(searchResults, function(index, cont) {
					geocodeSearch.results.push(cont);
				})
		},
		displayResults : function(input) {
			var idParts = input.id.split('-');
			var id = idParts[0] + '-results-' + idParts[1];
			var list = document.getElementById(id);
			$.each(geocodeSearch.results, function(index, content) {
				var li = document.createElement('li');
				li.appendChild(document.createTextNode(content.name));
				li.setAttribute('id', 'entry-' + index);
				var className = 'geocoder-list-item';
				if(content.type == 'favorite') className += ' icon-starred';
				li.setAttribute('class', className);
				li.addEventListener('click', function() {
					input.className = input.className.replace('not-geocoded', 'is-geocoded');
					var marker = L.marker(content.center);
					geocodeSearch.markers.push([[input.id], [marker]]);
					toolKit.addMarker(marker, content.name);
					var points = document.getElementsByClassName('is-geocoded');
					if(points.length < 2 && content.bbox != null) {
						map.fitBounds(content.bbox);
					};
					input.value = content.name;
					geocodeSearch.clearResults(input);
					geocodeSearch.computeRoute();
				});
				list.appendChild(li);
			}, null);
			list.style.display = 'block';
		},
		computeRoute : function() {
			geocodeSearch.waypoints = [];
			var points = document.getElementsByClassName('is-geocoded');
			if(points.length < 2) return;
			$.each(points, function(i) {
				var id = $(this).attr('id');
				var val = $(this).val();
				for(var i=0; i<geocodeSearch.markers.length; ++i) {
					var curr = geocodeSearch.markers[i];
					if(curr[0] == id) {
						geocodeSearch.waypoints.push(L.Routing.waypoint(curr[1][0]._latlng, val));
						map.removeLayer(curr[1][0]);
					}
				}
			});
			routing.setWaypoints(geocodeSearch.waypoints);
			routing.on('routeselected', function(e) {
				var r = e.route;
				var name = r.name;
				var time = r.summary.totalTime;
				var distance = r.summary.totalDistance;
				if(distance >= 1000) distance = parseFloat(distance/1000).toFixed(1) + 'km';
				else distance += 'm';
				time = Math.ceil(time / 60); // time as minutes
				var hours = Math.floor(time / 60);
				var minutes = time % 60;
				alert('Route ' + name + ' is ' + distance + ' long and takes about ' + hours + ':' + minutes);
			});
		},
		waypointsChanged : function(e) {
			var wps = e.waypoints;
			var wpscount = wps.length;
			var inputcount = document.getElementById('search').childNodes.length;
			while(wpscount > inputcount) {
				geocodeSearch.addGeocoder();
				inputcount++;
			}
			var inputContainers = document.getElementById('search').childNodes;
			$.each(wps, function(idx, wp) {
				var name = wp.name;
				if(name == null || name == '') {
					geocoder.reverse(wp.latLng, 67108864, function(data) {
						var input = inputContainers[idx].getElementsByClassName('geocoder')[0];
						data = data[0];
						input.value = data.name;
						wp.name = data.name;
						wp.latLng = data.center;
					});
				}
			});
		}
	},

	mapSearch = {
		searchItems : [],
		_ids : [],
		getSearchResults : function(data, callback) {
			$.getJSON(OC.generateUrl('/apps/maps/search'), data, function renderSearchResults(r) {
				callback(r)
			})
		},

		showResultsOnMap : function(r) {
			if (map.getZoom() <= 14) {
				var zoomMSG = '<div class="leaflet-control-minZoomIndecator leaflet-control" style="border-radius: 3px; padding: 3px 7px; display: block; background: rgba(255, 255, 255, 0.701961);">Results might be limited due current zoom, zoom in to get more</div>'
				$('.leaflet-top.leaflet-middle').html(zoomMSG);
			} else {
				$('.leaflet-control-minZoomIndecator ').remove();
			}
			console.log(r)

			$.each(r.contacts, function() {
				var contact = this;
				if ($.inArray(contact.id, mapSearch._ids) != -1) {
					return;
				}
				if (contact.location) {
					if (contact.thumbnail) {
						var imagePath = contact.thumbnail;
						var iconImage = L.icon({
							iconUrl : imagePath,
							iconSize : [42, 49],
							iconAnchor : [21, 49],
							popupAnchor : [0, -49],
							className : 'contact-icon'
						});
					} else {
						var imagePath = OC.filePath('maps', 'img', 'icons/marker_anonPerson.png');
						var iconImage = L.icon({
							iconUrl : imagePath,
							iconSize : [42, 49],
							iconAnchor : [21, 49],
							popupAnchor : [0, -49]
						});
					}

					for(i=0; i<contact.location.length; i++){
						if(contact.location[i] == null) continue;
						var markerHTML = '<h2>' + contact.FN + "</h2>";
						var street = [contact.ADR[i][0], contact.ADR[i][1], contact.ADR[i][2]].clean('').join('<br />');
						var city = (contact.ADR[i][3]) ? contact.ADR[i][3] : '';
						markerHTML += '<br />' + street + ", " + city;
						markerHTML += (contact.TEL) ? '<br />Tel: ' + escape(contact.TEL[0]) : '';
						var marker = L.marker([contact.location[i].lat * 1, contact.location[i].lon * 1], {
							icon : iconImage
						});
						toolKit.addMarker(marker, markerHTML)
						mapSearch.searchItems.push(marker);
					}
				}
				mapSearch._ids.push(contact.id)
			})

			$.each(r.addresses, function() {
				if ($.inArray(this.place_id, mapSearch._ids) != -1) {
					return;
				}
				var markerHTML = this.display_name.replace(',', '<br />');
				var marker = new L.marker([this.lat * 1, this.lon * 1]);
				toolKit.addMarker(marker, markerHTML)
				mapSearch.searchItems.push(marker);
				mapSearch._ids.push(this.place_id)

			});

			$.each(r.nodes, function() {
				if ($.inArray(this.place_id, mapSearch._ids) != -1) {
					return;
				}
				var iconImage = toolKit.getPoiIcon(this.type)
				var markerHTML = this.display_name.replace(',', '<br />');
				if (iconImage) {
					var marker = L.marker([this.lat * 1, this.lon * 1], {
						icon : iconImage
					});
				} else {
					var marker = new L.marker([this.lat * 1, this.lon * 1]);
				}
				toolKit.addMarker(marker, markerHTML)
				mapSearch.searchItems.push(marker);
				mapSearch._ids.push(this.place_id)

			});

		},

		clearSearchResults : function() {
			for ( i = 0; i < mapSearch.searchItems.length; i++) {
				map.removeLayer(mapSearch.searchItems[i]);
			}
			mapSearch.searchItems = []
		}
	}

	Maps = {
		addressbooks : [],
		addresses : [],
		tempArr : [],
		tempCounter : 0,
		tempTotal : 0,
		activeLayers : [],
		poiMarker : [],
		overpassLayers : [],
		mouseDowntime : 0,
		droppedPin : {},
		dragging : false,
		activeDevices : [],
		deviceMarkers : [],
		trackMarkers : {},
		trackingTimer : {},
		deviceTimer : 0,
		historyTrack : {},
		traceDevice : null,
		arrowHead : {},
		displayPoiIcons : function(zoomLevel) {
	        $.each(poiLayers, function(i, layer) {
                if(i > zoomLevel) return true;
		        $.each(layer, function(group, values) {
			        $.each(values, function(j, value) {
			            setTimeout(function() {
				            var overpassLayer = new L.OverPassLayer({
					            minZoom: 9,
					            query: 'node({{bbox}})[' + group + '=' + value + '];out;',
					            onSuccess: function(data) {
						            for ( i = 0; i < data.elements.length; i++) {
							            e = data.elements[i];
							            if (e.id in this.instance._ids) {
								            return;
							            }
							            this.instance._ids[e.id] = true;
							            var pos = new L.LatLng(e.lat, e.lon);
							            var popup = Maps.getPoiPopupHTML(e.tags).innerHTML;
							            poiIcon = toolKit.getPoiIcon(value);
							            if (poiIcon) {
								            var marker = L.marker(pos, {
									            icon : poiIcon,
								            }).bindPopup(popup);
								            Maps.poiMarker.push(marker);
								            toolKit.addMarker(marker, popup)
							            }
						            }
					            }
				            });
				            map.addLayer(overpassLayer);
				            Maps.overpassLayers.push(overpassLayer);
				        }, 200);
			        });
		        });
	        });
		},
		hidePoiIcons : function() {
			$.each(Maps.overpassLayers, function(i, layer) {
				map.removeLayer(layer);
			});
			Maps.overpassLayers = [];
			$.each(Maps.poiMarker, function(i, marker) {
				map.removeLayer(marker);
			});
			Maps.poiMarker = [];
		},
		getPoiPopupHTML : function(tags) {
			var div = document.createElement('div');
			
			var housenr = '';
			var street = '';
			var city = '';
			var postcode = '';
			var suburb = '';
			if(tags['addr:street']) street = tags['addr:street'];
			else if(tags['road']) street = tags['road'];
			else if(tags['footway']) street = tags['footway'];
			if(tags['addr:housenumber']) housenr = tags['addr:housenumber'];
			else if(tags['house_number']) housenr = tags['house_number'];
			if(tags['addr:postcode']) postcode = tags['addr:postcode'];
			else if(tags['postcode']) postcode = tags['postcode'];
			if(tags['addr:city']) city = tags['addr:city'];
			else if(tags['town']) city = tags['town'];
			else if(tags['city']) city = tags['city'];
			if(tags['suburb']) suburb = tags['suburb'];
			else if(tags['city_district']) suburb = tags['city_district'];
			
			var title = document.createElement('h2');
			var titleTxt = '';
			if(tags['name']) titleTxt = tags['name'];
			else if(tags['building']) titleTxt = tags['building'];
			//use street as title fallback
			else if(street.length > 0) {
				titleTxt = street;
				if(housenr.length > 0) titleTxt += ' ' + housenr;
			}
			title.appendChild(document.createTextNode(titleTxt));
			div.appendChild(title);
			
			var addr = '';
			if(street.length > 0) addr += street;
			if(housenr.length > 0) addr += ' ' + housenr;
			if(postcode.length > 0) addr += ', ' + postcode;
			if(city.length > 0) addr += ' ' + city;
			if(suburb.length > 0) addr += ' (' + suburb + ')';
			div.appendChild(document.createTextNode(addr));
			if(tags['phone']) {
				var phoneDiv = document.createElement('div');
				var phone = document.createElement('a');
				var phoneN = tags['phone'];
				if(phoneN.startsWith('+')) phoneN = phoneN.substring(1);
				phone.setAttribute('href', 'tel://' + phoneN);
				phone.appendChild(document.createTextNode(tags['phone']));
				phoneDiv.appendChild(phone);
				div.appendChild(phoneDiv);
			}
			if(tags['website']) {
				var webDiv = document.createElement('div');
				var web = document.createElement('a');
				var webN = tags['website'];
				web.setAttribute('href', webN);
				web.setAttribute('target', '_blank');
				web.appendChild(document.createTextNode(webN));
				webDiv.appendChild(web);
				div.appendChild(webDiv);
			}
			if(tags['email']) {
				var mailDiv = document.createElement('div');
				var mail = document.createElement('a');
				var mailN = tags['email'];
				mail.setAttribute('href', 'mailto:' + mailN);
				mail.appendChild(document.createTextNode(mailN));
				mailDiv.appendChild(mail);
				div.appendChild(mailDiv);
			}
			return div;
		},
		loadAdressBooks : function() {
			if($('#contactMenu').length == 0) return;
			Maps.addressbooks = [];
			$.get(OC.generateUrl('apps/contacts/addressbooks/'), function(r) {
				$.each(r.addressbooks, function() {
					var book = {
						'id' : this.id,
						'backend' : this.backend
					}
					Maps.addressbooks.push(book);
				})
				//Load addresses in array
				$.each(Maps.addressbooks, function(ai) {
					$.get(OC.generateUrl('/apps/contacts/addressbook/' + this.backend + '/' + this.id + '/contacts'), function(r) {
						$.each(r.contacts, function(ci) {
							var name = this.data.FN[0].value;
							var adr = {};
							var orgAdr = {};
							if(this.data.ADR){
								for(var i=0; i< this.data.ADR.length; i++){
									var currAddr = this.data.ADR[i].value;
									//remove empty cells (didn't work with delete or any other function...thus: ugly code
									for (var j=currAddr.length-1; j>=0; j--) {
										if (currAddr[j] === null || currAddr[j] === undefined || currAddr[j] === "") {
											currAddr.splice(j,1);
										}
									}
									adr[i] = currAddr.toString().replace(/,/g, ", ");
									orgAdr[i] = this.data.ADR[i].value;
								}

							}
							if(!adr[0]) return true;
							var addr = {
								'name': name,
								'add': adr,
								'orgAdd': orgAdr
							};
							Maps.addresses.push(addr);
						});
					});
				});
				//end
				Maps.loadContacts();
			})
		},
		removePosistionHistory : function(deviceId) {
			for ( i = 0; i < Maps.trackMarkers[deviceId].length; i++) {
				map.removeLayer(Maps.trackMarkers[deviceId][i]);
			}
			if (Maps.historyTrack[deviceId]) {
				map.removeLayer(Maps.historyTrack[deviceId]);
				map.removeLayer(Maps.arrowHead[deviceId]);
			}
			clearTimeout(Maps.trackingTimer[deviceId]);
		},

		loadDevicePosistionHistory : function(deviceId, trackCurrentPosition, from, till) {
			var trackCurrentPosition = (trackCurrentPosition) ? true : false;
			var from = (from) ? from : '';
			var till = (till) ? till : '';
			var data = {
				devices : deviceId,
				'from' : from,
				'till' : till
			};

			if (!Maps.trackMarkers[deviceId]) {
				Maps.trackMarkers[deviceId] = [];
				Maps.arrowHead[deviceId] = [];
				Maps.historyTrack[deviceId] = [];
				Maps.trackingTimer[deviceId] = [];
			}

			clearTimeout(Maps.trackingTimer);
			$.get(OC.generateUrl('/apps/maps/api/1.0/location/loadLocations'), data, function(response) {
				var locations = response[deviceId];
				var points = [];
				var bounds = []
				var lastPosition = locations[0];
				for ( i = 0; i < Maps.trackMarkers[deviceId].length; i++) {
					map.removeLayer(Maps.trackMarkers[deviceId][i]);
				}
				if (Maps.historyTrack[deviceId]) {
					map.removeLayer(Maps.historyTrack[deviceId]);
					map.removeLayer(Maps.arrowHead[deviceId]);
				}
				if (locations.length === 0) {
					OC.Notification.showTimeout('No results');
					return;
				}
				var lastPObject = L.latLng(lastPosition.lat, lastPosition.lng) //distanceTo
				$.each(locations, function(k, location) {
					var markerHTML = '';
					var marker = new L.marker([location.lat * 1, location.lng * 1]);
					var point = new L.LatLng(location.lat * 1, location.lng * 1);
					//bounds.push(marker.getBounds());
					var markerHTML = location.name + '<br />';
					markerHTML += 'Lat: ' + location.lat + ' Lon: ' + location.lng + '<br />';
					markerHTML += 'Speed: ' + location.speed + '<br />'
					markerHTML += 'Time: ' + new Date(location.timestamp * 1000).toLocaleString("nl-NL")
					var marker = new L.marker([location.lat * 1, location.lng * 1]);
					if(Math.round(lastPObject.distanceTo(point))> 15){ //If markers are more then 15m between each other
						console.log('Distance from last point: ',Math.round(lastPObject.distanceTo(point)));
						Maps.trackMarkers[location.deviceId].push(marker);
						toolKit.addMarker(marker, markerHTML);
						points.push(point);
					}
					lastPObject = point;
				});
				points.reverse();
				Maps.historyTrack[deviceId] = new L.Polyline(points, {
					color : 'green',
					weight : 6,
					opacity : 0.5,
					smoothFactor : 1

				});
				Maps.historyTrack[deviceId].addTo(map);
				Maps.arrowHead[deviceId] = L.polylineDecorator(Maps.historyTrack[deviceId]).addTo(map);
				Maps.arrowHead[deviceId].setPatterns([{
					offset : '0%',
					repeat : 100,
					symbol : L.Symbol.arrowHead({
						pixelSize : 25,
						polygon : false,
						pathOptions : {
							stroke : true
						}
					})
				}]);
				Maps.trackingTimer[deviceId] = setTimeout(function() {
					Maps.loadDevicePosistionHistory(deviceId, trackCurrentPosition, from, till);
				}, 60000)
				if (trackCurrentPosition && lastPosition.deviceId == Maps.traceDevice) {
					map.panTo(new L.LatLng(lastPosition.lat, lastPosition.lng));
				}
			});
		},
		clearDevicePosistionHistory : function(deviceId) {
			for ( i = 0; i < Maps.trackMarkers[deviceId].length; i++) {
				map.removeLayer(Maps.trackMarkers[deviceId][i]);
			}
			map.removeLayer(Maps.historyTrack[deviceId]);
			map.removeLayer(Maps.arrowHead[deviceId]);
			clearTimeout(Maps.trackingTimer[deviceId]);
			Maps.trackMarkers[deviceId] = [];
		},
		loadDevicesLastPosition : function() {
			var data = {
				devices : Maps.activeDevices.join(','),
				limit : 1
			};
			clearTimeout(Maps.deviceTimer);
			if (Maps.activeDevices.length > 0) {
				$.get(OC.generateUrl('/apps/maps/api/1.0/location/loadLocations'), data, function(response) {
					Maps.clearDevicePositions();
					$.each(response, function(deviceId, location) {
						console.log(deviceId, location);
						var device = location[0];
						if (!device)
							return;

						var markerHTML = device.name + '<br />';
						markerHTML += 'Lat: ' + device.lat + ' Lon: ' + device.lng + '<br />';
						markerHTML += 'Speed: ' + device.speed + '<br />'
						markerHTML += 'Time: ' + new Date(device.timestamp * 1000).toLocaleString("nl-NL")
						var marker = new L.marker([device.lat * 1, device.lng * 1]);
						toolKit.addMarker(marker, markerHTML)
						Maps.deviceMarkers.push(marker);
						if (device.deviceId == Maps.traceDevice) {
							map.panTo(new L.LatLng(device.lat * 1, device.lng * 1));
						}
					});
					Maps.deviceTimer = setTimeout(Maps.loadDevicesLastPosition, 60000);
				});
			}
		},
		clearDevicePositions : function() {
			for ( i = 0; i < Maps.deviceMarkers.length; i++) {
				map.removeLayer(Maps.deviceMarkers[i]);
			}
			Maps.deviceMarkers = [];
		},

		loadContacts : function() {
			Maps.tempArr = [];
			Maps.tempTotal = 0;
			Maps.tempCounter = 0;
			$.each(Maps.addressbooks, function(ai) {
				$.get(OC.generateUrl('/apps/contacts/addressbook/' + this.backend + '/' + this.id + '/contacts'), function(r) {
					$.each(r.contacts, function(ci) {
						Maps.tempArr.push(this);
						if (ai == Maps.addressbooks.length - 1 && ci == r.contacts.length - 1) {
							Maps.getContactPositionData();
							Maps.tempTotal = Maps.tempArr.length;
							$('#loadingContacts').show();
						}
					});
				});
			});
		},
		getContactPositionData : function() {

			if (Maps.tempArr.length > 0) {
				temp = Maps.tempArr.pop();
				var contact = toolKit.vcardToObject(temp);
				toolKit.adresLookup(contact.adr, function(d) {
					var curperson = $.extend({}, d, contact);
					try {
						toolKit.addFavContactMarker(curperson);
					} catch(e) {

					}
					var total = Maps.tempTotal;
					var index = Maps.tempCounter
					var percent = Math.round((index / total * 100) * 100) / 100;
					toolKit.setProgress(percent);
					$('#cCounter').text(index + 1 + ' of ' + (total * 1 + 1));
					Maps.tempCounter++;
					if (index == total)
						$('#loadingContacts').hide()
					Maps.getContactPositionData();
				})
			}
		},

		saveCurrentLocation : function(e) {
			var center = map.getBounds().getCenter();
			var location = {
				lat : center.lat,
				lng : center.lng
			};
			$.jStorage.set('location', location)
			$.jStorage.set('zoom', e.target._zoom)
		},

		showContact : function(data) {

		}
	}
	toolKit = {
		addMarker : function(marker, markerHTML, openPopup, fav) {
			if(marker == null || marker._latlng == null) return;
			fav = fav || false;
			var openPopup = (openPopup) ? true : false;
			var latlng = marker._latlng.lat + ',' + marker._latlng.lng;
			var markerHTML2 = '<div class="';
			if(fav) {
				markerHTML2 += 'icon-starred removeFromFav" fav-id="' + marker.options.id + '"';
				var editButton = document.createElement('button');
				editButton.className = 'icon-rename editFav';
				var titleEnd = markerHTML.indexOf('</h2>') + 5;
				markerHTML = markerHTML.slice(0, titleEnd) + editButton.outerHTML + markerHTML.slice(titleEnd);
			} else {
				markerHTML2 += 'icon-star addToFav"';
			}
			markerHTML2 += ' data-latlng="' + latlng + '" style="float: left;"></div><div class="marker-popup-content">' + markerHTML + '</div><div><a class="setDestination" data-latlng="' + latlng + '">Navigate here</a></div>';
			marker.addTo(map).bindPopup(markerHTML2);
			marker.on('mouseover', function (e) {
				var tgt = e.originalEvent.fromElement || e.originalEvent.relatedTarget;
				var parent = this._getParent(tgt, 'leaflet-popup');
				if(parent == marker._popup._container) return true;
				marker.openPopup();
			}, this);
			marker.on('mouseout', function (e) {
				var tgt = e.originalEvent.toElement || e.originalEvent.relatedTarget;
				if(this._getParent(tgt, 'leaflet-popup')) {
					L.DomEvent.on(marker._popup._container, 'mouseout', this._popupMouseOut, this);
					return true;
				}
				marker.closePopup();
			}, this);
			if (openPopup === true) {
				setTimeout(function() {
					//L.popup().setLatLng([marker._latlng.lat, marker._latlng.lng]).setContent("I am a standalone popup.").openOn(map);
					marker.openPopup();
				}, 50);
			}
		},
		_getParent: function(element, className) {
			var parent = element.parentNode;
			while (parent != null) {
				if (parent.className && L.DomUtil.hasClass(parent, className))
					return parent;
				parent = parent.parentNode;
			}
			return false;
		},
		_popupMouseOut: function(e) {
			if(marker == null || marker._popup == null) return false;
			L.DomEvent.off(marker._popup, 'mouseout', this._popupMouseOut, this);
			var tgt = e.toElement || e.relatedTarget;
			if (this._getParent(tgt, 'leaflet-popup')) return true;
			if (tgt == this._icon) return true;
			marker.closePopup();
		},
		getPoiIcon : function(icon) {
			return toolKit.toMakiMarkerIcon(icon.replace(' ', ''));
		},

		toMakiMarkerIcon : function(type) {
			var mapper = {
				'aerialway' : [ 'aerialway' ],
				'airfield' : [ 'airfield' ],
				'airport' : [ 'airport' ],
				'alcohol-shop' : [ 'alcohol-shop', 'alcohol', 'beverages' ],
				'america-football' : [ 'america-football' ],
				'art-gallery' : [ 'art-gallery', 'gallery', 'artwork', 'paint', 'arts_centre' ],
				'bakery' : [ 'bakery' ],
				'bank' : [ 'bank', 'atm' ],
				'bar' : [ 'bar' ],
				'baseball' : [ 'baseball' ],
				'basketball' : [ 'basketball' ],
				'beer' : [ 'beer', , 'biergarten', 'pub' ],
				'bicycle' : [ 'bicycle', 'bicycle_parking' ],
				'building' : [ 'building' ],
				'bus' : [ 'bus', 'bus_stop', 'bus_station'],
				'cafe' : [ 'cafe', 'coffee_shop', 'coffeeshop' ],
				'camera' : [ 'camera', 'viewpoint', 'citytour', 'photo' ],
				'campsite' : [ 'campsite', 'camp_site', 'caravan_site' ],
				'car' : [ 'car', 'driving_school', 'taxi', 'car_rental' ],
				'cemetery' : [ 'cemetery' ],
				'chemist' : [ 'chemist' ],
				'cinema' : [ 'cinema' ],
				'circle' : [ 'circle', 'lost_found' ],
				'circle-stroked' : [ 'circle-stroked' ],
				'city' : [ 'city' ],
				'clothing-store' : [ 'clothing-store', 'clothes' ],
				'college' : [ 'college', 'university' ],
				'commercial' : [ 'commercial' ],
				'cricket' : [ 'cricket' ],
				'cross' : [ 'cross' ],
				'dam' : [ 'dam' ],
				'danger' : [ 'danger' ],
				'dentist' : [ 'dentist' ],
				'disability' : [ 'disability' ],
				'dog-park' : [ 'dog-park' ],
				'embassy' : [ 'embassy' ],
				'emergency-telephone' : [ 'emergency-telephone' ],
				'entrance' : [ 'entrance' ],
				'farm' : [ 'farm' ],
				'fast-food' : [ 'fast-food', 'fast_food' ],
				'ferry' : [ 'ferry' ],
				'fire-station' : [ 'fire-station', 'fire_station' ],
				'fuel' : [ 'fuel' ],
				'garden' : [ 'garden', 'florist' ],
				'gift' : [ 'gift' ],
				'golf' : [ 'golf' ],
				'grocery' : [ 'grocery', 'supermarket', 'deli', 'convenience', 'greengrocer', 'fishmonger', 'butcher', 'marketplace', 'mall', 'market' ],
				'hairdresser' : [ 'hairdresser' ],
				'harbor' : [ 'harbor' ],
				'heart' : [ 'heart' ],
				'heliport' : [ 'heliport' ],
				'hospital' : [ 'hospital' ],
				'ice-cream' : [ 'ice-cream', 'ice_cream' ],
				'industrial' : [ 'industrial' ],
				'land-use' : [ 'land-use' ],
				'laundry' : [ 'laundry' ],
				'library' : [ 'library', 'books' ],
				'lighthouse' : [ 'lighthouse' ],
				'lodging' : [ 'lodging', 'hotel', 'guest_house', 'hostel', 'motel', 'apartment', 'chalet' ],
				'logging' : [ 'logging' ],
				'london-underground' : [ 'london-underground' ],
				'marker' : [ 'marker' ],
				'marker-stroked' : [ 'marker-stroked', 'information' ],
				'minefield' : [ 'minefield' ],
				'mobilephone' : [ 'mobilephone', 'mobile_phone', 'gsm' ],
				'monument' : [ 'monument' ],
				'museum' : [ 'museum' ],
				'music' : [ 'music' ],
				'oil-well' : [ 'oil-well' ],
				'park' : [ 'park' ],
				'park2' : [ 'picnic_site' ],
				'parking' : [ 'parking' ],
				'parking-garage' : [ 'parking-garage', 'parking-entrance', 'parking_entrance' ],
				'pharmacy' : [ 'pharmacy', 'drugstore', 'doctors' ],
				'pitch' : [ 'pitch', 'sports' ],
				'place-of-worship' : [ 'place-of-worship', 'place_of_worship' ],
				'playground' : [ 'playground' ],
				'police' : [ 'police' ],
				'polling-place' : [ 'polling-place' ],
				'post' : [ 'post', 'post_box', 'post_office' ],
				'prison' : [ 'prison' ],
				'rail' : [ 'rail' ],
				'rail-above' : [ 'rail-above' ],
				'rail-light' : [ 'rail-light' ],
				'rail-metro' : [ 'rail-metro' ],
				'rail-underground' : [ 'rail-underground' ],
				'religious-christian' : [ 'religious-christian' ],
				'religious-jewish' : [ 'religious-jewish' ],
				'religious-muslim' : [ 'religious-muslim' ],
				'restaurant' : [ 'restaurant' ],
				'roadblock' : [ 'roadblock' ],
				'rocket' : [ 'rocket' ],
				'school' : [ 'school', 'kindergarten' ],
				'scooter' : [ 'scooter' ],
				'shop' : [ 'shop', 'general', 'bag', 'furniture', 'variety_store', 'houseware', 'department_store', 'florist', 'outdoor', 'kiosk', 'kitchen', 'shoes', 'jewelry', 'sports' ],
				'skiing' : [ 'skiing' ],
				'slaughterhouse' : [ 'slaughterhouse' ],
				'soccer' : [ 'soccer' ],
				'square' : [ 'square' ],
				'square-stroked' : [ 'square-stroked' ],
				'star' : [ 'star' ],
				'star-stroked' : [ 'star-stroked' ],
				'suitcase' : [ 'suitcase' ],
				'swimming' : [ 'swimming', 'swimming_pool' ],
				'telephone' : [ 'telephone', 'phone' ],
				'tennis' : [ 'tennis' ],
				'theatre' : [ 'theatre', 'attraction', 'theme_park' ],
				'toilets' : [ 'toilets' ],
				'town' : [ 'town' ],
				'town-hall' : [ 'town-hall', 'townhall', 'community_centre' ],
				'triangle' : [ 'triangle' ],
				'triangle-stroked' : [ 'triangle-stroked' ],
				'village' : [ 'village' ],
				'warehouse' : [ 'warehouse' ],
				'waste-basket' : [ 'waste-basket' ],
				'water' : [ 'water' ],
				'wetland' : [ 'wetland' ],
				'zoo' : [ 'zoo', 'aquarium' ]
			}
			var iconUrl = OC.filePath('maps', 'vendor', 'maki/src/');
			var iconSuffix = '-18.svg';
			var name;
			var icon;
			$.each(mapper, function(iconName, types) {
				if (types.indexOf(type) > -1) {
					name = iconName;
					icon = iconUrl + iconName + iconSuffix;
					return false;
				}
			})
			return L.icon({
					className: type + ' maki-icon',
					icon: name,
					iconUrl : icon,
					iconSize : [18, 18],
					iconAnchor : [9, 18],
					popupAnchor : [0, -18]
			});
		},
		vcardToObject : function(vcard) {
			var contact = {};

			$.each(vcard.data, function(k, v) {
				if(vcard.data.photo == true){
					var uid = vcard.metadata['id'];
					var parent = vcard.metadata['parent'];
					var backend = vcard.metadata['backend'];
					contact.thumbnail = 'apps/contacts/addressbook/' + backend + '/' + parent + '/contact/' + uid + '/photo';
					contact.thumbnail = OC.generateUrl(contact.thumbnail);
				}
				if (v[0]) {
					if ($.isArray(v[0]['value'])) {
						if (k === 'ADR') {
							var adr = {
								street : v[0]['value'][0] + v[0]['value'][1] + v[0]['value'][2],
								city : v[0]['value'][3],
								postalCode : v[0]['value'][5],
								country : v[0]['value'][6]
							}
							contact[k.toLowerCase()] = adr;
						} else {
							contact[k.toLowerCase()] = v[0]['value'].clean('').join(',');
						}
					} else {
						contact[k.toLowerCase()] = v[0]['value'];
					}
				} else {
					console.log(k, v)
				}
			})
			return contact;
		},
		/**
		 *
		 * @param {Object} street+houseno,City,Country
		 * @param {Function} callback func
		 */
		adresLookup : function(address, callback) {

			if(address !== undefined){
				var getData = {
					street : address.street,
					city : address.city,
					country : address.country
				}
			} else {
				var getData = null;
			}
			$.getJSON(OC.generateUrl('/apps/maps/adresslookup'), getData, function(r) {
				callback(r)
			})
		},
		currentMarker : null,
		favMarkers : [],
		addFavContactMarker : function(contact) {
			if (contact.thumbnail) {
				var imagePath = contact.thumbnail
				var iconImage = L.icon({
					iconUrl : imagePath,
					iconSize : [42, 49],
					iconAnchor : [21, 49],
					popupAnchor : [0, -49],
					className : 'contact-icon'
				});
			} else {
				var imagePath = OC.filePath('maps', 'img', 'icons/marker_anonPerson.png');
				var iconImage = L.icon({
					iconUrl : imagePath,
					iconSize : [42, 49],
					iconAnchor : [21, 49],
					popupAnchor : [0, -49]
				});
			}

			var markerHTML = '<h2>' + contact.fn + "</h2>";
			var street = (contact.adr.street) ? contact.adr.street : '';
			var city = (contact.adr.city) ? contact.adr.city : '';
			markerHTML += '<br />' + street + " " + city;
			markerHTML += (contact.tel) ? '<br />Tel: ' + escape(contact.tel) : '';
			var marker = L.marker([contact.lat * 1, contact.lon * 1], {
				icon : iconImage
			});
			toolKit.addMarker(marker, markerHTML)
			toolKit.favMarkers.push(marker);
		},
		removeFavMarkers : function() {
			for ( i = 0; i < toolKit.favMarkers.length; i++) {
				map.removeLayer(toolKit.favMarkers[i]);
			}
			toolKit.favMarkers = []
		},

		setProgress : function(percent) {
			var $element = $('#progressBar');
			var progressBarWidth = percent * $element.width() / 100;
			$element.find('div').animate({
				width : progressBarWidth
			}, 50);
		}
	}

	poiLayers = {
		9 : {
			shop : [
				'supermarket', 'bicycle', 'mall', 'market', 'grocery'
			],
			amenity : [
				'atm', 'bank', 'restaurant', 'swimming_pool', 'library', 'toilets', 'post_box'
			],
			tourism : [
				'hotel', 'hostel', 'motel', 'camp_site', 'apartment', 'guest_house', 'chalet'
			]
		},
		10 : {
		    shop : [
		        
		    ],
		    amenity : [
		        'university', 'college', 'school', 'kindergarten', 'community_centre', 'cinema', 'townhall', 'arts_centre', 'hospital', 'telephone'
		    ],
		    tourism : [
		        'museum', 'gallery', 'zoo', 'artwork', 'information', 'theatre'
		    ]
		},
		11 : {},
	 	12: {
		    shop : [
		        
		    ],
		    amenity : [
		        'lost_found', 'embassy', 'bar', 'pub', 'coffee_shop', 'coffeeshop', 'cafe', 'fast_food', 'ice_cream'
		    ],
		    tourism : [
		        'attraction', 'theme_park', 'aquarium'
		    ]
		},
		13 : {
		    shop : [
		        
		    ],
		    amenity : [
		        'dentist', 'doctors', 'pharmacy', 'health_centre', 'police', 'taxi', 'car_rental', 'fire_station', 'bus_station', 'driving_school', 'bicycle_parking', 'parking_entrance', 'parking'
		    ],
		    tourism : [
		        'viewpoint', 'picnic_site', 'citytour'
		    ]
		},
		14 : {
		    shop : [
		        'bakery', 'mobile_phone', 'clothes', 'hairdresser', 'florist', 'photo', 'ice_cream'
		    ]
		},
		15 : {
		    shop : [
		        'laundry'
		    ]
		}
	}
		
//amenity : ["recycling", "fuel", "place_of_worship", "", "drinking_water", "bench", "waste_disposal", "nightclub", "post_office", "charging_station", "waste_basket", "vending_machine", "marketplace", "ev_charging", "bureau_de_change", "car_wash", "fountain", "boat_rental", "public_building", "physical therapy", "vacant", "casino", "grit_bin", "clock", ""sauna", "ferry_terminal", "fitness_center", "veterinary", "gym", "fablab", "money_transfer"
//shop : ['kiosk', 'computer', 'electronics', 'sports', 'jewelry', 'musical_instrument', 'chemist', 'shoes', 'beverages', 'toys', 'copyshop', 'furniture', 'alcohol', ''optician', 'books', 'car_repair', 'butcher', 'outdoor', 'motorcycle', 'travel_agency', 'tea', 'wine', 'medical_supply', 'department_store', 'dry_cleaning', 'video', 'second_hand', 'greengrocer', 'curtain', 'haberdashery', 'garden_centre', 'art', 'fashion', 'accessoires', 'confectionery', '', 'organic', 'music', 'boutique', 'interior', 'kitchen', 'vacant', 'tattoo', 'mall', 'camera', 'gallery', 'rc_models', 'coffee', 'bicycle_rental', 'photographer', 'ticket', 'charity', 'Shisha', 'hats', 'funeral_directors', 'locksmith', 'fabric', 'hardware', 'shoe_repair', 'hifi', 'fabrics', 'tailor', 'anime', 'no', 'surf', 'tobacco', 'animals', 'currency_exchange', 'souvenirs', 'internet-tele-cafe', 'photography', 'car_parts', 'antiques', 'bed', 'skating', 'ceramics', 'internet cafe', 'frame', 'brushes', 'fish', 'callshop', 'glass', 'comics', 'pottery', 'internet_cafe', 'stamps', 'radiotechnics', 'interior_decoration', 'carrental', 'interior_design', 'gramophone', 'Trödel', 'unused', 'watches', 'jewellery', 'tatoo', 'travelling', 'telecommunication', 'cigarettes', 'sports food', 'perfumery', 'unknown', 'orthopedics', 'fire_extinguisher', 'fishmonger', 'wholesale', 'lights', 'carpet', 'office_supplies', 'parquet', 'porcelain', 'lamps', 'make-up', 'art_gallery', 'telecom', 'underwear', 'watch', 'tableware', 'scuba_diving', 'christmas', 'tanning', 'craft', 'leather', 'for rent', 'glaziery', 'seafood', 'Sicherheitstechnik', 'coffee machines', 'alteration', 'decoration', 'sport_bet', 'seefood', 'mobile phone service', 'window_blind', 'tyres', 'cheese', 'medical', 'sewing-machine', 'Kaugummi-Automaten', 'Kaugummi-Automat', 'baby', 'games', 'piercing', 'Elektrohaushaltsgeräte', 'electrician', 'glasses', 'circus', 'food', 'marine', 'lottery', 'Hockey', 'electric', 'coins', 'metal workshop', 'nails', 'general', 'tanning_salon', 'crafts', 'household', 'floor', 'baby_goods', 'Patissier', 'delicatessen', 'telephone', 'Hema', 'soft_drugs', 'board_games', 'lingerie', 'candy', 'cd', 'stones', 'spiritual', 'health', 'juice', 'hemp_products', 'smartshop', 'cannabis', 'frozen_yoghurt', 'art_supplies', 'cigar', 'department', 'sok_shop', 'realestate', 'lighting', 'generic', 'nail', 'ink', 'traiteur', 'toko', 'key', 'gsm', 'artist', 'hearth', 'framing', 'espresso_machine', 'knives', 'rental', 'thrift_store', 'snacks', 'tobacconist', 'disused:butcher', 'party', 'audiologist', 'housewares', 'Fashion', 'printing', 'chandler', 'Shoes', 'Electronics', 'softdrugs', 'houseware', 'textiles', 'perfume'], "kitchen_studio", "studio"],

	mapSettings = {
		openTrackingSettings : function() {
			$.get(OC.generateUrl('/apps/maps/api/1.0/location/loadDevices'), function(d) {
				var tableHTML;
				$.each(d, function() {
					tableHTML += '<tr><td>' + this.name + '</td><td>' + this.hash + '</td><td><div class="icon-delete icon" data-deviceId="' + this.id + '"></div></td></tr>';
				});
				$('#trackingDevices tbody').html(tableHTML);
			})
			$('#trackingSettingsPopup').dialog({
				buttons : {
					"Close" : function() {
						$(this).dialog('destroy');
						$('#addtracking')[0].reset()
					}
				}

			});
		},
		saveDevice : function() {
			if ($('#addtracking input').val() == '')
				return
			var formData = {
				name : $('#addtracking input').val()
			};
			$.post(OC.generateUrl('/apps/maps/api/1.0/location/adddevice'), formData, function(d) {
				$('#trackingDevices tbody').append('<tr><td>' + formData.name + '</td><td>' + d.hash + '</td><td><div class="icon-delete icon" data-deviceId="' + d.id + '"></div></td></tr>');
				$('#addtracking input').val('');
			});
		},

		deleteDevice : function(e) {
			var dId = $(this).attr('data-deviceId');
			$(this).parent().parent().remove();
			///api/1.0/location/removedevice
			var formData = {
				deviceId : dId
			};
			$.post(OC.generateUrl('/apps/maps/api/1.0/location/removedevice'), formData);

		}
	}

	favorites = {
		favArray : [],
		add : function(){
			var latlng = $(this).attr('data-latlng').split(',');
			var popup = document.getElementsByClassName('leaflet-popup-content')[0];
			var favicon = popup.getElementsByTagName('div')[0];
			var content = popup.getElementsByTagName('div')[1];

			var popupText = content.innerHTML;
			var orgTitle = content.getElementsByTagName('h2')[0].innerHTML;
			var titleLength = '<h2>' + orgTitle + '</h2>'.length;
			content.innerHTML = popupText.substring(titleLength);
			var formData = {
				lat : latlng[0],
				lng : latlng[1],
				name : orgTitle
			};
			$.post(OC.generateUrl('/apps/maps/api/1.0/favorite/addToFavorites'), formData, function(data) {
				var markerHTML = '<h2>' + formData.name + '</h2>';
				var marker = L.marker([formData.lat, formData.lng], {
								icon : favMarkerIcon,
								id : data.id
				});
				map.removeLayer(currentMarker);
				currentMarker = null;
				toolKit.addMarker(marker, markerHTML, true, true);
				favorites.favArray.push(marker);
			});
		},
		show : function(){
			$.post(OC.generateUrl('/apps/maps/api/1.0/favorite/getFavorites'), null, function(data){
				for(var i=0; i<data.length; i++){
					var fav = data[i];

					var imagePath = OC.filePath('maps', 'img', 'icons/favMarker.png');
					var iconImage = L.icon({
						iconUrl : imagePath,
						iconSize : [31, 37],
						iconAnchor : [15, 37],
						popupAnchor : [0, -37]
					});

					var markerHTML = '<h2>' + fav.name + '</h2>';
					var marker = L.marker([fav.lat, fav.lng], {
									icon : iconImage,
									id : fav.id
					});
					toolKit.addMarker(marker, markerHTML, false, true);
					favorites.favArray.push(marker);
				}
			});
		},
		hide : function(){
			for(var i=0; i<favorites.favArray.length; i++){
				map.removeLayer(favorites.favArray[i]);
			}
			favorites.favArray = [];
		},
		remove : function(){
			var id = $(this).attr('fav-id');
			var formData = {
				id : id
			};
			$.post(OC.generateUrl('/apps/maps/api/1.0/favorite/removeFromFavorites'), formData, function(data){
				for(i=0; i<favorites.favArray.length; ++i) {
					if(favorites.favArray[i].options.id == id) {
						//TODO this code toggles the star icon and the add/remove methods. Should be used as soon as the marker replacement works
						var popup = document.getElementsByClassName('leaflet-popup-content')[0];
						var favicon = popup.getElementsByTagName('div')[0];
						var newClass = favicon.className.replace('icon-starred', 'icon-star');
						favicon.className = newClass.replace('removeFromFav', 'addToFav');
						var removedFav = favorites.favArray.splice(i,1)[0];
						addGeocodeMarker(removedFav.getLatLng());
						map.removeLayer(removedFav);
						return;
					}
				}
			});
		},
		edit : function() {
			var popup = document.getElementsByClassName('leaflet-popup-content')[0];
			var favicon = popup.getElementsByTagName('div')[0];
			var id = favicon.getAttributeNode('fav-id').value;
			var content = popup.getElementsByTagName('div')[1];
			var titleNode = content.getElementsByTagName('h2')[0];
			var title = titleNode.innerHTML;
			var button = content.getElementsByClassName('editFav')[0];
			var input = document.createElement('input');
			input.type = 'text';
			input.value = title;
			titleNode.parentNode.replaceChild(input, titleNode);
			button.className = 'confirmFavEdit icon-checkmark';
			$(document).on('click', '.confirmFavEdit', function() {
				button.className = 'icon-rename editFav';
				if(title != input.value) title = input.value;
				titleNode.innerHTML = title;
				content.replaceChild(titleNode, input);
				var formData = {
					name : title,
					id : id
				};
				$.post(OC.generateUrl('/apps/maps/api/1.0/favorite/updateFavorite'), formData, function(data){
				});
				$(document).off('click', '.confirmFavEdit');
			});
		}
	}

	$(document).on('click', '#tracking-settings', mapSettings.openTrackingSettings);
	$(document).on('click', '#addtracking button', mapSettings.saveDevice);
	$(document).on('click', '#trackingDevices .icon-delete', mapSettings.deleteDevice);
	$(document).on('click', '.addToFav', favorites.add);
	$(document).on('click', '.removeFromFav', favorites.remove);
	$(document).on('click', '.editFav', favorites.edit)

	/**
	 * Extend the OC.Notification object with our own methods
	 */
	OC.Notification.showTimeout = function(text, timeout, isHTML) {
		isHTML = (!isHTML) ? false : true;
		OC.Notification.hide();
		if (OC.Notification.notificationTimer) {
			clearTimeout(notificationTimer);
		}
		timeout = (!timeout) ? 3000 : timeout;
		if (isHTML) {
			OC.Notification.showHtml(text);
		} else {
			OC.Notification.show(text);
		}
		OC.Notification.notificationTimer = setTimeout(function() {
			OC.Notification.hide();
		}, timeout);
	}
})(jQuery, OC);
