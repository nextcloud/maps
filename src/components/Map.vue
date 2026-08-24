<!--
 - SPDX-FileCopyrightText: 2019 Paul Schwörer <hello@paulschwoerer.de>
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	Port in progress: the map engine moved from Leaflet to vue-maplibre-gl.
	Ported so far: the base map (street/satellite/terrain), click-to-add,
	favorites/contacts/photos/photo-suggestions/tracks/devices markers and
	clustering, routing, the search box (including the base style's own
	poi icons), every layer's right-click menu, drag-to-edit for favorites
	and photos (devices never had it - see its own header comment), the
	history (undo/redo) control, the date-range slider (Slider.vue
	itself needed no changes at all - it was never Leaflet-dependent,
	just an absolutely-positioned div using noUiSlider), the track
	elevation profile chart (ElevationChart.vue, chart.js instead of
	@raruto/leaflet-elevation - see its own header comment), placing a
	contact address by clicking the map (PlaceContactMarker.vue), and
	the empty-map right-click menu (add a favorite / place photos /
	place contact / share this location / third-party
	window.OCA.Maps.mapActions entries).
-->
<template>
	<div id="map-wrapper" :class="{ loading: state === 'loading', adding: state === 'adding' }">
		<MglMap :map-style="activeStyle"
			:center="[0, 0]"
			:zoom="2"
			:min-zoom="2"
			:max-zoom="19"
			@map:click="onMapClick"
			@map:contextmenu="onMapContextMenu">
			<MglRasterDemSource :source-id="TERRAIN_SOURCE_ID"
				:tiles="TERRAIN_SOURCE.tiles"
				:tile-size="TERRAIN_SOURCE.tileSize"
				:maxzoom="TERRAIN_SOURCE.maxzoom"
				:encoding="TERRAIN_SOURCE.encoding"
				:attribution="TERRAIN_SOURCE.attribution" />

			<TracksLayer v-if="tracksEnabled"
				:tracks="tracks"
				:start="sliderStartTimestamp"
				:end="sliderEndTimestamp"
				@click="emit('click-track', $event)"
				@change-color="emit('change-track-color', $event)"
				@display-elevation="displayElevation($event)"
				@add-to-map-track="emit('add-to-map-track', $event)" />

			<DevicesLayer v-if="devicesEnabled"
				:devices="devices"
				:start="sliderStartTimestamp"
				:end="sliderEndTimestamp"
				@click="emit('click-device', $event)"
				@toggle-history="emit('toggle-device-history', $event)"
				@change-color="emit('change-device-color', $event)"
				@export="emit('export-device', $event)"
				@add-to-map-device="emit('add-to-map-device', $event)" />

			<FavoritesLayer v-if="favoritesEnabled"
				:favorites="favorites"
				:categories="favoriteCategories"
				:draggable="favoritesDraggable"
				@click="emit('click-favorite', $event)"
				@edit="emit('edit-favorite', $event)"
				@delete="emit('delete-favorite', $event)"
				@delete-favorites="emit('delete-favorites', $event)"
				@add-to-map-favorite="emit('add-to-map-favorite', $event)" />

			<ContactsLayer v-if="contactsEnabled"
				:contacts="contacts"
				:groups="contactGroups"
				@address-deleted="emit('address-deleted', $event)"
				@add-to-map-contact="emit('add-to-map-contact', $event)" />

			<PhotosLayer v-if="photosEnabled && !photoSuggestionsHidePhotos"
				:photos="photos"
				:date-filter-enabled="sliderEnabled"
				:date-filter-start="sliderStartTimestamp"
				:date-filter-end="sliderEndTimestamp"
				:draggable="photosDraggable"
				@open-sidebar="emit('open-sidebar', $event)"
				@coords-reset="emit('coords-reset', $event)"
				@add-to-map-photo="emit('add-to-map-photo', $event)"
				@photo-moved="(photo, latLng) => emit('photo-moved', photo, latLng)" />

			<PhotoSuggestionsLayer v-if="photosEnabled && showPhotoSuggestions"
				:photo-suggestions="photoSuggestions"
				:photo-suggestions-tracks-and-devices="photoSuggestionsTracksAndDevices"
				:photo-suggestions-selected-indices="photoSuggestionsSelectedIndices"
				:date-filter-enabled="sliderEnabled"
				:date-filter-start="sliderStartTimestamp"
				:date-filter-end="sliderEndTimestamp"
				:draggable="photosDraggable"
				@open-sidebar="emit('open-sidebar', $event)"
				@photo-suggestion-selected="emit('photo-suggestion-selected', $event)"
				@photo-suggestion-moved="(index, latLng) => emit('photo-suggestion-moved', index, latLng)" />

			<SearchControl :visible="!showRouting"
				:search-data="searchData"
				:loading="searching"
				:result-poi-number="searchPois.length"
				@validate="onSearchValidate"
				@clear-pois="searchPois = []" />

			<RoutingControl :visible="showRouting"
				:search-data="routingSearchData"
				@close="showRouting = false"
				@track-added="emit('track-added', $event)" />

			<PoiMarkersLayer v-if="searchPois.length" :pois="searchPois" @add-favorite="emit('add-address-favorite', $event)" />

			<StylePoiLayer @add-favorite="emit('add-address-favorite', $event)" />

			<ClickSearchMarker v-if="clickSearchLatLng"
				:lat-lng="clickSearchLatLng"
				@add-favorite="emit('add-address-favorite', $event)"
				@close="clickSearchLatLng = null" />

			<PlaceContactMarker v-if="placingContactLatLng"
				:lat-lng="placingContactLatLng"
				@contact-placed="onContactPlaced"
				@close="placingContactLatLng = null" />

			<HistoryControl :last-actions="lastActions"
				:last-canceled-actions="lastCanceledActions"
				@cancel="emit('cancel')"
				@redo="emit('redo')" />

			<MglNavigationControl position="bottom-right" />
			<MglScaleControl position="bottom-left" />
			<MglGeolocationControl position="bottom-right" />

			<MglCustomControl position="bottom-right">
				<div class="map-style-switch">
					<button type="button"
						:class="{ active: baseStyleId === BaseStyleIds.Street }"
						:title="t('maps', 'Street map')"
						@click="baseStyleId = BaseStyleIds.Street">
						<Earth :size="18" />
					</button>
					<button type="button"
						:class="{ active: baseStyleId === BaseStyleIds.Satellite }"
						:title="t('maps', 'Satellite map')"
						@click="baseStyleId = BaseStyleIds.Satellite">
						<Satellite :size="18" />
					</button>
					<button type="button"
						:class="{ active: terrainEnabled }"
						:title="t('maps', 'Terrain')"
						@click="toggleTerrain">
						<TerrainIcon :size="18" />
					</button>
					<button type="button"
						:class="{ active: showRouting }"
						:title="t('maps', 'Find directions')"
						@click="showRouting = !showRouting">
						<Directions :size="18" />
					</button>
				</div>
			</MglCustomControl>
		</MglMap>

		<Slider v-show="sliderEnabled"
			:start="sliderStartTimestamp"
			:end="sliderEndTimestamp"
			:range-min="minDataTimestamp"
			:range-max="maxDataTimestamp"
			@range-change="emit('slider-range-changed', $event)" />

		<ElevationChart v-if="elevationTrack"
			:track="elevationTrack"
			@close="elevationTrack = null" />
	</div>
