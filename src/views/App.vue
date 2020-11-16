<template>
	<Content app-name="maps">
		<!--MapsNavigation
			@project-clicked="onProjectClicked"
			@save-option="onSaveOption" /-->
		<AppContent>
			<div id="app-content-wrapper">
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
					@baselayerchange="onBaselayerchange">
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
						:collapsed="true" />
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
				</LMap>
			</div>
			<Actions
				class="content-buttons"
				:title="t('maps', 'Details')">
				<ActionButton
					icon="icon-menu-sidebar"
					@click="onMainDetailClicked" />
			</Actions>
		</AppContent>
		<!--Sidebar
			v-if="currentProjectId"
			:show="showSidebar"
			:active-tab="activeSidebarTab"
			@active-changed="onActiveSidebarTabChanged"
			@close="showSidebar = false" /-->
	</Content>
</template>

<script>
import { getLocale } from '@nextcloud/l10n'
import Content from '@nextcloud/vue/dist/Components/Content'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Actions from '@nextcloud/vue/dist/Components/Actions'
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
	Layers,
	baseLayersByName,
	overlayLayersByName,
} from '../data/mapLayers'
import { LControlScale, LControlZoom, LMap, LTileLayer, LControlLayers } from 'vue2-leaflet'

import 'leaflet-easybutton/src/easy-button'
import 'leaflet-easybutton/src/easy-button.css'

import RoutingControl from '../components/map/RoutingControl'
import optionsController from '../optionsController'

export default {
	name: 'App',

	components: {
		Content,
		AppContent,
		Actions,
		LMap,
		LControlScale,
		LControlZoom,
		LControlLayers,
		LTileLayer,
		RoutingControl,
	},

	data() {
		return {
			locale: getLocale(),
			map: null,
			allBaseLayers: {},
			allOverlayLayers: {},
			defaultStreetLayer: 'Open Street Map',
			defaultSatelliteLayer: 'ESRI',
			activeLayerId: null,
			streetButton: null,
			satelliteButton: null,
			showExtraLayers: false,
			showRouting: false,
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
				},
				scaleControlShouldUseImperial: false,
			},
		}
	},

	computed: {
		layers() {
			return Layers
		},
	},

	created() {
	},
	mounted() {
		// subscribe('nextcloud:unified-search.search', this.filter)
		// subscribe('nextcloud:unified-search.reset', this.cleanSearch)
	},
	beforeDestroy() {
		// unsubscribe('nextcloud:unified-search.search', this.filter)
		// unsubscribe('nextcloud:unified-search.reset', this.cleanSearch)
	},
	methods: {
		onMainDetailClicked() {
			// this.showSidebar = !this.showSidebar
			// this.activeSidebarTab = 'project-settings'
		},
		onMapReady(map) {
			this.initLayers(map)
			this.map = map
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

			const optionsValues = optionsController.optionValues
			if ('mapboxAPIKEY' in optionsValues && optionsValues.mapboxAPIKEY !== '' && gl !== null) {
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
						name: 'Mapbox vector streets',
						type: 'base',
						attribution: attrib,
						tileLayerClass: L.myMapboxGL,
						options: {
							accessToken: optionsValues.mapboxAPIKEY,
							style: 'mapbox://styles/mapbox/streets-v8',
							minZoom: 1,
							maxZoom: 22,
							attribution: attrib,
						},
					},
					Topographic: {
						name: 'Topographic',
						type: 'base',
						attribution: attrib,
						tileLayerClass: L.myMapboxGL,
						options: {
							accessToken: optionsValues.mapboxAPIKEY,
							style: 'mapbox://styles/mapbox/outdoors-v11',
							minZoom: 1,
							maxZoom: 22,
							attribution: attrib,
						},
					},
					'Mapbox satellite': {
						name: 'Mapbox satellite',
						type: 'base',
						attribution: attrib,
						tileLayerClass: L.myMapboxGL,
						options: {
							accessToken: optionsValues.mapboxAPIKEY,
							style: 'mapbox://styles/mapbox/satellite-streets-v9',
							minZoom: 1,
							maxZoom: 22,
							attribution: attribSat,
						},
					},
					'Mapbox dark': {
						name: 'Mapbox dark',
						type: 'base',
						attribution: attrib,
						tileLayerClass: L.myMapboxGL,
						options: {
							accessToken: optionsValues.mapboxAPIKEY,
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
			this.streetButton = L.easyButton({
				position: 'bottomright',
				states: [{
					stateName: 'no-importa',
					icon: '<a class="icon icon-osm" style="height: 100%"> </a>',
					title: t('maps', 'Street map'),
					onClick: (btn, map) => {
						this.activeLayerId = this.defaultStreetLayer
						btn.button.parentElement.classList.add('hidden')
						this.satelliteButton.button.parentElement.classList.remove('hidden')
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
						btn.button.parentElement.classList.add('hidden')
						this.streetButton.button.parentElement.classList.remove('hidden')
					},
				}],
			})
			this.satelliteButton.addTo(map)

			this.streetButton.button.parentElement.classList.add('hidden')

			// initial selected layer, restore or fallback to default street
			if (optionsController.optionValues.tileLayer in this.allBaseLayers) {
				this.activeLayerId = optionsController.optionValues.tileLayer
			} else {
				this.activeLayerId = this.defaultStreetLayer
			}
		},
		onBaselayerchange(e) {
			this.activeLayerId = e.name
			if (e.name === this.defaultStreetLayer) {
				this.streetButton.button.parentElement.classList.add('hidden')
				this.satelliteButton.button.parentElement.classList.remove('hidden')
			} else {
				this.streetButton.button.parentElement.classList.remove('hidden')
				this.satelliteButton.button.parentElement.classList.add('hidden')
			}
			optionsController.saveOptionValues({ tileLayer: this.activeLayerId })

			// take care of max zoom issue
			if (e.layer.options.maxZoom) {
				e.layer._map.setMaxZoom(e.layer.options.maxZoom)
			}
		},
		onUpdateBounds(b) {
			const boundsStr = b.getNorth() + ';' + b.getSouth() + ';' + b.getEast() + ';' + b.getWest()
			optionsController.saveOptionValues({ mapBounds: boundsStr })
		},
		onRoutingClose() {
			this.showRouting = false
		},
	},
}
</script>

<style lang="scss" scoped>
@import '~leaflet/dist/leaflet.css';

.leaflet-container {
	position: relative;
	height: 100%;
	width: 100%;
}

.content-buttons {
	position: absolute !important;
	top: 0px;
	right: 8px;
}

#app-content-wrapper {
	display: flex;
	height: 100%;
}

::v-deep .icon-osm {
	background-image: url('./../../css/images/osm.png');
	background-size: 35px;
}

::v-deep .icon-esri {
	background:  url('./../../css/images/esri.jpg');
	background-size: 35px;
}

::v-deep .easy-button-container.hidden {
	display: none;
}
</style>
