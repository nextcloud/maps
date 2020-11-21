<template>
	<Vue2LeafletMarkerCluster :options="clusterOptions"
		@clusterclick="onClusterClick">
		<LMarker v-for="(p, i) in displayedPhotos"
			:key="i"
			:options="{ data: p }"
			:icon="getPhotoMarkerIcon(p)"
			:lat-lng="[p.lat, p.lng]">
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
import { geoToLatLng } from '../../utils/mapUtils'

const PHOTO_MARKER_VIEW_SIZE = 40

export default {
	name: 'PhotosLayer',
	components: {
		Vue2LeafletMarkerCluster,
		LMarker,
		LTooltip,
		LPopup,
	},

	props: {
		photos: {
			type: Array,
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
				maxClusterRadius: PHOTO_MARKER_VIEW_SIZE + 10,
				icon: {
					iconSize: [PHOTO_MARKER_VIEW_SIZE, PHOTO_MARKER_VIEW_SIZE],
				},
			},
			tooltipOptions: {
				className: 'leaflet-marker-contact-tooltip',
				direction: 'top',
				offset: L.point(0, 0),
			},
			popupOptions: {
				closeOnClick: true,
				className: 'popovermenu open popupMarker contactPopup',
				offset: L.point(-5, 10),
			},
		}
	},

	computed: {
		displayedPhotos() {
			return this.photos
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
			const photo = cluster.getAllChildMarkers()[0].options.data
			const iconUrl = this.getPreviewUrl(photo)
			const label = cluster.getChildCount()
			return new L.DivIcon(L.extend({
				className: 'leaflet-marker-photo cluster-marker',
				html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');"></div>​<span class="label">' + label + '</span>',
			}))
		},
		getPhotoMarkerIcon(photo) {
			const iconUrl = this.getPreviewUrl(photo)
			return L.divIcon(L.extend({
				className: 'leaflet-marker-photo photo-marker',
				html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');"></div>​',
			}, photo, {
				iconSize: [PHOTO_MARKER_VIEW_SIZE, PHOTO_MARKER_VIEW_SIZE],
				iconAnchor: [PHOTO_MARKER_VIEW_SIZE / 2, PHOTO_MARKER_VIEW_SIZE],
			}))
		},
		getPreviewUrl(photo) {
			return photo.hasPreview
				? generateUrl('core') + '/preview?fileId=' + photo.fileId + '&x=341&y=256&a=1'
				: generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2'
		},
	},
}
</script>

<style lang="scss" scoped>
@import '~leaflet.markercluster/dist/MarkerCluster.css';
@import '~leaflet.markercluster/dist/MarkerCluster.Default.css';

.popup-contact-wrapper,
.tooltip-contact-wrapper {
	display: flex;
}

.left-contact-popup {
	display: flex;
	flex-direction: column;
	align-items: center;
	button {
		width: 44px;
		height: 44px !important;
		background-color: transparent;
		border: 0;
		&:hover {
			background-color: var(--color-background-hover);
		}
	}
}
</style>
