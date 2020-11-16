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
					<LControlZoom position="bottomright" />
					<LControlLayers
						position="bottomright"
						:collapsed="true" />
					<LControlScale
						position="bottomleft"
						:imperial="mapOptions.scaleControlShouldUseImperial"
						:metric="!mapOptions.scaleControlShouldUseImperial" />
					<LTileLayer
						v-for="l in layers"
						:key="l.id"
						:visible="activeLayerName === l.name"
						:url="l.url"
						:attribution="l.attribution"
						:name="l.name"
						:layer-type="l.type"
						:options="l.options"
						:opacity="l.type === 'overlay' ? l.opacity : 1" />
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
import Content from '@nextcloud/vue/dist/Components/Content'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Actions from '@nextcloud/vue/dist/Components/Actions'
/* import {
	L,
	// DivIcon,
	latLngBounds,
} from 'leaflet' */
import L from 'leaflet'
import {
	// LayerIds,
	Layers,
} from '../data/mapLayers'
import { LControlScale, LControlZoom, LMap, LTileLayer, LControlLayers } from 'vue2-leaflet'

import 'leaflet-easybutton/src/easy-button'
import 'leaflet-easybutton/src/easy-button.css'

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
	},

	data() {
		return {
			activeLayerName: 'Open Street Map',
			streetButton: null,
			satelliteButton: null,
			showExtraLayers: false,
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
			this.streetButton = L.easyButton({
				position: 'bottomright',
				states: [{
					stateName: 'no-importa',
					icon: '<a class="icon icon-osm" style="height: 100%"> </a>',
					title: t('maps', 'Street map'),
					onClick: (btn, map) => {
						this.activeLayerName = 'Open Street Map'
						btn.button.parentElement.classList.add('hidden')
						this.satelliteButton.button.parentElement.classList.remove('hidden')
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
						this.activeLayerName = 'ESRI'
						btn.button.parentElement.classList.add('hidden')
						this.streetButton.button.parentElement.classList.remove('hidden')
					},
				}],
			})
			this.satelliteButton.addTo(map)

			this.streetButton.button.parentElement.classList.add('hidden')
		},
		onBaselayerchange(e) {
			console.debug(e)
			this.activeLayerName = e.name
			if (e.name === 'Open Street Map') {
				this.streetButton.button.parentElement.classList.add('hidden')
				this.satelliteButton.button.parentElement.classList.remove('hidden')
			} else {
				this.streetButton.button.parentElement.classList.remove('hidden')
				this.satelliteButton.button.parentElement.classList.add('hidden')
			}
		},
		onUpdateBounds(b) {
			const boundsStr = b.getNorth() + ';' + b.getSouth() + ';' + b.getEast() + ';' + b.getWest()
			optionsController.saveOptionValues({ mapBounds: boundsStr })
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
