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
				:class="{
					'tooltip-photo-suggestion-wrapper': true,
					'photo-suggestion-marker-selected': photoSuggestionsSelectedIndices.includes(currentSuggestion?.i)
				}"
				:options="{ ...tooltipOptions, opacity: draggable ? 0 : 1 }">
				<img class="photo-suggestion-tooltip"
					:src="getPreviewUrl(currentSuggestion)">
				<p class="tooltip-photo-suggestion-date">
					{{ getPhotoFormattedDate(currentSuggestion) }}
				</p>
				<p class="tooltip-photo-suggestion-name">
					{{ currentSuggestion ? basename(currentSuggestion.path) : '' }}
				</p>
			</LTooltip>
			<LPopup
				ref="markerPopup"
				class="popup-photo-wrapper"
				:options="popupOptions">
				<NcActionButton icon="icon-toggle" @click="viewPhoto(currentSuggestion)">
					{{ t('maps', 'Display picture') }}
				</NcActionButton>
			</LPopup>
		</LMarker>
		<LMarker
			:lat-lng="[0, 0]"
			:visible="false">
			<LPopup
				ref="clusterPopup"
				class="popup-photo-suggestion-wrapper"
				:options="clusterPopupOptions">
				<NcActionButton icon="icon-checkmark" @click="onSelectAll">
					{{ t('maps', 'Select All') }}
				</NcActionButton>
				<NcActionButton icon="icon-toggle" @click="onDisplayClusterClick">
					{{ t('maps', 'Display pictures') }}
				</NcActionButton>
				<NcActionButton icon="icon-toggle-pictures" @click="onSpiderfyClusterClick">
					{{ t('maps', 'Spiderfy') }}
				</NcActionButton>
				<NcActionButton icon="icon-search" @click="onZoomClusterClick">
					{{ t('maps', 'Zoom on bounds') }}
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
import { binSearch, getToken } from '../../utils/common.js'

const PHOTO_MARKER_VIEW_SIZE = 40

