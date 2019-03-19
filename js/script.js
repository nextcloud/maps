(function($, OC) {
    $(function() {
        mapController.initMap();
        photosController.appendToMap(mapController.map);
        photosController.showLayer();

        // Popup
        $(document).on('click', '#opening-hours-header', function() {
            $('#opening-hours-table').toggle();
            $('#opening-hours-table-toggle-expand').toggle();
            $('#opening-hours-table-toggle-collapse').toggle();
        });

        // Search
        $('#search-form').submit(function(e) {
            e.preventDefault();
            submitSearchForm();
        });
        $('#search-submit').click(function() {
            submitSearchForm();
        });

        function submitSearchForm() {
            var str = $('#search-term').val();
            if(str.length < 1) return;

            searchController.search(str).then(function(results) {
                if(results.length == 0) return;
                var result = results[0];
                mapController.displaySearchResult(result);
            });
        }
    });

    var helpers = {
        beautifyUrl: function(url) {
            return url.replace(/^(?:\w+:|)\/\/(?:www\.|)(.*[^\/])\/*$/, '$1');
        }
    };

    var mapController = {
        searchMarker: {},
        map: {},
        locControl: undefined,
        displaySearchResult: function(result) {
            if(this.searchMarker) this.map.removeLayer(this.searchMarker);
            this.searchMarker = L.marker([result.lat, result.lon]);
            var name = result.display_name;
            var popupContent = searchController.parseOsmResult(result);
            this.searchMarker.bindPopup(popupContent);
            this.searchMarker.addTo(this.map);
            this.searchMarker.openPopup();
        },
        initMap: function() {
            var attribution = '&copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>';

            var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution : attribution,
                noWrap: true,
                detectRetina: false
            });

            var attributionESRI = 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community';
            var ESRIAerial = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution : attributionESRI,
                noWrap: false,
                detectRetina: true
            });
            this.map = L.map('map', {
                zoom: 8,
                zoomControl: true,
                center: new L.LatLng(40.745, 74.2),
                maxBounds: new L.LatLngBounds(new L.LatLng(-90, 180), new L.LatLng(90, -180)),
                layers: []
            });
            this.locControl = L.control.locate({
                position: 'topright', // default = topleft
                drawCircle: false,
                drawMarker: false,
                showPopup: false,
                icon: 'fa fa-map-marker',
                iconLoading: 'fa fa-spinner fa-spin',
                strings: {
                    title: "Get current location"
                }
            }).addTo(this.map);
            this.locControl.start(); // try to get the user's location
            L.control.scale({metric: true, imperial: true, position: 'topleft'})
                .addTo(this.map);

            // tile layer selector
            this.map.addLayer(osm);
            var baseLayers = {
                'OpenStreetMap': osm,
                'ESRI Aerial': ESRIAerial
            }
            L.control.layers(baseLayers, {}, {position: 'bottomright'}).addTo(this.map);

            // main layers buttons
            var osmButton = L.easyButton({
                position: 'bottomright',
                states: [{
                    stateName: 'no-importa',
                    icon:      'fa-map',
                    title:     t('maps', 'Classic map'),
                    onClick: function(btn, map) {
                        map.removeLayer(ESRIAerial);
                        map.addLayer(osm);
                    }
                }]
            });
            osmButton.addTo(this.map);
            var esriButton = L.easyButton({
                position: 'bottomright',
                states: [{
                    stateName: 'no-importa',
                    icon:      'fa-image',
                    title:     t('maps', 'Aerial map'),
                    onClick: function(btn, map) {
                        map.removeLayer(osm);
                        map.addLayer(ESRIAerial);
                    }
                }]
            });
            esriButton.addTo(this.map);
        }
    };

    var photosController = new PhotosController();

    var searchController = {
        isGeocodeabe: function(str) {
            var pattern = /^\s*\d+\.?\d*\,\s*\d+\.?\d*\s*$/;
            return pattern.test(str);
        },
        search: function(str) {
            var searchTerm = str.replace(' ', '%20'); // encode spaces
            var apiUrl = 'https://nominatim.openstreetmap.org/search/'+searchTerm+'?format=json&addressdetails=1&extratags=1&namedetails=1&limit=1';
            return $.getJSON(apiUrl, {}, function(response) {
                return response;
            });
        },
        geocode: function(latlng) {
            if(!this.isGeocodeabe(latlng)) return;
            var splits = latlng.split(',');
            var lat = splits[0].trim();
            var lon = splits[1].trim();
            var apiUrl = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon='+ lon + '&addressdetails=1';
            return $.getJSON(apiUrl, {}, function(response) {
                return response;
            });
        },
        parseOsmResult: function(result) {
            var add = result.address;
            var road, postcode, city, state, name;
            if(add.road) {
                road = add.road;
                if(add.house_number) road += ' ' + add.house_number;
            }
            if(add.postcode) postcode = add.postcode;
            if(add.city || add.town || add.village) {
                if(add.city) city = add.city;
                else if(add.town) city = add.town;
                else if(add.village) city = add.village;
                if(add.state) {
                     state = add.state;
                }
            }
            var details = result.namedetails;
            if(details.name) name = details.name;

            var unformattedHeader;
            if(name) unformattedHeader = name;
            else if(road) unformattedHeader = road;
            else if(city) unformattedHeader = city;

            var unformattedDesc = '';
            var needSeparator = false;
            // add road to desc if it is not heading and exists (isn't heading, if 'name' is set)
            if(name && road) {
                unformattedDesc = road;
                needSeparator = true;
            }
            if(postcode) {
                if(needSeparator) {
                    unformattedDesc += ', ';
                    needSeparator = false;
                }
                unformattedDesc += postcode;
            }
            if(city) {
                if(needSeparator) {
                    unformattedDesc += ', ';
                    needSeparator = false;
                } else if(unformattedDesc.length > 0) {
                    unformattedDesc += ' ';
                }
                unformattedDesc += city;
            }
            if(state && add && add.country_code == 'us') { // assume that state is only important for us addresses
                if(unformattedDesc.length > 0) {
                    unformattedDesc += ' ';
                }
                unformattedDesc += '(' + state + ')';
            }

            var header = '<h2 class="location-header">' + unformattedHeader + '</h2>';
            if(result.icon) header = '<div class="inline-wrapper"><img class="location-icon" src="' + result.icon + '" />' + header + '</div>';
            var desc = '<span class="location-city">' + unformattedDesc + '</span>';

            // Add extras to parsed desc
            var extras = result.extratags;
            if(extras.opening_hours) {
                desc += '<div id="opening-hours-header" class="inline-wrapper"><img class="popup-icon" src="'+OC.filePath('maps', 'img', 'recent.svg')+'" />';
                var oh = new opening_hours(extras.opening_hours, result);
                var isCurrentlyOpen = oh.getState();
                var changeDt = oh.getNextChange();
                var currentDt = new Date();
                var dtDiff = changeDt.getTime() - currentDt.getTime();
                dtDiff = dtDiff / 60000; // get diff in minutes
                if(oh.getState()) { // is open?
                    desc += '<span class="poi-open">Open</span>';
                    if(dtDiff <= 60) {
                        desc += '<span class="poi-closes">,&nbsp;closes in ' + dtDiff + ' minutes</span>';
                    } else {
                        desc += '<span>&nbsp;until ' + changeDt.toLocaleTimeString() + '</span>';
                    }
                } else {
                    desc += '<span class="poi-closed">Closed</span>';
                    desc += '<span class="poi-opens">opens at ' + changeDt.toLocaleTimeString() + '</span>';
                }
                desc += '<img id="opening-hours-table-toggle-collapse" src="'+OC.filePath('maps', 'img', 'triangle-s.svg')+'" /><img id="opening-hours-table-toggle-expand" src="'+OC.filePath('maps', 'img', 'triangle-e.svg')+'" /></div>';
                var todayStart = currentDt;
                todayStart.setHours(0);
                todayStart.setMinutes(0);
                todayStart.setSeconds(0);
                var sevDaysEnd = new Date(todayStart);
                var sevDaysMs = 7 * 24 * 60 * 60 * 1000;
                sevDaysEnd.setTime(sevDaysEnd.getTime()+sevDaysMs);
                var intervals = oh.getOpenIntervals(todayStart, sevDaysEnd);
                desc += '<table id="opening-hours-table">';
                // intervals should be 7, if 8, then first entry is interval after 00:00:00 from last day
                if(intervals.length == 8) {
                    // set end time of last element to end time of first element and remove it
                    intervals[7][1] = intervals[0][1];
                    intervals.splice(0, 1);
                }
                for(var i=0; i<intervals.length; i++) {
                    var from = intervals[i][0];
                    var to = intervals[i][1];
                    var day = from.toLocaleDateString([], {weekday:'long'});
                    if(i==0) desc += '<tr class="selected">';
                    else desc += '<tr>';
                    desc += '<td class="opening-hours-day">' + day + '</td>';
                    var startTime = from.toLocaleTimeString();
                    var endTime =to.toLocaleTimeString();
                    desc += '<td class="opening-hours-hours">' + startTime + ' - ' + endTime + '</td>';
                    desc += '</tr>';
                }
                desc += '</table>';
            }
            if(extras.website) {
                desc += '<div class="inline-wrapper"><img class="popup-icon" src="'+OC.filePath('maps', 'img', 'link.svg')+'" /><a href="' + extras.website + '" target="_blank">' + helpers.beautifyUrl(extras.website) + '</a></div>';
            }
            if(extras.phone) {
                desc += '<div class="inline-wrapper"><img class="popup-icon" src="'+OC.filePath('maps', 'img', 'link.svg')+'" /><a href="tel:' + extras.phone + '" target="_blank">' + extras.phone + '</a></div>';
            }
            if(extras.email) {
                desc += '<div class="inline-wrapper"><img class="popup-icon" src="'+OC.filePath('maps', 'img', 'mail.svg')+'" /><a href="mailto:' + extras.email + '" target="_blank">' + extras.email + '</a></div>';
            }

            return header + desc;
        }
    };
})(jQuery, OC);