</template>

<script setup lang="ts">
import type { LngLatBoundsLike, MapMouseEvent } from 'maplibre-gl'
import { Popup } from 'maplibre-gl'
import { translate as t } from '@nextcloud/l10n'
import { computed, ref, watch } from 'vue'
import {
	MglCustomControl,
	MglGeolocationControl,
	MglMap,
	MglNavigationControl,
	MglRasterDemSource,
	MglScaleControl,
	useMap,
} from 'vue-maplibre-gl'
import 'maplibre-gl/dist/maplibre-gl.css'
import 'vue-maplibre-gl/dist/vue-maplibre-gl.css'
import Earth from 'vue-material-design-icons/Earth.vue'
import Satellite from 'vue-material-design-icons/Satellite.vue'
import TerrainIcon from 'vue-material-design-icons/Terrain.vue'
import Directions from 'vue-material-design-icons/Directions.vue'
import RoutingControl from './map/routing/RoutingControl.vue'
import SearchControl from './map/SearchControl.vue'
import PoiMarkersLayer from './map/PoiMarkersLayer.vue'
import StylePoiLayer from './map/StylePoiLayer.vue'
import ClickSearchMarker from './map/ClickSearchMarker.vue'
import type { AddressBookContact } from './map/PlaceContactMarker.vue'
import PlaceContactMarker from './map/PlaceContactMarker.vue'
import type { HistoryAction } from './map/HistoryControl.vue'
import HistoryControl from './map/HistoryControl.vue'
import Slider from './map/Slider.vue'
import ElevationChart from './map/ElevationChart.vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { searchPois as searchPoisNetwork } from '../network.js'
import { escapeHtml } from '../utils/domUtils.ts'
import type { Favorite, FavoriteCategory } from './map/FavoritesLayer.vue'
import FavoritesLayer from './map/FavoritesLayer.vue'
import type { Contact, ContactGroup } from './map/ContactsLayer.vue'
import ContactsLayer from './map/ContactsLayer.vue'
import type { Photo } from './map/PhotosLayer.vue'
import PhotosLayer from './map/PhotosLayer.vue'
import type { PhotoSuggestion, TrackOrDevice } from './map/PhotoSuggestionsLayer.vue'
import PhotoSuggestionsLayer from './map/PhotoSuggestionsLayer.vue'
import type { Track } from './map/TracksLayer.vue'
import TracksLayer from './map/TracksLayer.vue'
import type { Device } from './map/DevicesLayer.vue'
import DevicesLayer from './map/DevicesLayer.vue'
import type { BaseStyleId } from '../data/mapLayers.ts'
import { BaseStyleIds, BaseStyles, TERRAIN_SOURCE, TERRAIN_SOURCE_ID } from '../data/mapLayers.ts'

