<template>
	<Vue2LeafletMarkerCluster :options="clusterOptions"
		@clusterclick="onClusterClick">
		<FavoriteMarker v-for="f in displayedFavorites"
			:key="f.id + f.name + f.category"
			:favorite="f"
			:categories="categories"
			:color="categories[f.category].color"
			:icon="getFavoriteMarkerIcon(f)"
			@edit="$emit('edit', $event)"
			@delete="$emit('delete', $event)" />
	</Vue2LeafletMarkerCluster>
</template>

<script>
// import { generateUrl } from '@nextcloud/router'
// import { getCurrentUser } from '@nextcloud/auth'

import L from 'leaflet'
import Vue2LeafletMarkerCluster from 'vue2-leaflet-markercluster'

import FavoriteMarker from './FavoriteMarker'
import optionsController from '../../optionsController'

const CLUSTER_MARKER_VIEW_SIZE = 27

export default {
	name: 'FavoritesLayer',
	components: {
		Vue2LeafletMarkerCluster,
		FavoriteMarker,
	},

	props: {
		favorites: {
			type: Object,
			required: true,
		},
		categories: {
			type: Object,
			required: true,
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
				},
			},
		}
	},

	computed: {
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
					iconAnchor: [9, 9],
					className: 'leaflet-marker-favorite',
					html: '<div class="favoriteMarker ' + catid + 'CategoryMarker" style="background-color: #' + color + '"></div>',
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
		getClusterMarkerIcon(cluster) {
			const favorite = cluster.getAllChildMarkers()[0].options.data
			const catid = favorite.category
			const color = this.categories[catid].color
			const label = cluster.getChildCount()
			return new L.DivIcon(L.extend({
				className: 'leaflet-marker-favorite-cluster cluster-marker',
				html: '<div class="favoriteClusterMarker ' + catid + 'CategoryMarker" style="background-color: #' + color + '"></div>'
					+ '​<span class="label">' + label + '</span>',
			}, cluster, {
				iconSize: [CLUSTER_MARKER_VIEW_SIZE, CLUSTER_MARKER_VIEW_SIZE],
				iconAnchor: [CLUSTER_MARKER_VIEW_SIZE / 2, CLUSTER_MARKER_VIEW_SIZE],
			}))
		},
		getFavoriteMarkerIcon(favorite) {
			return this.categoryIcons[favorite.category]
		},
	},
}
</script>

<style lang="scss" scoped>
// nothing
</style>
