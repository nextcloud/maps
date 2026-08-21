<!--
 - SPDX-FileCopyrightText: 2019 Paul Schwörer <hello@paulschwoerer.de>
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	Port in progress: the map engine moved from Leaflet to vue-maplibre-gl.
	Ported so far: the base map (street/satellite/terrain), click-to-add,
	favorites/contacts/photos/tracks/devices markers and clustering, and
	routing. Everything below is intentionally not wired up yet and will
	come back one feature at a time in follow-up steps:
	- right-click context menu, drag-to-edit
	- search box / history control / slider
	- track elevation profile chart
-->
<template>
	<div id="map-wrapper" :class="{ loading: state === 'loading', adding: state === 'adding' }">
		<MglMap :map-style="activeStyle"
			:center="[0, 0]"
			:zoom="2"
			:min-zoom="2"
			:max-zoom="19"
			@map:click="onMapClick">
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
				@click="emit('click-track', $event)" />

			<DevicesLayer v-if="devicesEnabled"
				:devices="devices"
				:start="sliderStartTimestamp"
				:end="sliderEndTimestamp"
				@click="emit('click-device', $event)" />

			<FavoritesLayer v-if="favoritesEnabled"
				:favorites="favorites"
				:categories="favoriteCategories"
				@click="emit('click-favorite', $event)" />

			<ContactsLayer v-if="contactsEnabled"
				:contacts="contacts"
				:groups="contactGroups"
				@address-deleted="emit('address-deleted', $event)"
				@add-to-map-contact="emit('add-to-map-contact', $event)" />

			<PhotosLayer v-if="photosEnabled"
				:photos="photos"
				:date-filter-enabled="sliderEnabled"
				:date-filter-start="sliderStartTimestamp"
				:date-filter-end="sliderEndTimestamp"
				@open-sidebar="emit('open-sidebar', $event)" />

			<RoutingControl :visible="showRouting"
				:search-data="routingSearchData"
				@close="showRouting = false"
				@track-added="emit('track-added', $event)" />

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
	</div>
</template>

<script setup lang="ts">
import type { LngLatBoundsLike, MapMouseEvent } from 'maplibre-gl'
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
import type { Favorite, FavoriteCategory } from './map/FavoritesLayer.vue'
import FavoritesLayer from './map/FavoritesLayer.vue'
import type { Contact, ContactGroup } from './map/ContactsLayer.vue'
import ContactsLayer from './map/ContactsLayer.vue'
import type { Photo } from './map/PhotosLayer.vue'
import PhotosLayer from './map/PhotosLayer.vue'
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
	contacts?: Contact[]
	contactGroups?: Record<string, ContactGroup>
	contactsEnabled?: boolean
	photos?: Photo[]
	photosEnabled?: boolean
	tracks?: Track[]
	tracksEnabled?: boolean
	devices?: Device[]
	devicesEnabled?: boolean
	sliderEnabled?: boolean
	sliderStartTimestamp?: number
	sliderEndTimestamp?: number
	routingSearchData?: unknown[]
}>(), {
	mapBoundsProp: () => [],
	state: '',
	favorites: () => ({}),
	favoriteCategories: () => ({}),
	favoritesEnabled: false,
	contacts: () => [],
	contactGroups: () => ({}),
	contactsEnabled: false,
	photos: () => [],
	photosEnabled: false,
	tracks: () => [],
	tracksEnabled: false,
	devices: () => [],
	devicesEnabled: false,
	sliderEnabled: false,
	sliderStartTimestamp: 0,
	sliderEndTimestamp: 0,
	routingSearchData: () => [],
})

const emit = defineEmits<{
	'add-click': [position: { lat: number, lng: number }]
	'click-favorite': [favorite: Favorite]
	'address-deleted': [contact: Contact]
	'add-to-map-contact': [contact: Contact]
	'open-sidebar': [path: string]
	'click-track': [track: Track]
	'click-device': [device: Device]
	'track-added': [track: unknown]
}>()

const baseStyleId = ref<BaseStyleId>(BaseStyleIds.Street)
const activeStyle = computed(() => BaseStyles[baseStyleId.value])
const terrainEnabled = ref(false)
const showRouting = ref(false)

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

function toggleTerrain() {
	terrainEnabled.value = !terrainEnabled.value
	mapInstance.map?.setTerrain(terrainEnabled.value ? { source: TERRAIN_SOURCE_ID, exaggeration: 1.5 } : null)
}

interface LeafletLikeBounds {
	getWest(): number
	getSouth(): number
	getEast(): number
	getNorth(): number
}

/**
 * @param bounds either a Leaflet-style LatLngBounds (still constructed that way by some
 *   App.vue call sites) or a plain [[west, south], [east, north]] MapLibre bounds array
 * @param options passed through to MapLibre's fitBounds
 */
function fitBounds(bounds: LeafletLikeBounds | LngLatBoundsLike, options?: object) {
	const asLeafletBounds = bounds as Partial<LeafletLikeBounds>
	const boundsArray = typeof asLeafletBounds.getWest === 'function'
		? [[asLeafletBounds.getWest!(), asLeafletBounds.getSouth!()], [asLeafletBounds.getEast!(), asLeafletBounds.getNorth!()]] as LngLatBoundsLike
		: bounds as LngLatBoundsLike
	mapInstance.map?.fitBounds(boundsArray, options)
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
 * The track elevation profile chart isn't ported to vue-maplibre-gl yet.
 */
function displayElevation() {
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