const props = withDefaults(defineProps<{
	/** Initial bounds, stored Leaflet-style as [[north, east], [south, west]]. */
	mapBoundsProp?: number[][]
	state?: string
	favorites?: Record<string, Favorite>
	favoriteCategories?: Record<string, FavoriteCategory>
	favoritesEnabled?: boolean
	favoritesDraggable?: boolean
	contacts?: Contact[]
	contactGroups?: Record<string, ContactGroup>
	contactsEnabled?: boolean
	photos?: Photo[]
	photosEnabled?: boolean
	photosDraggable?: boolean
	showPhotoSuggestions?: boolean
	photoSuggestions?: PhotoSuggestion[]
	photoSuggestionsTracksAndDevices?: Record<string, TrackOrDevice>
	photoSuggestionsSelectedIndices?: number[]
	photoSuggestionsHidePhotos?: boolean
	tracks?: Track[]
	tracksEnabled?: boolean
	devices?: Device[]
	devicesEnabled?: boolean
	sliderEnabled?: boolean
	sliderStartTimestamp?: number
	sliderEndTimestamp?: number
	minDataTimestamp?: number
	maxDataTimestamp?: number
	lastActions?: HistoryAction[]
	lastCanceledActions?: HistoryAction[]
	routingSearchData?: unknown[]
	searchData?: unknown[]
}>(), {
	mapBoundsProp: () => [],
	state: '',
	favorites: () => ({}),
	favoriteCategories: () => ({}),
	favoritesEnabled: false,
	favoritesDraggable: false,
	contacts: () => [],
	contactGroups: () => ({}),
	contactsEnabled: false,
	photos: () => [],
	photosEnabled: false,
	photosDraggable: false,
	showPhotoSuggestions: false,
	photoSuggestions: () => [],
	photoSuggestionsTracksAndDevices: () => ({}),
	photoSuggestionsSelectedIndices: () => [],
	photoSuggestionsHidePhotos: false,
	tracks: () => [],
	tracksEnabled: false,
	devices: () => [],
	devicesEnabled: false,
	sliderEnabled: false,
	sliderStartTimestamp: 0,
	sliderEndTimestamp: 0,
	minDataTimestamp: 0,
	maxDataTimestamp: 0,
	lastActions: () => [],
	lastCanceledActions: () => [],
	routingSearchData: () => [],
	searchData: () => [],
})

