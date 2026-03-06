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
import { binSearch, getToken, isPublic } from '../../utils/common.js'

const PHOTO_MARKER_VIEW_SIZE = 40

export default {
	name: 'PhotosLayer',
	props: {
		map: { type: Object, required: true },
		photos: { type: Array, required: true },
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
		// Non-reactive storage for leaflet objects
		this.clusterGroup = null;
		this.photoMarkers = [];
	},
	watch: {
		photos() {
			this.renderMarkers();
		},
		draggable() {
			this.updatePhotoMarkersDraggable();
		},
		dateFilterEnabled() {
			this.renderMarkers();
		},
		dateFilterStart() {
			this.renderMarkers();
		},
		dateFilterEnd() {
			this.renderMarkers();
		}
	},
	mounted() {
		this.clusterGroup = L.markerClusterGroup({
			iconCreateFunction: this.getClusterMarkerIcon,
			spiderfyOnMaxZoom: false,
			singleMarkerMode: true,
			showCoverageOnHover: false,
			zoomToBoundsOnClick: false,
			maxClusterRadius: PHOTO_MARKER_VIEW_SIZE + 10,
			chunkedLoading: true,
			chunkDelay: 50,
			chunkInterval: 250,
			chunkProgress: this.updateClusterLoadingProgress,
		});

		this.clusterGroup.on('clusterclick', this.onClusterClick.bind(this));
		this.clusterGroup.on('clustercontextmenu', this.onClusterRightClick.bind(this));
		this.clusterGroup.on('spiderfied', this.onSpiderfied.bind(this));

		this.map.addLayer(this.clusterGroup);
		this.renderMarkers();
	},
	beforeDestroy() {
		if (this.clusterGroup && this.map) {
			this.map.removeLayer(this.clusterGroup);
		}
	},
	methods: {
		basename(path) { return basename(path) },
		isPublic() { return isPublic() },
		
		getFilteredPhotos() {
			if (!this.dateFilterEnabled) return this.photos;
			
			const lastNullIndex = binSearch(this.photos, (p) => !p.dateTaken);
			const firstShownIndex = binSearch(this.photos, (p) => (p.dateTaken || 0) < this.dateFilterStart) + 1;
			const lastShownIndex = binSearch(this.photos, (p) => (p.dateTaken || 0) < this.dateFilterEnd);
			
			return [
				...this.photos.slice(0, lastNullIndex + 1),
				...this.photos.slice(firstShownIndex, lastShownIndex + 1),
			];
		},
		
		renderMarkers() {
			this.clusterGroup.clearLayers();
			const filteredPhotos = this.getFilteredPhotos();
			
			this.photoMarkers = filteredPhotos.map((photo) => {
				const marker = L.marker([photo.lat, photo.lng], {
					draggable: this.draggable,
					icon: this.getPhotoMarkerIcon(photo)
				});
				
				marker.data = photo;
				
				// Bind tooltips
				marker.bindTooltip(`
					<div class="tooltip-photo-wrapper">
						<img class="photo-tooltip" src="${this.getPreviewUrl(photo)}" />
						<p class="tooltip-photo-date">${this.getPhotoFormattedDate(photo)}</p>
						<p class="tooltip-photo-name">${this.basename(photo.path)}</p>
					</div>
				`, { className: 'leaflet-marker-photo-tooltip', direction: 'right', offset: L.point(0, -30), opacity: this.draggable ? 0 : 1 });

				// Event Listeners
				marker.on('click', (e) => this.onPhotoClick(e, photo));
				marker.on('contextmenu', (e) => this.onPhotoRightClick(e, photo));
				marker.on('moveend', (e) => this.onPhotoMoved(e, photo));
				
				return marker;
			});
			
			this.clusterGroup.addLayers(this.photoMarkers);
		},
		
		updatePhotoMarkersDraggable() {
			this.photoMarkers.forEach((m) => {
				if (m.dragging) {
					this.draggable ? m.dragging.enable() : m.dragging.disable();
				}
				m.setTooltipContent(m.getTooltip().getContent()); // refresh opacity logic if needed
			});
		},

		// Actions
		onPhotoClick(e, photo) {
			this.viewPhoto(photo);
		},
		onPhotoRightClick(e, photo) {
			const popupContent = L.DomUtil.create('div', 'popup-photo-wrapper');
			popupContent.innerHTML = `
				<button class="action-btn" data-action="sidebar">${t('maps', 'Open in Sidebar')}</button>
				<button class="action-btn" data-action="display">${t('maps', 'Display picture')}</button>
				${photo.isUpdateable ? `<button class="action-btn" data-action="reset">${t('maps', 'Remove geo data')}</button>` : ''}
				${!this.isPublic() ? `<button class="action-btn" data-action="copy">${t('maps', 'Copy to map')}</button>` : ''}
			`;

			popupContent.addEventListener('click', (event) => {
				const action = event.target.getAttribute('data-action');
				if (action === 'sidebar') this.$emit('open-sidebar', photo.path);
				if (action === 'display') this.viewPhoto(photo);
				if (action === 'reset') { this.$emit('coords-reset', [photo.path]); this.map.closePopup(); }
				if (action === 'copy') { this.$emit('add-to-map-photo', photo); this.map.closePopup(); }
			});

			L.popup({ closeOnClick: false, offset: L.point(-5, -20), className: 'popovermenu open popupMarker photoPopup' })
				.setLatLng([photo.lat, photo.lng])
				.setContent(popupContent)
				.openOn(this.map);
		},
		onPhotoMoved(e, photo) {
			this.$emit('photo-moved', photo, e.target.getLatLng());
		},

		onClusterClick(a) {
			if (a.layer.getChildCount() > 10 && this.map.getZoom() !== this.map.getMaxZoom()) {
				a.layer.zoomToBounds();
			} else {
				if (window.OCA && window.OCA.Viewer && window.OCA.Viewer.open) {
					this.displayCluster(a.layer);
				} else {
					this.$emit('open-sidebar', a.layer.getAllChildMarkers()[0].data.path);
					a.layer.spiderfy();
				}
			}
		},
		onClusterRightClick(a) {
			const clusterSize = a.layer.getChildCount();
			const popupContent = L.DomUtil.create('div', 'popup-photo-wrapper');
			popupContent.innerHTML = `
				<button class="action-btn" data-action="display">${t('maps', 'Display pictures')}</button>
				<button class="action-btn" data-action="spiderfy">${t('maps', 'Spiderfy')}</button>
				<button class="action-btn" data-action="zoom">${t('maps', 'Zoom on bounds')}</button>
				${!this.readOnly ? `<button class="action-btn" data-action="reset">${t('maps', 'Remove geo data')}</button>` : ''}
			`;

			popupContent.addEventListener('click', (event) => {
				const action = event.target.getAttribute('data-action');
				if (action === 'display') this.displayCluster(a.layer);
				if (action === 'spiderfy') { a.layer.spiderfy(); this.map.closePopup(); }
				if (action === 'zoom') { if (this.map.getZoom() !== this.map.getMaxZoom()) a.layer.zoomToBounds(); this.map.closePopup(); }
				if (action === 'reset') {
					OC.dialogs.confirmDestructive('', t('maps', 'Are you sure you want to remove geo data of {nb} photos?', { nb: clusterSize }), {
						type: OC.dialogs.YES_NO_BUTTONS, confirm: t('maps', 'Yes'), cancel: t('maps', 'Cancel')
					}, (result) => {
						if (result) {
							const photos = a.layer.getAllChildMarkers().map(m => m.data);
							this.$emit('coords-reset', photos.map(p => p.path));
							this.map.closePopup();
						}
					}, true);
				}
			});

			L.popup({ closeOnClick: false, offset: L.point(-5, 20), className: 'popovermenu open popupMarker photoPopup' })
				.setLatLng(a.latlng)
				.setContent(popupContent)
				.openOn(this.map);
		},
		onSpiderfied(e) {
			if (this.draggable) {
				e.markers.forEach((m) => m.dragging.enable());
			}
			this.spiderfied = true;
		},
		displayCluster(cluster) {
			const photoList = cluster.getAllChildMarkers().map(m => m.data);
			photoList.sort((a, b) => a.dateTaken - b.dateTaken);
			if (window.OCA && window.OCA.Viewer && window.OCA.Viewer.open) {
				window.OCA.Viewer.open({ path: photoList[0].path, list: photoList });
				this.map.closePopup();
			}
		},
		
		getClusterMarkerIcon(cluster) {
			const count = cluster.getChildCount();
			const photo = cluster.getAllChildMarkers()[0].data;
			if (count === 1) return this.getPhotoMarkerIcon(photo);
			
			const iconUrl = this.getPreviewUrl(photo);
			return new L.DivIcon(L.extend({
				className: 'leaflet-marker-photo cluster-marker',
				html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');"></div>​<span class="label">' + count + '</span>',
			}, cluster, { iconSize: [PHOTO_MARKER_VIEW_SIZE, PHOTO_MARKER_VIEW_SIZE], iconAnchor: [PHOTO_MARKER_VIEW_SIZE / 2, PHOTO_MARKER_VIEW_SIZE] }));
		},
		getPhotoMarkerIcon(photo) {
			const iconUrl = this.getPreviewUrl(photo);
			return L.divIcon(L.extend({
				className: 'leaflet-marker-photo photo-marker',
				html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');"></div>​',
			}, photo, { iconSize: [PHOTO_MARKER_VIEW_SIZE, PHOTO_MARKER_VIEW_SIZE], iconAnchor: [PHOTO_MARKER_VIEW_SIZE / 2, PHOTO_MARKER_VIEW_SIZE] }));
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
				if(this.map) this.map.closePopup();
			}
		},
		updateClusterLoadingProgress(processed, total, elapsed) {
			if (elapsed > 100 && !this.clustersLoading) this.clustersLoading = true;
			this.$emit('cluster-loading', processed, total);
			if (processed === total) {
				this.clustersLoading = false;
				this.$emit('cluster-loaded');
			}
		}
	}
}
</script>

<style lang="scss">
/* Note: Since we are rendering HTML templates via JS, we remove the "scoped" attribute so styles apply to the dynamic HTML */
.tooltip-photo-wrapper {
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