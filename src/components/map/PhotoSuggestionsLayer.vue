<template>
	<Vue2LeafletMarkerCluster :options="clusterOptions"
		@clusterclick="onClusterClick"
		@clustercontextmenu="onClusterRightClick"
		@spiderfied="onSpiderfied">
		<LMarker v-for="(p, i) in photoSuggestions"
			:key="i"
			v-if="p"
			:options="{ data: p }"
			:icon="getPhotoMarkerIcon(p, i)"
			:draggable="draggable"
			:lat-lng="[p.lat, p.lng]"
			@click="onPhotoClick($event, i)"
			@contextmenu="onPhotoRightClick($event, p)"
			@moveend="onPhotoMoved($event, i)">
			<LTooltip v-if="p"
				:class="{
					'tooltip-photo-suggestion-wrapper': true,
					'photo-suggestion-marker-selected': photoSuggestionsSelectedIndices.includes(i)
				}"
				:options="{ ...tooltipOptions, opacity: draggable ? 0 : 1 }">
				<img class="photo-suggestion-tooltip"
					:src="getPreviewUrl(p)">
				<p class="tooltip-photo-suggestion-date">
					{{ getPhotoFormattedDate(p) }}
				</p>
				<p class="tooltip-photo-suggestion-name">
					{{ basename(p.path) }}
				</p>
			</LTooltip>
			<LPopup v-if="p"
				class="popup-photo-suggestion-wrapper"
				:options="popupOptions">
				<ActionButton icon="icon-toggle" @click="viewPhoto(p)">
					{{ t('maps', 'Display picture') }}
				</ActionButton>
			</LPopup>
		</LMarker>
		<LMarker
			:lat-lng="[0, 0]"
			:visible="false">
			<LPopup
				ref="clusterPopup"
				class="popup-photo-suggestion-wrapper"
				:options="clusterPopupOptions">
				<ActionButton icon="icon-toggle" @click="onDisplayClusterClick">
					{{ t('maps', 'Display pictures') }}
				</ActionButton>
				<ActionButton icon="icon-toggle-suggestion-pictures" @click="onSpiderfyClusterClick">
					{{ t('maps', 'Spiderfy') }}
				</ActionButton>
				<ActionButton icon="icon-search" @click="onZoomClusterClick">
					{{ t('maps', 'Zoom on bounds') }}
				</ActionButton>
			</LPopup>
		</LMarker>
	</Vue2LeafletMarkerCluster>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import { basename } from '@nextcloud/paths'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'

import L from 'leaflet'
import { LMarker, LTooltip, LPopup } from 'vue2-leaflet'
import Vue2LeafletMarkerCluster from 'vue2-leaflet-markercluster'

import optionsController from '../../optionsController'

const PHOTO_MARKER_VIEW_SIZE = 40

export default {
	name: 'PhotoSuggestionsLayer',
	components: {
		Vue2LeafletMarkerCluster,
		LMarker,
		LTooltip,
		LPopup,
		ActionButton,
	},

	props: {
		map: {
			type: Object,
			required: true,
		},
		photoSuggestions: {
			type: Array,
			required: true,
		},
		photoSuggestionsSelectedIndices: {
			type: Array,
			required: true,
		},
		draggable: {
			type: Boolean,
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
				className: 'leaflet-marker-photo-suggestion-tooltip',
				direction: 'right',
				offset: L.point(0, -30),
			},
			popupOptions: {
				closeOnClick: false,
				className: 'popovermenu open popupMarker photoPopup',
				offset: L.point(-5, -20),
			},
			clusterPopupOptions: {
				closeOnClick: false,
				className: 'popovermenu open popupMarker photoPopup',
				offset: L.point(-5, 20),
			},
			contextCluster: null,
			spiderfied: false,
		}
	},

	computed: {
	},

	beforeMount() {
	},

	methods: {
		basename(path) {
			return basename(path)
		},
		onClusterClick(a) {
			if (a.layer.getChildCount() > 10 && this.map.getZoom() !== this.map.getMaxZoom()) {
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
		onZoomClusterClick() {
			const cluster = this.contextCluster
			if (this.map.getZoom() !== this.map.getMaxZoom()) {
				cluster.zoomToBounds()
			}
			this.map.closePopup()
		},
		onSpiderfyClusterClick() {
			this.contextCluster.spiderfy()
			this.map.closePopup()
		},
		onDisplayClusterClick() {
			this.displayCluster(this.contextCluster)
		},
		displayCluster(cluster) {
			const photoList = cluster.getAllChildMarkers().map((m) => {
				return m.options.data
			})
			photoList.sort((a, b) => {
				return a.dateTaken - b.dateTaken
			})
			this.$emit('open-sidebar', photoList[0].path)
			OCA.Viewer.open({ path: photoList[0].path, list: photoList })
			this.map.closePopup()
		},
		getClusterMarkerIcon(cluster) {
			const photo = cluster.getAllChildMarkers()[0].options.data
			const iconUrl = this.getPreviewUrl(photo)
			const label = cluster.getChildCount()
			return new L.DivIcon(L.extend({
				className: 'leaflet-marker-photo-suggestion cluster-suggestion-marker',
				html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');"></div>​<span class="label">' + label + '</span>',
			}, cluster, {
				iconSize: [PHOTO_MARKER_VIEW_SIZE, PHOTO_MARKER_VIEW_SIZE],
				iconAnchor: [PHOTO_MARKER_VIEW_SIZE / 2, PHOTO_MARKER_VIEW_SIZE],
			}))
		},
		getPhotoMarkerIcon(photo, i) {
			const iconUrl = this.getPreviewUrl(photo)
			const selectedClass = this.photoSuggestionsSelectedIndices.includes(i)
				? '-selected'
				: ''
			return L.divIcon(L.extend({
				className: 'leaflet-marker-photo-suggestion photo-suggestion-marker' + selectedClass,
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
			return moment.unix(photo.dateTaken).format('LLL')
		},
		onPhotoClick(e, index) {
			// we want popup to open on right click only
			this.$nextTick(() => {
				e.target.closePopup()
			})
			this.$emit('photo-suggestion-selected', index)
		},
		viewPhoto(photo) {
			if (OCA.Viewer && OCA.Viewer.open) {
				OCA.Viewer.open({ path: photo.path, list: [photo] })
				this.map.closePopup()
			}
		},
		onPhotoRightClick(e, photo) {
			this.$nextTick(() => {
				e.target.openPopup()
			})
		},
		resetClusterPhotoCoords() {
			const clusterSize = this.contextCluster.getChildCount()
			OC.dialogs.confirmDestructive(
				'',
				t('maps', 'Are you sure you want to remove geo data of {nb} photos?', { nb: clusterSize }),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: t('maps', 'Yes'),
					confirmClasses: '',
					cancel: t('maps', 'Cancel'),
				},
				(result) => {
					if (result) {
						const photos = this.contextCluster.getAllChildMarkers().map((m) => {
							return m.options.data
						})
						this.resetPhotosCoords(photos)
					}
				},
				true
			)
		},
		onPhotoMoved(e, index) {
			this.$emit('photo-suggestion-moved', index, e.target.getLatLng())
		},
	},
}
</script>

<style lang="scss" scoped>
// nothing
.tooltip-photo-suggestion-wrapper {
	display: flex;
	flex-direction: column;
	align-items: center;
}

</style>