const emit = defineEmits<{
	'add-click': [position: { lat: number, lng: number }]
	'click-favorite': [favorite: Favorite]
	'edit-favorite': [favorite: Favorite]
	'delete-favorite': [id: number]
	'delete-favorites': [ids: number[]]
	'add-to-map-favorite': [favorite: Favorite]
	'address-deleted': [contact: Contact]
	'add-to-map-contact': [contact: Contact]
	'open-sidebar': [path: string]
	'click-track': [track: Track]
	'click-device': [device: Device]
	'track-added': [track: unknown]
	'add-address-favorite': [favorite: { latLng: { lat: number, lng: number }, name: string, formattedAddress: string }]
	'search-enable-track': [track: Track]
	'search-enable-device': [device: Device]
	'change-track-color': [track: Track]
	'add-to-map-track': [track: Track]
	'toggle-device-history': [device: Device]
	'change-device-color': [device: Device]
	'export-device': [device: Device]
	'add-to-map-device': [device: Device]
	'coords-reset': [paths: string[]]
	'add-to-map-photo': [photo: Photo]
	'photo-moved': [photo: Photo, latLng: { lat: number, lng: number }]
	'photo-suggestion-selected': [index: number]
	'photo-suggestion-moved': [index: number, latLng: { lat: number, lng: number }]
	'add-favorite': [latLng: { lat: number, lng: number }]
	'place-photos': [latLng: { lat: number, lng: number }]
	'contact-placed': [data: { contact: AddressBookContact, latLng: { lat: number, lng: number }, address: Record<string, string>, addressType: string }]
	cancel: []
	redo: []
	'slider-range-changed': [range: { start: number, end: number }]
}>()

const baseStyleId = ref<BaseStyleId>(BaseStyleIds.Street)
const activeStyle = computed(() => BaseStyles[baseStyleId.value])
const terrainEnabled = ref(false)
const showRouting = ref(false)

interface SearchResult {
	type: string
	latLng?: { lat: number, lng: number }
	device?: Device
	track?: Track
	subtype?: string
	value?: string
	rawResult?: { lat: string, lon: string, [key: string]: unknown }
	lat?: number
	lng?: number
}

const searchPois = ref<Array<{ lat: string, lon: string, [key: string]: unknown }>>([])
const searching = ref(false)
const clickSearchLatLng = ref<{ lat: number, lng: number } | null>(null)
const placingContactLatLng = ref<{ lat: number, lng: number } | null>(null)
const elevationTrack = ref<Track | null>(null)

const mapInstance = useMap()

const initialBounds = computed<LngLatBoundsLike | undefined>(() => {
	if (props.mapBoundsProp?.length !== 2) {
		return undefined
	}
	const [[north, east], [south, west]] = props.mapBoundsProp
	if (![north, east, south, west].every((n) => Number.isFinite(n))) {
		return undefined
	}
	// MapLibre wants [[west, south], [east, north]], Leaflet stored [[north, east], [south, west]].
	return [[west, south], [east, north]]
})

watch(() => mapInstance.isLoaded, (loaded) => {
	if (loaded && initialBounds.value) {
		mapInstance.map?.fitBounds(initialBounds.value, { animate: false })
	}
})

/**
 * @param wrapper vue-maplibre-gl's map event envelope; the native MapMouseEvent is under `.event`
 */
function onMapClick(wrapper: { event: MapMouseEvent }) {
	if (props.state === 'adding') {
		emit('add-click', wrapper.event.lngLat)
	}
}

/**
 * @param data the emitted contact-placement data, forwarded as-is
 */
function onContactPlaced(data: { contact: AddressBookContact, latLng: { lat: number, lng: number }, address: Record<string, string>, addressType: string }) {
	emit('contact-placed', data)
	placingContactLatLng.value = null
}

/**
 * @param latLng the coordinates to build a geo: link for and copy to the clipboard
 */
async function shareLocation(latLng: { lat: number, lng: number }) {
	const geoLink = 'geo:' + latLng.lat.toFixed(6) + ',' + latLng.lng.toFixed(6)
	try {
		await navigator.clipboard.writeText(geoLink)
		showSuccess(t('maps', 'Geo link ({geoLink}) copied to clipboard', { geoLink }))
	} catch (error) {
		console.debug(error)
		showError(t('maps', 'Geo link could not be copied to clipboard'))
	}
}

