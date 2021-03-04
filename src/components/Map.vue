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
				@close="showRouting = false" />
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
				@edit="$emit('edit-favorite', $event)"
				@delete="$emit('delete-favorite', $event)"
				@delete-multiple="$emit('delete-favorites', $event)" />
			<PhotosLayer
				v-if="map && photosEnabled"
				ref="photosLayer"
				:map="map"
				:photos="photos"
				:draggable="photosDraggable"
				@coords-reset="$emit('coords-reset', $event)"
				@photo-moved="onPhotoMoved" />
			<ContactsLayer
				v-if="map && contactsEnabled"
				ref="contactsLayer"
				:contacts="contacts"
				:groups="contactGroups"
				@address-deleted="$emit('address-deleted', $event)" />
			<PlaceContactPopup v-if="placingContact"
				:lat-lng="placingContactLatLng"
				@contact-placed="onContactPlaced" />
			<TracksLayer
				v-if="map && tracksEnabled"
				ref="tracksLayer"
				:map="map"
				:tracks="tracks"
				@click="$emit('click-track', $event)"
				@change-color="$emit('change-track-color', $event)"
				@display-elevation="displayElevation" />
			<DevicesLayer
				v-if="map && devicesEnabled"
				ref="devicesLayer"
				:map="map"
				:devices="devices"
				@click="$emit('click-device', $event)"
				@toggle-history="$emit('toggle-device-history', $event)"
				@change-color="$emit('change-device-color', $event)" />
			<ClickSearchPopup v-if="leftClickSearching"
				:lat-lng="leftClickSearchLatLng"
				@place-contact="onAddContactAddress"
				@add-favorite="$emit('add-address-favorite', $event); leftClickSearching = false" />
			<LFeatureGroup>
				<PoiMarker v-for="poi in searchPois"
					:key="poi.place_id"
					:poi="poi" />
			</LFeatureGroup>
		</LMap>
		<Slider v-show="sliderEnabled"
			:min="minDataTimestamp"
			:max="maxDataTimestamp"
			@range-change="$emit('slider-range-changed', $event)" />
	</div>
</template>

<script>
import { getLocale } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'

import L from 'leaflet'
import 'mapbox-gl/dist/mapbox-gl'
import 'mapbox-gl-leaflet/leaflet-mapbox-gl'
import {
	baseLayersByName,
	overlayLayersByName,
} from '../data/mapLayers'
import { LControlScale, LControlZoom, LMap, LTileLayer, LControlLayers, LFeatureGroup } from 'vue2-leaflet'

import 'leaflet-easybutton/src/easy-button'
import 'leaflet-easybutton/src/easy-button.css'
import 'leaflet-contextmenu/dist/leaflet.contextmenu.min'
import 'leaflet-contextmenu/dist/leaflet.contextmenu.min.css'
import 'leaflet.locatecontrol/dist/L.Control.Locate.min'
import 'leaflet.locatecontrol/dist/L.Control.Locate.min.css'
// import 'd3/dist/d3.min'
import GeoJSON from 'geojson'
import '@raruto/leaflet-elevation/dist/leaflet-elevation'
import '@raruto/leaflet-elevation/dist/leaflet-elevation.css'

