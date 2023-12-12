<template>
	<Vue2LeafletMarkerCluster
		ref="markerCluster"
		:options="clusterOptions"
		@clusterclick="onClusterClick"
		@clustercontextmenu="onClusterRightClick"
		@spiderfied="onSpiderfied">
		<LMarker
			:lat-lng="[0, 0]"
			:visible="false">
			<LTooltip
				ref="markerTooltip"
				:class="{ 'tooltip-photo-wrapper': true }"
				:options="{ ...tooltipOptions, opacity: draggable ? 0 : 1 }">
				<img class="photo-tooltip"
					:src="getPreviewUrl(currentPhoto)">
				<p class="tooltip-photo-date">
					{{ getPhotoFormattedDate(currentPhoto) }}
				</p>
				<p class="tooltip-photo-name">
					{{ currentPhoto ? basename(currentPhoto.path) : '' }}
				</p>
			</LTooltip>
			<LPopup
				ref="markerPopup"
				class="popup-photo-wrapper"
				:options="popupOptions">
				<NcActionButton v-if="currentPhoto && currentPhoto.path" icon="icon-toggle" @click="$emit('open-sidebar',currentPhoto.path)">
					{{ t('maps', 'Open in Sidebar') }}
				</NcActionButton>
				<NcActionButton icon="icon-toggle" @click="viewPhoto(currentPhoto)">
					{{ t('maps', 'Display picture') }}
				</NcActionButton>
				<NcActionButton v-if="currentPhoto && currentPhoto.isUpdateable" icon="icon-history" @click="resetPhotosCoords([currentPhoto])">
					{{ t('maps', 'Remove geo data') }}
				</NcActionButton>
				<NcActionButton v-if="!isPublic()"
					icon="icon-share"
					@click="$emit('add-to-map-photo', currentPhoto)">
					{{ t('maps', 'Copy to map') }}
				</NcActionButton>
			</LPopup>
		</LMarker>
		<LMarker
			:lat-lng="[0, 0]"
			:visible="false">
			<LPopup
				ref="clusterPopup"
				class="popup-photo-wrapper"
				:options="clusterPopupOptions">
				<NcActionButton icon="icon-toggle" @click="onDisplayClusterClick">
					{{ t('maps', 'Display pictures') }}
				</NcActionButton>
				<NcActionButton icon="icon-toggle-pictures" @click="onSpiderfyClusterClick">
					{{ t('maps', 'Spiderfy') }}
				</NcActionButton>
				<NcActionButton icon="icon-search" @click="onZoomClusterClick">
					{{ t('maps', 'Zoom on bounds') }}
				</NcActionButton>
				<NcActionButton v-if="readOnly" icon="icon-history" @click="resetClusterPhotoCoords">
					{{ t('maps', 'Remove geo data') }}
				</NcActionButton>
			</LPopup>
		</LMarker>
	</Vue2LeafletMarkerCluster>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import { basename } from '@nextcloud/paths'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'

import L from 'leaflet'
import { LMarker, LTooltip, LPopup } from 'vue2-leaflet'
import Vue2LeafletMarkerCluster from 'vue2-leaflet-markercluster'

import optionsController from '../../optionsController.js'
import { binSearch, getToken, isPublic } from '../../utils/common.js'

const PHOTO_MARKER_VIEW_SIZE = 40

