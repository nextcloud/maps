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
		<LMap
			ref="map"
			:center="mapOptions.center"
			:max-bounds="mapOptions.maxBounds"
			:min-zoom="mapOptions.minZoom"
			:max-zoom="mapOptions.maxZoom"
			:zoom="mapOptions.zoom"
			:options="mapOptions.native"
			@ready="onMapReady">
			<LControlZoom position="bottomright" />
			<LControlScale
				position="bottomleft"
				:imperial="mapOptions.scaleControlShouldUseImperial"
				:metric="!mapOptions.scaleControlShouldUseImperial" />

			<LTileLayer
				:key="activeLayer.id"
				:url="activeLayer.url"
				:attribution="activeLayer.attribution"
				:name="activeLayer.name"
				:layer-type="activeLayer.type"
				:options="activeLayer.options"
				:opacity="activeLayer.type === 'overlay' ? activeLayer.opacity : 1" />

			<LMarkerCluster
				v-for="categoryKey in Object.keys(favoriteCategories)"
				:key="categoryKey"
				:options="{
					...clusterOptions,
					iconCreateFunction: getClusterIconCreateFunction(categoryKey)
				}">
				<LMarker
					v-for="favorite in favoriteCategories[categoryKey]"
					:key="favorite.id"
					:lat-lng="[favorite.lat, favorite.lng]"
					:icon="createNewDivIcon(categoryKey)"
					@popupopen="handleMarkerPopupOpened(favorite.id)"
					@popupclose="handleMarkerPopupClosed(favorite.id)"
					@ready="marker => handleMarkerReady(favorite.id, marker)">
					<LPopup>
						<FavoritePopup
							:favorite="favorite"
							:is-visible="openMarkerPopupId === favorite.id"
							:allow-category-customization="!isPublicShare"
							:allow-edits="allowFavoriteEdits"
							@deleteFavorite="handleDeleteFavorite"
							@updateFavorite="handleUpdateFavorite" />
					</LPopup>
				</LMarker>
			</LMarkerCluster>

			<LFeatureGroup @ready="onFeatureGroupReady">
				<LPopup :lat-lng="popup.latLng">
					<ClickPopup
						:is-visible="popup.visible"
						:lat-lng="popup.latLng"
						:allow-category-customization="!isPublicShare"
						:allow-edits="allowFavoriteEdits"
						@close="handlePopupCloseRequest"
						@addFavorite="handleAddFavorite" />
				</LPopup>
			</LFeatureGroup>
		</LMap>
	</div>
</template>

<script>
import { DivIcon, latLngBounds } from 'leaflet'
import VueTypes from 'vue-types'

import { LControlScale, LControlZoom, LFeatureGroup, LMap, LMarker, LPopup, LTileLayer } from 'vue2-leaflet'
import LMarkerCluster from 'vue2-leaflet-markercluster'

import { mapActions, mapState } from 'vuex'
import ClickPopup from './map/ClickPopup'
import FavoritePopup from './map/FavoritePopup'
import { isPublicShare } from '../utils/common'
import { PUBLIC_FAVORITES_NAMESPACE } from '../store/modules/publicFavorites'
import { LayerIds, Layers } from '../data/mapLayers'
import { getThemingColorFromCategoryKey } from '../utils/favoritesUtils'
import { getShouldMapUseImperial } from '../utils/mapUtils'

const CLUSTER_MAX_ZOOM_LEVEL = 14

