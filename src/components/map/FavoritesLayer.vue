<template>
	<div style="display: none;"></div>
</template>

<script>
import L from 'leaflet'
import 'leaflet.markercluster'
import optionsController from '../../optionsController.js'
import { isPublic } from '../../utils/common.js'

const CLUSTER_MARKER_VIEW_SIZE = 36

export default {
	name: 'FavoritesLayer',

	props: {
		map: { type: Object, required: true },
		favorites: { type: Object, required: true },
		categories: { type: Object, required: true },
		draggable: { type: Boolean, default: false },
	},

	data() {
		return {
			spiderfied: false,
		}
	},

	created() {
		this.clusterGroup = null;
		this.favoriteMarkers = [];
	},

	computed: {
		readOnly() {
			const farray = Object.values(this.favorites);
			return !farray.some((f) => f.isUpdateable) && !(farray.length === 0 && optionsController.optionValues?.isCreatable);
		},
		displayedFavorites() {
			const favIds = Object.keys(this.favorites).filter((fid) => {
				const catid = this.favorites[fid].category;
				return this.categories[catid]?.enabled;
			});
			return favIds.map(fid => this.favorites[fid]);
		}
	},

	watch: {
		displayedFavorites: { handler: 'renderMarkers', deep: true },
		categories: { handler: 'renderMarkers', deep: true },
		draggable: 'updateDraggableState'
	},

	mounted() {
		this.clusterGroup = L.markerClusterGroup({
			iconCreateFunction: this.getClusterMarkerIcon.bind(this),
			spiderfyOnMaxZoom: false,
			showCoverageOnHover: false,
			zoomToBoundsOnClick: false,
			maxClusterRadius: CLUSTER_MARKER_VIEW_SIZE + 10,
		});

		this.clusterGroup.on('clusterclick', this.onClusterClick.bind(this));
		this.clusterGroup.on('clustercontextmenu', this.onClusterRightClick.bind(this));
		this.clusterGroup.on('spiderfied', this.onSpiderfied.bind(this));

		this.map.addLayer(this.clusterGroup);
		this.renderMarkers();
	},

	beforeUnmount() {
		if (this.clusterGroup && this.map) {
			this.map.removeLayer(this.clusterGroup);
		}
	},

	methods: {
		isPublic() { return isPublic() },

		renderMarkers() {
			this.clusterGroup.clearLayers();
			
			this.favoriteMarkers = this.displayedFavorites.map((f) => {
				const color = this.categories[f.category]?.color || '0082c9';
				const icon = this.getFavoriteMarkerIcon(f, color);

				const marker = L.marker([f.lat, f.lng], {
					draggable: this.draggable && f.isUpdateable,
					icon: icon
				});
				
				marker.data = f;

				// Tooltip
				const tooltipHtml = `
					<div class="tooltip-favorite-wrapper" style="border: 2px solid #${color}">
						<b>${window.t('maps', 'Name')}:</b> <span>${f.name || window.t('maps', 'No name')}</span><br>
						<b>${window.t('maps', 'Category')}:</b> <span>${f.category}</span>
						${f.comment ? `<br><b>${window.t('maps', 'Comment')}:</b> <span>${f.comment}</span>` : ''}
					</div>
				`;
				marker.bindTooltip(tooltipHtml, {
					className: 'leaflet-marker-favorite-tooltip',
					direction: 'top',
					offset: L.point(0, 0),
					opacity: (this.draggable && f.isUpdateable) ? 0 : 1
				});

				// Events
				marker.on('click', () => this.$emit('click', f));
				marker.on('contextmenu', (e) => this.onMarkerRightClick(e, f));
				marker.on('moveend', (e) => {
					const pos = e.target.getLatLng();
					this.$emit('edit', { ...f, lat: pos.lat, lng: pos.lng });
				});

				return marker;
			});

			this.clusterGroup.addLayers(this.favoriteMarkers);
		},

		updateDraggableState() {
			this.favoriteMarkers.forEach(m => {
				if (m.dragging && m.data.isUpdateable) {
					this.draggable ? m.dragging.enable() : m.dragging.disable();
					m.setTooltipContent(m.getTooltip().getContent()); // Refresh tooltip opacity
				}
			});
		},

		onMarkerRightClick(e, f) {
			const popupContent = L.DomUtil.create('div', 'popup-favorite-wrapper');
			popupContent.innerHTML = `
				${f.isDeletable ? `<button class="action-btn" data-action="delete">${window.t('maps', 'Delete favorite')}</button>` : ''}
				${!this.isPublic() ? `<button class="action-btn" data-action="copy">${window.t('maps', 'Copy to map')}</button>` : ''}
			`;

			popupContent.addEventListener('click', (event) => {
				const action = event.target.getAttribute('data-action');
				if (action === 'delete') this.$emit('delete', f.id);
				if (action === 'copy') this.$emit('add-to-map-favorite', f);
				this.map.closePopup();
			});

			L.popup({ closeButton: false, className: 'popovermenu open popupMarker favoritePopup', offset: L.point(-5, 10) })
				.setLatLng(e.latlng)
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
			const popupContent = L.DomUtil.create('div', 'popup-favorite-wrapper');
			popupContent.innerHTML = `
				${!this.readOnly ? `<button class="action-btn" data-action="delete">${window.t('maps', 'Delete favorites')}</button>` : ''}
				<button class="action-btn" data-action="zoom">${window.t('maps', 'Zoom on bounds')}</button>
			`;

			popupContent.addEventListener('click', (event) => {
				const action = event.target.getAttribute('data-action');
				if (action === 'delete') {
					const favIds = a.layer.getAllChildMarkers().map(m => m.data.id);
					this.$emit('delete-multiple', favIds);
				}
				if (action === 'zoom') {
					if (this.map.getZoom() !== this.map.getMaxZoom()) a.layer.zoomToBounds();
				}
				this.map.closePopup();
			});

			L.popup({ closeOnClick: false, className: 'popovermenu open popupMarker favoritePopup', offset: L.point(-5, 20) })
				.setLatLng(a.latlng)
				.setContent(popupContent)
				.openOn(this.map);
		},

		onSpiderfied(e) {
			if (this.draggable) {
				e.markers.forEach((m) => {
					if (m.data && m.data.isUpdateable) m.dragging.enable();
				});
			}
			this.spiderfied = true;
		},

		getClusterMarkerIcon(cluster) {
			const favorite = cluster.getAllChildMarkers()[0].data;
			const color = this.categories[favorite.category]?.color || '0082c9';
			const label = cluster.getChildCount();
			
			return new L.DivIcon({
				className: 'leaflet-marker-favorite-cluster cluster-marker',
				html: `<div class="favoriteClusterMarker icon-star-white" style="background-color: #${color};"></div>​<span class="label">${label}</span>`,
				iconSize: [CLUSTER_MARKER_VIEW_SIZE, CLUSTER_MARKER_VIEW_SIZE],
				iconAnchor: [CLUSTER_MARKER_VIEW_SIZE / 2, CLUSTER_MARKER_VIEW_SIZE],
			});
		},

		getFavoriteMarkerIcon(favorite, color) {
			const selectedClass = favorite.selected ? 'selected' : '';
			return L.divIcon({
				iconAnchor: [18, 18],
				className: 'leaflet-marker-favorite',
				html: `<div class="favoriteMarker icon-star-white ${selectedClass}" style="background-color: #${color};"></div>`,
			});
		},
	},
}
</script>

<style lang="scss">
.tooltip-favorite-wrapper {
	padding: 6px;
	border-radius: 3px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
}
.popup-favorite-wrapper .action-btn {
	display: block;
	width: 100%;
	padding: 8px;
	border: none;
	background: transparent;
	text-align: left;
	cursor: pointer;
}
.popup-favorite-wrapper .action-btn:hover {
	background: var(--color-background-hover);
}
</style>