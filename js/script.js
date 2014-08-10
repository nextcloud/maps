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
	var map;

	// Get rid of address bar on iphone/ipod
	var fixSize = function() {
		window.scrollTo(0,0);
		document.body.style.height = '100%';
		if (!(/(iphone|ipod)/.test(navigator.userAgent.toLowerCase()))) {
			if (document.body.parentNode) {
				document.body.parentNode.style.height = '100%';
			}
		}
	};
	setTimeout(fixSize, 700);
	setTimeout(fixSize, 1500);

	var initMap = function () {
		// create map
		map = new OpenLayers.Map({
			div: "map",
			theme: null,
			controls: [
				new OpenLayers.Control.Attribution(),
				new OpenLayers.Control.Navigation({'zoomWheelEnabled': true}),
				new OpenLayers.Control.TouchNavigation({
					dragPanOptions: {
						enableKinetic: true
					}
				}),
				new OpenLayers.Control.Zoom()
			],
			layers: [
				new OpenLayers.Layer.OSM("OpenStreetMap", null, {
					transitionEffect: 'resize'
				})
			],
			center: new OpenLayers.LonLat(742000, 5861000),
			zoom: 3
		});
	};
	$(document).ready(function(){
		initMap();
	})
})(jQuery, OC);