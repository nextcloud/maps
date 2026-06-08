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
		<div ref="mapContainer" style="width: 100%; height: 100%;"></div>

		<div style="display: none;">
			<div ref="favPopupWrapper">
				<FavoritePopup v-if="popupFavorite"
					:favorite="popupFavorite"
					:is-visible="!!openMarkerPopupId"
					:allow-category-customization="!isPublicShare"
					:allow-edits="allowFavoriteEdits"
					@delete-favorite="emitDeleteFavoriteEvent"
					@update-favorite="emitUpdateFavoriteEvent" />
			</div>
			
			<div ref="clickPopupWrapper">
				<ClickPopup
					:is-visible="placePopup.visible"
					:lat-lng="placePopup.latLng"
					:allow-category-customization="!isPublicShare"
					:allow-edits="allowFavoriteEdits"
					@close="closePopup"
					@add-favorite="emitAddFavoriteEvent" />
			</div>
		</div>
	</div>
</template>

<script>
import L from 'leaflet'
import 'leaflet.markercluster'
import VueTypes from 'vue-types'
import { mapActions, mapState } from 'vuex'
import ClickPopup from './map/ClickPopup.vue'
import FavoritePopup from './map/FavoritePopup.vue'
import { isPublicShare } from '../utils/common.js'
import { PUBLIC_FAVORITES_NAMESPACE } from '../store/modules/publicFavorites.js'
import { LayerIds, Layers } from '../data/mapLayers.js'
import { getThemingColorFromCategoryKey } from '../utils/favoritesUtils.js'
import { getShouldMapUseImperial } from '../utils/mapUtils.js'

const CLUSTER_MAX_ZOOM_LEVEL = 14
const MARKER_TOUCH_TARGET_SIZE = 44

