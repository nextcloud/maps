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
				position="topright"
				:last-actions="lastActions"
				:last-canceled-actions="lastCanceledActions"
				@cancel="$emit('cancel')"
				@redo="$emit('redo')" />
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
				:tile-layer-class="l.tileLayerClass"
				:options="l.options"
				:opacity="l.opacity" />
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
				@open-sidebar="$emit('open-sidebar',$event)"
				@cluster-loading="$emit('photo-clusters-loading',$event)"
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
			<PlaceContactPopup v-if="placingContact"
				:lat-lng="placingContactLatLng"
				@contact-placed="onContactPlaced" />
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
				@display-elevation="displayElevation" />
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
			<ClickSearchPopup v-if="leftClickSearching"
				:lat-lng="leftClickSearchLatLng"
				:favorite-is-creatable="isFavoriteCreatable"
				:contact-is-creatable="isContactCreatable"
				@place-contact="onAddContactAddress"
				@add-favorite="$emit('add-address-favorite', $event); leftClickSearching = false" />
			<LFeatureGroup>
				<PoiMarker v-for="poi in searchPois"
					:key="poi.place_id"
					:poi="poi"
					@place-contact="onAddContactAddress"
					@add-favorite="$emit('add-address-favorite', $event); leftClickSearching = false" />
			</LFeatureGroup>
		</LMap>
		<Slider v-show="sliderEnabled"
			:start="sliderStartTimestamp"
			:end="sliderEndTimestamp"
			:range-min="minDataTimestamp"
			:range-max="maxDataTimestamp"
			@range-change="$emit('slider-range-changed', $event)" />
	</div>
</template>

<script>
import { getLocale } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'

import L from 'leaflet'
import 'mapbox-gl/dist/mapbox-gl'
import 'mapbox-gl/dist/mapbox-gl.css'
import 'mapbox-gl-leaflet/leaflet-mapbox-gl'
import '@maplibre/maplibre-gl-leaflet'
import ResourceType from 'maplibre-gl'
import {
	baseLayersByName,
	overlayLayersByName,
} from '../data/mapLayers.js'
import { LControlScale, LControlZoom, LMap, LTileLayer, LControlLayers, LFeatureGroup } from 'vue2-leaflet'

