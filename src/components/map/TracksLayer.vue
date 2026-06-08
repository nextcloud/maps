<template>
	<div style="display: none;"></div>
</template>

<script>
import L from 'leaflet'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'
import optionsController from '../../optionsController.js'
import { binSearch, isPublic, getToken } from '../../utils/common.js'

const TRACK_MARKER_VIEW_SIZE = 40
const WAYPOINT_MARKER_VIEW_SIZE = 30

export default {
	name: 'TracksLayer',

	props: {
		tracks: { type: Array, required: true },
		map: { type: Object, required: true },
		start: { type: Number, default: 0 },
		end: { type: Number, default: () => moment().unix() },
	},

	created() {
		this.featureGroup = L.featureGroup();
	},

	computed: {
		displayedTracks() {
			return this.tracks.filter(track => track.enabled);
		},
	},

	watch: {
		displayedTracks: { handler: 'renderTracks', deep: true },
		start: 'renderTracks',
		end: 'renderTracks',
	},

	mounted() {
		this.featureGroup.addTo(this.map);
		this.renderTracks();
	},

	beforeUnmount() {
		if (this.featureGroup && this.map) {
			this.map.removeLayer(this.featureGroup);
		}
	},

	methods: {
		isPublic() { return isPublic() },

		renderTracks() {
			this.featureGroup.clearLayers();

			this.displayedTracks.forEach(track => {
				const trackGroup = L.featureGroup();
				const color = track.color || '#0082c9';

				// Calculate Lines
				const linesData = this.calculateLines(track);
				linesData.forEach(line => {
					const lineBg = L.polyline(line.points, { color: 'black', opacity: 1, weight: 4 * 1.6 });
					const lineFg = L.polyline(line.points, { color: color, opacity: 1, weight: 4 });
					
					const lineGroup = L.featureGroup([lineBg, lineFg]);
					lineGroup.on('mouseover', (e) => this.onTrackLineMouseover(e, line, track, color));
					lineGroup.on('contextmenu', (e) => this.showTrackPopup(e, track));
					lineGroup.on('click', () => this.$emit('click', track));

					trackGroup.addLayer(lineGroup);
				});

				// Calculate First Point & Waypoints
				const firstPoint = this.calculateFirstPoint(track);
				if (firstPoint) {
					const marker = this.createTrackMarker(firstPoint, track, color, true);
					trackGroup.addLayer(marker);
				}

				const waypoints = this.calculateWaypoints(track, firstPoint);
				waypoints.forEach(pt => {
					const marker = this.createTrackMarker(pt, track, color, false);
					trackGroup.addLayer(marker);
				});

				this.featureGroup.addLayer(trackGroup);
			});
		},

		calculateLines(track) {
			const trkSegments = [];
			if ((track.metadata?.begin > 0 && track.metadata.begin >= this.end) || (track.metadata?.end > 0 && track.metadata.end <= this.start)) {
				return [...(track.data?.routes || []), trkSegments];
			} else if ((!track.metadata?.begin || track.metadata.begin >= this.start) && (!track.metadata?.end || track.metadata.end <= this.end)) {
				(track.data?.tracks || []).forEach((trk) => {
					trk.segments.forEach((segment) => {
						trkSegments.push({ ...segment, name: trk.name });
					});
				});
			} else {
				(track.data?.tracks || []).forEach((trk) => {
					trk.segments.forEach((segment) => {
						const lastNullIndex = binSearch(segment.points, (p) => !p.timestamp);
						const firstShownIndex = binSearch(segment.points, (p) => (p.timestamp || -1) < this.start) + 1;
						const lastShownIndex = binSearch(segment.points, (p) => (p.timestamp || -1) < this.end);
						const points = [
							...segment.points.slice(0, lastNullIndex + 1),
							...segment.points.slice(firstShownIndex, lastShownIndex + 1),
						];
						trkSegments.push({ ...segment, name: trk.name, points });
					});
				});
			}
			return [...(track.data?.routes || []), ...trkSegments];
		},

		calculateFirstPoint(track) {
			let firstPoint = null;
			if (track.data?.tracks?.[0]?.segments?.[0]?.points?.length > 0) {
				firstPoint = track.data.tracks[0].segments[0].points[0];
			}
			if (track.data?.routes?.[0]?.points?.length > 0 && (firstPoint === null || (!firstPoint.timestamp && track.data.routes[0].points[0].timestamp) || (track.data.routes[0].points[0].timestamp && firstPoint.timestamp && track.data.routes[0].points[0].timestamp < firstPoint.timestamp))) {
				firstPoint = track.data.routes[0].points[0];
			}
			if (track.data?.waypoints?.length > 0 && (firstPoint === null || (!firstPoint.timestamp && track.data.waypoints[0].timestamp) || (track.data.waypoints[0].timestamp && firstPoint.timestamp && track.data.waypoints[0].timestamp < firstPoint.timestamp))) {
				firstPoint = track.data.waypoints[0];
			}
			return firstPoint;
		},

		calculateWaypoints(track, firstPoint) {
			if(!track.data?.waypoints) return [];
			let points = firstPoint === track.data.waypoints[0] ? track.data.waypoints.slice(1) : track.data.waypoints;

			if (track.metadata?.begin >= this.end || (track.metadata?.end >= 0 && track.metadata?.end <= this.start)) {
				return [];
			} else if (track.metadata?.begin >= this.start && track.metadata?.end <= this.end) {
				return points;
			} else {
				const lastNullIndex = binSearch(points, (p) => !p.timestamp);
				const firstShownIndex = binSearch(points, (p) => (p.timestamp || -1) < this.start) + 1;
				const lastShownIndex = binSearch(points, (p) => (p.timestamp || -1) < this.end);
				return [
					...points.slice(0, lastNullIndex + 1),
					...points.slice(firstShownIndex, lastShownIndex + 1),
				];
			}
		},

		createTrackMarker(point, track, color, isFirstPoint) {
			const size = isFirstPoint ? TRACK_MARKER_VIEW_SIZE : WAYPOINT_MARKER_VIEW_SIZE;
			const markerClass = isFirstPoint ? `track-marker ${track.selected ? 'selected' : ''}` : 'track-waypoint';

			const marker = L.marker([point.lat, point.lng], {
				icon: L.divIcon({
					html: `<div class="thumbnail-wrapper" style="--custom-color: ${color}; border-color: ${color};"><div class="thumbnail" style="background-color: ${color};"></div></div>​`,
					className: `leaflet-marker-track ${markerClass}`,
					iconSize: [size, size],
					iconAnchor: [size / 2, size],
				})
			});

			const dateBegin = track.metadata?.begin ? moment.unix(track.metadata.begin).format('LLL') : '';
			let tooltipHtml = `<div class="tooltip-track-wrapper" style="border: 2px solid ${color}"><b>${t('maps', 'File')}:</b> <span>${track.file_name}</span>`;
			if (isFirstPoint && dateBegin) tooltipHtml += `<br><b>${t('maps', 'Begins at')}:</b> <span>${dateBegin}</span>`;
			if (point.name) tooltipHtml += `<br><b>${t('maps', 'Name')}:</b> <span>${point.name}</span>`;
			if (point.desc) tooltipHtml += `<br><b>${t('maps', 'Description')}:</b> <span>${point.desc}</span>`;
			if (point.ele) tooltipHtml += `<br><b>${t('maps', 'Altitude')}:</b> <span>${point.ele} m</span>`;
			tooltipHtml += `</div>`;

			marker.bindTooltip(tooltipHtml, { sticky: true, className: 'leaflet-marker-track-tooltip', direction: 'top', offset: L.point(0, 0) });
			marker.on('contextmenu', (e) => this.showTrackPopup(e, track));
			marker.on('click', () => this.$emit('click', track));

			return marker;
		},

		showTrackPopup(e, track) {
			const downloadUrl = (window.OCA?.Files?.App?.fileList?.filesClient?.getBaseUrl) 
				? window.OCA.Files.App.fileList.filesClient.getBaseUrl() + track.file_path 
				: generateUrl('s/' + getToken() + '/download' + '?path=/&files=' + track.file_name);

			const popupContent = L.DomUtil.create('div', 'popup-track-wrapper');
			popupContent.innerHTML = `
				${track.isUpdateable ? `<button class="action-btn" data-action="color">${t('maps', 'Change color')}</button>` : ''}
				<button class="action-btn" data-action="elevation">${t('maps', 'Display elevation')}</button>
				${!this.isPublic() && track.isShareable ? `<button class="action-btn" data-action="copy">${t('maps', 'Copy to map')}</button>` : ''}
				${(!this.isPublic() || (this.isPublic() && !track.hideDownload)) ? `<a class="action-btn" href="${downloadUrl}" target="_self">${t('maps', 'Download track')}</a>` : ''}
			`;

			popupContent.addEventListener('click', (event) => {
				const action = event.target.getAttribute('data-action');
				if (action === 'color') this.$emit('change-color', track);
				if (action === 'elevation') this.$emit('display-elevation', track);
				if (action === 'copy') this.$emit('add-to-map-track', track);
				if (action !== 'download') this.map.closePopup(); // Let anchor handle native download
			});

			L.popup({ closeButton: false, className: 'popovermenu open popupMarker trackPopup', offset: L.point(-5, -20) })
				.setLatLng(e.latlng)
				.setContent(popupContent)
				.openOn(this.map);
		},

		onTrackLineMouseover(e, line, track, color) {
			const overLatLng = e.layer._map.layerPointToLatLng(e.layerPoint);
			let minDist = 40000000, tmpDist, closestI = -1;
			for (let i = 0; i < line.points.length; i++) {
				tmpDist = e.layer._map.distance(overLatLng, line.points[i]);
				if (tmpDist < minDist) { minDist = tmpDist; closestI = i; }
			}
			if (closestI !== -1) {
				this.$emit('point-hover', {
					...line.points[closestI],
					color,
					file_name: track.file_name,
					track_name: line.name,
				});
			}
		}
	},
}
</script>

<style lang="scss">
.tooltip-track-wrapper {
	padding: 6px;
	border-radius: 3px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
}
.popup-track-wrapper .action-btn {
	display: block;
	width: 100%;
	padding: 8px;
	border: none;
	background: transparent;
	text-decoration: none;
	color: var(--color-main-text);
	text-align: left;
	cursor: pointer;
}
.popup-track-wrapper .action-btn:hover {
	background: var(--color-background-hover);
}
</style>