export default {
	name: 'MapContainer',

	components: {
		ClickPopup,
		FavoritePopup,
	},

	props: {
		favoriteCategories: VueTypes.object.isRequired.def({}),
		isPublicShare: VueTypes.bool.isRequired.def(false),
		allowFavoriteEdits: VueTypes.bool.def(false),
	},

	data() {
		return {
			activeLayerId: LayerIds.OSM,
			openMarkerPopupId: null,
			popupFavorite: null, // Holds data for the active popup
			placePopup: {
				visible: false,
				latLng: { lat: 0, lng: 0 },
			},
			mapOptions: {
				center: [0, 0],
				zoom: 2,
				minZoom: 2,
				maxZoom: 19,
				scaleControlShouldUseImperial: getShouldMapUseImperial(),
			},
		}
	},

	computed: {
		...mapState({
			selectedFavoriteId: state =>
				isPublicShare()
					? state[PUBLIC_FAVORITES_NAMESPACE].selectedFavoriteId
					: null,
		}),
		layers() { return Layers },
		activeLayer() { return this.layers.find(layer => layer.id === this.activeLayerId) },
	},

	watch: {
		selectedFavoriteId(val) {
			if (val !== null && this.markerMap.has(val)) {
				const marker = this.markerMap.get(val);
				this.map.setView(marker.getLatLng(), CLUSTER_MAX_ZOOM_LEVEL);
				
				// Set dynamic popup data before opening
				this.popupFavorite = marker.data;
				this.openMarkerPopupId = val;
				
				// Bind the Vue rendered HTML and open
				marker.bindPopup(this.$refs.favPopupWrapper).openPopup();
			}
		},
		favoriteCategories: {
			handler: 'renderMarkers',
			deep: true
		}
	},

	created() {
		// Non-reactive Leaflet instances
		this.map = null;
		this.clusterGroup = null;
		this.markerMap = new Map();
		this.mapClickPopupLocked = false;
	},

	mounted() {
		this.map = L.map(this.$refs.mapContainer, {
			center: this.mapOptions.center,
			zoom: this.mapOptions.zoom,
			minZoom: this.mapOptions.minZoom,
			maxZoom: this.mapOptions.maxZoom,
			zoomControl: false,
		});

		L.control.zoom({ position: 'bottomright' }).addTo(this.map);
		L.control.scale({
			position: 'bottomleft',
			imperial: this.mapOptions.scaleControlShouldUseImperial,
			metric: !this.mapOptions.scaleControlShouldUseImperial
		}).addTo(this.map);

		L.tileLayer(this.activeLayer.url, {
			attribution: this.activeLayer.attribution,
			...this.activeLayer.options
		}).addTo(this.map);

		this.clusterGroup = L.markerClusterGroup({
			showCoverageOnHover: false,
			zoomToBoundsOnClick: true,
			spiderfyOnMaxZoom: false,
			disableClusteringAtZoom: CLUSTER_MAX_ZOOM_LEVEL,
		});
		this.map.addLayer(this.clusterGroup);

		this.map.on('click', this.handleMapClick);
		this.map.on('popupclose', this.handlePopupCloseEvent);

		this.renderMarkers();
	},

	beforeUnmount() {
		if (this.map) {
			this.map.remove();
		}
	},

	methods: {
		...mapActions({
			selectFavorite: `${PUBLIC_FAVORITES_NAMESPACE}/selectFavorite`,
		}),

		renderMarkers() {
			this.clusterGroup.clearLayers();
			this.markerMap.clear();

			const markers = [];

			Object.keys(this.favoriteCategories).forEach(categoryKey => {
				const favorites = this.favoriteCategories[categoryKey];
				
				// Apply custom icon creation function for this category
				this.clusterGroup.options.iconCreateFunction = this.getClusterIconCreateFunction(categoryKey);

				favorites.forEach(favorite => {
					const marker = L.marker([favorite.lat, favorite.lng], {
						icon: this.createNewDivIcon(categoryKey)
					});

					marker.data = favorite;
					
					// Setup popup binding on click
					marker.on('click', () => {
						this.popupFavorite = favorite;
						this.openMarkerPopupId = favorite.id;
						marker.bindPopup(this.$refs.favPopupWrapper).openPopup();
					});

					this.markerMap.set(favorite.id, marker);
					markers.push(marker);
				});
			});

			this.clusterGroup.addLayers(markers);

			// Automatically fit bounds to markers if we have any
			if (markers.length > 0) {
				this.map.fitBounds(this.clusterGroup.getBounds(), { padding: [30, 30] });
			}
		},

		emitAddFavoriteEvent(data) { this.$emit('add-favorite', data) },
		emitUpdateFavoriteEvent(data) { this.$emit('update-favorite', data) },
		emitDeleteFavoriteEvent(data) { 
			this.$emit('delete-favorite', data);
			this.map.closePopup();
		},

		openPopup(lat, lng) {
			this.placePopup.visible = true;
			this.placePopup.latLng = { lat, lng };
			
			L.popup()
				.setLatLng([lat, lng])
				.setContent(this.$refs.clickPopupWrapper)
				.openOn(this.map);
		},

		closePopup() {
			this.resetPopupState();
			this.map.closePopup();
		},

		resetPopupState() {
			this.placePopup.visible = false;
			this.placePopup.latLng = { lat: 0, lng: 0 };
		},

		handleMapClick(e) {
			if (!this.placePopup.visible && !this.mapClickPopupLocked) {
				this.openPopup(e.latlng.lat, e.latlng.lng);
			}
		},

		handlePopupCloseEvent() {
			this.mapClickPopupLocked = true;
			this.resetPopupState();
			this.openMarkerPopupId = null;
			this.selectFavorite(null);

			this.$nextTick(() => {
				this.mapClickPopupLocked = false;
			});
		},

		createNewDivIcon(categoryKey) {
			return new L.DivIcon({
				iconAnchor: [MARKER_TOUCH_TARGET_SIZE * 0.5, MARKER_TOUCH_TARGET_SIZE * 0.5],
				iconSize: [MARKER_TOUCH_TARGET_SIZE, MARKER_TOUCH_TARGET_SIZE],
				className: 'leaflet-marker-favorite',
				html: `<div class="favorite-marker ${categoryKey}" style="background-color: ${this.getMarkerBackgroundColor(categoryKey)};"></div>`,
			})
		},

		getMarkerBackgroundColor(categoryKey) {
			return getThemingColorFromCategoryKey(categoryKey)
		},

		getClusterIconCreateFunction(categoryKey) {
			return cluster => {
				const label = cluster.getChildCount()
				return new L.DivIcon({
					iconAnchor: [MARKER_TOUCH_TARGET_SIZE * 0.5, MARKER_TOUCH_TARGET_SIZE * 0.5],
					iconSize: [MARKER_TOUCH_TARGET_SIZE, MARKER_TOUCH_TARGET_SIZE],
					className: 'leaflet-marker-favorite-cluster cluster-marker',
					html: `<div class="favorite-cluster-marker ${categoryKey}" style="background-color: ${this.getMarkerBackgroundColor(categoryKey)};"></div><span class="label">${label}</span>`,
				})
			}
		},
	},
}
</script>

<style lang="scss">
@import '~leaflet/dist/leaflet.css';
@import '~leaflet.markercluster/dist/MarkerCluster.css';
@import '~leaflet.markercluster/dist/MarkerCluster.Default.css';

.leaflet-tooltip { white-space: normal !important; }
.leaflet-container { background: var(--color-main-background); }
.leaflet-marker-favorite, .leaflet-marker-favorite-cluster {
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 50%;
}
.leaflet-marker-favorite .favorite-marker,
.leaflet-marker-favorite-cluster .favorite-cluster-marker {
	cursor: pointer;
	background: var(--maps-icon-favorite-star) no-repeat 50% 50%;
	border-radius: 50%;
	box-shadow: 0 0 4px #888;
}
.leaflet-marker-favorite .favorite-marker {
	height: 18px;
	width: 18px;
	background-size: 12px 12px;
}
.leaflet-marker-favorite-cluster .favorite-cluster-marker {
	height: 26px;
	width: 26px;
	background-size: 16px 16px;
}
.leaflet-marker-favorite-cluster .label {
	position: absolute;
	top: 0;
	right: 0;
	color: #fff;
	background-color: #333;
	border-radius: 9px;
	height: 18px;
	min-width: 18px;
	line-height: 12px;
	text-align: center;
	padding: 3px;
}
.leaflet-popup .leaflet-popup-content-wrapper { border-radius: 4px; }
.leaflet-popup .leaflet-popup-close-button { top: 9px; right: 9px; }
</style>