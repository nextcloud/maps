(function($, OC) {
    $(function() {
        mapController.initMap();

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

    var mapController = {
        searchMarker: {},
        map: {},
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

    		var mapQuest = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    			attribution : attribution,
                noWrap: true,
                detectRetina: true
            });
            this.map = L.map('map', {
                zoom: 3,
                center: new L.LatLng(40.745, 74.2),
                maxBounds: new L.LatLngBounds(new L.LatLng(-90, 180), new L.LatLng(90, -180)),
                layers: [mapQuest]
            });
        }
    };

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
            // add road to desc if it is not heading (isn't heading, if 'name' is set)
            if(name) {
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
                    unformattedDesc += '';
                }
                unformattedDesc += '(' + state + ')';
            }

            var header = '<h2 class="location-header">' + unformattedHeader + '</h2>';
            var desc = '<span class="location-city">' + unformattedDesc + '</span>';

            // Add extras to parsed desc
            var extras = result.extratags;
            if(extras.opening_hours) {
                desc += '<h3>Opening Hours</h3>';
                var hours = extras.opening_hours.split('; ');
                for(var i=0; i<hours.length; i++) {
                    desc += '<p class="opening-hours">' + hours[i] + '</p>';
                }
            }
            if(extras.website) {
                desc += '<p><a href="' + extras.website + '" target="_blank">' + extras.website + '</a></p>';
            }

            return header + desc;
        }
    };
})(jQuery, OC);