export default {
	name: 'MapContainer',

	components: {
		ClickPopup,
		LMap,
		LFeatureGroup,
		LMarker,
		LMarkerCluster,
		LTileLayer,
		LPopup,
		FavoritePopup,
		LControlZoom,
		LControlScale,
	},

	props: {
		favoriteCategories: VueTypes.object.isRequired.def(null),
		isPublicShare: VueTypes.bool.isRequired.def(false),
		allowFavoriteEdits: VueTypes.bool.def(false),
	},

	data() {
		return {
			activeLayerId: LayerIds.OSM,
			openMarkerPopupId: null,
			popup: {
				visible: false,
				latLng: { lat: 0, lng: 0 },
			},
			mapOptions: {
				center: [0, 0],
				zoom: 2,
				minZoom: 2,
				maxZoom: 19,
				initialBounds: latLngBounds([
					[40.70081290280357, -74.26963806152345],
					[40.82991732677597, -74.08716201782228],
				]),
				maxBounds: latLngBounds([
					[-90, 720],
					[90, -720],
				]),
				native: {
					zoomControl: false,
				},
				scaleControlShouldUseImperial: getShouldMapUseImperial(),
			},
			clusterOptions: {
				showCoverageOnHover: false,
				zoomToBoundsOnClick: true,
				spiderfyOnMaxZoom: false,
				disableClusteringAtZoom: CLUSTER_MAX_ZOOM_LEVEL,
			},
		}
	},

	computed: {
		...mapState({
			selectedFavoriteId: state =>
				isPublicShare()
					? state[PUBLIC_FAVORITES_NAMESPACE].selectedFavoriteId
					: null,
			selectedFavorite: state =>
				isPublicShare()
					? state[PUBLIC_FAVORITES_NAMESPACE].favorites.find(
						favorite =>
							favorite.id
                            === state[PUBLIC_FAVORITES_NAMESPACE].selectedFavoriteId
					)
					: null,
		}),
		layers() {
			return Layers
		},
		activeLayer() {
			return this.layers.find(layer => layer.id === this.activeLayerId)
		},
	},

	watch: {
		selectedFavoriteId(val) {
			if (val !== null) {
				const marker = this.markerMap[val]

				if (marker) {
					this.setMapView(marker.getLatLng(), CLUSTER_MAX_ZOOM_LEVEL)
					marker.openPopup()
				} else {
					console.warn(
						'[MapContainer] Cannot find marker for favorite id: ',
						val
					)
				}
			}
		},
	},

	created() {
		this.featureGroup = null
		this.popupWasJustClosed = false
		this.markerMap = []
	},

	methods: {
		...mapActions({
			selectFavorite: `${PUBLIC_FAVORITES_NAMESPACE}/selectFavorite`,
		}),

		setMapView(latLng, zoom) {
			this.$refs.map.mapObject.setView(latLng, zoom)
		},

		handleMarkerReady(favoriteId, marker) {
			this.markerMap[favoriteId] = marker
		},

		handleAddFavorite(data) {
			this.$emit('addFavorite', data)
		},

		handleUpdateFavorite(data) {
			this.$emit('updateFavorite', data)
		},

		handleDeleteFavorite(data) {
			this.$emit('deleteFavorite', data)
		},

		handleMarkerPopupOpened(id) {
			this.openMarkerPopupId = id
		},

		handleMarkerPopupClosed() {
			this.openMarkerPopupId = null

			this.selectFavorite(null)
		},

		openPopup(lat, lng) {
			this.popup.visible = true
			this.popup.latLng = { lat, lng }
			this.featureGroup.openPopup([lat, lng])
		},

		closePopup() {
			this.resetPopupState()
			this.featureGroup.closePopup()
		},

		resetPopupState() {
			this.popup.visible = false
			this.popup.latLng = { lat: 0, lng: 0 }
		},

		handleMapClick(e) {
			if (!this.popup.visible && !this.popupWasJustClosed) {
				this.openPopup(e.latlng.lat, e.latlng.lng)
			}
		},

		handlePopupCloseRequest() {
			this.closePopup()
		},

		createNewDivIcon(categoryKey) {
			return new DivIcon({
				iconAnchor: [9, 9],
				iconSize: [18, 18],
				className: 'leaflet-marker-favorite',
				html: `<div
            class="favorite-marker ${categoryKey}"
            style="
              background-color: ${this.getMarkerBackgroundColor(categoryKey)};
            "
          ></div>`,
			})
		},

		getMarkerBackgroundColor(categoryKey) {
			return getThemingColorFromCategoryKey(categoryKey)
		},

		getClusterIconCreateFunction(categoryKey) {
			return cluster => {
				const label = cluster.getChildCount()

				return new DivIcon({
					iconAnchor: [14, 14],
					iconSize: [28, 28],
					className: 'leaflet-marker-favorite-cluster cluster-marker',
					html: `
                        <div
                          class="favorite-cluster-marker ${categoryKey}"
                          style="background-color: ${this.getMarkerBackgroundColor(categoryKey)};"
                        ></div>
                        <span class="label">${label}</span>`,
				})
			}
		},

		handlePopupOpenEvent() {},

		handlePopupCloseEvent() {
			this.popupWasJustClosed = true
			this.resetPopupState()

			this.$nextTick(() => {
				this.popupWasJustClosed = false
			})
		},

		onMapReady(map) {
			map.on('click', this.handleMapClick)
		},

		onFeatureGroupReady(featureGroup) {
			featureGroup.on('popupopen', this.handlePopupOpenEvent)
			featureGroup.on('popupclose', this.handlePopupCloseEvent)

			this.featureGroup = featureGroup
		},
	},
}
</script>

<style lang="scss">
@import "~leaflet/dist/leaflet.css";
@import "~leaflet.markercluster/dist/MarkerCluster.css";
@import "~leaflet.markercluster/dist/MarkerCluster.Default.css";

.leaflet-tooltip {
    white-space: normal !important;
}

.leaflet-container {
    background: var(--color-main-background);
}

.leaflet-control-layers-base {
    line-height: 30px;
}

.leaflet-control-layers-selector {
    min-height: 0;
}

.leaflet-control-layers-toggle {
    background-size: 75% !important;
}

.leaflet-control-layers:not(.leaflet-control-layers-expanded) {
    width: 33px;
    height: 37px;
}

.leaflet-control-layers:not(.leaflet-control-layers-expanded) > a {
    width: 100%;
    height: 100%;
}

.favorite-marker,
.favorite-cluster-marker {
    background: var(--maps-icon-favorite-star) no-repeat 50% 50%;
    border-radius: 50%;
    box-shadow: 0 0 4px #888;
}

.favorite-marker {
    height: 18px;
    width: 18px;
    background-size: 12px 12px;
}

.favorite-cluster-marker {
    height: 26px;
    width: 26px;
    background-size: 16px 16px;
}

.leaflet-marker-favorite-cluster {
    .label {
        position: absolute;
        top: -7px;
        right: -11px;
        color: #fff;
        background-color: #333;
        border-radius: 9px;
        height: 18px;
        min-width: 18px;
        line-height: 12px;
        text-align: center;
        padding: 3px;
    }
}

.leaflet-touch {
    .leaflet-control-layers,
    .leaflet-bar {
        border: none;
        border-radius: var(--border-radius);
    }
}

.leaflet-control-attribution.leaflet-control {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 50vw;
}

.leaflet-popup {
    .leaflet-popup-content-wrapper {
        border-radius: 4px;
    }

    .leaflet-popup-close-button {
        top: 9px;
        right: 9px;
    }
}
</style>

<style lang="scss" scoped>
.map-container {
    position: relative;
    height: 100%;
    width: 100%;
}
</style>
