(function($, OC) {
    $(function() {
        var attribution = '&copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>';

		var mapQuest = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution : attribution,
            noWrap: true,
            detectRetina: true
        });
        var map = L.map('map', {
            zoom: 3,
            center: new L.LatLng(40.745, 74.2),
            maxBounds: new L.LatLngBounds(new L.LatLng(-90, 180), new L.LatLng(90, -180)),
            layers: [mapQuest]
        });
        var searchMarker;

        // Search
        $('#search-submit').click(function() {
            var str = $('#search-term').val();
            if(str.length < 1) return;

            searchController.search(str).then(function(results) {
                if(results.length == 0) return;
                var result = results[0];
                if(searchMarker) map.removeLayer(searchMarker);
                searchMarker = L.marker([result.lat, result.lon]);
                var name = result.display_name;
                var popupContent = searchController.parseOsmResult(result);
                searchMarker.bindPopup(popupContent);
                searchMarker.addTo(map);
                searchMarker.openPopup();
            });
        });
    });

    var searchController = {
        isGeocodeabe: function(str) {
            var pattern = /^\s*\d+\.?\d*\,\s*\d+\.?\d*\s*$/;
            return pattern.test(str);
        },
        search: function(str) {
            var searchTerm = str.replace(' ', '%20'); // encode spaces
            var apiUrl = 'https://nominatim.openstreetmap.org/search/'+searchTerm+'?format=json&addressdetails=1&extratags=1&limit=1';
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
            var unformattedHeader = '';
            if(add.road) {
                unformattedHeader = add.road;
                if(add.house_number) unformattedHeader += ' ' + add.house_number;
            } else if(add.village){
                unformattedHeader = add.village;
            }
            var unformattedDesc = '';
            if(add.postcode) unformattedDesc = add.postcode;
            if(add.city || add.town || add.village) {
                var city;
                if(add.city) city = add.city;
                else if(add.town) city = add.town;
                else if(add.village) city = add.village;
                if(unformattedHeader.length == 0) {
                    unformattedHeader = city;
                } else {
                    if(unformattedDesc.length > 0) { // if desc is not empty, add ' ' in front of village name
                        unformattedDesc += ' ';
                    }
                    unformattedDesc += city;
                }
                if(add.state) {
                    if(unformattedDesc.length > 0) { // if desc is not empty, add ' ' in front of state
                        unformattedDesc += ' ';
                    }
                    unformattedDesc += '(' + add.state + ')';
                }
            }
            var header = '<h2 class="location-header">' + unformattedHeader + '</h2>';
            var desc = '<p class="location-city">' + unformattedDesc + '</p>';
            var extras = result.extratags;
            if(extras.opening_hours) {
                desc += '<h3>Opening Hours</h3>';
                var hours = extras.opening_hours.split('; ');
                for(var i=0; i<hours.length; i++) {
                    desc += '<p class="opening-hours">' + hours[i] + '</p>';
                }
            }

            return header + desc;
        }
    };
})(jQuery, OC);
