<template>
	<div id="map-wrapper">
		<div ref="mapContainer" class="leaflet-map-container">
			
			<template v-if="isMapReady">
				<RoutingControl
					v-show="showRouting"
					ref="routingControl"
					:visible="showRouting"
					:map="map"
					:search-data="routingSearchData"
					@close="showRouting = false"
					@track-added="$emit('track-added', $event)" />
					
				<SearchControl
					v-show="!showRouting"
					:map="map"
					:search-data="searchData"
					:loading="searching"
					:result-poi-number="searchPois.length"
					@validate="onSearchValidate"
					@routing-clicked="showRouting = true"
					@clear-pois="searchPois = []" />
					
				<HistoryControl
					:map="map"
					position="topright"
					:last-actions="lastActions"
					:last-canceled-actions="lastCanceledActions"
					@cancel="$emit('cancel')"
					@redo="$emit('redo')" />
					
				<FavoritesLayer
					v-if="favoritesEnabled"
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
					v-if="photosEnabled && !photoSuggestionsHidePhotos"
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
					v-if="photosEnabled && showPhotoSuggestions"
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
					v-if="contactsEnabled"
					:map="map"
					ref="contactsLayer"
					:contacts="contacts"
					:groups="contactGroups"
					@address-deleted="$emit('address-deleted', $event)"
					@add-to-map-contact="$emit('add-to-map-contact', $event)" />
					
				<PlaceContactPopup v-if="placingContact"
					:map="map"
					:lat-lng="placingContactLatLng"
					@contact-placed="onContactPlaced" />
					
				<TracksLayer
					v-if="tracksEnabled"
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
					v-if="devicesEnabled"
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
					:map="map"
					:lat-lng="leftClickSearchLatLng"
					:favorite-is-creatable="isFavoriteCreatable"
					:contact-is-creatable="isContactCreatable"
					@place-contact="onAddContactAddress"
					@add-favorite="$emit('add-address-favorite', $event); leftClickSearching = false" />
					
				<template :key="poi.place_id" v-for="poi in searchPois">
					<PoiMarker 
						:poi="poi"
						:map="map"
						@place-contact="onAddContactAddress"
						@add-favorite="$emit('add-address-favorite', $event); leftClickSearching = false" />
				</template>
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
import { getLocale } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'

import { LocateControl } from "leaflet.locatecontrol"
import 'leaflet.locatecontrol/dist/L.Control.Locate.min.css'
import L from 'leaflet'
import 'mapbox-gl/dist/mapbox-gl'
import 'mapbox-gl/dist/mapbox-gl.css'
import 'mapbox-gl-leaflet/leaflet-mapbox-gl'
import '@maplibre/maplibre-gl-leaflet'
import ResourceType from 'maplibre-gl'
import { baseLayersByName, overlayLayersByName } from '../data/mapLayers.js'

