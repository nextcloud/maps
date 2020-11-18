<template>
	<Content app-name="maps">
		<MapsNavigation>
			<template #items>
				<AppNavigationContactsItem
					:selected="contactsEnabled"
					:loading="contactsLoading"
					:contacts="contacts"
					:groups="contactGroups"
					@contacts-clicked="onContactsClicked"
					@group-clicked="onContactGroupClicked" />
			</template>
		</MapsNavigation>
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
					<ContactsLayer
						v-if="contactsEnabled"
						:contacts="contacts"
						:groups="contactGroups" />
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
	// Layers,
	baseLayersByName,
	overlayLayersByName,
} from '../data/mapLayers'
import { LControlScale, LControlZoom, LMap, LTileLayer, LControlLayers } from 'vue2-leaflet'

import 'leaflet-easybutton/src/easy-button'
import 'leaflet-easybutton/src/easy-button.css'

import RoutingControl from '../components/map/RoutingControl'
import ContactsLayer from '../components/map/ContactsLayer'
import MapsNavigation from '../components/MapsNavigation'
import AppNavigationContactsItem from '../components/AppNavigationContactsItem'
import optionsController from '../optionsController'
import * as network from '../network'
import { showError } from '@nextcloud/dialogs'

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
		MapsNavigation,
		AppNavigationContactsItem,
		ContactsLayer,
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
			contactsLoading: false,
			contactsEnabled: optionsController.contactsEnabled,
			contacts: [],
			contactGroups: {},
			disabledContactGroups: [],
		}
	},

	computed: {
	},

	created() {
		window.onclick = (event) => {
			if (event.button === 0) {
				document.querySelector('.leaflet-control-layers').style.display = 'none'
				this.layersButton.button.parentElement.classList.remove('hidden')
				this.streetButton.button.parentElement.classList.remove('hidden')
				this.satelliteButton.button.parentElement.classList.remove('hidden')
			}
		}

		this.getContacts()
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
						name: 'Mapbox vector streets',
						type: 'base',
						attribution: attrib,
						tileLayerClass: L.myMapboxGL,
						options: {
							accessToken: this.optionValues.mapboxAPIKEY,
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
							accessToken: this.optionValues.mapboxAPIKEY,
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
							accessToken: this.optionValues.mapboxAPIKEY,
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

			document.querySelector('.leaflet-control-layers').style.display = 'none'
		},
		onBaselayerchange(e) {
			this.activeLayerId = e.name
			if (e.name === this.defaultStreetLayer) {
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
		onContactsClicked() {
			this.contactsEnabled = !this.contactsEnabled
			optionsController.saveOptionValues({ contactLayer: this.contactsEnabled ? 'true' : 'false' })
		},
		onContactGroupClicked(groupId) {
			this.contactGroups[groupId].enabled = !this.contactGroups[groupId].enabled
			const newDisabledContactGroups = []
			for (const gid in this.contactGroups) {
				if (!this.contactGroups[gid].enabled) {
					newDisabledContactGroups.push(gid)
				}
			}
			optionsController.saveOptionValues({ jsonDisabledContactGroups: JSON.stringify(newDisabledContactGroups) })
		},
		getContacts() {
			this.contactsLoading = true
			this.disabledContactGroups = []
			if ('jsonDisabledContactGroups' in this.optionValues) {
				try {
					this.disabledContactGroups = JSON.parse(this.optionValues.jsonDisabledContactGroups)
				} catch (error) {
					console.error(error)
				}
			}

			network.getContacts().then((response) => {
				this.contacts = response.data
				this.buildContactGroups()
			}).catch((error) => {
				showError(
					t('maps', 'Failed to load contacts')
					+ ': ' + error.response?.request?.responseText
				)
			}).then(() => {
				this.contactsLoading = false
			})
		},
		buildContactGroups() {
			const notGroupedId = '0'
			this.$set(this.contactGroups, notGroupedId, {
				name: t('maps', 'Not grouped'),
				counter: 0,
				enabled: !this.disabledContactGroups.includes(notGroupedId),
			})
			this.contacts.forEach((c) => {
				if (c.GROUPS) {
					try {
						const cGroups = c.GROUPS.split(/[^\\],/).map((name) => {
							return name.replace('\\,', ',')
						})
						if (cGroups.length > 0) {
							cGroups.forEach((g) => {
								if (this.contactGroups[g]) {
									this.contactGroups[g].counter++
								} else {
									this.$set(this.contactGroups, g, {
										name: g,
										counter: 1,
										enabled: !this.disabledContactGroups.includes(g),
									})
								}
							})
						} else {
							this.contactGroups[notGroupedId].counter++
						}
					} catch (error) {
						console.error(error)
					}
				} else {
					this.contactGroups[notGroupedId].counter++
				}
			})
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

::v-deep .easy-button-container.behind {
	display: none;
}

::v-deep .easy-button-container.hidden {
	display: none;
}
</style>
