<template>
	<Vue2LeafletMarkerCluster :options="clusterOptions"
		@clusterclick="onClusterClick"
		@clustercontextmenu="onClusterRightClick"
		@spiderfied="onSpiderfied">
		<FavoriteMarker v-for="f in displayedFavorites"
			:key="f.id + f.name + f.category"
			:favorite="f"
			:categories="categories"
			:draggable="draggable"
			:color="categories[f.category].color"
			:icon="getFavoriteMarkerIcon(f)"
			@click="$emit('click', $event)"
			@add-to-map-favorite="$emit('add-to-map-favorite', $event)"
			@edit="$emit('edit', $event)"
			@delete="$emit('delete', $event)" />
		<LMarker
			:lat-lng="[0, 0]"
			:visible="false">
			<LPopup
				ref="clusterPopup"
				class="popup-favorite-wrapper"
				:options="clusterPopupOptions">
				<NcActionButton v-if="!readOnly" icon="icon-delete" @click="onDeleteClusterClick">
					{{ t('maps', 'Delete favorites') }}
				</NcActionButton>
				<NcActionButton icon="icon-search" @click="onZoomClusterClick">
					{{ t('maps', 'Zoom on bounds') }}
				</NcActionButton>
			</LPopup>
		</LMarker>
	</Vue2LeafletMarkerCluster>
</template>

<script>
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'

import L from 'leaflet'
import { LMarker, LPopup } from 'vue2-leaflet'
import Vue2LeafletMarkerCluster from 'vue2-leaflet-markercluster'

import FavoriteMarker from './FavoriteMarker.vue'
import optionsController from '../../optionsController.js'

const CLUSTER_MARKER_VIEW_SIZE = 36

export default {
	name: 'FavoritesLayer',
	components: {
		LMarker,
		LPopup,
		Vue2LeafletMarkerCluster,
		NcActionButton,
		FavoriteMarker,
	},

	props: {
		map: {
			type: Object,
			required: true,
		},
		favorites: {
			type: Object,
			required: true,
		},
		categories: {
			type: Object,
			required: true,
		},
		draggable: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			optionValues: optionsController.optionValues,
			clusterOptions: {
				iconCreateFunction: this.getClusterMarkerIcon,
				spiderfyOnMaxZoom: false,
				showCoverageOnHover: false,
				zoomToBoundsOnClick: false,
				maxClusterRadius: CLUSTER_MARKER_VIEW_SIZE + 10,
				icon: {
					iconSize: [CLUSTER_MARKER_VIEW_SIZE, CLUSTER_MARKER_VIEW_SIZE],
					iconAnchor: [CLUSTER_MARKER_VIEW_SIZE / 2, CLUSTER_MARKER_VIEW_SIZE],
				},
			},
			clusterPopupOptions: {
				closeOnClick: false,
				className: 'popovermenu open popupMarker favoritePopup',
				offset: L.point(-5, 20),
			},
			contextCluster: null,
			spiderfied: false,
		}
	},

	computed: {
		readOnly() {
			const farray = Object.values(this.favorites)
			return !farray.some((f) => (f.isUpdateable))
				&& !(farray.length === 0 && optionsController.optionValues?.isCreatable)
		},
		displayedFavorites() {
			const favIds = Object.keys(this.favorites).filter((fid) => {
				const catid = this.favorites[fid].category
				return this.categories[catid].enabled
			})
			return favIds.map((fid) => {
				return this.favorites[fid]
			})
		},
		categoryIcons() {
			const icons = {}
			Object.keys(this.categories).forEach((catid) => {
				const color = this.categories[catid].color
				icons[catid] = L.divIcon({
					iconAnchor: [18, 18],
					className: 'leaflet-marker-favorite',
					// html: '<div class="favoriteMarker ' + darkClass + '" style="background-color: #' + color + '"></div>',
					html: '<div class="favoriteMarker icon-star-white" style="background-color: #' + color + ';"></div>',
				})
			})
			return icons
		},
		categoryIconsSelected() {
			const icons = {}
			Object.keys(this.categories).forEach((catid) => {
				const color = this.categories[catid].color
				icons[catid] = L.divIcon({
					iconAnchor: [18, 18],
					className: 'leaflet-marker-favorite',
					// html: '<div class="favoriteMarker ' + darkClass + '" style="background-color: #' + color + '"></div>',
					html: '<div class="favoriteMarker selected icon-star-white" style="background-color: #' + color + ';"></div>',
				})
			})
			return icons
		},
	},

	beforeMount() {
	},

	methods: {
		onClusterClick(a) {
			if (a.layer.getChildCount() > 10 && a.layer._map.getZoom() !== a.layer._map.getMaxZoom()) {
				a.layer.zoomToBounds()
			} else {
				a.layer.spiderfy()
			}
		},
		onClusterRightClick(a) {
			this.contextCluster = a.layer
			const popup = this.$refs.clusterPopup.mapObject
			popup.setLatLng(a.latlng)
			this.$nextTick(() => {
				popup.openOn(this.map)
			})
		},
		onDeleteClusterClick() {
			const favorites = this.contextCluster.getAllChildMarkers().map((m) => {
				return m.options.data
			})
			const favIds = favorites.map((f) => {
				return f.id
			})
			this.$emit('delete-multiple', favIds)
			this.map.closePopup()
		},
		onZoomClusterClick() {
			const cluster = this.contextCluster
			if (this.map.getZoom() !== this.map.getMaxZoom()) {
				cluster.zoomToBounds()
			}
			this.map.closePopup()
		},
		getClusterMarkerIcon(cluster) {
			const favorite = cluster.getAllChildMarkers()[0].options.data
			const catid = favorite.category
			const color = this.categories[catid].color
			const label = cluster.getChildCount()
			return new L.DivIcon(L.extend({
				className: 'leaflet-marker-favorite-cluster cluster-marker',
				// html: '<div class="favoriteClusterMarker ' + darkClass + '" style="background-color: #' + color + '"></div>'
				html: '<div class="favoriteClusterMarker icon-star-white" style="background-color: #' + color + ';"></div>'
					+ '​<span class="label">' + label + '</span>',
			}, cluster, {
				iconSize: [CLUSTER_MARKER_VIEW_SIZE, CLUSTER_MARKER_VIEW_SIZE],
				iconAnchor: [CLUSTER_MARKER_VIEW_SIZE / 2, CLUSTER_MARKER_VIEW_SIZE],
			}))
		},
		getFavoriteMarkerIcon(favorite) {
			return favorite.selected
				? this.categoryIconsSelected[favorite.category]
				: this.categoryIcons[favorite.category]
		},
		onSpiderfied(e) {
			// markers that were in a cluster when draggable changed are not draggable
			// so we set them when cluster is spiderfied
			if (this.draggable) {
				e.markers.forEach((m) => {
					m.dragging.enable()
				})
			}
			this.spiderfied = true
		},
	},
}
</script>

<style lang="scss" scoped>

</style>
