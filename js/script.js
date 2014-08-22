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

    var oldPosition = $.jStorage.get('location',{lat: 21.303210151521565,lng: 6.15234375});
    var oldZoom = $.jStorage.get('zoom',3);

		map = L.map('map', {
			center : new L.LatLng(oldPosition.lat, oldPosition.lng),
			zoom : oldZoom,
			layers : [mapQuest]
		});

		/*var baseMaps = {
			"MapBox" : mapbox,
			"Mapnik" : mapnik,
			"Black and White" : blackAndWhite,
			"Airial" : airial
		};*/

		
		map.addControl( new L.Control.Compass() );
		map.addControl( new L.Control.Gps({
		    style: {radius: 16,   //marker circle style
        weight:3,
        color: '#0A00FF',
        fill: true},
		  }) );
		$('.leaflet-control-layers-overlays').removeProp('multiple');

		L.Routing.control({
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

		map.on('mousedown', function(e) {
			console.log('mousedown');
		});
		map.on('mouseup', function(e) {
			console.log('mouseup');
		});
		map.on("dragend zoomend",function(e){
      Maps.saveCurrentLocation(e);
    })		
		$(document).on('click', '.enable-layer', function() {
			var layer = $(this).attr('data-layer');
			var active = ($(this).attr('data-layer-enabled')) ? true : false;
			var visibleIcon = '<i class="icon-toggle fright micon"></i>';

			if (active) {
				$(this).removeAttr('data-layer-enabled');
				$(this).find('i').remove();
			} else {
				$(this).attr('data-layer-enabled', 'true');
				$(this).append(visibleIcon);
			}

			switch(layer) {
			case "contacts":
				if (active) {
					toolKit.removeFavMarkers();

				} else {
					Maps.loadAdressBooks();
				}
				break;

			case "tourism":
				if (active) {
					Maps.removeLayer('tourism-all');
					$('#tourism-items').html('')
				} else {
					Maps.addLayer('tourism', 'tourism-all');
				}
				break;

			case "amenity":
				if (active) {
					Maps.removeLayer('amenity-all');
					$('#amenity-items').html('')
				} else {
					Maps.addLayer('amenity', 'amenity-all');
				}
				break;

			case "shop":
				if (active) {
					Maps.removeLayer('shop-all');
					$('#shop-items').html('')
				} else {
					Maps.addLayer('shop', 'shop-all');
				}
				break;
			}
		})

		$(document).on('click', '.subLayer', function() {
			var subLayer = $(this).attr('data-subLayer');
			var isVisible = $(this).find('i').length;

			if (isVisible == 1) {
				Maps.hiddenPOITypes.push(subLayer)
				$('.' + subLayer).addClass('hidden');
				$(this).find('i').remove();
			} else {
				delete Maps.hiddenPOITypes[subLayer];
				$('.' + subLayer).removeClass('hidden');
				Maps.hiddenPOITypes.splice(Maps.hiddenPOITypes.indexOf(subLayer), 1);
				$(this).append('<i class="icon-toggle fright micon"></i>');
			}
		})
	})
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

	Maps = {
		hiddenPOITypes : [],
		addressbooks : [],
		tempArr : [],
		tempCounter : 0,
		tempTotal : 0,
		activeLayers : [],
		loadAdressBooks : function() {
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
		
		saveCurrentLocation: function(e){
		  var center = map.getBounds().getCenter();
      var location = {lat: center.lat, lng: center.lng};
      $.jStorage.set('location', location)
      $.jStorage.set('zoom', e.target._zoom)
		},
		
		showContact : function(data) {

		},

		addLayer : function(group, layer) {
			//OverPassAPI overlay
			//k:amenity  v:postbox
			Maps.activeLayers[layer] = new L.OverPassLayer({
				minzoom : 14,
				query : OC.generateUrl('apps/maps/router?url=http://overpass-api.de/api/interpreter?data=[out:json];node(BBOX)[' + group + '];out;'),
				callback : function(data) {
					for ( i = 0; i < data.elements.length; i++) {
						e = data.elements[i];
						// if (e.id in this.instance._ids) return;
						this.instance._ids[e.id] = true;
						var pos = new L.LatLng(e.lat, e.lon);
						var popup = this.instance._poiInfo(e.tags, e.id);
						var color = e.tags.collection_times ? 'green' : 'red';
						var icon = e.tags[group].split(';');
						if(icon[0].indexOf(',')!=-1){
							icon = icon[0].split(',');
						}
						var isVisible = ($.inArray(group + '-' + icon[0], Maps.hiddenPOITypes) == -1) ? '' : 'hidden';
						if (icon[0] != 'yes') {
							  var marker = L.AwesomeMarkers.icon({
                  icon: toolKit.toFAClass(icon[0].replace(' ','')),
                  prefix: 'fa',
                  markerColor: 'red'
                });

							var marker = L.marker(pos, {
								icon : marker
							}).bindPopup(popup);
							this.instance.addLayer(marker);
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
			map.addLayer(Maps.activeLayers[layer]);
		},
		removeLayer : function(layer) {
			map.removeLayer(Maps.activeLayers[layer])
		}
	}

	toolKit = {
	  toFAClass : function(type){
	    console.log(type);
	    mapper = {
	       'shopping-cart': ['supermarkt','supermarket','department_store','deli'], 
	       'medkit': ['hospital'],
	       'cutlery': ['fast_food','restaurant'],
	       'beer': ['pub'],
	       'credit-card': ['atm'],
	       'graduation-cap': ['school'],
	       'lightbulb-o': ['electronics'],
	       'cut': ['hairdresser'],
	       'info': ['information']
	      } 
       var returnClass = type;
	     $.each(mapper,function(faClass,types){
	         if(types.toString().indexOf(type) > -1){
	           returnClass = faClass
	         }
	     })
	     return returnClass;
	  },
	  
	  
		vcardToObject : function(vcard) {
			var contact = {};

			$.each(vcard.data, function(k, v) {
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
			contact.thumbnail = vcard.data.thumbnail
			return contact;
		},
		/**
		 *
		 * @param {Object} street+houseno,City,Country
		 * @param {Function} callback func
		 */
		adresLookup : function(address, callback) {

			var getData = {
				street : address.street,
				city : address.city,
				country : address.country
			}
			$.getJSON(OC.generateUrl('/apps/maps/adresslookup'), getData, function(r) {
				callback(r)
			})
		},
		favMarkers : [],
		addFavContactMarker : function(contact) {
			if (contact.thumbnail) {
				var imagePath = 'data:image/png;base64,' + contact.thumbnail
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
			markerHTML += '<br />' + contact.adr.street + " " + contact.adr.city;
			markerHTML += (contact.tel) ? '<br />Tel: ' + escape(contact.tel) : '';
			var marker = L.marker([contact.lat * 1, contact.lon * 1], {
				icon : iconImage
			}).addTo(map).bindPopup(markerHTML);
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
    shop: ['supermarket','bakery','car','stationery','hairdresser','mobile_phone','convenience','newsagent','kiosk','computer','clothes','variety_store','hearing_aids','florist','handicraft','candle','antique','pet','massage','electronics','laundry','doityourself','sports','jewelry','musical_instrument','chemist','shoes','beverages','toys','fishing','copyshop','beauty','bag','paint','bicycle','communication','furniture','alcohol','deli','optician','books','car_repair','butcher','outdoor','motorcycle','estate_agent','photo','gift','travel_agency','tea','wine','medical_supply','department_store','dry_cleaning','video','second_hand','greengrocer','erotic','curtain','haberdashery','garden_centre','art','fashion','bags','accessoires','confectionery','ice_cream','organic','music','boutique','interior','kitchen','vacant','tattoo','mall','camera','gallery','rc_models','coffee','bicycle_rental','photographer','ticket','charity','Shisha','hats','funeral_directors','locksmith','fabric','hardware','shoe_repair','hifi','fabrics','tailor','anime','market','grocery','no','surf','tobacco','animals','currency_exchange','souvenirs','internet-tele-cafe','photography','car_parts','antiques','bed','skating','ceramics','internet cafe','frame','brushes','fish','callshop','glass','comics','pottery','internet_cafe','stamps','radiotechnics','interior_decoration','carrental','interior_design','gramophone','Trödel','unused','watches','jewellery','tatoo','travelling','telecommunication','cigarettes','sports food','perfumery','unknown','orthopedics','fire_extinguisher','fishmonger','wholesale','lights','carpet','office_supplies','parquet','porcelain','lamps','make-up','art_gallery','telecom','underwear','watch','tableware','scuba_diving','christmas','tanning','craft','leather','for rent','glaziery','seafood','Sicherheitstechnik','coffee machines','alteration','decoration','sport_bet','seefood','mobile phone service','window_blind','tyres','cheese','medical','sewing-machine','Kaugummi-Automaten','Kaugummi-Automat','baby','games','piercing','Elektrohaushaltsgeräte','electrician','glasses','circus','food','marine','lottery','Hockey','electric','coins','metal workshop','nails','general','tanning_salon','crafts','household','floor','baby_goods','Patissier','delicatessen','telephone','Hema','soft_drugs','board_games','lingerie','candy','cd','stones','spiritual','health','juice','hemp_products','smartshop','cannabis','frozen_yoghurt','art_supplies','cigar','department','sok_shop','realestate','lighting','generic','nail','ink','traiteur','toko','key','gsm','artist','hearth','framing','espresso_machine','knives','rental','thrift_store','snacks','tobacconist','disused:butcher','party','audiologist','housewares','Fashion','printing','chandler','Shoes','Electronics','softdrugs','houseware','textiles','perfume'],
    amenity: ["post_box", "police", "atm", "recycling", "parking", "fuel", "telephone", "school", "pub", "doctors", "arts_centre", "cafe", "fast_food", "restaurant", "place_of_worship", "bank", "bicycle_parking", "drinking_water", "theatre", "bar", "bench", "waste_disposal", "nightclub", "pharmacy", "bicycle_rental", "post_office", "charging_station", "waste_basket", "vending_machine", "kindergarten", "marketplace", "dentist", "ev_charging", "bureau_de_change", "library", "cinema", "toilets", "car_wash", "fountain", "boat_rental", "taxi", "bus_parking", "public_building", "driving_school", "physical therapy", "coffee_shop", "embassy", "vacant", "coffeeshop", "ice_cream", "car_rental", "swimming_pool", "university", "casino", "community_centre", "lost_found", "grit_bin", "clock", "parking_entrance", "sauna", "brothel", "ferry_terminal", "fitness_center", "bus_station", "college", "fire_station", "health_centre", "townhall", "hospital", "veterinary", "gym", "fablab", "money_transfer", "kitchen_studio", "tanning_salon", "tanning", "studio"],
    tourism: ["artwork", "hostel", "attraction", "hotel", "information", "museum", "gallery", "viewpoint", "picnic_site", "guest_house", "theme_park", "apartment", "zoo", "camp_site", "chalet", "motel", "citytour", "aquarium"]   
  }

})(jQuery, OC);
