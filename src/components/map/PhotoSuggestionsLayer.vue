<template>
	<div style="display: none;"></div>
</template>

<script>
import L from 'leaflet'
import 'leaflet.markercluster'
import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import { basename } from '@nextcloud/paths'
import optionsController from '../../optionsController.js'
import { binSearch, getToken } from '../../utils/common.js'

const PHOTO_MARKER_VIEW_SIZE = 40

export default {
	name: 'PhotoSuggestionsLayer',
	props: {
		map: { type: Object, required: true },
		photoSuggestions: { type: Array, required: true },
		photoSuggestionsTracksAndDevices: { type: Object, required: true },
		photoSuggestionsSelectedIndices: { type: Array, required: true },
		dateFilterEnabled: { type: Boolean, required: true },
		dateFilterStart: { type: Number, required: true },
		dateFilterEnd: { type: Number, required: true },
		draggable: { type: Boolean, required: true },
	},

	data() {
		return {
			spiderfied: false,
			clustersLoading: false,
		}
	},

	created() {
		this.clusterGroup = null;
		this.suggestionMarkers = [];
	},

	watch: {
		photoSuggestions: 'updateSuggestionMarkers',
		photoSuggestionsTracksAndDevices: { handler: 'updateSuggestionMarkers', deep: true },
		draggable: 'updateSuggestionMarkersDraggable',
		dateFilterEnabled: 'updateSuggestionMarkers',
		dateFilterStart: 'updateSuggestionMarkers',
		dateFilterEnd: 'updateSuggestionMarkers',
		photoSuggestionsSelectedIndices: 'updateClusterStyles'
	},

	mounted() {
		this.clusterGroup = L.markerClusterGroup({
			iconCreateFunction: this.getClusterMarkerIcon.bind(this),
			spiderfyOnMaxZoom: false,
			singleMarkerMode: true,
			showCoverageOnHover: false,
			zoomToBoundsOnClick: false,
			maxClusterRadius: PHOTO_MARKER_VIEW_SIZE + 10,
			chunkedLoading: true,
			chunkDelay: 50,
			chunkInterval: 250,
			chunkProgress: this.updateClusterLoadingProgress.bind(this),
		});

		this.clusterGroup.on('clusterclick', this.onClusterClick.bind(this));
		this.clusterGroup.on('clustercontextmenu', this.onClusterRightClick.bind(this));
		this.clusterGroup.on('spiderfied', this.onSpiderfied.bind(this));

		this.map.addLayer(this.clusterGroup);
		this.updateSuggestionMarkers();
	},

	beforeUnmount() {
		if (this.clusterGroup && this.map) {
			this.map.removeLayer(this.clusterGroup);
		}
	},

	methods: {
		basename(path) { return basename(path); },

		updateSuggestionMarkers() {
			this.clusterGroup.clearLayers();
			this.suggestionMarkers = [];

			const lastNullIndex = this.dateFilterEnabled ? binSearch(this.photoSuggestions, (p) => !p.dateTaken) : -1;
			const firstShownIndex = this.dateFilterEnabled ? binSearch(this.photoSuggestions, (p) => (p.dateTaken || 0) < this.dateFilterStart) + 1 : 0;
			const lastShownIndex = this.dateFilterEnabled ? binSearch(this.photoSuggestions, (p) => (p.dateTaken || 0) < this.dateFilterEnd) : this.photoSuggestions.length - 1;

			this.photoSuggestions.forEach((p, i) => {
				if (!p) return;

				// Date Filtering
				if (this.dateFilterEnabled) {
					if (i > lastNullIndex && i < firstShownIndex) return;
					if (i > lastShownIndex) return;
				}

				// Track/Device Enablement Filtering
				const parentTod = this.photoSuggestionsTracksAndDevices[p.trackOrDeviceId];
				if (!parentTod || !parentTod.enabled) return;

				const marker = L.marker([p.lat, p.lng], {
					draggable: this.draggable,
					icon: this.getPhotoMarkerIcon(p, i)
				});

				marker.data = p;
				marker.i = i;

				// Tooltip
				marker.bindTooltip(`
					<div class="tooltip-photo-suggestion-wrapper ${this.photoSuggestionsSelectedIndices.includes(i) ? 'photo-suggestion-marker-selected' : ''}">
						<img class="photo-suggestion-tooltip" src="${this.getPreviewUrl(p)}" />
						<p class="tooltip-photo-suggestion-date">${this.getPhotoFormattedDate(p)}</p>
						<p class="tooltip-photo-suggestion-name">${this.basename(p.path)}</p>
					</div>
				`, { className: 'leaflet-marker-photo-suggestion-tooltip', direction: 'right', offset: L.point(0, -30), opacity: this.draggable ? 0 : 1 });

				// Events
				marker.on('click', (e) => this.onPhotoClick(e, p, i));
				marker.on('contextmenu', (e) => this.onPhotoRightClick(e, p, i));
				marker.on('moveend', (e) => this.$emit('photo-suggestion-moved', i, e.target.getLatLng()));

				this.suggestionMarkers.push(marker);
			});

			this.clusterGroup.addLayers(this.suggestionMarkers);
		},

		updateSuggestionMarkersDraggable() {
			this.suggestionMarkers.forEach((m) => {
				if (m.dragging) this.draggable ? m.dragging.enable() : m.dragging.disable();
			});
		},

		updateClusterStyles() {
			this.updateSuggestionMarkers(); // Easiest way to force refresh of icons with selection state
		},

		onPhotoClick(e, photo, index) {
			this.$emit('photo-suggestion-selected', index);
		},

		onPhotoRightClick(e, photo, index) {
			const popupContent = L.DomUtil.create('div', 'popup-photo-wrapper');
			popupContent.innerHTML = `<button class="action-btn" data-action="display">${t('maps', 'Display picture')}</button>`;

			popupContent.addEventListener('click', (event) => {
				const action = event.target.getAttribute('data-action');
				if (action === 'display') this.viewPhoto(photo);
			});

			L.popup({ closeOnClick: false, offset: L.point(-5, -20), className: 'popovermenu open popupMarker photoPopup' })
				.setLatLng([photo.lat, photo.lng])
				.setContent(popupContent)
				.openOn(this.map);
		},

		onClusterClick(a) {
			if (a.layer.getChildCount() > 10 && this.map.getZoom() !== this.map.getMaxZoom()) {
				a.layer.zoomToBounds();
			} else {
				a.layer.spiderfy();
			}
		},

		onClusterRightClick(a) {
			const popupContent = L.DomUtil.create('div', 'popup-photo-suggestion-wrapper');
			popupContent.innerHTML = `
				<button class="action-btn" data-action="selectall">${t('maps', 'Select All')}</button>
				<button class="action-btn" data-action="display">${t('maps', 'Display pictures')}</button>
				<button class="action-btn" data-action="spiderfy">${t('maps', 'Spiderfy')}</button>
				<button class="action-btn" data-action="zoom">${t('maps', 'Zoom on bounds')}</button>
			`;

			popupContent.addEventListener('click', (event) => {
				const action = event.target.getAttribute('data-action');
				if (action === 'selectall') a.layer.getAllChildMarkers().forEach(m => this.$emit('photo-suggestion-selected', m.i));
				if (action === 'display') this.displayCluster(a.layer);
				if (action === 'spiderfy') { a.layer.spiderfy(); this.map.closePopup(); }
				if (action === 'zoom') { if (this.map.getZoom() !== this.map.getMaxZoom()) a.layer.zoomToBounds(); this.map.closePopup(); }
			});

			L.popup({ closeOnClick: false, offset: L.point(-5, 20), className: 'popovermenu open popupMarker photoPopup' })
				.setLatLng(a.latlng)
				.setContent(popupContent)
				.openOn(this.map);
		},

		onSpiderfied(e) {
			if (this.draggable) e.markers.forEach((m) => m.dragging.enable());
		},

		displayCluster(cluster) {
			const photoList = cluster.getAllChildMarkers().map(m => m.data);
			photoList.sort((a, b) => a.dateTaken - b.dateTaken);
			this.$emit('open-sidebar', photoList[0].path);
			if (window.OCA && window.OCA.Viewer && window.OCA.Viewer.open) {
				window.OCA.Viewer.open({ path: photoList[0].path, list: photoList });
			}
			this.map.closePopup();
		},

		getClusterMarkerIcon(cluster) {
			const count = cluster.getChildCount();
			const markers = cluster.getAllChildMarkers();
			const selectedCount = markers.filter(m => this.photoSuggestionsSelectedIndices.includes(m.i)).length;
			const photo = markers[0].data;
			
			if (count === 1) return this.getPhotoMarkerIcon(photo, markers[0].i);

			const iconUrl = this.getPreviewUrl(photo);
			return new L.DivIcon({
				className: 'leaflet-marker-photo-suggestion cluster-suggestion-marker',
				html: `<div class="thumbnail" style="background-image: url('${iconUrl}');"></div>​<span class="label">${selectedCount > 0 ? `<div style="color: var(--color-warning); display: inline;">${selectedCount}</div>/` : ''}${count}</span>`,
				iconSize: [PHOTO_MARKER_VIEW_SIZE, PHOTO_MARKER_VIEW_SIZE],
				iconAnchor: [PHOTO_MARKER_VIEW_SIZE / 2, PHOTO_MARKER_VIEW_SIZE],
			});
		},

		getPhotoMarkerIcon(photo, index) {
			const iconUrl = this.getPreviewUrl(photo);
			const selectedClass = this.photoSuggestionsSelectedIndices.includes(index) ? '-selected' : '';
			
			return L.divIcon({
				className: `leaflet-marker-photo-suggestion photo-suggestion-marker${selectedClass}`,
				html: `<div class="thumbnail" style="background-image: url('${iconUrl}');"></div>​`,
				iconSize: [PHOTO_MARKER_VIEW_SIZE, PHOTO_MARKER_VIEW_SIZE],
				iconAnchor: [PHOTO_MARKER_VIEW_SIZE / 2, PHOTO_MARKER_VIEW_SIZE],
			});
		},

		getPreviewUrl(photo) {
			if (photo && photo.hasPreview) {
				const token = getToken();
				return token ? generateUrl('apps/files_sharing/publicpreview/') + token + '?file=' + encodeURIComponent(photo.path) + '&x=341&y=256&a=1'
					: generateUrl('core') + '/preview?fileId=' + photo.fileId + '&x=341&y=256&a=1';
			}
			return generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2';
		},

		getPhotoFormattedDate(photo) {
			if (photo) {
				const d = new Date(photo.dateTaken * 1000);
				const mom = moment.unix(photo.dateTaken + d.getTimezoneOffset() * 60);
				return mom.format('LL') + ' ' + mom.format('HH:mm:ss');
			}
			return '';
		},

		viewPhoto(photo) {
			if (window.OCA && window.OCA.Viewer && window.OCA.Viewer.open) {
				window.OCA.Viewer.open({ path: photo.path, list: [photo] });
				this.map.closePopup();
			}
		},

		updateClusterLoadingProgress(processed, total, elapsed) {
			if (elapsed > 100 && !this.clustersLoading) this.clustersLoading = true;
			this.$emit('cluster-loading', processed, total);
			if (processed === total) {
				this.clustersLoading = false;
				this.$emit('cluster-loaded');
			}
		},
	},
}
</script>

<style lang="scss">
.tooltip-photo-suggestion-wrapper {
	display: flex;
	flex-direction: column;
	align-items: center;
}
.popup-photo-wrapper .action-btn {
	display: block;
	width: 100%;
	padding: 8px;
	border: none;
	background: transparent;
	text-align: left;
	cursor: pointer;
}
.popup-photo-wrapper .action-btn:hover {
	background: var(--color-background-hover);
}
</style>