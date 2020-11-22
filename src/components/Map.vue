<template>
	<div id="map-wrapper">
		<LMap
			ref="map"
			:center="mapOptions.center"
			:bounds="mapOptions.bounds"
			:max-bounds="mapOptions.maxBounds"
			:min-zoom="mapOptions.minZoom"
			:max-zoom="mapOptions.maxZoom"
			:zoom="mapOptions.zoom"
			:options="mapOptions.native"
			@ready="onMapReady"
			@update:bounds="onUpdateBounds"
			@baselayerchange="onBaselayerchange"
			@click="onMapClick"
			@contextmenu="onMapContextmenu">
			<RoutingControl v-if="map"
				:map="map"
				:visible="showRouting"
				@close="onRoutingClose" />
			<LControlZoom position="bottomright" />
			<LControlScale
				position="bottomleft"
				:imperial="mapOptions.scaleControlShouldUseImperial"
				:metric="!mapOptions.scaleControlShouldUseImperial" />
			<LControlLayers
				position="bottomright"
				:collapsed="false" />
			<LTileLayer
				v-for="(l, lid) in allBaseLayers"
				:key="lid"
				:visible="activeLayerId === lid"
				:url="l.url"
				:attribution="l.attribution"
				:name="l.name"
				:layer-type="l.type"
				:tile-layer-class="l.tileLayerClass"
				:options="l.options"
				:opacity="1" />
			<LTileLayer
				v-for="(l, lid) in allOverlayLayers"
				:key="lid"
				:visible="['Watercolor', 'ESRI'].includes(activeLayerId) && lid === 'Roads Overlay'"
				:url="l.url"
				:attribution="l.attribution"
				:name="l.name"
				:layer-type="l.type"
				:options="l.options"
				:opacity="l.opacity" />
			<PhotosLayer
				v-if="map && photosEnabled"
				:map="map"
				:photos="photos"
				@coords-reset="$emit('coords-reset')" />
			<ContactsLayer
				v-if="map && contactsEnabled"
				:contacts="contacts"
				:groups="contactGroups"
				@address-deleted="$emit('address-deleted')" />
			<PlaceContactPopup v-if="placingContact"
				:lat-lng="placingContactLatLng"
				@contact-placed="onContactPlaced" />
		</LMap>
		<Slider v-show="sliderEnabled"
			:min="3"
			:max="33" />
	</div>
</template>

<script>
import { getLocale } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

/* import {
	L,
	// DivIcon,
	latLngBounds,
} from 'leaflet' */
import L from 'leaflet'
import 'mapbox-gl/dist/mapbox-gl'
import 'mapbox-gl-leaflet/leaflet-mapbox-gl'
import {
	// LayerIds,
	// Layers,
	baseLayersByName,
	overlayLayersByName,
} from '../data/mapLayers'
import { LControlScale, LControlZoom, LMap, LTileLayer, LControlLayers } from 'vue2-leaflet'

import 'leaflet-easybutton/src/easy-button'
import 'leaflet-easybutton/src/easy-button.css'
import 'leaflet-contextmenu/dist/leaflet.contextmenu.min'
import 'leaflet-contextmenu/dist/leaflet.contextmenu.min.css'

import Slider from '../components/map/Slider'
import RoutingControl from '../components/map/RoutingControl'
import PhotosLayer from '../components/map/PhotosLayer'
import ContactsLayer from '../components/map/ContactsLayer'
import PlaceContactPopup from '../components/map/PlaceContactPopup'
import { getCurrentUser } from '@nextcloud/auth'
import optionsController from '../optionsController'

