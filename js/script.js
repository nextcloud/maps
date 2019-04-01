(function($, OC) {
    $(function() {
        mapController.initMap();
        mapController.map.favoritesController = favoritesController;
        favoritesController.initFavorites(mapController.map);
        favoritesController.getFavorites();
        //slider

        //Photos
        photosController.initLayer(mapController.map);
        //timeFilterController.startDateSlider.oninput = function() {
        //    photosController.updateTimeFilterBegin(parseInt(this.value));
        //};


        //timeFilterController.endDateSlider.oninput = function() {
        //    photosController.updateTimeFilterEnd(parseInt(this.value));
        //};


        // once controllers have been set/initialized, we can restore option values from server
        optionsController.restoreOptions();

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
            if(str.length < 1) {
                return;
            }

            searchController.search(str).then(function(results) {
                if (results.length === 0) {
                    return;
                }
                else if (results.length === 1) {
                    var result = results[0];
                    mapController.displaySearchResult(result);
                }
                else {
                    console.log('multiple results');
                    var result = results[0];
                    mapController.displaySearchResult(result);
                }
            });
        }

        document.onkeydown = function (e) {
            e = e || window.event;
            if (e.key === 'Escape' && favoritesController.movingFavoriteId !== null) {
                favoritesController.leaveMoveFavoriteMode();
            }
        };
    });

    var helpers = {
        beautifyUrl: function(url) {
            return url.replace(/^(?:\w+:|)\/\/(?:www\.|)(.*[^\/])\/*$/, '$1');
        }
    };

    var optionsController = {
        optionValues: {},
        enabledFavoriteCategories: [],
        saveOptionValues: function (optionValues) {
            var req = {
                options: optionValues
            };
            var url = OC.generateUrl('/apps/maps/saveOptionValue');
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true
            }).done(function (response) {
            }).fail(function() {
                OC.Notification.showTemporary(
                    t('maps', 'Failed to save option values')
                );
            });
        },

        restoreOptions: function () {
            var that = this;
            var url = OC.generateUrl('/apps/maps/getOptionsValues');
            var req = {};
            var optionsValues = {};
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true
            }).done(function (response) {
                optionsValues = response.values;
                // set tilelayer before showing photo layer because it needs a max zoom value
                if (optionsValues.hasOwnProperty('tileLayer')) {
                    mapController.map.addLayer(mapController.baseLayers[optionsValues.tileLayer]);
                }
                else {
                    mapController.map.addLayer(mapController.baseLayers['OpenStreetMap']);
                }
                if (!optionsValues.hasOwnProperty('photosLayer') || optionsValues.photosLayer === 'true') {
                    photosController.toggleLayer();
                }
                if (optionsValues.hasOwnProperty('locControlEnabled') && optionsValues.locControlEnabled === 'true') {
                    mapController.locControl.start();
                }
                if (!optionsValues.hasOwnProperty('favoritesEnabled') || optionsValues.favoritesEnabled === 'true') {
                    favoritesController.toggleFavorites();
                }
                if (!optionsValues.hasOwnProperty('favoriteCategoryListShow') || optionsValues.favoriteCategoryListShow === 'true') {
                    favoritesController.toggleCategoryList();
                }
                if (optionsValues.hasOwnProperty('enabledFavoriteCategories')
                    && optionsValues.enabledFavoriteCategories
                    && optionsValues.enabledFavoriteCategories !== '')
                {
                    that.enabledFavoriteCategories = optionsValues.enabledFavoriteCategories.split('|');
                    if (favoritesController.favoritesLoaded) {
                        favoritesController.restoreCategoriesState(that.enabledFavoriteCategories);
                    }
                }

                // save tile layer when changed
                // do it after restore, otherwise restoring triggers save
                mapController.map.on('baselayerchange ', function(e) {
                    optionsController.saveOptionValues({tileLayer: e.name});
                });
            }).fail(function() {
                OC.Notification.showTemporary(
                    t('maps', 'Failed to restore options values')
                );
            });
        }
    };

    var mapController = {
        searchMarker: {},
        map: {},
        locControl: undefined,
        baseLayers: undefined,
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
                detectRetina: false,
                maxZoom: 19
            });

            var attributionESRI = 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community';
            var ESRIAerial = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution : attributionESRI,
                noWrap: false,
                detectRetina: true,
                maxZoom: 19
            });
            var ESRITopo = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {
                attribution : attributionESRI,
                noWrap: false,
                detectRetina: false,
                maxZoom: 19
            });
            var attributionOpenTopo = 'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, <a href="http://viewfinderpanoramass.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)';
            var openTopo = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
                attribution : attributionOpenTopo,
                noWrap: false,
                detectRetina: false,
                maxZoom: 17
            });
            var attributionDark = '&copy; Map tiles by CartoDB, under CC BY 3.0. Data by OpenStreetMap, under ODbL.';
            var dark = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png', {
                attribution : attributionDark,
                noWrap: false,
                detectRetina: false,
                maxZoom: 18
            });
            var attributionWatercolor = '<a href="https://leafletjs.com" title="A JS library for interactive maps">Leaflet</a> | © Map tiles by <a href="https://stamen.com">Stamen Design</a>, under <a href="https://creativecommons.org/licenses/by/3.0">CC BY 3.0</a>, Data by <a href="https://openstreetmap.org">OpenStreetMap</a>, under <a href="https://creativecommons.org/licenses/by-sa/3.0">CC BY SA</a>.';
            var watercolor = L.tileLayer('http://{s}.tile.stamen.com/watercolor/{z}/{x}/{y}.jpg', {
                attribution : attributionWatercolor,
                noWrap: false,
                detectRetina: false,
                maxZoom: 18
            });
            this.map = L.map('map', {
                zoom: 2,
                zoomControl: true,
                center: new L.LatLng(0, 0),
                maxBounds: new L.LatLngBounds(new L.LatLng(-90, 180), new L.LatLng(90, -180)),
                layers: []
            });
            this.locControl = L.control.locate({
                position: 'topright',
                drawCircle: true,
                drawMarker: true,
                showPopup: false,
                icon: 'fa fa-map-marker-alt',
                iconLoading: 'fa fa-spinner fa-spin',
                strings: {
                    title: t('maps', 'Get current location')
                }
            }).addTo(this.map);
            $('.leaflet-control-locate a').click( function(e) {
                optionsController.saveOptionValues({locControlEnabled: mapController.locControl._active});
            });
            L.control.scale({metric: true, imperial: true, position: 'topleft'})
                .addTo(this.map);

            // tile layer selector
            var baseLayers = {
                'OpenStreetMap': osm,
                'ESRI Aerial': ESRIAerial,
                'ESRI Topo': ESRITopo,
                'OpenTopoMap': openTopo,
                'Dark': dark,
                'Watercolor': watercolor
            }
            this.baseLayers = baseLayers;
            L.control.layers(baseLayers, {}, {position: 'bottomright'}).addTo(this.map);

            // main layers buttons
            var esriImageUrl = $('#dummylogo').css('content').replace('url("', '').replace('")', '').replace('.png', 'esri.jpg');
            var esriButton = L.easyButton({
                position: 'bottomright',
                states: [{
                    stateName: 'no-importa',
                    icon:      '<img src="'+esriImageUrl+'"/>',
                    title:     t('maps', 'Aerial map'),
                    onClick: function(btn, map) {
                        for (var tl in baseLayers) {
                            map.removeLayer(baseLayers[tl]);
                        }
                        map.addLayer(ESRIAerial);
                    }
                }]
            });
            esriButton.addTo(this.map);
            var osmImageUrl = $('#dummylogo').css('content').replace('url("', '').replace('")', '').replace('.png', 'osm.png');
            var osmButton = L.easyButton({
                position: 'bottomright',
                states: [{
                    stateName: 'no-importa',
                    icon:      '<img src="'+osmImageUrl+'"/>',
                    title:     t('maps', 'Classic map'),
                    onClick: function(btn, map) {
                        for (var tl in baseLayers) {
                            map.removeLayer(baseLayers[tl]);
                        }
                        map.addLayer(osm);
                    }
                }]
            });
            osmButton.addTo(this.map);
        }
    };

    var timeFilterController = {
        min: 0,
        max: Date.now()/1000,
        updateFilterTimeBegin: [],
        updateFilterTimeEnd: [],
        onUpdateCallbackBlock: false,
        createOnUpdateCallback: function () {
            var that = this;
            return function(values, handle, unencoded, tap, positions){
                if (!that.onUpdateCallbackBlock){
                    that.onUpdateCallbackBlock = true;
                    if (handle === 0) {
                        that.updateFilterTimeBegin.forEach(function (f) {
                            f(unencoded[handle]);
                        });
                    } else {
                        that.updateFilterTimeEnd.forEach(function (f) {
                            f(unencoded[handle]);
                        });
                    }
                    that.onUpdateCallbackBlock = false;
                }
            };
        },
        onChangeCallbackBlock: false,
        createOnChangeCallback: function () {
            var that = this;
            return function(values, handle, unencoded, tap, positions){
                if (!that.onChangeCallbackBlock){
                    that.onChangeCallbackBlock = true;
                    if (unencoded[0] < that.min) {
                        var delta = that.min-unencoded[0];
                        var r = that.max-that.min;
                        that.updateSliderRange(that.min - 25* delta*delta/r, that.max);
                    }
                    if (unencoded[1] > that.max) {
                        var delta = -that.max+unencoded[1];
                        var r = that.max-that.min;
                        that.updateSliderRange(that.min, that.max + 25*delta*delta/r);

                    }
                    if (positions[1] - positions[0] < 10) {
                        var m = (unencoded[0] + unencoded[1])/2;
                        var d = (unencoded[1] - unencoded[0])/2;
                        that.updateSliderRange(m-2.5*d, m+2.5*d);
                        that.setSlider(unencoded[0], unencoded[1]);
                    }
                    that.onChangeCallbackBlock = false;
                }
            };
        },
        slider : document.getElementById("timeRangeSlider"),
        connect: function () {
            noUiSlider.create(this.slider, {
                start: [20, 80],
                connect: true,
                behaviour: 'drag',
                tooltips: [{
                        to: function (x) {
                            return new Date(x*1000);
                        },
                    }, {
                    to: function (x) {
                        return new Date(x*1000);
                    }
                }],
                range: {
                    'min': 0,
                    'max': 1
                },
            });
            this.updateSliderRange(this.min, this.max);
            this.setSlider(this.min, this.max);
            this.slider.noUiSlider.on('update', this.createOnUpdateCallback());
            this.slider.noUiSlider.on('change', this.createOnChangeCallback());
        },
        connectUpdateFilterTimeBegin: function (func) {
            this.updateFilterTimeBegin.push(func);
        },
        connectUpdateFilterTimeEnd: function (func) {
            this.updateFilterTimeEnd.push(func);
        },
        updateSliderRange :  function (min, max) {
            var range = max - min;
            this.slider.noUiSlider.updateOptions({
                range: {
                    'min': min - range/10,
                    'max': max + range/10
                }
            });
            this.min = min;
            this.max = max;
        },
        setSlider: function(min, max) {
            this.slider.noUiSlider.set([min, max]);
        }
    };

    timeFilterController.connect();

    var photosController = new PhotosController(optionsController, timeFilterController);
    var favoritesController = new FavoritesController(optionsController, timeFilterController);

    timeFilterController.connectUpdateFilterTimeBegin(function (date) {photosController.updateTimeFilterBegin(date);});
    timeFilterController.connectUpdateFilterTimeEnd(function (date) {photosController.updateTimeFilterEnd(date);});

    var searchController = {
        isGeocodeabe: function(str) {
            var pattern = /^\s*\d+\.?\d*\,\s*\d+\.?\d*\s*$/;
            return pattern.test(str);
        },
        search: function(str) {
            var searchTerm = str.replace(' ', '%20'); // encode spaces
            var apiUrl = 'https://nominatim.openstreetmap.org/search/'+searchTerm+'?format=json&addressdetails=1&extratags=1&namedetails=1&limit=8';
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
