<template>
	<div id="map-wrapper" :class="mapStateClass">
		<MglMap
			ref="mapRef"
			:map-style="mapStyle"
			:center="[0, 0]"
			:zoom="2"
			:min-zoom="2"
			:max-zoom="22"
			:bounds="initialBounds"
			:fit-bounds-options="{ padding: 30 }"
			width="100%"
			height="100%"
			@map:load="onMapReady"
			@map:click="onMapClick"
			@map:contextmenu="onMapContextmenu"
			@map:moveend="onUpdateBounds">
			<!-- Raster tile layers when active layer is raster (no vector style) -->
			<template v-if="activeBaseLayers.length > 0 && !optionValues.maplibreStreetStyleURL">
				<template v-for="layer in activeBaseLayers" :key="'base-' + layer.id">
					<MglRasterSource
						:source-id="layer.id"
						:tiles="layer.tiles"
						:tile-size="layer.tileSize"
						:attribution="layer.attribution"
						:maxzoom="layer.maxzoom">
						<MglRasterLayer :layer-id="layer.id + '-layer'" />
					</MglRasterSource>
				</template>
				<template v-for="layer in activeOverlayLayers" :key="'overlay-' + layer.id">
					<MglRasterSource
						:source-id="layer.id"
						:tiles="layer.tiles"
						:tile-size="layer.tileSize"
						:attribution="layer.attribution"
						:maxzoom="layer.maxzoom">
						<MglRasterLayer
							:layer-id="layer.id + '-layer'"
							:paint="{ 'raster-opacity': layer.opacity || 1 }" />
					</MglRasterSource>
				</template>
			</template>

			<!-- Controls -->
			<MglNavigationControl position="bottom-right" :show-compass="false" />
			<MglScaleControl position="bottom-left" :unit="scaleUnit" />
			<MglGeolocateControl
				position="bottom-right"
				:track-user-location="false"
				:show-accuracy-circle="true" />

			<!-- Custom controls -->
			<RoutingControl v-if="map"
				v-show="showRouting"
				ref="routingControl"
				:visible="showRouting"
				:map="map"
				:search-data="routingSearchData"
				@close="showRouting = false"
				@track-added="$emit('track-added', $event)" />
			<SearchControl v-if="map"
				v-show="!showRouting"
				:map="map"
				:search-data="searchData"
				:loading="searching"
				:result-poi-number="searchPois.length"
				@validate="onSearchValidate"
				@routing-clicked="showRouting = true"
				@clear-pois="searchPois = []" />
			<HistoryControl v-if="map"
				position="top-right"
				:last-actions="lastActions"
				:last-canceled-actions="lastCanceledActions"
				@cancel="$emit('cancel')"
				@redo="$emit('redo')" />

			<!-- Map layers -->
			<FavoritesLayer
				v-if="map && favoritesEnabled"
				ref="favoritesLayer"
				:map="map"
				:favorites="favorites"
				:categories="favoriteCategories"
				:draggable="favoritesDraggable"
				@click="$emit('click-favorite', $event)"
				@add-to-map-favorite="$emit('add-to-map-favorite', $event)"
				@edit="$emit('edit-favorite', $event)"
				@delete="$emit('delete-favorite', $event)"
				@delete-multiple="$emit('delete-favorites', $event)" />
			<PhotosLayer
				v-if="map && photosEnabled && !photoSuggestionsHidePhotos"
				ref="photosLayer"
				:map="map"
				:photos="photos"
				:date-filter-enabled="sliderEnabled"
				:date-filter-start="sliderStartTimestamp"
				:date-filter-end="sliderEndTimestamp"
				:draggable="photosDraggable"
				@add-to-map-photo="$emit('add-to-map-photo', $event)"
				@coords-reset="$emit('coords-reset', $event)"
				@photo-moved="onPhotoMoved"
				@open-sidebar="$emit('open-sidebar', $event)"
				@cluster-loading="$emit('photo-clusters-loading', $event)"
				@cluster-loaded="$emit('photo-clusters-loaded')" />
			<PhotoSuggestionsLayer
				v-if="map && photosEnabled && showPhotoSuggestions"
				ref="photoSuggestionsLayer"
				:map="map"
				:photo-suggestions="photoSuggestions"
				:photo-suggestions-tracks-and-devices="photoSuggestionsTracksAndDevices"
				:photo-suggestions-selected-indices="photoSuggestionsSelectedIndices"
				:date-filter-enabled="sliderEnabled"
				:date-filter-start="sliderStartTimestamp"
				:date-filter-end="sliderEndTimestamp"
				:draggable="photosDraggable"
				@photo-suggestion-moved="onPhotoSuggestionMoved"
				@photo-suggestion-selected="$emit('photo-suggestion-selected', $event)" />
			<ContactsLayer
				v-if="map && contactsEnabled"
				ref="contactsLayer"
				:contacts="contacts"
				:groups="contactGroups"
				@address-deleted="$emit('address-deleted', $event)"
				@add-to-map-contact="$emit('add-to-map-contact', $event)" />
			<TracksLayer
				v-if="map && tracksEnabled"
				ref="tracksLayer"
				:map="map"
				:tracks="tracks"
				:start="sliderStartTimestamp"
				:end="sliderEndTimestamp"
				@add-to-map-track="$emit('add-to-map-track', $event)"
				@click="$emit('click-track', $event)"
				@change-color="$emit('change-track-color', $event)"
				@display-elevation="$emit('display-elevation', $event)" />
			<DevicesLayer
				v-if="map && devicesEnabled"
				ref="devicesLayer"
				:map="map"
				:devices="devices"
				:start="sliderStartTimestamp"
				:end="sliderEndTimestamp"
				@click="$emit('click-device', $event)"
				@export="$emit('export-device', $event)"
				@toggle-history="$emit('toggle-device-history', $event)"
				@change-color="$emit('change-device-color', $event)"
				@add-to-map-device="$emit('add-to-map-device', $event)" />

			<!-- Popups and POI markers -->
			<ClickSearchPopup v-if="leftClickSearching"
				:lat-lng="leftClickSearchLatLng"
				:favorite-is-creatable="isFavoriteCreatable"
				:contact-is-creatable="isContactCreatable"
				@place-contact="onAddContactAddress"
				@add-favorite="$emit('add-address-favorite', $event); leftClickSearching = false" />
			<PlaceContactPopup v-if="placingContact"
				:lat-lng="placingContactLatLng"
				@contact-placed="onContactPlaced" />
			<PoiMarker v-for="poi in searchPois"
				:key="poi.place_id"
				:poi="poi"
				@place-contact="onAddContactAddress"
				@add-favorite="$emit('add-address-favorite', $event); leftClickSearching = false" />
		</MglMap>

		<!-- Layer switcher buttons -->
		<div class="map-layer-buttons maplibregl-ctrl maplibregl-ctrl-group">
			<button
				class="maplibregl-ctrl-icon"
				:class="{ behind: activeLayerId === defaultStreetLayer }"
				:title="t('maps', 'Street map')"
				@click="setLayer(defaultStreetLayer)">
				<span class="icon icon-osm" />
			</button>
			<button
				class="maplibregl-ctrl-icon"
				:class="{ behind: activeLayerId === defaultSatelliteLayer }"
				:title="t('maps', 'Satellite map')"
				@click="setLayer(defaultSatelliteLayer)">
				<span class="icon icon-esri" />
			</button>
			<button
				class="maplibregl-ctrl-icon"
				:title="t('maps', 'Other maps')"
				@click="showLayerPicker = !showLayerPicker">
				<span class="icon icon-menu" />
			</button>
			<div v-if="showLayerPicker" class="layer-picker">
				<div
					v-for="layer in allBaseLayers"
					:key="layer.id"
					class="layer-picker-item"
					:class="{ active: activeLayerId === layer.id }"
					@click="setLayer(layer.id); showLayerPicker = false">
					{{ layer.name }}
				</div>
			</div>
		</div>

		<!-- Context menu -->
		<div v-if="contextMenu.visible"
			class="maps-context-menu"
			:style="{ left: contextMenu.x + 'px', top: contextMenu.y + 'px' }"
			@click.stop>
			<template v-for="item in contextMenuItems" :key="item === '-' ? 'sep-' + Math.random() : item.text">
				<hr v-if="item === '-'" class="context-separator">
				<div v-else class="context-menu-item" @click="invokeContextItem(item)">
					<span :class="'icon ' + item.iconCls" />
					{{ item.text }}
				</div>
			</template>
		</div>

		<Slider v-show="sliderEnabled"
			:start="sliderStartTimestamp"
			:end="sliderEndTimestamp"
			:range-min="minDataTimestamp"
			:range-max="maxDataTimestamp"
			@range-change="$emit('slider-range-changed', $event)" />
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { addProtocol } from 'maplibre-gl'
import { Protocol } from 'pmtiles'
import {
	MglMap,
	MglNavigationControl,
	MglScaleControl,
	MglGeolocateControl,
	MglRasterSource,
	MglRasterLayer,
} from '@indoorequal/vue-maplibre-gl'