// The source id of every layer that has its own right-click menu, so a right-click on one
// of their markers/lines defers to that instead of showing the generic empty-map one below.
// PoiMarkersLayer/StylePoiLayer aren't listed here - neither has a right-click menu of its
// own, so right-clicking one of their markers falls through to the generic menu instead.
const INTERACTIVE_SOURCE_IDS = ['favorites', 'contacts', 'photos', 'photo-suggestions', 'tracks', 'devices']

let mapContextMenuPopup: Popup | null = null

/**
 * @param wrapper vue-maplibre-gl's map event envelope; the native MapMouseEvent is under `.event`
 */
function onMapContextMenu(wrapper: { event: MapMouseEvent }) {
	const map = mapInstance.map
	const event = wrapper.event
	if (!map) {
		return
	}
	const hasOwnMenu = map.queryRenderedFeatures(event.point).some((f) => INTERACTIVE_SOURCE_IDS.includes(f.source))
	if (hasOwnMenu) {
		return
	}
	event.preventDefault()

	const latLng = { lat: event.lngLat.lat, lng: event.lngLat.lng }
	const mapActions: Array<{ label: string, icon: string, callback: (obj: { id: string, name: string, latitude: string, longitude: string }) => void }> = window.OCA?.Maps?.mapActions ?? []

	const container = document.createElement('div')
	container.className = 'popup-map-wrapper'
	container.innerHTML = `
		<button class="action-btn" data-action="add-favorite">${escapeHtml(t('maps', 'Add a favorite'))}</button>
		<button class="action-btn" data-action="place-photos">${escapeHtml(t('maps', 'Place photos'))}</button>
		<button class="action-btn" data-action="place-contact">${escapeHtml(t('maps', 'Place contact'))}</button>
		<button class="action-btn" data-action="share-location">${escapeHtml(t('maps', 'Share this location'))}</button>
		${mapActions.map((action, index) => `<button class="action-btn" data-action="map-action-${index}">${escapeHtml(action.label)}</button>`).join('')}
	`
	container.addEventListener('click', (domEvent) => {
		const action = (domEvent.target as HTMLElement).getAttribute('data-action')
		if (action === 'add-favorite') {
			emit('add-favorite', latLng)
		} else if (action === 'place-photos') {
			emit('place-photos', latLng)
		} else if (action === 'place-contact') {
			placingContactLatLng.value = latLng
		} else if (action === 'share-location') {
			shareLocation(latLng)
		} else if (action?.startsWith('map-action-')) {
			const mapAction = mapActions[parseInt(action.slice('map-action-'.length), 10)]
			mapAction?.callback({
				id: 'geo:' + latLng.lat + ',' + latLng.lng,
				name: t('maps', 'Shared location'),
				latitude: latLng.lat.toString(),
				longitude: latLng.lng.toString(),
			})
		}
		mapContextMenuPopup?.remove()
	})

	mapContextMenuPopup?.remove()
	mapContextMenuPopup = new Popup({ closeButton: false, className: 'popovermenu open popupMarker mapContextMenuPopup' })
		.setLngLat(event.lngLat)
		.setDOMContent(container)
		.addTo(map)
}

function toggleTerrain() {
	terrainEnabled.value = !terrainEnabled.value
	mapInstance.map?.setTerrain(terrainEnabled.value ? { source: TERRAIN_SOURCE_ID, exaggeration: 1.5 } : null)
}

/**
 * @param bounds a [[west, south], [east, north]] MapLibre bounds array
 * @param options passed through to MapLibre's fitBounds
 */
function fitBounds(bounds: LngLatBoundsLike, options?: object) {
	mapInstance.map?.fitBounds(bounds, options)
}

/**
 * @param track the track to zoom to, using its precomputed metadata bounding box
 */
function zoomOnTrack(track: Track) {
	const md = track.metadata as undefined | { s?: number, w?: number, n?: number, e?: number }
	if (md?.s === undefined || md.w === undefined || md.n === undefined || md.e === undefined) {
		return
	}
	mapInstance.map?.fitBounds([[md.w, md.s], [md.e, md.n]], { padding: 30 })
}

/**
 * @param device the device to zoom to, fitting the bounds of all its recorded points
 */
