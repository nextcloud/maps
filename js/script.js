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
(function($, OC) {

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
			layers : [mapQuest]
		});
		map.options.minZoom = 3;
		var hash = new L.Hash(map);
		/*var baseMaps = {
		 "MapBox" : mapbox,
		 "Mapnik" : mapnik,
		 "Black and White" : blackAndWhite,
		 "Airial" : airial
		 };*/

		map.addControl(new L.Control.Compass());
		map.addControl(new L.Control.Gps({
			minZoom : 14,
			autoActive: 1,
			style : {
				radius : 16, //marker circle style
				weight : 3,
				color : '#0A00FF',
				fill : true
			},
		}));
		$('.leaflet-control-layers-overlays').removeProp('multiple');

		routing = L.Routing.control({
			waypoints : [],
			geocoder : L.Control.Geocoder.nominatim(),
			plan : L.Routing.plan(null, {
				waypointIcon : function(i) {
					return new L.Icon.Label.Default({
						labelText : String.fromCharCode(65 + i)
					});
				}
			})
		}).addTo(map);
		$(".leaflet-routing-geocoders").appendTo("#searchContainer");
		$(".leaflet-routing-container").appendTo("#searchContainer");
		$('.geocoder-0').attr('completiontype', 'local');

		// properly style as input field
		$('#searchContainer').find('input').attr('type', 'text');

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
				if ($('.geocoder-0').attr('completiontype') === 'local' && ($('.geocoder-0').val() == '')) {
					$('.geocoder-0').val('');
				}
			}
			if ((curTime - Maps.mouseDowntime) > 200 && Maps.dragging === false) {//200 = 2 seconds
				console.log('Long press', (curTime - Maps.mouseDowntime))
				Maps.droppedPin = new L.marker(e.latlng);
				var decoder = L.Control.Geocoder.nominatim();
				decoder.reverse(e.latlng, 67108864, function(results) {
					var result = results[0];
					console.log(result);
					setTimeout(function() {
						var knownFields = ['country', 'country_code', 'postcode', 'residential', 'road', 'state', 'suburb', 'town', 'house_number'];
						var popupHtml = '';
						$.each(result.originalObject.address, function(k, v) {
							if ($.inArray(k, knownFields) == -1) {
								var prefix = k;
								popupHtml += v + '<br />';
							}
						})
						var houseNo = (result.originalObject.address.house_number) ? result.originalObject.address.house_number : '';
						popupHtml += result.originalObject.address.road + ' ' + houseNo + '<br />';
						popupHtml += result.originalObject.address.town + ', ' + result.originalObject.address.state + ', ' + result.originalObject.address.country;
						toolKit.addMarker(Maps.droppedPin, popupHtml, true);
					}, 50);

				})
			}

		});

		map.on("dragstart", function() {
			Maps.dragging = true;
		})
		map.on("dragend zoomend", function(e) {
			Maps.saveCurrentLocation(e);
			Maps.dragging = false;
			var searchInput = $('.geocoder-0')
			if (searchInput.attr('completiontype') == 'local' && searchInput.val() != '') {
				var data = {
					search : searchInput.val(),
					bbox : map.getBounds().toBBoxString()
				}
				clearTimeout(searchTimeout);
				searchTimeout = setTimeout(function() {
					console.log('Get new results');
					mapSearch.getSearchResults(data, mapSearch.showResultsOnMap);
				}, 500);
			}
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
		var poiCats = ['shop', 'amenity', 'tourism'];
		$.each(poiCats, function(i, cat) {
			poiTypes[cat] = poiTypes[cat].sort()
			iconHTML = '';
			$.each(poiTypes[cat], function(i, layer) {
				if (this != "") {
					iconHTML = '';
					var icon = toolKit.getPoiIcon(this);
					if (icon) {
						$('#' + cat + '-items').append('<li><a class="subLayer" data-layerGroup="' + cat + '" data-layerValue="' + this + '">' + iconHTML + this + '</a></li>')
					}
				}
			})
		});
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

								var markerHTML = '<b>' + file + "</b>";
								markerHTML += '<br />Latitude: ' + latlon.lat + " " + latDir;
								markerHTML += '<br />Longitude: ' + latlon.lon + " " + lonDir;
								markerHTML += '<br />Altitude: ' + exif.GPSAltitude + "m";
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
		$('.favoriteLayer').clickToggle(function() {
			favorites.show();
		}, function() {
			favorites.hide();
		});
		$('.contactLayer').clickToggle(function() {
			Maps.loadAdressBooks()
		}, function() {
			toolKit.removeFavMarkers()
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

		$(document).on('keyup blur', '.geocoder-0', function(e) {
			if ($(this).attr('completiontype') != 'local')
				return;

			var data = {
				search : $(this).val(),
				bbox : map.getBounds().toBBoxString()
			}
			clearTimeout(searchTimeout);
			if ($(this).val() == '') {
				mapSearch.clearSearchResults();
				mapSearch._ids = [];
			}
			if (e.keyCode != 13) {
				searchTimeout = setTimeout(function() {

				}, 1000)
			} else {
				mapSearch.clearSearchResults();
				mapSearch._ids = [];
				mapSearch.getSearchResults(data, mapSearch.showResultsOnMap);
			}

		});
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
			routing.setWaypoints([])
			var end = latlng.split(',');
			end[0] = end[0] * 1;
			end[1] = end[1] * 1;
			//map.removeLayer(routing);

			routing.setWaypoints([L.latLng(currentlocation[0], currentlocation[1]), L.latLng(end[0], end[1])]);

			$('.geocoder-1').show();
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
				var zoomMSG = '<div class="leaflet-control-minZoomIndecator leaflet-control" style="font-size: 2em; border-top-left-radius: 10px; border-top-right-radius: 10px; border-bottom-right-radius: 10px; border-bottom-left-radius: 10px; padding: 1px 15px; display: block; background: rgba(255, 255, 255, 0.701961);">Results might be limited due current zoom, zoom in to get more</div>'
				$('.leaflet-bottom.leaflet-left').html(zoomMSG);
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
						var markerHTML = '<b>' + contact.FN + "</b>";
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
		tempArr : [],
		tempCounter : 0,
		tempTotal : 0,
		activeLayers : [],
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

		},

		updateLayers : function(group, layer) {
			//OverPassAPI overlay
			//k:amenity  v:postbox
			var overPassQ = '';
			groupArr = []
			$('.activeLayer').each(function() {
				var parent = $(this).parent();
				var layerGroup = parent.attr('data-layerGroup');
				var layerValue = parent.attr('data-layerValue');
				overPassQ += 'node(BBOX)[' + layerGroup + '=' + layerValue + '];out;'
				groupArr.push(layerGroup);
			})
			if (!$('body').data('POIactive')) {
				Maps.activeLayers = new L.OverPassLayer({
					minzoom : 14,
					//query : OC.generateUrl('apps/maps/router?url=http://overpass-api.de/api/interpreter?data=[out:json];node(BBOX)[' + group + '='+layer+'];out;'),
					query : OC.generateUrl('apps/maps/router?url=(SERVERAPI)interpreter?data=[out:json];' + overPassQ),
					callback : function createMaker(data) {
						for ( i = 0; i < data.elements.length; i++) {
							e = data.elements[i];
							if (e.id in this.instance._ids) {
								return;
							}
							this.instance._ids[e.id] = true;
							var pos = new L.LatLng(e.lat, e.lon);
							var popup = this.instance._poiInfo(e.tags, e.id);
							var color = e.tags.collection_times ? 'green' : 'red';
							var curgroup = null;

							$.each(groupArr, function() {
								if (e.tags[this]) {
									curgroup = this;
								}
							})
							var icon = e.tags[curgroup].split(';');
							icon[0] = icon[0].toLowerCase();
							var marker = false;
							if (icon[0].indexOf(',') != -1) {
								icon = icon[0].split(',');
							}
							if (icon[0] != 'yes') {
								marker = toolKit.getPoiIcon(icon[0])
								if (marker) {
									var marker = L.marker(pos, {
										icon : marker,
									}).bindPopup(popup);
									toolKit.addMarker(marker, popup)
									//this.instance.addLayer(marker);
								}
							}

						}
						/*tmpTypes = tmpTypes.unique().clean('');
						 tmpHTML = ''
						 $.each(tmpTypes, function() {
						 isVisible = $.inArray(group + '-' + this, Maps.hiddenPOITypes);
						 vIcon = (isVisible == -1) ? '<i class="icon-toggle fright micon"></i>' : ''
						 tmpHTML += '<li><a class="subLayer" data-subLayer="' + group + '-' + this + '">' + this + vIcon + '</a><li>'
						 })
						 console.log(tmpTypes);
						 $('#' + group + '-items').html(tmpHTML)
						 $('#' + group + '-items').show();*/
					},
				});
				map.addLayer(Maps.activeLayers);
				$('body').data('POIactive', true);
			} else {
				Maps.activeLayers.options.query = OC.generateUrl('apps/maps/router?url=(SERVERAPI)interpreter?data=[out:json];' + overPassQ);
				if (group && layer) {
					Maps.activeLayers.onMoveEnd();
				}
			}
		},
		removeLayer : function(layer) {
			//map.removeLayer(Maps.activeLayers[layer])
		}
	}

	toolKit = {
		addMarker : function(marker, markerHTML, openPopup, fav) {
			fav = fav || false;
			var openPopup = (openPopup) ? true : false;
			var latlng = marker._latlng.lat + ',' + marker._latlng.lng;
			var markerHTML2 = '<div class="' + (fav ? 'icon-starred removeFromFav"' : 'icon-star addToFav" data-latlng="' + latlng + '"' ) + ' style="float: left;"></div><div class="marker-popup-content">' + markerHTML + '</div><div><a class="setDestination" data-latlng="' + latlng + '">Navigate to here</a></div>';
			marker.addTo(map).bindPopup(markerHTML2);
			if (openPopup === true) {
				setTimeout(function() {
					//L.popup().setLatLng([marker._latlng.lat, marker._latlng.lng]).setContent("I am a standalone popup.").openOn(map);
					marker.openPopup();
				}, 50);
			}
		},
		getPoiIcon : function(icon) {
			marker = false;
			if ($.inArray(icon, L.MakiMarkers.icons) > -1) {
				marker = L.MakiMarkers.icon({
					'icon' : icon,
					color : "#b0b",
					size : "l",
					className : icon
				});
			} else {
				var mIcon = toolKit.toMakiMarkerIcon(icon.replace(' ', ''));
				if (mIcon !== false) {
					marker = L.MakiMarkers.icon({
						'icon' : mIcon,
						color : "#b0b",
						size : "l",
						className : icon
					});
				} else {
					var amIcon = toolKit.toFAClass(icon.replace(' ', ''))
					if (amIcon !== false) {
						marker = L.AwesomeMarkers.icon({
							icon : amIcon,
							prefix : 'fa',
							markerColor : 'red'
						});
						marker.options.className += ' ' + icon
					}
				}
			}
			return marker;
		},

		toMakiMarkerIcon : function(type) {
			var mapper = {
				'fast-food' : ['fast_food'],
				'bus' : ['bus_stop', 'bus_station'],
				'beer' : ['bar', 'biergarten', 'pub'],
				'telephone' : ['phone'],
				'swimming' : ['swimming_pool'],
				'bank' : ['atm', 'bank'],
				'town-hall' : ['townhall'],
				'post' : ['post_box', 'post_office'],
				'bicycle' : ['bicycle_parking'],
				'waste-basket' : ['waste_disposal'],
				'campsite' : ['camp_site', 'caravan_site'],
				'camera' : ['viewpoint'],
				'grocery' : ['supermarket', 'deli', 'convenience', 'greengrocer', 'fishmonger', 'butcher', 'marketplace'],
				'alcohol-shop' : ['alcohol', 'beverages'],
				'shop' : ['general', 'bag', 'furniture', 'variety_store', 'houseware', 'department_store', 'florist', 'outdoor', 'kiosk', 'kitchen', 'shoes', 'jewelry', 'sports'],
				'clothing-store' : ['clothes'],
				'fire-station' : ['fire_station'],
				'place-of-worship' : ['place_of_worship'],
				'school' : ['kindergarten'],
				'parking-garage' : ['parking-entrance'],
				'lodging' : ['hotel', 'guest_house', 'chalet', 'hostel'],
				'art-gallery' : ['artwork', 'paint'],
				'paperclip' : ['craft'],
				'library' : ['books'],
				'pitch' : ['sports'],
				'mobilephone' : ['mobile_phone', 'gsm'],
				'pharmacy' : ['drugstore']

			}
			var returnClass = false;
			$.each(mapper, function(faClass, types) {
				if (types.toString().indexOf(type) > -1) {
					returnClass = faClass
				}
			})
			return returnClass;
		},
		toFAClass : function(type) {
			var mapper = {
				'shopping-cart' : ['supermarkt', 'supermarket', 'department_store', 'deli'],
				'medkit' : ['hospital'],
				'cutlery' : ['fast_food', 'restaurant'],
				'beer' : ['pub'],
				'credit-card' : ['atm'],
				'graduation-cap' : ['school'],
				'lightbulb-o' : ['electronics'],
				'cut' : ['hairdresser'],
				'info' : ['information'],
				'refresh' : ['recycling'],
				'asterisk' : ['attraction'],
				'cogs' : ['car_repair'],
				'wrench' : ['doityourself'],
				'music' : ['hifi'],
				'gift' : ['gift'],
				'globe' : ['travel_agency'],
				'minus' : ['bench', 'picnic_site'],
				'desktop' : ['computer'],
				'eye' : ['optician'],
				'cubes' : ['toys'],
				'file-picture' : ['photo'],
				'copy' : ['copyshop'],
				'paw' : ['pet'],
				'clock-o' : ['clock'],
				'key' : ['locksmith'],
				'video-camera' : ['video'],
				'magic' : ['party'],
				'road' : ['living_street'],
				'home' : ['residential']
			}
			var returnClass = false;
			$.each(mapper, function(faClass, types) {
				if (types.toString().indexOf(type) > -1) {
					returnClass = faClass
				} else {
				}
			})
			if (returnClass == false)
				console.log('Type icon not found: ' + type)
			return returnClass;
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

			var markerHTML = '<b>' + contact.fn + "</b>";
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
			}, 50).html(percent + "%&nbsp;");
		}
	}

	poiTypes = {
		shop : ['supermarket', 'bakery', 'car', 'stationery', 'hairdresser', 'mobile_phone', 'convenience', 'newsagent', 'kiosk', 'computer', 'clothes', 'variety_store', 'hearing_aids', 'florist', 'handicraft', 'candle', 'antique', 'pet', 'massage', 'electronics', 'laundry', 'doityourself', 'sports', 'jewelry', 'musical_instrument', 'chemist', 'shoes', 'beverages', 'toys', 'fishing', 'copyshop', 'beauty', 'bag', 'paint', 'bicycle', 'communication', 'furniture', 'alcohol', 'deli', 'optician', 'books', 'car_repair', 'butcher', 'outdoor', 'motorcycle', 'estate_agent', 'photo', 'gift', 'travel_agency', 'tea', 'wine', 'medical_supply', 'department_store', 'dry_cleaning', 'video', 'second_hand', 'greengrocer', 'erotic', 'curtain', 'haberdashery', 'garden_centre', 'art', 'fashion', 'bags', 'accessoires', 'confectionery', 'ice_cream', 'organic', 'music', 'boutique', 'interior', 'kitchen', 'vacant', 'tattoo', 'mall', 'camera', 'gallery', 'rc_models', 'coffee', 'bicycle_rental', 'photographer', 'ticket', 'charity', 'Shisha', 'hats', 'funeral_directors', 'locksmith', 'fabric', 'hardware', 'shoe_repair', 'hifi', 'fabrics', 'tailor', 'anime', 'market', 'grocery', 'no', 'surf', 'tobacco', 'animals', 'currency_exchange', 'souvenirs', 'internet-tele-cafe', 'photography', 'car_parts', 'antiques', 'bed', 'skating', 'ceramics', 'internet cafe', 'frame', 'brushes', 'fish', 'callshop', 'glass', 'comics', 'pottery', 'internet_cafe', 'stamps', 'radiotechnics', 'interior_decoration', 'carrental', 'interior_design', 'gramophone', 'Trödel', 'unused', 'watches', 'jewellery', 'tatoo', 'travelling', 'telecommunication', 'cigarettes', 'sports food', 'perfumery', 'unknown', 'orthopedics', 'fire_extinguisher', 'fishmonger', 'wholesale', 'lights', 'carpet', 'office_supplies', 'parquet', 'porcelain', 'lamps', 'make-up', 'art_gallery', 'telecom', 'underwear', 'watch', 'tableware', 'scuba_diving', 'christmas', 'tanning', 'craft', 'leather', 'for rent', 'glaziery', 'seafood', 'Sicherheitstechnik', 'coffee machines', 'alteration', 'decoration', 'sport_bet', 'seefood', 'mobile phone service', 'window_blind', 'tyres', 'cheese', 'medical', 'sewing-machine', 'Kaugummi-Automaten', 'Kaugummi-Automat', 'baby', 'games', 'piercing', 'Elektrohaushaltsgeräte', 'electrician', 'glasses', 'circus', 'food', 'marine', 'lottery', 'Hockey', 'electric', 'coins', 'metal workshop', 'nails', 'general', 'tanning_salon', 'crafts', 'household', 'floor', 'baby_goods', 'Patissier', 'delicatessen', 'telephone', 'Hema', 'soft_drugs', 'board_games', 'lingerie', 'candy', 'cd', 'stones', 'spiritual', 'health', 'juice', 'hemp_products', 'smartshop', 'cannabis', 'frozen_yoghurt', 'art_supplies', 'cigar', 'department', 'sok_shop', 'realestate', 'lighting', 'generic', 'nail', 'ink', 'traiteur', 'toko', 'key', 'gsm', 'artist', 'hearth', 'framing', 'espresso_machine', 'knives', 'rental', 'thrift_store', 'snacks', 'tobacconist', 'disused:butcher', 'party', 'audiologist', 'housewares', 'Fashion', 'printing', 'chandler', 'Shoes', 'Electronics', 'softdrugs', 'houseware', 'textiles', 'perfume'],
		amenity : ["post_box", "police", "atm", "recycling", "parking", "fuel", "telephone", "school", "pub", "doctors", "arts_centre", "cafe", "fast_food", "restaurant", "place_of_worship", "bank", "bicycle_parking", "drinking_water", "theatre", "bar", "bench", "waste_disposal", "nightclub", "pharmacy", "bicycle_rental", "post_office", "charging_station", "waste_basket", "vending_machine", "kindergarten", "marketplace", "dentist", "ev_charging", "bureau_de_change", "library", "cinema", "toilets", "car_wash", "fountain", "boat_rental", "taxi", "bus_parking", "public_building", "driving_school", "physical therapy", "coffee_shop", "embassy", "vacant", "coffeeshop", "ice_cream", "car_rental", "swimming_pool", "university", "casino", "community_centre", "lost_found", "grit_bin", "clock", "parking_entrance", "sauna", "brothel", "ferry_terminal", "fitness_center", "bus_station", "college", "fire_station", "health_centre", "townhall", "hospital", "veterinary", "gym", "fablab", "money_transfer", "kitchen_studio", "tanning_salon", "tanning", "studio"],
		tourism : ["artwork", "hostel", "attraction", "hotel", "information", "museum", "gallery", "viewpoint", "picnic_site", "guest_house", "theme_park", "apartment", "zoo", "camp_site", "chalet", "motel", "citytour", "aquarium"]
	}

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
			
			// TODO remove input field, still WIP
			if(document.getElementsByClassName('fav-input-title').length > 0) {
				var inputDiv = document.getElementsByClassName('fav-input-title-container')[0];
				var title = inputDiv.firstChild.value + '<br>';
				inputDiv.parentNode.removeChild(inputDiv);
				$('.addToFav').after(title);
				return;
			}
			
			var popupText = content.innerHTML;
			var delimiter = '<br>';
			var splitIndex = popupText.indexOf(delimiter);
			content.innerHTML = popupText.substring(splitIndex + delimiter.length);
			var nameDiv = document.createElement('div');
			nameDiv.className = 'fav-input-title-container';
			var nameInput = document.createElement('input');
			nameInput.type = 'text';
			nameInput.className = 'fav-input-title';
			var orgTitle = popupText.substring(0, splitIndex);
			nameInput.value = orgTitle;
			var submit = document.createElement('button');
			submit.className = 'icon-checkmark';
			submit.onclick = function(){
				content.removeChild(nameDiv);
				content.innerHTML = orgTitle + content.innerHTML;
				var formData = {
					lat : latlng[0],
					lng : latlng[1],
					name : nameInput.value
				};
				$.post(OC.generateUrl('/apps/maps/api/1.0/favorite/addToFavorites'), formData);
			}
			nameDiv.appendChild(nameInput);
			nameDiv.appendChild(submit);
			content.insertBefore(nameDiv, content.firstChild);
			content.insertBefore(favicon, content.firstChild);
		},
		show : function(){
			$.post(OC.generateUrl('/apps/maps/api/1.0/favorite/getFavorites'), null, function(data){
				for(var i=0; i<data.length; i++){
					var fav = data[i];

					var imagePath = OC.filePath('maps', 'img', 'icons/favMarker.png');
					var iconImage = L.icon({
						iconUrl : imagePath,
						iconSize : [42, 49],
						iconAnchor : [21, 49],
						popupAnchor : [0, -49]
					});

					var markerHTML = '<b>' + fav.name + " (" + fav.userId + ")</b>";
					markerHTML += '<br />Latitude: ' + parseFloat(fav.lat).toFixed(3);
					markerHTML += '<br />Longitude: ' + parseFloat(fav.lng).toFixed(3);
					markerHTML += '<br />Added: ' + new Date(fav.timestamp*1000).toString();
					var marker = L.marker([fav.lat, fav.lng], {
									icon : iconImage
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
		}
	}

	$(document).on('click', '#tracking-settings', mapSettings.openTrackingSettings);
	$(document).on('click', '#addtracking button', mapSettings.saveDevice);
	$(document).on('click', '#trackingDevices .icon-delete', mapSettings.deleteDevice);
	$(document).on('click', '.addToFav', favorites.add);

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
