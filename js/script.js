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
    });
})(jQuery, OC);