export default {
	name: 'Map',

	components: {
		LMap,
		LControlScale,
		LControlZoom,
		LControlLayers,
		LTileLayer,
		Slider,
		RoutingControl,
		PhotosLayer,
		ContactsLayer,
		PlaceContactPopup,
	},

	props: {
		photos: {
			type: Array,
			required: true,
		},
		photosEnabled: {
			type: Boolean,
			required: true,
		},
		contacts: {
			type: Array,
			required: true,
		},
		contactGroups: {
			type: Object,
			required: true,
		},
		contactsEnabled: {
			type: Boolean,
			required: true,
		},
		sliderEnabled: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			locale: getLocale(),
			optionValues: optionsController.optionValues,
			// map
			map: null,
			mapOptions: {
				center: [0, 0],
				zoom: 2,
				minZoom: 2,
				maxZoom: 19,
				bounds: L.latLngBounds(optionsController.bounds),
				maxBounds: L.latLngBounds([
					[-90, 720],
					[90, -720],
				]),
				native: {
					zoomControl: false,
					contextmenu: false,
					contextmenuWidth: 160,
					contextmenuItems: this.getContextmenuItems(),
				},
				scaleControlShouldUseImperial: false,
			},
			// layers
			allBaseLayers: {},
			allOverlayLayers: {},
			defaultStreetLayer: 'Open Street Map',
			defaultSatelliteLayer: 'ESRI',
			activeLayerId: null,
			layersButton: null,
			streetButton: null,
			satelliteButton: null,
			showExtraLayers: false,
			// routing
			showRouting: false,
			// contacts
			placingContactLatLng: null,
			placingContact: false,
		}
	},

	methods: {
		onMapReady(map) {
			this.initLayers(map)
			this.map = map
		},
		fitBounds(latLng) {
			this.map.fitBounds(latLng)
		},
		getContextmenuItems() {
			const cmi = [
				{
					text: t('maps', 'Add a favorite'),
					icon: generateUrl('/svg/core/actions/starred?color=000000'),
					callback: () => {},
				}, {
					text: t('maps', 'Place photos'),
					icon: generateUrl('/svg/core/places/picture?color=000000'),
					callback: this.contextPlacePhotos,
				}, {
					text: t('maps', 'Place contact'),
					icon: generateUrl('/svg/core/actions/user?color=000000'),
					callback: this.placeContactClicked,
				}, {
					text: t('maps', 'Share this location'),
					icon: generateUrl('/svg/core/actions/share?color=000000'),
					callback: () => {},
				},
			]
			if (optionsController.nbRouters > 0 || getCurrentUser().isAdmin) {
				const routingItems = [
					'-',
					{
						text: t('maps', 'Route from here'),
						icon: generateUrl('/svg/core/filetypes/location?color=00cc00'),
						callback: () => {},
					}, {
						text: t('maps', 'Add route point'),
						icon: generateUrl('/svg/core/filetypes/location?color=0000cc'),
						callback: () => {},
					}, {
						text: t('maps', 'Route to here'),
						icon: generateUrl('/svg/core/filetypes/location?color=cc0000'),
						callback: () => {},
					},
				]
				cmi.push(...routingItems)
			}
			return cmi
		},
		onMapClick(e) {
			// layers management stuff
			document.querySelector('.leaflet-control-layers').style.display = 'none'
			this.layersButton.button.parentElement.classList.remove('hidden')
			this.streetButton.button.parentElement.classList.remove('hidden')
			this.satelliteButton.button.parentElement.classList.remove('hidden')

			this.map.contextmenu.hide()
			this.placingContact = false
		},
		onMapContextmenu(e) {
			if (e.originalEvent.target.classList.contains('vue2leaflet-map') || e.originalEvent.target.classList.contains('mapboxgl-map')) {
				this.map.contextmenu.showAt(L.latLng(e.latlng.lat, e.latlng.lng))
			}
		},
		initLayers(map) {
			// tile layers
			this.allBaseLayers = baseLayersByName
			this.allOverlayLayers = overlayLayersByName

			// detect Webgl
			const canvas = document.createElement('canvas')
			// let experimental = false
			let gl

			try {
				gl = canvas.getContext('webgl')
			} catch (x) {
				gl = null
			}

			if (gl == null) {
				try {
					gl = canvas.getContext('experimental-webgl')
					// experimental = true
				} catch (x) { gl = null }
			}

			if ('mapboxAPIKEY' in this.optionValues && this.optionValues.mapboxAPIKEY !== '' && gl !== null) {
				// wrapper to make tile layer component correctly pass arguments
				L.myMapboxGL = function(url, options) {
					return new L.MapboxGL(options)
				}

				this.defaultStreetLayer = 'Mapbox vector streets'
				this.defaultSatelliteLayer = 'Mapbox satellite'

				// add mapbox-gl tile servers
				const attrib = '<a href="https://www.mapbox.com/about/maps/">© Mapbox</a> '
					+ '<a href="https://www.openstreetmap.org/copyright">© OpenStreetMap</a> '
					+ '<a href="https://www.mapbox.com/map-feedback/">' + t('maps', 'Improve this map') + '</a>'
				const attribSat = attrib + '<a href="https://www.digitalglobe.com/">© DigitalGlobe</a>'

				this.allOverlayLayers = {}
				this.allBaseLayers = {
					'Mapbox vector streets': {
						name: 'Street map',
						type: 'base',
						attribution: attrib,
						tileLayerClass: L.myMapboxGL,
						options: {
							id: 'Mapbox vector streets',
							accessToken: this.optionValues.mapboxAPIKEY,
							style: 'mapbox://styles/mapbox/streets-v8',
							minZoom: 1,
							maxZoom: 22,
							attribution: attrib,
						},
					},
					'Mapbox satellite': {
						name: 'Satellite map',
						type: 'base',
						attribution: attrib,
						tileLayerClass: L.myMapboxGL,
						options: {
							id: 'Mapbox satellite',
							accessToken: this.optionValues.mapboxAPIKEY,
							style: 'mapbox://styles/mapbox/satellite-streets-v9',
							minZoom: 1,
							maxZoom: 22,
							attribution: attribSat,
						},
					},
					Topographic: {
						name: 'Topographic',
						type: 'base',
						attribution: attrib,
						tileLayerClass: L.myMapboxGL,
						options: {
							id: 'Topographic',
							accessToken: this.optionValues.mapboxAPIKEY,
							style: 'mapbox://styles/mapbox/outdoors-v11',
							minZoom: 1,
							maxZoom: 22,
							attribution: attrib,
						},
					},
					'Mapbox dark': {
						name: 'Dark',
						type: 'base',
						attribution: attrib,
						tileLayerClass: L.myMapboxGL,
						options: {
							id: 'Mapbox dark',
							accessToken: this.optionValues.mapboxAPIKEY,
							style: 'mapbox://styles/mapbox/dark-v8',
							minZoom: 1,
							maxZoom: 22,
							attribution: attrib,
						},
					},
					Watercolor: baseLayersByName.Watercolor,
				}
			}

			// LAYER BUTTONS
			this.layersButton = L.easyButton({
				position: 'bottomright',
				states: [{
					stateName: 'no-importa',
					icon: '<a class="icon icon-menu" style="height: 100%"> </a>',
					title: t('maps', 'Other maps'),
					onClick: (btn, map) => {
						document.querySelector('.leaflet-control-layers').style.display = 'block'
						btn.button.parentElement.classList.add('hidden')
						this.streetButton.button.parentElement.classList.add('hidden')
						this.satelliteButton.button.parentElement.classList.add('hidden')
					},
				}],
			})
			this.layersButton.addTo(map)

			this.streetButton = L.easyButton({
				position: 'bottomright',
				states: [{
					stateName: 'no-importa',
					icon: '<a class="icon icon-osm" style="height: 100%"> </a>',
					title: t('maps', 'Street map'),
					onClick: (btn, map) => {
						this.activeLayerId = this.defaultStreetLayer
						btn.button.parentElement.classList.add('behind')
						this.satelliteButton.button.parentElement.classList.remove('behind')
						this.showRouting = !this.showRouting
					},
				}],
			})
			this.streetButton.addTo(map)

			this.satelliteButton = L.easyButton({
				position: 'bottomright',
				states: [{
					stateName: 'no-importa',
					icon: '<a class="icon icon-esri" style="height: 100%"> </a>',
					title: t('maps', 'Satellite map'),
					onClick: (btn, map) => {
						this.activeLayerId = this.defaultSatelliteLayer
						btn.button.parentElement.classList.add('behind')
						this.streetButton.button.parentElement.classList.remove('behind')
						this.showRouting = !this.showRouting
					},
				}],
			})
			this.satelliteButton.addTo(map)

			this.streetButton.button.parentElement.classList.add('behind')

			// initial selected layer, restore or fallback to default street
			if (this.optionValues.tileLayer in this.allBaseLayers) {
				this.activeLayerId = this.optionValues.tileLayer
			} else {
				this.activeLayerId = this.defaultStreetLayer
			}
			console.debug(this.activeLayerId)

			document.querySelector('.leaflet-control-layers').style.display = 'none'
		},
		onBaselayerchange(e) {
			this.activeLayerId = e.layer.options.id
			if (this.activeLayerId === this.defaultStreetLayer) {
				this.streetButton.button.parentElement.classList.add('behind')
				this.satelliteButton.button.parentElement.classList.remove('behind')
			} else {
				this.streetButton.button.parentElement.classList.remove('behind')
				this.satelliteButton.button.parentElement.classList.add('behind')
			}
			optionsController.saveOptionValues({ tileLayer: this.activeLayerId })

			// take care of max zoom issue
			if (e.layer.options.maxZoom) {
				e.layer._map.setMaxZoom(e.layer.options.maxZoom)
			}
			// buttons/control visibility
			document.querySelector('.leaflet-control-layers').style.display = 'none'
			this.layersButton.button.parentElement.classList.remove('hidden')
			this.streetButton.button.parentElement.classList.remove('hidden')
			this.satelliteButton.button.parentElement.classList.remove('hidden')
		},
		onUpdateBounds(b) {
			const boundsStr = b.getNorth() + ';' + b.getSouth() + ';' + b.getEast() + ';' + b.getWest()
			optionsController.saveOptionValues({ mapBounds: boundsStr })
		},
		onRoutingClose() {
			this.showRouting = false
		},
		// contacts
		placeContactClicked(e) {
			this.placingContactLatLng = L.latLng(e.latlng.lat, e.latlng.lng)
			this.placingContact = true
		},
		onContactPlaced() {
			this.placingContact = false
			this.$emit('contact-placed')
		},
		// photos
		contextPlacePhotos(e) {
			this.$emit('place-photos', e.latlng)
		},
	},
}
</script>

<style lang="scss" scoped>
@import '~leaflet/dist/leaflet.css';
@import '~leaflet.markercluster/dist/MarkerCluster.css';
@import '~leaflet.markercluster/dist/MarkerCluster.Default.css';

#map-wrapper {
	display: flex;
	height: 100%;
	width: 100%;
}

.leaflet-container {
	position: relative;
	height: 100%;
	width: 100%;
}

::v-deep .icon-osm {
	background-image: url('./../../css/images/osm.png');
	background-size: 35px;
}

::v-deep .icon-esri {
	background:  url('./../../css/images/esri.jpg');
	background-size: 35px;
	border: none !important;
}

::v-deep .easy-button-container.behind {
	display: none;
}

::v-deep .easy-button-container.hidden {
	display: none;
}

::v-deep .leaflet-marker-photo,
::v-deep .leaflet-marker-contact {
	width: 40px !important;
	height: 40px !important;
}

::v-deep .placement-marker-icon {
	border-radius: 50%;
	object-fit: cover;
	border: 2px solid var(--color-border);
}

::v-deep .popup-photo-wrapper .action {
	height: 44px;
	.action-button {
		height: 44px !important;
		padding: 0 !important;
	}
}
</style>
