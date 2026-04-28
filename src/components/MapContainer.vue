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

<script>
import VueTypes from 'vue-types'

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
import { computed } from 'vue'
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

export default {
	name: 'MapContainer',

	components: {
		MglMap,
		MglRasterSource,
		MglRasterLayer,
		MglMarker,
		MglPopup,
		MglNavigationControl,
		MglScaleControl,
		ClickPopup,
		FavoritePopup,
	},

	props: {
		favoriteCategories: VueTypes.object.isRequired.def({}),
		isPublicShare: VueTypes.bool.isRequired.def(false),
		allowFavoriteEdits: VueTypes.bool.def(false),
	},

	setup() {
		const publicFavoritesStore = usePublicFavoritesStore()
		return {
			selectedFavoriteId: computed(() => isPublicShare() ? publicFavoritesStore.selectedFavoriteId : null),
			selectedFavorite: computed(() => isPublicShare()
				? publicFavoritesStore.favorites.find(f => f.id === publicFavoritesStore.selectedFavoriteId)
				: null),
			selectFavorite: (id) => publicFavoritesStore.selectFavorite(id),
		}
	},

	data() {
		return {
			minimalStyle,
			activeLayerId: LayerIds.OSM,
			openMarkerPopupId: null,
			placePopup: {
				visible: false,
				latLng: { lat: 0, lng: 0 },
			},
			mapInstance: null,
			mapClickPopupLocked: false,
		}
	},

	computed: {
		scaleUnit() {
			return getShouldMapUseImperial() ? 'imperial' : 'metric'
		},
		layers() {
			return Layers
		},
		activeLayer() {
			return this.layers.find(layer => layer.id === this.activeLayerId)
		},
		allFavorites() {
			return Object.entries(this.favoriteCategories).flatMap(([categoryKey, favs]) =>
				favs.map(f => ({ ...f, categoryKey })),
			)
		},
		favoriteBounds() {
			if (this.allFavorites.length === 0) return null
			const lats = this.allFavorites.map(f => f.lat)
			const lngs = this.allFavorites.map(f => f.lng)
			return [[Math.min(...lngs), Math.min(...lats)], [Math.max(...lngs), Math.max(...lats)]]
		},
	},

	watch: {
		selectedFavoriteId(val) {
			if (val !== null) {
				const fav = this.allFavorites.find(f => f.id === val)
				if (fav) {
					this.setMapView({ lat: fav.lat, lng: fav.lng }, CLUSTER_MAX_ZOOM_LEVEL)
					this.openMarkerPopupId = val
				} else {
					console.warn('[MapContainer] Cannot find favorite id: ', val)
				}
			}
		},
	},

	methods: {
		onMapLoad(map) {
			this.mapInstance = map
			if (this.favoriteBounds) {
				map.fitBounds(this.favoriteBounds, { padding: 30 })
			}
		},

		setMapView(latLng, zoom) {
			if (this.mapInstance) {
				this.mapInstance.flyTo({ center: [latLng.lng, latLng.lat], zoom })
			}
		},

		emitAddFavoriteEvent(data) {
			this.$emit('add-favorite', data)
		},

		emitUpdateFavoriteEvent(data) {
			this.$emit('update-favorite', data)
		},

		emitDeleteFavoriteEvent(data) {
			this.$emit('delete-favorite', data)
		},

		storeCurrentlyOpenPopup(id) {
			this.openMarkerPopupId = id
		},

		forgetCurrentlyOpenPopup() {
			this.mapClickPopupLocked = true
			this.openMarkerPopupId = null
			this.$nextTick(() => {
				this.mapClickPopupLocked = false
			})
			this.selectFavorite(null)
		},

		openPopup(lat, lng) {
			this.placePopup.visible = true
			this.placePopup.latLng = { lat, lng }
		},

		closePopup() {
			this.resetPopupState()
		},

		resetPopupState() {
			this.placePopup.visible = false
			this.placePopup.latLng = { lat: 0, lng: 0 }
		},

		handleMapClick(e) {
			if (!this.placePopup.visible && !this.mapClickPopupLocked) {
				this.openPopup(e.lngLat.lat, e.lngLat.lng)
			}
		},

		getMarkerBackgroundColor(categoryKey) {
			return getThemingColorFromCategoryKey(categoryKey)
		},
	},
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