import 'leaflet-easybutton/src/easy-button'
import 'leaflet-easybutton/src/easy-button.css'
import 'leaflet-contextmenu/dist/leaflet.contextmenu.min'
import 'leaflet-contextmenu/dist/leaflet.contextmenu.min.css'
import 'leaflet.locatecontrol/dist/L.Control.Locate.min'
import 'leaflet.locatecontrol/dist/L.Control.Locate.min.css'
import 'd3/dist/d3.min'
import GeoJSON from 'geojson'
import '@raruto/leaflet-elevation/dist/leaflet-elevation'
import '@raruto/leaflet-elevation/dist/leaflet-elevation.css'

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
		LMap,
		LControlScale,
		LControlZoom,
		LControlLayers,
		LTileLayer,
		LFeatureGroup,
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
			locale: getLocale(),
			isDarkTheme: OCA.Accessibility?.theme === 'dark',
			optionValues: optionsController.optionValues,
			// map
			map: null,
			mapOptions: {
				center: [0, 0],
				zoom: 2,
				minZoom: 2,
				maxZoom: 19,
				bounds: L.latLngBounds(this.mapBoundsProp),
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
				closePopupOnClick: false,
			},
			// layers
			allBaseLayers: {},
			allOverlayLayers: {},
			defaultStreetLayer: 'Open Street Map',
			defaultSatelliteLayer: 'ESRI',
			activeLayerId: this.activeLayerIdProp,
			layersButton: null,
			streetButton: null,
			satelliteButton: null,
			showExtraLayers: false,
			// routing
			showRouting: false,
			// contacts
			placingContact: false,
			placingContactLatLng: null,
			// tracks
			elevationControl: null,
			// poi
			searchPois: [],
			searching: false,
			// left click search
			leftClickSearching: false,
			leftClickSearchLatLng: null,
		}
	},

	computed: {
		isFavoriteCreatable() {
			const favArray = Object.values(this.favorites)
			return (favArray.some((f) => (f.isUpdateable)) || (favArray.length === 0 && this.optionValues.isCreatable))
		},
		isContactCreatable() {
			return this.optionValues.isCreatable
		},
	},

	watch: {
		state() {
			this.$refs.map.$el.classList.remove('loading')
			this.$refs.map.$el.classList.remove('adding')
			if (this.state === 'loading') {
				this.$refs.map.$el.classList.add('loading')
			} else if (this.state === 'adding') {
				this.$refs.map.$el.classList.add('adding')
			}
		},

		activeLayerIdProp(newValue) {
		    this.activeLayerId = newValue
		},

		mapBoundsProp(newValue) {
			this.mapOptions.bounds = L.latLngBounds(newValue)
		},
	},

	methods: {
		onMapReady(map) {
			this.initLocControl(map)
			this.initLayers(map)
			this.map = map
		},
		fitBounds(latLng) {
			this.map.fitBounds(latLng)
		},
		getContextmenuItems() {
			const iconColor = OCA.Accessibility?.theme === 'dark' ? 'ffffff' : '000000'
			const cmi = [
				{
					text: t('maps', 'Add a favorite'),
					iconCls: 'icon-favorite',
					callback: this.contextAddFavorite,
				},
				{
					text: t('maps', 'Place photos'),
					iconCls: 'icon-category-multimedia',
					callback: this.contextPlacePhotos,
				},
				{
					text: t('maps', 'Place contact'),
					iconCls: 'icon-group',
					callback: this.placeContactClicked,
				},
				{
					text: t('maps', 'Share this location'),
					iconCls: 'icon-address',
					callback: this.contextShareLocation,
				},
			]
			/* Making this interactive does currently not work
			const favArray = Object.values(this.favorites)
			if (favArray.some((f) => (f.isUpdateable))
				|| (favArray.length === 0 && optionsController.optionValues?.isCreatable)) {
				cmi.push({
					text: t('maps', 'Add a favorite'),
					icon: generateUrl('/svg/core/actions/starred?color=' + iconColor),
					callback: this.contextAddFavorite,
				})
			}
			if (optionsController.optionValues?.isCreatable) {
				cmi.push({
					text: t('maps', 'Place photos'),
					icon: generateUrl('/svg/core/places/picture?color=' + iconColor),
					callback: this.contextPlacePhotos,
				},
				{
					text: t('maps', 'Place contact'),
					icon: generateUrl('/svg/core/actions/user?color=' + iconColor),
					callback: this.placeContactClicked,
				})
			}
			cmi.push({
				text: t('maps', 'Share this location'),
				icon: generateUrl('/svg/core/actions/share?color=' + iconColor),
				callback: this.contextShareLocation,
			}) */
			window.OCA.Maps.mapActions.forEach((action) => {
				cmi.push({
					text: action.label,
					iconCls: action.icon,
					callback: (e) => {
						action.callback({
							id: 'geo:' + e.latlng.lat + ',' + e.latlng.lng,
							name: t('maps', 'Shared location'),
							latitude: e.latlng.lat,
							longitude: e.latlng.lng,
						})
					},
				})
			})
			if (optionsController.nbRouters > 0 || getCurrentUser()?.isAdmin) {
				const routingItems = [
					'-',
					{
						text: t('maps', 'Route from here'),
						iconCls: 'icon-address',
						callback: (e) => {
							if (!this.showRouting) {
								this.showRouting = true
							}
							this.$refs.routingControl.setRouteFrom(e.latlng)
						},
					}, {
						text: t('maps', 'Add route point'),
						iconCls: 'icon-add',
						callback: (e) => {
							if (!this.showRouting) {
								this.showRouting = true
							}
							this.$refs.routingControl.addRoutePoint(e.latlng)
						},
					}, {
						text: t('maps', 'Route to here'),
						iconCls: 'icon-address',
						callback: (e) => {
							if (!this.showRouting) {
								this.showRouting = true
							}
							this.$refs.routingControl.setRouteTo(e.latlng)
						},
					},
				]
				cmi.push(...routingItems)
			}
			return cmi
		},
		onMapClick(e) {
			if (this.state === 'adding') {
				this.$emit('add-click', e)
			} else {
				this.onMapNormalLeftClick(e)
			}
		},
		onMapNormalLeftClick(e) {
			// layers management stuff
			const layerSelector = document.querySelector('.leaflet-control-layers')
			const layerSelectorWasVisible = layerSelector.style.display !== 'none'
			layerSelector.style.display = 'none'
			this.layersButton.button.parentElement.classList.remove('hidden')
			this.streetButton.button.parentElement.classList.remove('hidden')
			this.satelliteButton.button.parentElement.classList.remove('hidden')

			// check if there was a popup or a spiderfied cluster
			const thereWasAPopup = this.map.contextmenu._visible
				|| this.placingContact
				|| (this.map._popup !== undefined && this.map._popup !== null)
				|| this.leftClickSearching

			const hadSpider = this.$refs.favoritesLayer?.spiderfied
				|| this.$refs.contactsLayer?.spiderfied
				|| this.$refs.photosLayer?.spiderfied
			if (this.$refs.favoritesLayer) {
				this.$refs.favoritesLayer.spiderfied = false
			}
			if (this.$refs.contactsLayer) {
				this.$refs.contactsLayer.spiderfied = false
			}
			if (this.$refs.photosLayer) {
				this.$refs.photosLayer.spiderfied = false
			}

			this.map.closePopup()
			this.map.contextmenu.hide()
			this.placingContact = false
			this.leftClickSearching = false
			if (!thereWasAPopup && !hadSpider && !layerSelectorWasVisible) {
				this.leftClickSearch(e.latlng.lat, e.latlng.lng)
			}
		},
		onMapContextmenu(e) {
			if (e.originalEvent.target.classList.contains('vue2leaflet-map') || e.originalEvent.target.classList.contains('mapboxgl-map')) {
				this.map.contextmenu.showAt(L.latLng(e.latlng.lat, e.latlng.lng))
				this.leftClickSearching = false
			}
		},
		leftClickSearch(lat, lng) {
			this.leftClickSearchLatLng = L.latLng(lat, lng)
			this.leftClickSearching = true
		},
		onAddContactAddress(obj) {
			this.leftClickSearching = false
			this.placingContactLatLng = L.latLng(obj.latLng.lat, obj.latLng.lng)
			this.placingContact = true
		},
		initLocControl(map) {
			// location control
			const locControl = L.control.locate({
				position: 'bottomright',
				drawCircle: true,
				drawMarker: true,
				showPopup: false,
				icon: 'icon icon-address',
				iconLoading: 'icon icon-loading-small',
				strings: {
					title: t('maps', 'Current location'),
				},
				flyTo: true,
				returnToPrevBounds: true,
				setView: 'untilPan',
				showCompass: true,
				locateOptions: { enableHighAccuracy: true, maxZoom: 15 },
				onLocationError: (e) => {
					optionsController.saveOptionValues({ locControlEnabled: 'false' })
					alert(e.message)
				},
			}).addTo(map)
			document.querySelector('.leaflet-control-locate a').addEventListener('click', (e) => {
				optionsController.saveOptionValues({ locControlEnabled: locControl._active ? 'true' : 'false' })
			})
			if (optionsController.locControlEnabled) {
				locControl.start()
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
				L.myMapboxGL = (url, options) => {
					return new L.MapboxGL(options)
				}

				this.defaultStreetLayer = 'Mapbox vector streets'
				this.defaultSatelliteLayer = 'Mapbox satellite'

				// add mapbox-gl tile servers
				const attrib = '<a href="https://www.mapbox.com/about/maps/">© Mapbox</a> '
					+ '<a href="https://www.openstreetmap.org/copyright">© OpenStreetMap</a> '
					+ '<a href="https://www.mapbox.com/map-feedback/">' + t('maps', 'Improve this map') + '</a>'
				const attribSat = attrib + '<a href="https://www.digitalglobe.com/">© DigitalGlobe</a>'

				this.allOverlayLayers = {
					'Mapbox traffic overlay': {
						name: 'Traffic',
						type: 'overlay',
						attribution: attrib,
						tileLayerClass: L.myMapboxGL,
						options: {
							id: 'Mapbox Traffic overlay',
							accessToken: this.optionValues.mapboxAPIKEY,
							style: generateUrl('/apps/maps/style/traffic'),
							minZoom: 1,
							maxZoom: 22,
							attribution: attrib,
							pane: 'overlayPane',
						},
					},
				}
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
					'Mapbox traffic': {
						name: 'Traffic',
						type: 'base',
						attribution: attrib,
						tileLayerClass: L.myMapboxGL,
						options: {
							id: 'Mapbox traffic',
							accessToken: this.optionValues.mapboxAPIKEY,
							style: 'mapbox://styles/mapbox/traffic-day-v2',
							minZoom: 1,
							maxZoom: 22,
							attribution: attrib,
						},
					},
					'Mapbox traffic night': {
						name: 'Traffic night',
						type: 'base',
						attribution: attrib,
						tileLayerClass: L.myMapboxGL,
						options: {
							id: 'Mapbox traffic night',
							accessToken: this.optionValues.mapboxAPIKEY,
							style: 'mapbox://styles/mapbox/traffic-night-v2',
							minZoom: 1,
							maxZoom: 22,
							attribution: attrib,
						},
					},
					'Mapbox 3d Preview (Beta)': {
						name: 'Mapbox 3d Preview (Beta)',
						type: 'base',
						attribution: attrib,
						tileLayerClass: L.myMapboxGL,
						options: {
							id: '3d',
							accessToken: this.optionValues.mapboxAPIKEY,
							minZoom: 1,
							maxZoom: 22,
							pitch: 60,
							attribution: attrib,
						},
					},
					Watercolor: baseLayersByName.Watercolor,
				}
			}

			if ((gl !== null)
				&& ('maplibreStreetStyleURL' in this.optionValues && this.optionValues.maplibreStreetStyleURL !== '')) {
				let token = null
				if ('maplibreStreetStyleAuth' in this.optionValues && this.optionValues.maplibreStreetStyleAuth !== '') {
					token = this.optionValues.maplibreStreetStyleAuth
				}

				// wrapper to make tile layer component correctly pass arguments
				L.myMaplibreGL = (url, options) => {
					if (token !== null) {
						token = 'Basic ' + btoa(token)
						const oldTransform = options.transformRequest
						options.transformRequest = (url, resourceType) => {
							const param = oldTransform?.() || {}
							param.url = param.url || url
							if (resourceType === ResourceType.Tile) {
								param.type = 'arrayBuffer'
							}
							param.headers = param.headers || {}
							param.headers.Authorization = token
							return param
						}
					}
					return new L.maplibreGL(options)
				}

				this.allBaseLayers = {}
				Object.keys(baseLayersByName).forEach(id => {
					if (id === 'Open Street Map') {
						const layer = Object.assign({}, baseLayersByName[id])
						delete layer.url
						layer.tileLayerClass = L.myMaplibreGL
						layer.options = Object.assign({}, layer.options)
						layer.options.style = this.optionValues.maplibreStreetStyleURL
						layer.options.minZoom = 0
						layer.options.maxZoom = 22
						this.allBaseLayers[id] = layer
					} else {
						this.allBaseLayers[id] = baseLayersByName[id]
					}
				})
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
					},
				}],
			})
			this.satelliteButton.addTo(map)

			this.streetButton.button.parentElement.classList.add('behind')

			// initial selected layer, restore or fallback to default street
			/* if (this.optionValues.tileLayer in this.allBaseLayers) {
				this.activeLayerId = this.optionValues.tileLayer
			} else {

			 */
			if (!this.activeLayerId) {
				this.activeLayerId = this.defaultStreetLayer
			}

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
		// favorites
		contextAddFavorite(e) {
			this.$emit('add-favorite', e.latlng)
		},
		async contextShareLocation(e) {
			const geoLink = 'geo:' + e.latlng.lat.toFixed(6) + ',' + e.latlng.lng.toFixed(6)
			try {
				await navigator.clipboard.writeText(geoLink)
				showSuccess(t('maps', 'Geo link ({geoLink}) copied to clipboard', { geoLink }))
			} catch (error) {
				console.debug(error)
				showError(t('maps', 'Geo link could not be copied to clipboard'))
			}
		},
		// contacts
		placeContactClicked(e) {
			this.placingContactLatLng = L.latLng(e.latlng.lat, e.latlng.lng)
			this.placingContact = true
		},
		onContactPlaced(e) {
			this.placingContact = false
			this.$emit('contact-placed', e)
		},
		// photos
		contextPlacePhotos(e) {
			this.$emit('place-photos', e.latlng)
		},
		onPhotoMoved(photo, latLng) {
			this.$emit('photo-moved', photo, latLng)
		},
		// photo suggestions
		onPhotoSuggestionMoved(index, latLng) {
			this.$emit('photo-suggestion-moved', index, latLng)
		},
		zoomOnPhotoSuggestion(photo) {
			if (photo.lat && photo.lng) {
				const md = {
					s: photo.lat - 0.001,
					w: photo.lng - 0.001,
					n: photo.lat + 0.001,
					e: photo.lng + 0.001,
				}
				this.map.fitBounds(L.latLngBounds([md.s, md.w], [md.n, md.e]), { padding: [30, 30] })
			}
		},
		// tracks
		zoomOnTrack(track) {
			if (track.metadata) {
				const md = track.metadata
				this.map.fitBounds(L.latLngBounds([md.s, md.w], [md.n, md.e]), { padding: [30, 30] })
			}
		},
		clearElevationControl() {
			if (this.elevationControl !== null) {
				this.elevationControl.clear()
				// this.elevationControl.remove()
				// this.elevationControl = null
			}
		},
		displayElevation(track) {
			this.map.closePopup()
			this.clearElevationControl()
			const data = []
			track.data.routes.forEach((r) => {
				data.push({ line: r.points.map((p) => { return [p.lng, p.lat, p.ele] }) })
			})
			track.data.tracks.forEach((t) => {
				t.segments.forEach((s) => {
					data.push({ line: s.points.map((p) => { return [p.lng, p.lat, p.ele] }) })
				})
			})
			const geojson = GeoJSON.parse(data, { LineString: 'line' })
			const el = this.elevationControl ?? L.control.elevation({
				position: 'bottomleft',
				detached: false,
				height: 150,
				width: 700,
				collapsed: true,
				autohide: false,
				followMarker: false,
				theme: 'steelblue-theme',
				// slope: false,
				// speed: true,
				// time: true,
				summary: 'line',
				ruler: false,
			})
			el.addTo(this.map)
			el.addData(geojson)
			this.elevationControl = el
			el.on('elechart_init', (e) => {
				el._expand()
				el._button.setAttribute('title', t('maps', 'Close'))
			})
		},
		// devices
		zoomOnDevice(device) {
			if (device.points) {
				const lats = device.points.map(p => p.lat)
				const lngs = device.points.map(p => p.lng)
				const latMin = Math.min.apply(Math, lats)
				const latMax = Math.max.apply(Math, lats)
				const lngMin = Math.min.apply(Math, lngs)
				const lngMax = Math.max.apply(Math, lngs)
				this.map.fitBounds(L.latLngBounds([latMin, lngMin], [latMax, lngMax]), { padding: [30, 30] })
			}
		},
		// search
		onSearchValidate(element) {
			if (['contact', 'favorite'].includes(element.type)) {
				this.map.setView(element.latLng, 15)
			} else if (element.type === 'device') {
				if (!element.device.enabled) {
					// zooming is done by parent component
					this.$emit('search-enable-device', element.device)
				} else {
					this.zoomOnDevice(element.device)
				}
			} else if (element.type === 'track') {
				if (!element.track.enabled) {
					// zooming is done by parent component
					this.$emit('search-enable-track', element.track)
				} else {
					this.zoomOnTrack(element.track)
				}
			} else if (element.type === 'poi') {
				this.searching = true
				const mapBounds = this.map.getBounds()
				const latMin = mapBounds.getSouth()
				const latMax = mapBounds.getNorth()
				const lngMin = mapBounds.getWest()
				const lngMax = mapBounds.getEast()
				const query = element.subtype + '=' + encodeURIComponent(element.value)
				const apiUrl = 'https://nominatim.openstreetmap.org/search'
					+ '?format=json&addressdetails=1&extratags=1&namedetails=1&limit=100&'
					+ 'viewbox=' + parseFloat(lngMin) + ',' + parseFloat(latMin) + ',' + parseFloat(lngMax) + ',' + parseFloat(latMax) + '&'
					+ 'bounded=1&' + query
				axios.get(apiUrl).then((response) => {
					this.searchPois = response.data
					this.searching = false
				})
			} else if (element.type === 'result') {
				this.searchPois = [element.rawResult]
				this.map.setView(element.latLng, 15)
			} else if (element.type === 'coordinate') {
				this.leftClickSearch(element.lat, element.lng)
				this.map.setView(element.latLng, 15)
			} else if (element.type === 'mylocation') {
				navigator.geolocation.getCurrentPosition((position) => {
					const lat = position.coords.latitude
					const lng = position.coords.longitude
					this.map.setView([lat, lng], 15)
				})
			}
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

::v-deep .leaflet-control-locate {
	&.active .icon {
		-webkit-filter: drop-shadow(2px 3px 2px var(--color-main-text));
		filter: drop-shadow(2px 3px 2px var(--color-main-text));
	}
	.icon {
		display: inline-block;
		width: 26px;
		height: 26px;
		margin-top: 1px;
		background-size: 24px;
	}
}

::v-deep .leaflet-container {
	cursor: grab;
	.mapboxgl-map {
		cursor: grab;
	}
}

::v-deep .leaflet-container.loading {
	cursor: progress;
	.mapboxgl-map {
		cursor: progress;
	}
}

::v-deep .leaflet-container.adding {
	cursor: crosshair;
	.mapboxgl-map {
		cursor: crosshair;
	}
}

::v-deep .leaflet-marker-icon {
	cursor: pointer;
	* {
		cursor: pointer;
	}
}

::v-deep .leaflet-marker-draggable {
	cursor: move;
	* {
		cursor: move;
	}
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

::v-deep .leaflet-contextmenu {
	background-color: var(--color-main-background);
}

::v-deep .leaflet-contextmenu-item {
	line-height: 30px !important;
	color: var(--color-text-lighter) !important;
	cursor: pointer !important;
}

::v-deep .leaflet-contextmenu-item:hover {
	color: var(--color-main-text) !important;
	background-color: var(--color-background-hover) !important;
	border-color: var(--color-border) !important;
}

::v-deep .leaflet-contextmenu-icon {
	margin: 7px 8px 0 0 !important;
}

::v-deep .leaflet-contextmenu-separator {
	border-color: var(--color-border) !important;
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

::v-deep .popup-contact-wrapper .action {
	p {
		height: 44px !important;
		line-height: 28px;
	}
}

::v-deep .popup-contact-wrapper .action,
::v-deep .popup-track-wrapper .action,
::v-deep .popup-device-wrapper .action,
::v-deep .popup-favorite-wrapper .action,
::v-deep .popup-photo-wrapper .action {
	height: 44px;
	.action-button {
		height: 44px !important;
		padding: 0 !important;
	}
}

::v-deep .leaflet-marker-track-tooltip,
::v-deep .leaflet-marker-device-tooltip,
::v-deep .leaflet-marker-favorite-tooltip {
	padding: 0 !important;
	border: 0 !important;
}

::v-deep .icon-routing {
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask: url('../../img/routing.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('../../img/routing.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
}

::v-deep .icon-road {
	background-color: var(--color-main-text);
	mask: url('../../img/road.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('../../img/road.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
}

::v-deep .icon-road-thin {
	background-color: var(--color-main-text);
	mask: url('../../img/road-thin.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('../../img/road-thin.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
}

::v-deep .leaflet-marker-favorite-cluster,
::v-deep .leaflet-marker-favorite {
	height: 36px !important;
	width: 36px !important;
	display: flex;
	border-radius: 50%;
}

::v-deep .favoriteMarker,
::v-deep .favoriteClusterMarker {
	box-shadow: 0px 0px 10px #888;
	border-radius: 50%;
}

::v-deep .favoriteMarker.selected {
	box-shadow: 0px 0px 10px #ff0000;
}

::v-deep .leaflet-marker-favorite-cluster .label {
	position: absolute;
	top: -3px;
	right: -5px;
	color: #fff;
	background-color: #333;
	border-radius: 9px;
	height: 18px;
	min-width: 18px;
	line-height: 12px;
	text-align: center;
	padding: 3px;
}

::v-deep .favoriteClusterMarkerDark {
	background: url('../../img/star-black.svg') no-repeat 50% 50%;
}

::v-deep .leaflet-left {
	margin-left: 0px;
}

::v-deep .leaflet-control {
	margin-left: 0px;
}

::v-deep .leaflet-control-layers-expanded {
	padding: 4px 0px !important;
}

::v-deep .leaflet-control-layers-base span:nth-child(1),
::v-deep .leaflet-control-layers-overlays span:nth-child(1) {
	display: block;
	height: 38px;
	padding: 4px 10px;
	border-top: 1px solid transparent;
	border-bottom: 1px solid transparent;
	color: var(--color-text-lighter) !important;
	cursor: pointer !important;
}

::v-deep .leaflet-control-layers-base span:hover,
::v-deep .leaflet-control-layers-overlays span:hover {
	color: var(--color-main-text) !important;
	background-color: var(--color-background-hover) !important;
	border-color: var(--color-border) !important;
}

::v-deep .leaflet-control-layers-base span:nth-child(2),
::v-deep .leaflet-control-layers-overlays span:nth-child(2) {
	display: inline-block;
	vertical-align: top;
	line-height: 30px;
	margin: 0px 10px;
	cursor: pointer !important;
}

::v-deep .leaflet-control-layers-selector {
	display: inline-block;
	height: 30px;
	margin: 0px;
}

::v-deep .leaflet-control-layers-separator {
	border-color: var(--color-border) !important;
}
</style>
