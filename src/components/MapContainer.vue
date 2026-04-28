<!--
  - @copyright Copyright (c) 2019 Paul Schwörer <hello@paulschwoerer.de>
  -
  - @author Paul Schwörer <hello@paulschwoerer.de>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<div class="map-container">
		<MglMap
			:center="[0, 0]"
			:zoom="2"
			:min-zoom="2"
			:max-zoom="19"
			:map-style="minimalStyle"
			@map:load="onMapLoad"
			@map:click="handleMapClick">
			<MglRasterSource
				source-id="base-tiles"
				:tiles="activeLayer.tiles"
				:tile-size="activeLayer.tileSize"
				:max-zoom="activeLayer.maxzoom"
				:attribution="activeLayer.attribution" />
			<MglRasterLayer
				layer-id="base-layer"
				source="base-tiles" />
			<MglNavigationControl position="bottom-right" />
			<MglScaleControl position="bottom-left" :unit="scaleUnit" />

			<MglMarker
				v-for="favorite in allFavorites"
				:key="favorite.id"
				:coordinates="[favorite.lng, favorite.lat]">
				<template #default>
					<div class="favorite-marker"
						:style="'background-color: ' + getMarkerBackgroundColor(favorite.categoryKey)"
						@click.stop="storeCurrentlyOpenPopup(favorite.id)" />
					<MglPopup v-if="openMarkerPopupId === favorite.id"
						:close-button="true"
						anchor="bottom"
						@close="forgetCurrentlyOpenPopup(favorite.id)">
						<FavoritePopup
							:favorite="favorite"
							:is-visible="openMarkerPopupId === favorite.id"
							:allow-category-customization="!isPublicShare"
							:allow-edits="allowFavoriteEdits"
							@delete-favorite="emitDeleteFavoriteEvent"
							@update-favorite="emitUpdateFavoriteEvent" />
					</MglPopup>
				</template>
			</MglMarker>

			<MglMarker v-if="placePopup.visible"
				:coordinates="[placePopup.latLng.lng, placePopup.latLng.lat]">
				<template #default>
					<div style="display:none" />
					<MglPopup :close-button="true" anchor="bottom" :showed="true" @close="closePopup">
						<ClickPopup
							:is-visible="placePopup.visible"
							:lat-lng="placePopup.latLng"
							:allow-category-customization="!isPublicShare"
							:allow-edits="allowFavoriteEdits"
							@close="closePopup"
							@add-favorite="emitAddFavoriteEvent" />
					</MglPopup>
				</template>
			</MglMarker>
		</MglMap>
	</div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import {
	MglMap,
	MglRasterSource,
	MglRasterLayer,
	MglMarker,
	MglPopup,
	MglNavigationControl,
	MglScaleControl,
} from '@indoorequal/vue-maplibre-gl'
import { usePublicFavoritesStore } from '../store/publicFavoritesStore.pinia.js'
import ClickPopup from './map/ClickPopup.vue'
import FavoritePopup from './map/FavoritePopup.vue'
import { isPublicShare } from '../utils/common.js'
import { LayerIds, Layers } from '../data/mapLayers.js'
import { getThemingColorFromCategoryKey } from '../utils/favoritesUtils.js'
import { getShouldMapUseImperial } from '../utils/mapUtils.js'

const CLUSTER_MAX_ZOOM_LEVEL = 14

const minimalStyle = {
	version: 8,
	sources: {},
	layers: [],
}

const props = defineProps({
	favoriteCategories: {
		type: Object,
		required: true,
		default: () => ({}),
	},
	isPublicShare: {
		type: Boolean,
		required: true,
		default: false,
	},
	allowFavoriteEdits: {
		type: Boolean,
		default: false,
	},
})

const emit = defineEmits(['add-favorite', 'update-favorite', 'delete-favorite'])

