<template>
	<Vue2LeafletMarkerCluster :options="clusterOptions"
		@clusterclick="onClusterClick">
		<LMarker v-for="(p, i) in displayedPhotos"
			:key="i"
			:options="{ data: p }"
			:icon="getPhotoMarkerIcon(p)"
			:lat-lng="[p.lat, p.lng]"
			@click="onPhotoClick($event, p)"
			@contextmenu="onPhotoRightClick($event, p)">
			<LTooltip
				class="tooltip-photo-wrapper"
				:options="tooltipOptions">
				<img class="photo-tooltip"
					:src="getPreviewUrl(p)">
				<p class="tooltip-photo-date">
					{{ getPhotoFormattedDate(p) }}
				</p>
				<p class="tooltip-photo-name">
					{{ basename(p.path) }}
				</p>
			</LTooltip>
			<LPopup
				class="popup-photo-wrapper"
				:options="popupOptions">
				<ActionButton icon="icon-toggle" @click="viewPhoto(p)">
					{{ t('maps', 'View') }}
				</ActionButton>
				<ActionButton icon="icon-link" @click="viewPhoto(p)">
					{{ t('maps', 'Move') }}
				</ActionButton>
				<ActionButton icon="icon-history" @click="viewPhoto(p)">
					{{ t('maps', 'Remove geo data') }}
				</ActionButton>
			</LPopup>
		</LMarker>
	</Vue2LeafletMarkerCluster>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import { basename } from '@nextcloud/paths'

import L from 'leaflet'
import { LMarker, LTooltip, LPopup } from 'vue2-leaflet'
import Vue2LeafletMarkerCluster from 'vue2-leaflet-markercluster'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'

import optionsController from '../../optionsController'

const PHOTO_MARKER_VIEW_SIZE = 40

export default {
	name: 'PhotosLayer',
	components: {
		Vue2LeafletMarkerCluster,
		LMarker,
		LTooltip,
		LPopup,
		ActionButton,
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
				className: 'leaflet-marker-photo-tooltip',
				direction: 'right',
				offset: L.point(0, -30),
			},
			popupOptions: {
				closeOnClick: true,
				className: 'popovermenu open popupMarker photoPopup',
				offset: L.point(-5, -20),
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
		basename(path) {
			return basename(path)
		},
		onClusterClick(a) {
			if (a.layer.getChildCount() > 10 && a.layer._map.getZoom() !== a.layer._map.getMaxZoom()) {
				a.layer.zoomToBounds()
			} else {
				if (OCA.Viewer && OCA.Viewer.open) {
					const photoList = a.layer.getAllChildMarkers().map((m) => {
						return m.options.data
					})
					photoList.sort((a, b) => {
						return a.dateTaken - b.dateTaken
					})
					OCA.Viewer.open({ path: photoList[0].path, list: photoList })
				} else {
					a.layer.spiderfy()
				}
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
		getPhotoFormattedDate(photo) {
			return moment(photo.dateTaken).format('LLL')
		},
		onPhotoClick(e, photo) {
			this.$nextTick(() => {
				e.target.closePopup()
			})
			this.viewPhoto(photo)
		},
		viewPhoto(photo) {
			if (OCA.Viewer && OCA.Viewer.open) {
				OCA.Viewer.open({ path: photo.path, list: [photo] })
			}
		},
		onPhotoRightClick(e, photo) {
			this.$nextTick(() => {
				e.target.openPopup()
			})
		},
	},
}
</script>

<style lang="scss" scoped>
// nothing
</style>