import Slider from '../components/map/Slider'
import SearchControl from '../components/map/SearchControl'
import HistoryControl from '../components/map/HistoryControl'
import RoutingControl from '../components/map/routing/RoutingControl'
import FavoritesLayer from '../components/map/FavoritesLayer'
import PhotosLayer from '../components/map/PhotosLayer'
import TracksLayer from '../components/map/TracksLayer'
import DevicesLayer from '../components/map/DevicesLayer'
import ContactsLayer from '../components/map/ContactsLayer'
import PlaceContactPopup from '../components/map/PlaceContactPopup'
import PoiMarker from '../components/map/PoiMarker'
import ClickSearchPopup from '../components/map/ClickSearchPopup'
import optionsController from '../optionsController'

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
		TracksLayer,
		DevicesLayer,
		ContactsLayer,
		PlaceContactPopup,
		ClickSearchPopup,
		PoiMarker,
	},

	props: {
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
		minDataTimestamp: {
			type: Number,
			required: true,
		},
		maxDataTimestamp: {
			type: Number,
			required: true,
		},
		loading: {
			type: Boolean,
			required: true,
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
				closePopupOnClick: false,
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
	},

	watch: {
		loading() {
			if (this.loading) {
				this.$refs.map.$el.classList.add('loading')
			} else {
				this.$refs.map.$el.classList.remove('loading')
			}
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
					icon: generateUrl('/svg/core/actions/starred?color=' + iconColor),
					callback: this.contextAddFavorite,
				}, {
					text: t('maps', 'Place photos'),
					icon: generateUrl('/svg/core/places/picture?color=' + iconColor),
					callback: this.contextPlacePhotos,
				}, {
					text: t('maps', 'Place contact'),
					icon: generateUrl('/svg/core/actions/user?color=' + iconColor),
					callback: this.placeContactClicked,
				}, {
					text: t('maps', 'Share this location'),
					icon: generateUrl('/svg/core/actions/share?color=' + iconColor),
					callback: () => {},
				},
			]
			if (optionsController.nbRouters > 0 || getCurrentUser().isAdmin) {
				const routingItems = [
					'-',
					{
						text: t('maps', 'Route from here'),
						icon: generateUrl('/svg/core/filetypes/location?color=00cc00'),
						callback: (e) => {
							if (!this.showRouting) {
								this.showRouting = true
							}
							this.$refs.routingControl.setRouteFrom(e.latlng)
						},
					}, {
						text: t('maps', 'Add route point'),
						icon: generateUrl('/svg/core/filetypes/location?color=0000cc'),
						callback: (e) => {
							if (!this.showRouting) {
								this.showRouting = true
							}
							this.$refs.routingControl.addRoutePoint(e.latlng)
						},
					}, {
						text: t('maps', 'Route to here'),
						icon: generateUrl('/svg/core/filetypes/location?color=cc0000'),
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
			if (this.optionValues.tileLayer in this.allBaseLayers) {
				this.activeLayerId = this.optionValues.tileLayer
			} else {
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
		// tracks
		clearElevationControl() {
			if (this.elevationControl !== null) {
				this.elevationControl.clear()
				this.elevationControl.remove()
				this.elevationControl = null
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
			console.debug(geojson)
			const el = L.control.elevation({
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
		// search
		onSearchValidate(element) {
			console.debug(element)
			if (['contact', 'favorite'].includes(element.type)) {
				this.map.setView(element.latLng, 15)
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
}

::v-deep .leaflet-contextmenu-item:hover {
	color: var(--color-main-text) !important;
	background-color: var(--color-background-hover) !important;
	border-color: var(--color-border) !important;
}

::v-deep .leaflet-contextmenu-item img {
	margin: 7px 8px 0 0 !important;
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

// routing machine
::v-deep .leaflet-routing-container {
	width: 350px;
	max-width: calc(100vw - 45px);
	margin-top: 0;
	padding-top: 5px;
	border-top: 0;
	background-color: var(--color-main-background);
	border-top-left-radius: 0;
	border-top-right-radius: 0;

	.router-container {
		width: 100%;
		display: flex;
		#router-select {
			flex-grow: 1;
			text-overflow: ellipsis;
			margin: 0 5px 5px 5px;
		}
	}

	// we don't need this
	.leaflet-routing-geocoders {
		display: none;
	}
	.leaflet-routing-alternatives-container:not(:empty) {
		border-top: 1px solid var(--color-border);
	}

	// instruction table
	.leaflet-routing-alt {
		table {
			white-space: normal;
			width: 100%;

			.leaflet-routing-instruction-distance {
				width: 1px;
			}

			td:nth-child(2),
			td:nth-child(3) {
				display: block;
			}

			td:nth-child(3) {
				color: var(--color-text-light);
			}

			tr:nth-child(odd) {
				background-color: var(--color-background-dark);
			}
			tr:hover {
				background-color: var(--color-background-darker);
			}
		}
	}
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

::v-deep .leaflet-marker-favorite-cluster,
::v-deep .leaflet-marker-favorite {
	height: 36px !important;
	width: 36px !important;
	display: flex;
}

::v-deep .favoriteMarker,
::v-deep .favoriteClusterMarker {
	box-shadow: 0px 0px 10px #888;
	border-radius: 50%;
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

::v-deep .leaflet-control-layers-base span,
::v-deep .leaflet-control-layers-overlays span {
	cursor: pointer !important;
}

::v-deep .leaflet-control-layers-selector {
	min-height: 0;
	cursor: pointer !important;
}
</style>