import 'leaflet-easybutton/src/easy-button'
import 'leaflet-easybutton/src/easy-button.css'
import 'leaflet-contextmenu/dist/leaflet.contextmenu.min'
import 'leaflet-contextmenu/dist/leaflet.contextmenu.min.css'

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
		Slider, HistoryControl, SearchControl, RoutingControl, FavoritesLayer,
		PhotosLayer, PhotoSuggestionsLayer, TracksLayer, DevicesLayer, ContactsLayer,
		PlaceContactPopup, ClickSearchPopup, PoiMarker,
	},
	props: {
		activeLayerIdProp: { type: String, required: true },
		mapBoundsProp: { type: Array, required: true },
		searchData: { type: Array, required: true },
		routingSearchData: { type: Array, required: true },
		favorites: { type: Object, required: true },
		favoriteCategories: { type: Object, required: true },
		favoritesEnabled: { type: Boolean, required: true },
		favoritesDraggable: { type: Boolean, required: true },
		photos: { type: Array, required: true },
		photosEnabled: { type: Boolean, required: true },
		photosDraggable: { type: Boolean, required: true },
		showPhotoSuggestions: { type: Boolean, required: true },
		photoSuggestions: { type: Array, required: true },
		photoSuggestionsTracksAndDevices: { type: Object, required: true },
		photoSuggestionsSelectedIndices: { type: Array, required: true },
		photoSuggestionsHidePhotos: { type: Boolean, default: false },
		contacts: { type: Array, required: true },
		contactGroups: { type: Object, required: true },
		contactsEnabled: { type: Boolean, required: true },
		tracks: { type: Array, required: true },
		tracksEnabled: { type: Boolean, required: true },
		devices: { type: Array, required: true },
		devicesEnabled: { type: Boolean, required: true },
		sliderEnabled: { type: Boolean, required: true },
		sliderStartTimestamp: { type: Number, required: true },
		sliderEndTimestamp: { type: Number, required: true },
		minDataTimestamp: { type: Number, required: true },
		maxDataTimestamp: { type: Number, required: true },
		state: { type: String, default: '' },
		lastActions: { type: Array, required: true },
		lastCanceledActions: { type: Array, required: true },
	},
	data() {
		return {
			isMapReady: false,
			locale: getLocale(),
			isDarkTheme: OCA.Accessibility?.theme === 'dark',
			optionValues: optionsController.optionValues,
			mapOptions: {
				center: [0, 0],
				zoom: 2,
				minZoom: 2,
				maxZoom: 19,
				bounds: L.latLngBounds(this.mapBoundsProp),
				maxBounds: L.latLngBounds([[-90, 720], [90, -720]]),
				scaleControlShouldUseImperial: false,
			},
			defaultStreetLayer: 'Open Street Map',
			defaultSatelliteLayer: 'ESRI',
			activeLayerId: this.activeLayerIdProp,
			showExtraLayers: false,
			showRouting: false,
			placingContact: false,
			placingContactLatLng: null,
			searchPois: [],
			searching: false,
			leftClickSearching: false,
			leftClickSearchLatLng: null,
		}
	},
	created() {
		// Non-reactive Leaflet properties to prevent Vue 3 Proxy breakdown
		this.map = null
		this.layersControl = null
		this.elevationControl = null
		this.layersButton = null
		this.streetButton = null
		this.satelliteButton = null
		this.leafletBaseLayers = {}
		this.leafletOverlays = {}
	},
	mounted() {
		this.map = L.map(this.$refs.mapContainer, {
			center: this.mapOptions.center,
			zoom: this.mapOptions.zoom,
			minZoom: this.mapOptions.minZoom,
			maxZoom: this.mapOptions.maxZoom,
			maxBounds: this.mapOptions.maxBounds,
			zoomControl: false,
			closePopupOnClick: false,
			contextmenu: true,
			contextmenuWidth: 160,
			contextmenuItems: this.getContextmenuItems(),
		});

		if (this.mapOptions.bounds) {
			this.map.fitBounds(this.mapOptions.bounds);
		}

		L.control.zoom({ position: 'bottomright' }).addTo(this.map);
		L.control.scale({
			position: 'bottomleft',
			imperial: this.mapOptions.scaleControlShouldUseImperial,
			metric: !this.mapOptions.scaleControlShouldUseImperial
		}).addTo(this.map);

		this.map.on('moveend zoomend', () => this.onUpdateBounds(this.map.getBounds()));
		this.map.on('baselayerchange', this.onBaselayerchange);
		this.map.on('click', this.onMapClick);
		this.map.on('contextmenu', this.onMapContextmenu);

		this.initLocControl(this.map);
		this.initLayers(this.map);

		this.$nextTick(() => {
			this.map.invalidateSize();
		});

		this.isMapReady = true;
	},
	beforeUnmount() {
		if (this.map) {
			this.map.remove();
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
			if(!this.map) return;
			const mapEl = this.$refs.mapContainer;
			mapEl.classList.remove('loading');
			mapEl.classList.remove('adding');
			
			if (this.state === 'loading') {
				mapEl.classList.add('loading');
			} else if (this.state === 'adding') {
				mapEl.classList.add('adding');
			}
		},
		activeLayerIdProp(newValue) {
			if (this.activeLayerId !== newValue) {
				this.activeLayerId = newValue;
				this.updateBaseLayer();
			}
		},
		mapBoundsProp(newValue) {
			this.mapOptions.bounds = L.latLngBounds(newValue)
			if (this.map) this.map.fitBounds(this.mapOptions.bounds)
		},
	},
	methods: {
		fitBounds(latLng) {
			if(this.map) this.map.fitBounds(latLng)
		},
		getContextmenuItems() {
			const cmi = [
				{ text: window.t('maps', 'Add a favorite'), iconCls: 'icon-favorite', callback: this.contextAddFavorite },
				{ text: window.t('maps', 'Place photos'), iconCls: 'icon-category-multimedia', callback: this.contextPlacePhotos },
				{ text: window.t('maps', 'Place contact'), iconCls: 'icon-group', callback: this.placeContactClicked },
				{ text: window.t('maps', 'Share this location'), iconCls: 'icon-address', callback: this.contextShareLocation },
			]
			if (window.OCA && window.OCA.Maps && window.OCA.Maps.mapActions) {
				window.OCA.Maps.mapActions.forEach((action) => {
					cmi.push({
						text: action.label, iconCls: action.icon, callback: (e) => {
							action.callback({ id: 'geo:' + e.latlng.lat + ',' + e.latlng.lng, name: window.t('maps', 'Shared location'), latitude: e.latlng.lat.toString(), longitude: e.latlng.lng.toString() })
						}
					})
				})
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
			this.hideLayersControl();
			
			const thereWasAPopup = this.map.contextmenu._visible
				|| this.placingContact
				|| (this.map._popup !== undefined && this.map._popup !== null)
				|| this.leftClickSearching

			const hadSpider = this.$refs.favoritesLayer?.spiderfied
				|| this.$refs.contactsLayer?.spiderfied
				|| this.$refs.photosLayer?.spiderfied
				
			if (this.$refs.favoritesLayer) this.$refs.favoritesLayer.spiderfied = false
			if (this.$refs.contactsLayer) this.$refs.contactsLayer.spiderfied = false
			if (this.$refs.photosLayer) this.$refs.photosLayer.spiderfied = false

			this.map.closePopup()
			this.map.contextmenu.hide()
			this.placingContact = false
			this.leftClickSearching = false
			if (!thereWasAPopup && !hadSpider) {
				this.leftClickSearch(e.latlng.lat, e.latlng.lng)
			}
		},
		hideLayersControl() {
			if (this.layersControl) {
				this.layersControl.getContainer().style.display = 'none';
			}
			if (this.layersButton) this.layersButton.button.parentElement.classList.remove('hidden')
			if (this.streetButton) this.streetButton.button.parentElement.classList.remove('hidden')
			if (this.satelliteButton) this.satelliteButton.button.parentElement.classList.remove('hidden')
		},
		showLayersControl() {
			if (this.layersControl) {
				this.layersControl.getContainer().style.display = 'block';
			}
			if (this.layersButton) this.layersButton.button.parentElement.classList.add('hidden')
			if (this.streetButton) this.streetButton.button.parentElement.classList.add('hidden')
			if (this.satelliteButton) this.satelliteButton.button.parentElement.classList.add('hidden')
		},
		onMapContextmenu(e) {
			if (e.originalEvent.target.classList.contains('leaflet-container') || e.originalEvent.target.classList.contains('mapboxgl-map')) {
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
			const locControl = new LocateControl({
				position: 'bottomright',
				drawCircle: true,
				drawMarker: true,
				showPopup: false,
				icon: 'icon icon-address',
				iconLoading: 'icon icon-loading-small',
				strings: {
					title: window.t('maps', 'Current location'),
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
			const allBaseLayers = baseLayersByName;
			const allOverlayLayers = overlayLayersByName;
			this.leafletBaseLayers = {};
			this.leafletOverlays = {};

			const baseLayersForControl = {};
			const overlaysForControl = {};

			// Build Base Layers
			Object.keys(allBaseLayers).forEach(key => {
				const l = allBaseLayers[key];
				const layer = l.tileLayerClass
					? l.tileLayerClass(l.url, l.options)
					: L.tileLayer(l.url, l.options);
				
				this.leafletBaseLayers[key] = layer;
				baseLayersForControl[l.name] = layer;
			});

			// Safe Fallback logic for activeLayerId
			if (!this.activeLayerId || !this.leafletBaseLayers[this.activeLayerId]) {
				if (this.leafletBaseLayers[this.defaultStreetLayer]) {
					this.activeLayerId = this.defaultStreetLayer;
				} else {
					this.activeLayerId = Object.keys(this.leafletBaseLayers)[0]; // Force fallback to the very first available option
				}
			}

			// Apply the safely determined layer to the map
			if (this.activeLayerId && this.leafletBaseLayers[this.activeLayerId]) {
				this.leafletBaseLayers[this.activeLayerId].addTo(map);
			}

			// Build Overlay Layers
			Object.keys(allOverlayLayers).forEach(key => {
				const l = allOverlayLayers[key];
				const layer = l.tileLayerClass
					? l.tileLayerClass(l.url, l.options)
					: L.tileLayer(l.url, l.options);
				
				layer.setOpacity(l.opacity || 1);
				this.leafletOverlays[key] = layer;
				overlaysForControl[l.name] = layer;

				if (['Watercolor', 'ESRI'].includes(this.activeLayerId) && key === 'Roads Overlay') {
					layer.addTo(map);
				}
			});

			// Add the native layers control
			this.layersControl = L.control.layers(baseLayersForControl, overlaysForControl, {
				position: 'bottomright',
				collapsed: false
			}).addTo(map);

			// LAYER BUTTONS
			this.layersButton = L.easyButton({
				position: 'bottomright',
				states: [{
					stateName: 'no-importa',
					icon: '<a class="icon icon-menu" style="height: 100%"> </a>',
					title: window.t('maps', 'Other maps'),
					onClick: () => this.showLayersControl(),
				}],
			}).addTo(map);

			this.streetButton = L.easyButton({
				position: 'bottomright',
				states: [{
					stateName: 'no-importa',
					icon: '<a class="icon icon-osm" style="height: 100%"> </a>',
					title: window.t('maps', 'Street map'),
					onClick: () => {
						this.changeBaseLayer(this.defaultStreetLayer);
					},
				}],
			}).addTo(map);

			this.satelliteButton = L.easyButton({
				position: 'bottomright',
				states: [{
					stateName: 'no-importa',
					icon: '<a class="icon icon-esri" style="height: 100%"> </a>',
					title: window.t('maps', 'Satellite map'),
					onClick: () => {
						this.changeBaseLayer(this.defaultSatelliteLayer);
					},
				}],
			}).addTo(map);

			this.updateLayerButtons();

			this.$nextTick(() => {
				this.hideLayersControl();
			});
		},
		updateBaseLayer() {
			if (!this.map) return;
			this.changeBaseLayer(this.activeLayerId);
		},
		changeBaseLayer(newLayerId) {
			// Bulletproof fallback logic for layer swaps
			if (!newLayerId || !this.leafletBaseLayers[newLayerId]) {
				if (this.leafletBaseLayers[this.defaultStreetLayer]) {
					newLayerId = this.defaultStreetLayer;
				} else {
					newLayerId = Object.keys(this.leafletBaseLayers)[0]; // Ultimate fallback
				}
			}

			if (!newLayerId) return; // Prevent failure if no layers exist at all

			Object.keys(this.leafletBaseLayers).forEach(key => {
				const layer = this.leafletBaseLayers[key];
				if (this.map.hasLayer(layer)) {
					this.map.removeLayer(layer);
				}
			});

			this.leafletBaseLayers[newLayerId].addTo(this.map);
			this.activeLayerId = newLayerId;

			if (['Watercolor', 'ESRI'].includes(this.activeLayerId)) {
				if (this.leafletOverlays['Roads Overlay'] && !this.map.hasLayer(this.leafletOverlays['Roads Overlay'])) {
					this.leafletOverlays['Roads Overlay'].addTo(this.map);
				}
			} else {
				if (this.leafletOverlays['Roads Overlay'] && this.map.hasLayer(this.leafletOverlays['Roads Overlay'])) {
					this.map.removeLayer(this.leafletOverlays['Roads Overlay']);
				}
			}

			this.updateLayerButtons();
			optionsController.saveOptionValues({ tileLayer: this.activeLayerId });
		},
		updateLayerButtons() {
			if (!this.streetButton || !this.satelliteButton) return;
			if (this.activeLayerId === this.defaultStreetLayer) {
				this.streetButton.button.parentElement.classList.add('behind');
				this.satelliteButton.button.parentElement.classList.remove('behind');
			} else {
				this.streetButton.button.parentElement.classList.remove('behind');
				this.satelliteButton.button.parentElement.classList.add('behind');
			}
		},
		onBaselayerchange(e) {
			let newLayerId = null;
			Object.keys(this.leafletBaseLayers).forEach(key => {
				if (this.leafletBaseLayers[key] === e.layer) {
					newLayerId = key;
				}
			});

			if (newLayerId) {
				this.activeLayerId = newLayerId;
				this.updateLayerButtons();
				optionsController.saveOptionValues({ tileLayer: this.activeLayerId });

				if (e.layer && e.layer.options && e.layer.options.maxZoom) {
					e.layer._map.setMaxZoom(e.layer.options.maxZoom);
				}
			}
			this.hideLayersControl();
		},
		onUpdateBounds(bounds) {
			const boundsStr = bounds.getNorth() + ';' + bounds.getSouth() + ';' + bounds.getEast() + ';' + bounds.getWest()
			optionsController.saveOptionValues({ mapBounds: boundsStr })
		},
		// context handlers
		contextAddFavorite(e) { this.$emit('add-favorite', e.latlng) },
		async contextShareLocation(e) {
			const geoLink = 'geo:' + e.latlng.lat.toFixed(6) + ',' + e.latlng.lng.toFixed(6)
			try {
				await navigator.clipboard.writeText(geoLink)
				showSuccess(window.t('maps', 'Geo link ({geoLink}) copied to clipboard', { geoLink }))
			} catch (error) {
				console.debug(error)
				showError(window.t('maps', 'Geo link could not be copied to clipboard'))
			}
		},
		placeContactClicked(e) {
			this.placingContactLatLng = L.latLng(e.latlng.lat, e.latlng.lng)
			this.placingContact = true
		},
		onContactPlaced(e) {
			this.placingContact = false
			this.$emit('contact-placed', e)
		},
		contextPlacePhotos(e) { this.$emit('place-photos', e.latlng) },
		onPhotoMoved(photo, latLng) { this.$emit('photo-moved', photo, latLng) },
		onPhotoSuggestionMoved(index, latLng) { this.$emit('photo-suggestion-moved', index, latLng) },
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
		zoomOnTrack(track) {
			if (track.metadata) {
				const md = track.metadata
				this.map.fitBounds(L.latLngBounds([md.s, md.w], [md.n, md.e]), { padding: [30, 30] })
			}
		},
		clearElevationControl() {
			if (this.elevationControl !== null) {
				this.elevationControl.clear()
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
				summary: 'line',
				ruler: false,
				srcFolder: window.location.origin.concat(window.location.pathname, 'src/components/leaflet-elevation//'),
			})
			el.addTo(this.map)
			el.addData(geojson)
			this.elevationControl = el
			el.on('elechart_init', (e) => {
				el._expand()
				el._button.setAttribute('title', window.t('maps', 'Close'))
			})
		},
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
		onSearchValidate(element) {
			if (['contact', 'favorite'].includes(element.type)) {
				this.map.setView(element.latLng, 15)
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

.leaflet-map-container {
	position: relative;
	height: 100%;
	width: 100%;
	z-index: 1; /* Essential for placing Vue popups correctly over map */
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