export default {
	name: 'PhotosLayer',
	components: {
		Vue2LeafletMarkerCluster,
		LMarker,
		LTooltip,
		LPopup,
		NcActionButton,
	},

	props: {
		map: {
			type: Object,
			required: true,
		},
		photos: {
			type: Array,
			required: true,
		},
		dateFilterEnabled: {
			type: Boolean,
			required: true,
		},
		dateFilterStart: {
			type: Number,
			required: true,
		},
		dateFilterEnd: {
			type: Number,
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
				singleMarkerMode: true,
				showCoverageOnHover: false,
				zoomToBoundsOnClick: false,
				maxClusterRadius: PHOTO_MARKER_VIEW_SIZE + 10,
				icon: {
					iconSize: [PHOTO_MARKER_VIEW_SIZE, PHOTO_MARKER_VIEW_SIZE],
				},
				chunkedLoading: true,
				chunkDelay: 50,
				chunkInterval: 250,
				chunkProgress: this.updateClusterLoadingProgress,
			},
			tooltipOptions: {
				className: 'leaflet-marker-photo-tooltip',
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
			currentPhoto: null,
			photoMarkers: [],
			clustersLoading: false,
		}
	},

	computed: {
		readOnly() {
			return !this.photos.some((f) => (f.isUpdateable))
				&& !(this.photos.length === 0 && optionsController.optionValues?.isCreatable)
		},
		photosLastNullIndex() {
			return this.dateFilterEnabled ? binSearch(this.photos, (p) => !p.dateTaken) : -1
		},
		photosFirstShownIndex() {
			return this.dateFilterEnabled ? binSearch(this.photos, (p) => (p.dateTaken || 0) < this.dateFilterStart) + 1 : 0
		},
		photosLastShownIndex() {
			return this.dateFilterEnabled ? binSearch(this.photos, (p) => (p.dateTaken || 0) < this.dateFilterEnd) : this.photos.length - 1
		},
	},

	watch: {
		photos() {
			this.updatePhotoMarkers()
		},
		draggable() {
			this.updatePhotoMarkersDraggable()
		},
		dateFilterEnabled(newValue) {
			if (newValue) {
				this.$refs.markerCluster.mapObject.removeLayers(
					this.photoMarkers.slice(
						this.photosLastNullIndex + 1,
						this.photosFirstShownIndex,
					),
				)
				this.$refs.markerCluster.mapObject.removeLayers(
					this.photoMarkers.slice(
						this.photosLastShownIndex + 1,
					),
				)
			} else {
				this.$refs.markerCluster.mapObject.addLayers(
					this.photoMarkers.slice(
						this.photosLastNullIndex + 1,
						this.photosFirstShownIndex,
					),
				)
				this.$refs.markerCluster.mapObject.addLayers(
					this.photoMarkers.slice(
						this.photosLastShownIndex + 1,
					),
				)
			}
		},
		photosFirstShownIndex(newIndex, oldIndex) {
			if (newIndex < oldIndex) {
				this.$refs.markerCluster.mapObject.addLayers(
					this.photoMarkers.slice(
						newIndex,
						oldIndex,
					),
				)
			} else if (newIndex > oldIndex) {
				this.$refs.markerCluster.mapObject.removeLayers(
					this.photoMarkers.slice(
						oldIndex,
						newIndex,
					),
				)
			}
		},
		photosLastShownIndex(newIndex, oldIndex) {
			if (newIndex < oldIndex) {
				this.$refs.markerCluster.mapObject.removeLayers(
					this.photoMarkers.slice(
						newIndex + 1,
						oldIndex + 1,
					),
				)
			} else if (newIndex > oldIndex) {
				this.$refs.markerCluster.mapObject.addLayers(
					this.photoMarkers.slice(
						oldIndex + 1,
						newIndex + 1,
					),
				)
			}
		},
	},

	beforeMount() {
	},

	mounted() {
		this.updatePhotoMarkers()
	},

	methods: {
		basename(path) {
			return basename(path)
		},
		onClusterClick(a) {
			if (a.layer.getChildCount() > 10 && this.map.getZoom() !== this.map.getMaxZoom()) {
				a.layer.zoomToBounds()
			} else {
				if (OCA.Viewer && OCA.Viewer.open) {
					this.displayCluster(a.layer)
				} else {
					this.$emit('open-sidebar', a.layer.getAllChildMarkers()[0].data.path)
					a.layer.spiderfy()
				}
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
				return m.data
			})
			photoList.sort((a, b) => {
				return a.dateTaken - b.dateTaken
			})
			// this.$emit('open-sidebar', photoList[0].path)
			OCA.Viewer.open({ path: photoList[0].path, list: photoList })
			this.map.closePopup()
		},
		getClusterMarkerIcon(cluster) {
			const count = cluster.getChildCount()
			const photo = cluster.getAllChildMarkers()[0].data
			if (count === 1) {
				return this.getPhotoMarkerIcon(photo)
			}
			const iconUrl = this.getPreviewUrl(photo)
			return new L.DivIcon(L.extend({
				className: 'leaflet-marker-photo cluster-marker',
				html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');"></div>​<span class="label">' + count + '</span>',
			}, cluster, {
				iconSize: [PHOTO_MARKER_VIEW_SIZE, PHOTO_MARKER_VIEW_SIZE],
				iconAnchor: [PHOTO_MARKER_VIEW_SIZE / 2, PHOTO_MARKER_VIEW_SIZE],
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
			if (photo && photo.hasPreview) {
				const token = getToken()
				return token
					? generateUrl('apps/files_sharing/publicpreview/') + token + '?file=' + encodeURIComponent(photo.path) + '&x=341&y=256&a=1'
					: generateUrl('core') + '/preview?fileId=' + photo.fileId + '&x=341&y=256&a=1'
			} else {
				return generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2'
			}
		},
		getPhotoFormattedDate(photo) {
			if (photo) {
				const d = new Date(photo.dateTaken * 1000)
				const mom = moment.unix(photo.dateTaken + d.getTimezoneOffset() * 60)
				return mom.format('LL') + ' ' + mom.format('HH:mm:ss')
			}
			return ''
		},
		onPhotoClick(e) {
			const photo = e.target.data
			// we want popup to open on right click only
			this.$nextTick(() => {
				e.target.closePopup()
			})
			// this.$emit('open-sidebar', photo.path)
			this.viewPhoto(photo)
		},
		viewPhoto(photo) {
			if (OCA.Viewer && OCA.Viewer.open) {
				OCA.Viewer.open({ path: photo.path, list: [photo] })
				this.map.closePopup()
			}
		},
		onPhotoRightClick(e) {
			const photo = e.target.data
			this.currentPhoto = photo
			const popup = this.$refs.markerPopup.mapObject
			popup.setLatLng([photo.lat, photo.lng])
			this.$nextTick(() => {
				popup.openOn(this.map)
			})
		},
		onPhotoMouseOver(e) {
			const photo = e.target.data
			this.currentPhoto = photo
			const tooltip = this.$refs.markerTooltip.mapObject
			tooltip.setLatLng([photo.lat, photo.lng])
			this.$nextTick(() => {
				tooltip.openOn(this.map)
			})
		},
		onPhotoMouseOut(e) {
			const tooltip = this.$refs.markerTooltip.mapObject
			tooltip.close()
		},
		onPhotoMoved(e) {
			const photo = e.target.data
			this.$emit('photo-moved', photo, e.target.getLatLng())
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
							return m.data
						})
						this.resetPhotosCoords(photos)
					}
				},
				true,
			)
		},
		resetPhotosCoords(photos) {
			const paths = photos.map((p) => { return p.path })
			this.$emit('coords-reset', paths)
			this.map.closePopup()
		},
		updateClusterLoadingProgress(processed, total, elapsed, layersArray) {
			if (elapsed > 100 && !this.clustersLoading) {
				this.clustersLoading = true
			}
			this.$emit('cluster-loading', processed, total)

			if (processed === total) {
				this.clustersLoading = false
				// all markers processed - hide the progress bar:
				this.$emit('cluster-loaded')
			}
		},

		async updatePhotoMarkers() {
			this.$refs.markerCluster.mapObject.removeLayers(this.photoMarkers)
			this.photoMarkers = this.photos.map((p, i) => {
				const m = new L.Marker([p.lat, p.lng],
					{
						draggable: this.draggable,
					},
				)
				m.on(
					'click', this.onPhotoClick,
				)
				m.on(
					'contextmenu', this.onPhotoRightClick,
				)
				m.on(
					'mouseover', this.onPhotoMouseOver,
				)
				m.on(
					'mouseout', this.onPhotoMouseOut,
				)
				m.on(
					'moveend', this.onPhotoMoved,
				)
				m.data = p
				return m
			})
			this.$refs.markerCluster.mapObject.addLayers(this.photoMarkers)
			if (this.dateFilterEnabled) {
				this.$refs.markerCluster.mapObject.removeLayers(
					this.photoMarkers.slice(
						this.photosLastNullIndex + 1,
						this.photosFirstShownIndex,
					),
				)
				this.$refs.markerCluster.mapObject.removeLayers(
					this.photoMarkers.slice(
						this.photosLastShownIndex + 1,
					),
				)
			}
		},
		async updatePhotoMarkersDraggable() {
			this.photoMarkers.forEach((m) => {
				if (m.dragging) {
					this.draggable ? m.dragging.enable() : m.dragging.disable()
				}
				m.options.draggable = this.draggable
				m.update()
			})
		},
		isPublic() {
			return isPublic()
		},
	},
}
</script>

<style lang="scss" scoped>
// nothing
.tooltip-photo-wrapper {
	display: flex;
	flex-direction: column;
	align-items: center;
}
</style>