export default {
	name: 'PhotoSuggestionsLayer',
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
		photoSuggestions: {
			type: Array,
			required: true,
		},
		photoSuggestionsTracksAndDevices: {
			type: Object,
			required: true,
		},
		photoSuggestionsSelectedIndices: {
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
			currentSuggestion: null,
			suggestionMarkers: [],
			clustersLoading: false,
		}
	},

	computed: {
		suggestionsLastNullIndex() {
			return this.dateFilterEnabled ? binSearch(this.photoSuggestions, (p) => !p.dateTaken) : -1
		},
		suggestionsFirstShownIndex() {
			return this.dateFilterEnabled ? binSearch(this.photoSuggestions, (p) => (p.dateTaken || 0) < this.dateFilterStart) + 1 : 0
		},
		suggestionsLastShownIndex() {
			return this.dateFilterEnabled ? binSearch(this.photoSuggestions, (p) => (p.dateTaken || 0) < this.dateFilterEnd) : this.photoSuggestions.length - 1
		},
	},

	watch: {
		photoSuggestions() {
			this.updateSuggestionMarkers()
		},
		photoSuggestionsTracksAndDevices: {
			handler() {
				this.updateSuggestionMarkers()
			},
			deep: true,
		},
		draggable() {
			this.updateSuggestionMarkersDraggable()
		},
		dateFilterEnabled(newValue) {
			if (newValue) {
				this.$refs.markerCluster.mapObject.removeLayers(
					this.suggestionMarkers.slice(
						this.suggestionsLastNullIndex + 1,
						this.suggestionsFirstShownIndex,
					),
				)
				this.$refs.markerCluster.mapObject.removeLayers(
					this.suggestionMarkers.slice(
						this.suggestionsLastShownIndex + 1,
					),
				)
			} else {
				this.$refs.markerCluster.mapObject.addLayers(
					this.suggestionMarkers.slice(
						this.suggestionsLastNullIndex + 1,
						this.suggestionsFirstShownIndex,
					),
				)
				this.$refs.markerCluster.mapObject.addLayers(
					this.suggestionMarkers.slice(
						this.suggestionsLastShownIndex + 1,
					),
				)
			}
		},
		suggestionsFirstShownIndex(newIndex, oldIndex) {
			if (newIndex < oldIndex) {
				this.$refs.markerCluster.mapObject.addLayers(
					this.suggestionMarkers.slice(
						newIndex,
						oldIndex,
					),
				)
			} else if (newIndex > oldIndex) {
				this.$refs.markerCluster.mapObject.removeLayers(
					this.suggestionMarkers.slice(
						oldIndex,
						newIndex,
					),
				)
			}
		},
		suggestionsLastShownIndex(newIndex, oldIndex) {
			if (newIndex < oldIndex) {
				this.$refs.markerCluster.mapObject.removeLayers(
					this.suggestionMarkers.slice(
						newIndex + 1,
						oldIndex + 1,
					),
				)
			} else if (newIndex > oldIndex) {
				this.$refs.markerCluster.mapObject.addLayers(
					this.suggestionMarkers.slice(
						oldIndex + 1,
						newIndex + 1,
					),
				)
			}
		},
		photoSuggestionsSelectedIndices(newIndices, oldIndices) {
			const oldSet = new Set(oldIndices)
			const newSet = new Set(newIndices)
			const removedIndices = oldIndices.filter((i) => { return !newSet.has(i) })
			const addedIndices = newIndices.filter((i) => { return !oldSet.has(i) })
			const changedMarkers = removedIndices.concat(addedIndices).filter((i) => { return !!this.suggestionMarkers[i] }).map((i) => { return this.suggestionMarkers[i] })
			this.$refs.markerCluster.mapObject.refreshClusters(changedMarkers)
		},
	},

	beforeMount() {
	},

	mounted() {
		this.updateSuggestionMarkers()
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
		onSelectAll() {
			this.contextCluster.getAllChildMarkers().forEach((m) => {
				this.$emit('photo-suggestion-selected', m.i)
			})
		},
		displayCluster(cluster) {
			const photoList = cluster.getAllChildMarkers().map((m) => {
				return m.data
			})
			photoList.sort((a, b) => {
				return a.dateTaken - b.dateTaken
			})
			this.$emit('open-sidebar', photoList[0].path)
			OCA.Viewer.open({ path: photoList[0].path, list: photoList })
			this.map.closePopup()
		},
		getClusterMarkerIcon(cluster) {
			const count = cluster.getChildCount()
			const markers = cluster.getAllChildMarkers()
			const selectedCount = markers.filter((m) => this.photoSuggestionsSelectedIndices.includes(m.i)).length
			const marker = markers[0]
			const photo = marker.data
			const index = marker.i
			if (count === 1) {
				return this.getPhotoMarkerIcon(photo, index)
			}
			const iconUrl = this.getPreviewUrl(photo)
			return new L.DivIcon(L.extend({
				className: 'leaflet-marker-photo-suggestion cluster-suggestion-marker',
				html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');"></div>​<span class="label">'
					+ (selectedCount > 0 ? '<div style="color: var(--color-warning); display: inline;">' + selectedCount + '</div>/' : '')
					+ count + '</span>',
			}, cluster, {
				iconSize: [PHOTO_MARKER_VIEW_SIZE, PHOTO_MARKER_VIEW_SIZE],
				iconAnchor: [PHOTO_MARKER_VIEW_SIZE / 2, PHOTO_MARKER_VIEW_SIZE],
			}))
		},
		getPhotoMarkerIcon(photo, index) {
			const iconUrl = this.getPreviewUrl(photo)
			const selectedClass = this.photoSuggestionsSelectedIndices.includes(index)
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
			const index = e.target.i
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
		onPhotoRightClick(e) {
			const photo = e.target.data
			this.currentSuggestion = photo
			const popup = this.$refs.markerPopup.mapObject
			popup.setLatLng([photo.lat, photo.lng])
			this.$nextTick(() => {
				popup.openOn(this.map)
			})
		},
		onPhotoMouseOver(e) {
			const photo = e.target.data
			this.currentSuggestion = photo
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
			this.$emit('photo-suggestion-moved', e.target.i, e.target.getLatLng())
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

		async updateSuggestionMarkers() {
			this.$refs.markerCluster.mapObject.removeLayers(this.suggestionMarkers.filter((m) => !!m))
			this.suggestionMarkers = this.photoSuggestions.map((p, i) => {
				if (p) {
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
					m.i = i
					return m
				} else {
					return null
				}
			})
			this.$refs.markerCluster.mapObject.addLayers(this.suggestionMarkers.filter((m) => { return m ? this.photoSuggestionsTracksAndDevices[m.data.trackOrDeviceId].enabled : false }))
			if (this.dateFilterEnabled) {
				this.$refs.markerCluster.mapObject.removeLayers(
					this.suggestionMarkers.slice(
						this.suggestionsLastNullIndex + 1,
						this.suggestionsFirstShownIndex,
					),
				)
				this.$refs.markerCluster.mapObject.removeLayers(
					this.suggestionMarkers.slice(
						this.suggestionsLastShownIndex + 1,
					),
				)
			}
		},
		async updateSuggestionMarkersDraggable() {
			this.suggestionMarkers.forEach((m) => {
				if (m) {
					if (m.dragging) {
						this.draggable ? m.dragging.enable() : m.dragging.disable()
					}
					m.options.draggable = this.draggable
					m.update()
				}
			})
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