import {
	Layers,
	LayerTypes,
	LayerIds,
	baseLayersByName,
	overlayLayersByName,
} from '../data/mapLayers.js'

import Slider from '../components/map/Slider.vue'
import SearchControl from '../components/map/SearchControl.vue'
import HistoryControl from '../components/map/HistoryControl.vue'
import RoutingControl from '../components/map/routing/RoutingControl.vue'
import FavoritesLayer from '../components/map/FavoritesLayer.vue'
import PhotosLayer from '../components/map/PhotosLayer.vue'
import TracksLayer from '../components/map/TracksLayer.vue'
import DevicesLayer from '../components/map/DevicesLayer.vue'
import ContactsLayer from '../components/map/ContactsLayer.vue'
import PlaceContactPopup from '../components/map/PlaceContactPopup.vue'
import PoiMarker from '../components/map/PoiMarker.vue'
import ClickSearchPopup from '../components/map/ClickSearchPopup.vue'
import optionsController from '../optionsController.js'
import PhotoSuggestionsLayer from './map/PhotoSuggestionsLayer.vue'

export default {
	name: 'Map',

	components: {
		MglMap,
		MglNavigationControl,
		MglScaleControl,
		MglGeolocateControl,
		MglRasterSource,
		MglRasterLayer,
		Slider,
		HistoryControl,
		SearchControl,
		RoutingControl,
		FavoritesLayer,
		PhotosLayer,
		PhotoSuggestionsLayer,
		TracksLayer,
		DevicesLayer,
		ContactsLayer,
		PlaceContactPopup,
		ClickSearchPopup,
		PoiMarker,
	},

	props: {
		activeLayerIdProp: {
			type: String,
			required: true,
		},
		mapBoundsProp: {
			type: Array,
			required: true,
		},
		searchData: {
			type: Array,
			required: true,
		},
		routingSearchData: {
			type: Array,
			required: true,
		},
		favorites: {
			type: Object,
			required: true,
		},
		favoriteCategories: {
			type: Object,
			required: true,
		},
		favoritesEnabled: {
			type: Boolean,
			required: true,
		},
		favoritesDraggable: {
			type: Boolean,
			required: true,
		},
		photos: {
			type: Array,
			required: true,
		},
		photosEnabled: {
			type: Boolean,
			required: true,
		},
		photosDraggable: {
			type: Boolean,
			required: true,
		},
		showPhotoSuggestions: {
			type: Boolean,
			required: true,
		},
		photoSuggestions: {
			type: Array,
			required: true,
		},
		photoSuggestionsTracksAndDevices: {
			type: Object,
			required: true,
		},
		photoSuggestionsSelectedIndices: {
			type: Array,
			required: true,
		},
		photoSuggestionsHidePhotos: {
			type: Boolean,
			default: false,
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
		tracks: {
			type: Array,
			required: true,
		},
		tracksEnabled: {
			type: Boolean,
			required: true,
		},
		devices: {
			type: Array,
			required: true,
		},
		devicesEnabled: {
			type: Boolean,
			required: true,
		},
		sliderEnabled: {
			type: Boolean,
			required: true,
		},
		sliderStartTimestamp: {
			type: Number,
			required: true,
		},
		sliderEndTimestamp: {
			type: Number,
			required: true,
		},
		minDataTimestamp: {
			type: Number,
			required: true,
		},
		maxDataTimestamp: {
			type: Number,
			required: true,
		},
		state: {
			type: String,
			default: '',
		},
		lastActions: {
			type: Array,
			required: true,
		},
		lastCanceledActions: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
			optionValues: optionsController.optionValues,
			map: null,
			// layers
			allBaseLayers: Object.values(baseLayersByName),
			allOverlayLayers: Object.values(overlayLayersByName),
			defaultStreetLayer: LayerIds.OpenFreeMap,
			defaultSatelliteLayer: LayerIds.ESRI,
			activeLayerId: this.activeLayerIdProp || LayerIds.OpenFreeMap,
			showLayerPicker: false,
			// context menu
			contextMenu: {
				visible: false,
				x: 0,
				y: 0,
				lngLat: null,
			},
			// routing
			showRouting: false,
			// contacts
			placingContact: false,
			placingContactLatLng: null,
			// poi
			searchPois: [],
			searching: false,
			// left click search
			leftClickSearching: false,
			leftClickSearchLatLng: null,
		}
	},

	computed: {
		mapStyle() {
			// Admin-configured custom vector style takes highest priority
			if (this.optionValues.maplibreStreetStyleURL) {
				return this.optionValues.maplibreStreetStyleURL
			}
			// If the active layer is a vector layer, return its style URL
			const activeLayer = this.allBaseLayers.find(l => l.id === this.activeLayerId)
			if (activeLayer?.styleUrl) {
				return activeLayer.styleUrl
			}
			// Fallback: empty style for raster-only layers
			return {
				version: 8,
				sources: {},
				layers: [],
				glyphs: 'https://demotiles.maplibre.org/font/{fontstack}/{range}.pbf',
			}
		},
		initialBounds() {
			if (this.mapBoundsProp && this.mapBoundsProp.length === 2) {
				// mapBoundsProp is [[north, east], [south, west]] from optionsController
				const [[n, e], [s, w]] = this.mapBoundsProp
				return [[w, s], [e, n]]
			}
			return undefined
		},
		scaleUnit() {
			return optionsController.optionValues?.useImperialUnits ? 'imperial' : 'metric'
		},
		mapStateClass() {
			return {
				loading: this.state === 'loading',
				adding: this.state === 'adding',
			}
		},
		activeBaseLayers() {
			// Only return raster layers (vector layers use mapStyle, not MglRasterSource)
			return this.allBaseLayers.filter(l => l.id === this.activeLayerId && !l.styleUrl)
		},
		activeOverlayLayers() {
			// Show roads overlay on top of satellite/watercolor layers
			const satelliteIds = [LayerIds.ESRI, LayerIds.ESRITopo, LayerIds.Watercolor]
			if (satelliteIds.includes(this.activeLayerId)) {
				return this.allOverlayLayers
			}
			return []
		},
		isFavoriteCreatable() {
			const favArray = Object.values(this.favorites)
			return (favArray.some((f) => f.isUpdateable) || (favArray.length === 0 && this.optionValues.isCreatable))
		},
		isContactCreatable() {
			return this.optionValues.isCreatable
		},
		contextMenuItems() {
			const cmi = [
				{
					text: t('maps', 'Add a favorite'),
					iconCls: 'icon-favorite',
					callback: (lngLat) => this.$emit('add-favorite', { lat: lngLat.lat, lng: lngLat.lng }),
				},
				{
					text: t('maps', 'Place photos'),
					iconCls: 'icon-category-multimedia',
					callback: (lngLat) => this.$emit('place-photos', { lat: lngLat.lat, lng: lngLat.lng }),
				},
				{
					text: t('maps', 'Place contact'),
					iconCls: 'icon-group',
					callback: (lngLat) => {
						this.placingContactLatLng = { lat: lngLat.lat, lng: lngLat.lng }
						this.placingContact = true
					},
				},
				{
					text: t('maps', 'Share this location'),
					iconCls: 'icon-address',
					callback: async (lngLat) => {
						const geoLink = 'geo:' + lngLat.lat.toFixed(6) + ',' + lngLat.lng.toFixed(6)
						try {
							await navigator.clipboard.writeText(geoLink)
							showSuccess(t('maps', 'Geo link ({geoLink}) copied to clipboard', { geoLink }))
						} catch (error) {
							console.debug(error)
							showError(t('maps', 'Geo link could not be copied to clipboard'))
						}
					},
				},
			]
			window.OCA.Maps.mapActions.forEach((action) => {
				cmi.push({
					text: action.label,
					iconCls: action.icon,
					callback: (lngLat) => {
						action.callback({
							id: 'geo:' + lngLat.lat + ',' + lngLat.lng,
							name: t('maps', 'Shared location'),
							latitude: lngLat.lat.toString(),
							longitude: lngLat.lng.toString(),
						})
					},
				})
			})
			if (optionsController.nbRouters > 0 || getCurrentUser()?.isAdmin) {
				cmi.push(
					'-',
					{
						text: t('maps', 'Route from here'),
						iconCls: 'icon-address',
						callback: (lngLat) => {
							this.showRouting = true
							this.$refs.routingControl.setRouteFrom(lngLat)
						},
					},
					{
						text: t('maps', 'Add route point'),
						iconCls: 'icon-add',
						callback: (lngLat) => {
							this.showRouting = true
							this.$refs.routingControl.addRoutePoint(lngLat)
						},
					},
					{
						text: t('maps', 'Route to here'),
						iconCls: 'icon-address',
						callback: (lngLat) => {
							this.showRouting = true
							this.$refs.routingControl.setRouteTo(lngLat)
						},
					},
				)
			}
			return cmi
		},
	},

	watch: {
		activeLayerIdProp(newValue) {
			this.activeLayerId = newValue
		},
	},

	created() {
		if ('maplibreStreetStylePmtiles' in this.optionValues
			&& this.optionValues.maplibreStreetStylePmtiles === '1') {
			const protocol = new Protocol()
			addProtocol('pmtiles', protocol.tile)
		}
	},

	methods: {
		onMapReady(map) {
			this.map = map
		},
		fitBounds(bounds, options = {}) {
			// bounds: [[lngMin, latMin], [lngMax, latMax]] (MapLibre format)
			this.map.fitBounds(bounds, { padding: 30, ...options })
		},
		setLayer(layerId) {
			this.activeLayerId = layerId
			optionsController.saveOptionValues({ tileLayer: layerId })
		},
		onUpdateBounds() {
			const b = this.map.getBounds()
			const boundsStr = b.getNorth() + ';' + b.getSouth() + ';' + b.getEast() + ';' + b.getWest()
			optionsController.saveOptionValues({ mapBounds: boundsStr })
		},
		onMapClick(e) {
			if (this.contextMenu.visible) {
				this.contextMenu.visible = false
				return
			}
			if (this.state === 'adding') {
				this.$emit('add-click', { latlng: e.lngLat })
				return
			}
			const hadPopup = this.placingContact || this.leftClickSearching
			this.placingContact = false
			this.leftClickSearching = false
			if (!hadPopup) {
				this.leftClickSearch(e.lngLat.lat, e.lngLat.lng)
			}
		},
		onMapContextmenu(e) {
			this.leftClickSearching = false
			this.contextMenu.visible = true
			this.contextMenu.x = e.point.x
			this.contextMenu.y = e.point.y
			this.contextMenu.lngLat = e.lngLat
		},
		invokeContextItem(item) {
			this.contextMenu.visible = false
			item.callback(this.contextMenu.lngLat)
		},
		leftClickSearch(lat, lng) {
			this.leftClickSearchLatLng = { lat, lng }
			this.leftClickSearching = true
		},
		onAddContactAddress(obj) {
			this.leftClickSearching = false
			this.placingContactLatLng = { lat: obj.latLng.lat, lng: obj.latLng.lng }
			this.placingContact = true
		},
		onContactPlaced(e) {
			this.placingContact = false
			this.$emit('contact-placed', e)
		},
		onPhotoMoved(photo, latLng) {
			this.$emit('photo-moved', photo, latLng)
		},
		onPhotoSuggestionMoved(index, latLng) {
			this.$emit('photo-suggestion-moved', index, latLng)
		},
		zoomOnPhotoSuggestion(photo) {
			if (photo.lat && photo.lng) {
				this.map.fitBounds(
					[[photo.lng - 0.001, photo.lat - 0.001], [photo.lng + 0.001, photo.lat + 0.001]],
					{ padding: 30 },
				)
			}
		},
		zoomOnTrack(track) {
			if (track.metadata) {
				const md = track.metadata
				this.map.fitBounds([[md.w, md.s], [md.e, md.n]], { padding: 30 })
			}
		},
		zoomOnDevice(device) {
			if (device.points && device.points.length > 0) {
				const lats = device.points.map(p => p.lat)
				const lngs = device.points.map(p => p.lng)
				this.map.fitBounds(
					[[Math.min(...lngs), Math.min(...lats)], [Math.max(...lngs), Math.max(...lats)]],
					{ padding: 30 },
				)
			}
		},
		onSearchValidate(element) {
			if (['contact', 'favorite'].includes(element.type)) {
				this.map.flyTo({ center: [element.latLng.lng, element.latLng.lat], zoom: 15 })
			} else if (element.type === 'device') {
				if (!element.device.enabled) {
					this.$emit('search-enable-device', element.device)
				} else {
					this.zoomOnDevice(element.device)
				}
			} else if (element.type === 'track') {
				if (!element.track.enabled) {
					this.$emit('search-enable-track', element.track)
				} else {
					this.zoomOnTrack(element.track)
				}
			} else if (element.type === 'poi') {
				this.searching = true
				const b = this.map.getBounds()
				const query = element.subtype + '=' + encodeURIComponent(element.value)
				const apiUrl = 'https://nominatim.openstreetmap.org/search'
					+ '?format=json&addressdetails=1&extratags=1&namedetails=1&limit=100&'
					+ 'viewbox=' + b.getWest() + ',' + b.getSouth() + ',' + b.getEast() + ',' + b.getNorth() + '&'
					+ 'bounded=1&' + query
				axios.get(apiUrl).then((response) => {
					this.searchPois = response.data
					this.searching = false
				})
			} else if (element.type === 'result') {
				this.searchPois = [element.rawResult]
				this.map.flyTo({ center: [element.latLng.lng, element.latLng.lat], zoom: 15 })
			} else if (element.type === 'coordinate') {
				this.leftClickSearch(element.lat, element.lng)
				this.map.flyTo({ center: [element.lng, element.lat], zoom: 15 })
			} else if (element.type === 'mylocation') {
				navigator.geolocation.getCurrentPosition((position) => {
					const lat = position.coords.latitude
					const lng = position.coords.longitude
					this.map.flyTo({ center: [lng, lat], zoom: 15 })
				})
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@import 'maplibre-gl/dist/maplibre-gl.css';

#map-wrapper {
	display: flex;
	position: relative;
	height: 100%;
	width: 100%;

	&.loading :deep(.maplibregl-canvas) {
		cursor: progress;
	}

	&.adding :deep(.maplibregl-canvas) {
		cursor: crosshair;
	}
}

:deep(.maplibregl-map) {
	height: 100%;
	width: 100%;
	cursor: grab;
}

:deep(.maplibregl-marker) {
	cursor: pointer;
}

:deep(.maplibregl-popup) {
	max-width: 320px !important;
}

// Layer switcher buttons (positioned bottom-right above nav controls)
.map-layer-buttons {
	position: absolute;
	bottom: 110px;
	right: 10px;
	z-index: 1;
	display: flex;
	flex-direction: column;

	button {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 29px;
		height: 29px;
		background: var(--color-main-background, #fff);
		border: none;
		cursor: pointer;
		padding: 0;

		&.behind {
			display: none;
		}

		&:hover {
			background-color: var(--color-background-hover);
		}
	}

	.layer-picker {
		position: absolute;
		bottom: 100%;
		right: 0;
		min-width: 160px;
		background: var(--color-main-background, #fff);
		border: 1px solid var(--color-border);
		border-radius: 4px;
		box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
		z-index: 10;

		.layer-picker-item {
			padding: 8px 12px;
			cursor: pointer;
			color: var(--color-text-lighter);
			white-space: nowrap;

			&:hover,
			&.active {
				background-color: var(--color-background-hover);
				color: var(--color-main-text);
			}
		}
	}
}

// Custom context menu
.maps-context-menu {
	position: absolute;
	z-index: 1000;
	min-width: 160px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 4px;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
	padding: 4px 0;

	.context-menu-item {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 6px 12px;
		line-height: 30px;
		color: var(--color-text-lighter);
		cursor: pointer;
		white-space: nowrap;

		&:hover {
			color: var(--color-main-text);
			background-color: var(--color-background-hover);
		}

		.icon {
			flex-shrink: 0;
			width: 16px;
			height: 16px;
		}
	}

	.context-separator {
		margin: 4px 0;
		border-color: var(--color-border);
	}
}

:deep(.icon-osm) {
	background-image: url('../css/images/osm.png');
	background-size: 35px;
	width: 35px;
	height: 35px;
}

:deep(.icon-esri) {
	background: url('../css/images/esri.jpg');
	background-size: 35px;
	width: 35px;
	height: 35px;
	border: none !important;
}

:deep(.icon-routing) {
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask: url('../../img/routing.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('../../img/routing.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
}

:deep(.icon-road) {
	background-color: var(--color-main-text);
	mask: url('../../img/road.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('../../img/road.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
}

:deep(.icon-road-thin) {
	background-color: var(--color-main-text);
	mask: url('../../img/road-thin.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('../../img/road-thin.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
}

:deep(.popup-contact-wrapper .action),
:deep(.popup-track-wrapper .action),
:deep(.popup-device-wrapper .action),
:deep(.popup-favorite-wrapper .action),
:deep(.popup-photo-wrapper .action) {
	height: 44px;

	.action-button {
		height: 44px !important;
		padding: 0 !important;
	}
}

:deep(.favoriteMarker),
:deep(.favoriteClusterMarker) {
	box-shadow: 0px 0px 10px #888;
	border-radius: 50%;
}

:deep(.favoriteMarker.selected) {
	box-shadow: 0px 0px 10px #ff0000;
}

:deep(.favoriteClusterMarkerDark) {
	background: url('../../img/star-black.svg') no-repeat 50% 50%;
}

:deep(.placement-marker-icon) {
	border-radius: 50%;
	object-fit: cover;
	border: 2px solid var(--color-border);
}

:deep(.popup-contact-wrapper .action p) {
	height: 44px !important;
	line-height: 28px;
}
</style>
