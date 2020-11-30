<template>
	<Vue2LeafletMarkerCluster :options="clusterOptions"
		@clusterclick="onClusterClick">
		<LMarker v-for="f in displayedFavorites"
			:key="f.id"
			:options="{ data: f }"
			:icon="getFavoriteMarkerIcon(f)"
			:lat-lng="[f.lat, f.lng]">
			<!--LTooltip
				class="tooltip-contact-wrapper"
				:options="tooltipOptions">
				<img class="tooltip-contact-avatar"
					:src="getContactAvatar(c)"
					alt="">
				<div class="tooltip-contact-content">
					<h3 class="tooltip-contact-name">
						{{ c.FN }}
					</h3>
					<p v-if=" c.ADRTYPE.toLowerCase() === 'home'"
						class="tooltip-contact-address-type">
						{{ t('maps', 'Home') }}
					</p>
					<p v-else-if=" c.ADRTYPE.toLowerCase() === 'work'"
						class="tooltip-contact-address-type">
						{{ t('maps', 'Work') }}
					</p>
					<p v-for="l in getFormattedAddressLines(c)"
						:key="l"
						class="tooltip-contact-address">
						{{ l }}
					</p>
				</div>
			</LTooltip>
			<LPopup
				class="popup-contact-wrapper"
				:options="popupOptions">
				<div class="left-contact-popup">
					<img class="tooltip-contact-avatar"
						:src="getContactAvatar(c)"
						alt="">
					<button
						v-tooltip="{ content: t('maps', 'Delete this address') }"
						class="icon icon-delete"
						@click="onDeleteAddressClick(c)" />
				</div>
				<div class="tooltip-contact-content">
					<h3 class="tooltip-contact-name">
						{{ c.FN }}
					</h3>
					<p v-if=" c.ADRTYPE.toLowerCase() === 'home'"
						class="tooltip-contact-address-type">
						{{ t('maps', 'Home') }}
					</p>
					<p v-else-if=" c.ADRTYPE.toLowerCase() === 'work'"
						class="tooltip-contact-address-type">
						{{ t('maps', 'Work') }}
					</p>
					<p v-for="l in getFormattedAddressLines(c)"
						:key="l"
						class="tooltip-contact-address">
						{{ l }}
					</p>
					<a target="_blank"
						:href="getContactUrl(c)">
						{{ t('maps', 'Open in Contacts') }}
					</a>
				</div>
			</LPopup-->
		</LMarker>
	</Vue2LeafletMarkerCluster>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'

import L from 'leaflet'
import { LMarker, LTooltip, LPopup } from 'vue2-leaflet'
import Vue2LeafletMarkerCluster from 'vue2-leaflet-markercluster'

import optionsController from '../../optionsController'

// import { deleteContactAddress } from '../../network'

const CLUSTER_MARKER_VIEW_SIZE = 27

export default {
	name: 'FavoritesLayer',
	components: {
		Vue2LeafletMarkerCluster,
		LMarker,
		LTooltip,
		LPopup,
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
			tooltipOptions: {
				className: 'leaflet-marker-favorite-tooltip',
				direction: 'top',
				offset: L.point(0, 0),
			},
			popupOptions: {
				closeOnClick: true,
				className: 'popovermenu open popupMarker favoritePopup',
				offset: L.point(-5, 10),
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