function zoomOnDevice(device: Device) {
	if (!device.points?.length) {
		return
	}
	const lats = device.points.map((p) => p.lat)
	const lngs = device.points.map((p) => p.lng)
	mapInstance.map?.fitBounds([[Math.min(...lngs), Math.min(...lats)], [Math.max(...lngs), Math.max(...lats)]], { padding: 30 })
}

/**
 * @param photo the photo suggestion to zoom to
 */
function zoomOnPhotoSuggestion(photo: { lat?: number, lng?: number }) {
	if (!photo.lat || !photo.lng) {
		return
	}
	mapInstance.map?.fitBounds([[photo.lng - 0.001, photo.lat - 0.001], [photo.lng + 0.001, photo.lat + 0.001]], { padding: 30 })
}

/**
 * @param track the track whose elevation profile to display, or none to close the panel
 */
function displayElevation(track?: Track) {
	elevationTrack.value = track ?? null
}

/**
 * @param element the selected search result, from App.vue's searchData
 */
function onSearchValidate(element: SearchResult) {
	if (element.type === 'contact' || element.type === 'favorite') {
		if (element.latLng) {
			mapInstance.map?.flyTo({ center: [element.latLng.lng, element.latLng.lat], zoom: 15 })
		}
	} else if (element.type === 'device' && element.device) {
		if (!element.device.enabled) {
			emit('search-enable-device', element.device)
		} else {
			zoomOnDevice(element.device)
		}
	} else if (element.type === 'track' && element.track) {
		if (!element.track.enabled) {
			emit('search-enable-track', element.track)
		} else {
			zoomOnTrack(element.track)
		}
	} else if (element.type === 'poi' && element.subtype && element.value) {
		const bounds = mapInstance.map?.getBounds()
		if (!bounds) {
			return
		}
		searching.value = true
		searchPoisNetwork(element.subtype, element.value, {
			west: bounds.getWest(), south: bounds.getSouth(), east: bounds.getEast(), north: bounds.getNorth(),
		}).then((response: { data: Array<{ lat: string, lon: string }> }) => {
			searchPois.value = response.data
			if (!response.data.length) {
				showError(t('maps', 'No results found in the current map view'))
			}
		}).catch((error: unknown) => {
			console.error(error)
			showError(t('maps', 'Failed to search for {value}', { value: element.value }))
		}).finally(() => {
			searching.value = false
		})
	} else if (element.type === 'result' && element.rawResult) {
		searchPois.value = [element.rawResult]
		mapInstance.map?.flyTo({ center: [parseFloat(element.rawResult.lon), parseFloat(element.rawResult.lat)], zoom: 15 })
	} else if (element.type === 'coordinate' && element.lat !== undefined && element.lng !== undefined) {
		clickSearchLatLng.value = { lat: element.lat, lng: element.lng }
		mapInstance.map?.flyTo({ center: [element.lng, element.lat], zoom: 15 })
	} else if (element.type === 'mylocation') {
		navigator.geolocation.getCurrentPosition((position) => {
			mapInstance.map?.flyTo({ center: [position.coords.longitude, position.coords.latitude], zoom: 15 })
		})
	}
}

defineExpose({ fitBounds, zoomOnTrack, zoomOnDevice, zoomOnPhotoSuggestion, displayElevation })
</script>

<style lang="scss" scoped>
#map-wrapper {
	position: relative;
	width: 100%;
	height: 100%;

	&.loading {
		cursor: wait;
	}

	&.adding {
		cursor: crosshair;
	}
}

:deep(.maplibregl-map) {
	width: 100%;
	height: 100%;
}

.map-style-switch {
	display: flex;
	flex-direction: column;
	background-color: var(--color-main-background);

	button {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 29px;
		height: 29px;
		border: 0;
		background-color: transparent;
		color: var(--color-main-text);
		cursor: pointer;

		&.active {
			background-color: var(--color-background-hover);
		}

		&:hover {
			background-color: var(--color-background-hover);
		}
	}
}
</style>

<!-- Unscoped: the context menu's content is a raw DOM node handed to a maplibregl.Popup,
     not part of this component's own template, so scoped styles wouldn't reach it. -->
<style lang="scss">
.popup-map-wrapper .action-btn {
	display: block;
	width: 100%;
	padding: 8px;
	border: none;
	background: transparent;
	text-align: left;
	cursor: pointer;

	&:hover {
		background: var(--color-background-hover);
	}
}
</style>