const publicFavoritesStore = usePublicFavoritesStore()
const selectedFavoriteId = computed(() => isPublicShare() ? publicFavoritesStore.selectedFavoriteId : null)
const selectedFavorite = computed(() => isPublicShare()
	? publicFavoritesStore.favorites.find(f => f.id === publicFavoritesStore.selectedFavoriteId)
	: null)
const selectFavorite = (id) => publicFavoritesStore.selectFavorite(id)

const activeLayerId = ref(LayerIds.OSM)
const openMarkerPopupId = ref(null)
const placePopup = ref({ visible: false, latLng: { lat: 0, lng: 0 } })
const mapInstance = ref(null)
const mapClickPopupLocked = ref(false)

const scaleUnit = computed(() => getShouldMapUseImperial() ? 'imperial' : 'metric')
const layers = computed(() => Layers)
const activeLayer = computed(() => layers.value.find(layer => layer.id === activeLayerId.value))
const allFavorites = computed(() =>
	Object.entries(props.favoriteCategories).flatMap(([categoryKey, favs]) =>
		favs.map(f => ({ ...f, categoryKey })),
	),
)
const favoriteBounds = computed(() => {
	if (allFavorites.value.length === 0) return null
	const lats = allFavorites.value.map(f => f.lat)
	const lngs = allFavorites.value.map(f => f.lng)
	return [[Math.min(...lngs), Math.min(...lats)], [Math.max(...lngs), Math.max(...lats)]]
})

watch(selectedFavoriteId, (val) => {
	if (val !== null) {
		const fav = allFavorites.value.find(f => f.id === val)
		if (fav) {
			setMapView({ lat: fav.lat, lng: fav.lng }, CLUSTER_MAX_ZOOM_LEVEL)
			openMarkerPopupId.value = val
		} else {
			console.warn('[MapContainer] Cannot find favorite id: ', val)
		}
	}
})

function onMapLoad(map) {
	mapInstance.value = map
	if (favoriteBounds.value) {
		map.fitBounds(favoriteBounds.value, { padding: 30 })
	}
}

function setMapView(latLng, zoom) {
	if (mapInstance.value) {
		mapInstance.value.flyTo({ center: [latLng.lng, latLng.lat], zoom })
	}
}

function emitAddFavoriteEvent(data) { emit('add-favorite', data) }
function emitUpdateFavoriteEvent(data) { emit('update-favorite', data) }
function emitDeleteFavoriteEvent(data) { emit('delete-favorite', data) }

function storeCurrentlyOpenPopup(id) {
	openMarkerPopupId.value = id
}

function forgetCurrentlyOpenPopup() {
	mapClickPopupLocked.value = true
	openMarkerPopupId.value = null
	nextTick(() => {
		mapClickPopupLocked.value = false
	})
	selectFavorite(null)
}

function openPopup(lat, lng) {
	placePopup.value.visible = true
	placePopup.value.latLng = { lat, lng }
}

function closePopup() {
	placePopup.value.visible = false
	placePopup.value.latLng = { lat: 0, lng: 0 }
}

function handleMapClick(e) {
	if (!placePopup.value.visible && !mapClickPopupLocked.value) {
		openPopup(e.lngLat.lat, e.lngLat.lng)
	}
}

function getMarkerBackgroundColor(categoryKey) {
	return getThemingColorFromCategoryKey(categoryKey)
}
</script>

<style lang="scss">
.maplibregl-map {
	background: var(--color-main-background);
}

.maplibregl-ctrl-attrib {
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	max-width: 50vw;
}
</style>

<style lang="scss" scoped>
.map-container {
	position: relative;
	height: 100%;
	width: 100%;
}

.favorite-marker {
	width: 26px;
	height: 26px;
	border-radius: 50%;
	box-shadow: 0 0 4px #888;
	background: var(--maps-icon-favorite-star) no-repeat 50% 50%;
	background-size: 16px 16px;
	cursor: pointer;
}
</style>
