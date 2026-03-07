<template>
	<div style="display: none;">
		<LHeatMap v-if="points.length >= 2500"
			ref="devicesHeatMap"
			:map="map"
			:initial-points="points"
			:options="optionsHeatMap" />
	</div>
</template>

<script>
import L from 'leaflet'
import { isComputer } from '../../utils.js'
import { binSearch, isPublic } from '../../utils/common.js'
import moment from '@nextcloud/moment'
import LHeatMap from './LHeatMap.vue'
import optionsController from '../../optionsController.js'

const DEVICE_MARKER_VIEW_SIZE = 40

export default {
	name: 'DevicesLayer',
	components: {
		LHeatMap,
	},

	props: {
		devices: { type: Array, required: true },
		map: { type: Object, required: true },
		start: { type: Number, default: 0 },
		end: { type: Number, default: () => moment.unix() },
	},

	data() {
		return {
			optionsHeatMap: {
				radius: 15,
				blur: 10,
				gradient: { 0.4: 'blue', 0.65: 'lime', 1: 'red' },
			},
		}
	},

	created() {
		this.featureGroup = L.featureGroup();
		this.deviceLayersMap = new Map(); // Store reference to markers/lines
	},

	computed: {
		displayedDevices() {
			return this.devices.filter(d => d.enabled && d.points && d.points.length > 0);
		},
		points() {
			return this.devices.reduce((pts, device) => {
				if (device.enabled && device.historyEnabled) {
					const lastNullIndex = binSearch(device.points, (p) => !p.timestamp);
					const firstShownIndex = binSearch(device.points, (p) => (p.timestamp || 0) < this.start) + 1;
					const lastShownIndex = binSearch(device.points, (p) => (p.timestamp || 0) < this.end);
					if (lastNullIndex + 1 + lastShownIndex - firstShownIndex + 1 > 2500) {
						const filteredDevicePoints = [
							...device.points.slice(0, lastNullIndex + 1),
							...device.points.slice(firstShownIndex, lastShownIndex + 1),
						];
						pts = pts.concat(filteredDevicePoints.map((p) => [p.lat, p.lng]));
					}
				}
				return pts;
			}, []);
		},
	},

	watch: {
		displayedDevices: { handler: 'renderDevices', deep: true },
		start: 'renderDevices',
		end: 'renderDevices',
	},

	mounted() {
		this.featureGroup.addTo(this.map);
		this.renderDevices();
	},

	beforeUnmount() {
		if (this.featureGroup && this.map) {
			this.map.removeLayer(this.featureGroup);
		}
	},

	methods: {
		isPublic() { return isPublic() },
		
		getDevicePoints(device) {
			const lastNullIndex = binSearch(device.points, (p) => !p.timestamp);
			const firstShownIndex = binSearch(device.points, (p) => (p.timestamp || 0) < this.start) + 1;
			const lastShownIndex = binSearch(device.points, (p) => (p.timestamp || 0) < this.end);
			const filtered = [
				...device.points.slice(0, lastNullIndex + 1),
				...device.points.slice(firstShownIndex, lastShownIndex + 1),
			];
			return filtered.map((p) => [p.lat, p.lng]);
		},

		renderDevices() {
			this.featureGroup.clearLayers();
			this.deviceLayersMap.clear();

			this.displayedDevices.forEach(device => {
				const points = this.getDevicePoints(device);
				if (points.length === 0) return;

				const lastPoint = points[points.length - 1];
				const color = device.color || '#0082c9';
				const thumbnailClass = isComputer(device.user_agent) ? 'desktop' : 'phone';

				// Wrapper group for marker and line
				const deviceGroup = L.featureGroup();

				// Marker
				const marker = L.marker(lastPoint, {
					icon: L.divIcon({
						html: `<div class="thumbnail-wrapper" style="--custom-color: ${color}; border-color: ${color};"><div class="thumbnail ${thumbnailClass}" style="background-color: ${color};"></div></div>​`,
						className: 'leaflet-marker-device device-marker',
						iconSize: [DEVICE_MARKER_VIEW_SIZE, DEVICE_MARKER_VIEW_SIZE],
						iconAnchor: [DEVICE_MARKER_VIEW_SIZE / 2, DEVICE_MARKER_VIEW_SIZE],
					})
				});

				marker.bindTooltip(`
					<div class="tooltip-device-wrapper" style="border: 2px solid ${color}">
						<b>${t('maps', 'Name')}:</b> <span>${device.user_agent}</span>
					</div>
				`, { sticky: true, className: 'leaflet-marker-device-tooltip', direction: 'top', offset: L.point(0, 0) });

				marker.on('mouseover', () => this.$emit('point-hover', { ...device.points[device.points.length - 1], color, user_agent: device.user_agent }));
				marker.on('click', () => this.$emit('click', device));
				marker.on('contextmenu', (e) => this.showDevicePopup(e, device));

				deviceGroup.addLayer(marker);

				// History Polyline
				if (device.historyEnabled && points.length <= 2500) {
					const lineBg = L.polyline(points, { color: 'black', opacity: 1, weight: 4 * 1.6 });
					const lineFg = L.polyline(points, { color: color, opacity: 1, weight: 4 });
					
					const lineGroup = L.featureGroup([lineBg, lineFg]);
					lineGroup.on('mouseover', (e) => this.onDeviceLineMouseover(e, device, color));
					lineGroup.on('contextmenu', (e) => this.showDevicePopup(e, device));

					deviceGroup.addLayer(lineGroup);
				}

				this.featureGroup.addLayer(deviceGroup);
				this.deviceLayersMap.set(device.id, deviceGroup);
			});
		},

		showDevicePopup(e, device) {
			const mapIsUpdatable = optionsController.optionValues?.isUpdateable;
			const popupContent = L.DomUtil.create('div', 'popup-device-wrapper');
			
			popupContent.innerHTML = `
				<button class="action-btn" data-action="history">${device.historyEnabled ? t('maps', 'Hide history') : t('maps', 'Show history')}</button>
				${mapIsUpdatable ? `<button class="action-btn" data-action="color">${t('maps', 'Change color')}</button>` : ''}
				<button class="action-btn" data-action="export">${t('maps', 'Export')}</button>
				${!this.isPublic() ? `<button class="action-btn" data-action="link">${t('maps', 'Link to map')}</button>` : ''}
			`;

			popupContent.addEventListener('click', (event) => {
				const action = event.target.getAttribute('data-action');
				if (action === 'history') this.$emit('toggle-history', device);
				if (action === 'color') this.$emit('change-color', device);
				if (action === 'export') this.$emit('export', device);
				if (action === 'link') this.$emit('add-to-map-device', device);
				this.map.closePopup();
			});

			L.popup({ closeButton: false, className: 'popovermenu open popupMarker devicePopup', offset: L.point(-5, -20) })
				.setLatLng(e.latlng)
				.setContent(popupContent)
				.openOn(this.map);
		},

		onDeviceLineMouseover(e, device, color) {
			const overLatLng = e.layer._map.layerPointToLatLng(e.layerPoint);
			const closestPoint = device.points.reduce((target, p) => {
				if ((!p.timestamp || (p.timestamp >= this.start && p.timestamp <= this.end)) && (e.layer._map.distance(overLatLng, [p.lat, p.lng]) < target.minDist)) {
					target.minDist = e.layer._map.distance(overLatLng, [p.lat, p.lng]);
					target.hoverPoint = p;
				}
				return target;
			}, { minDist: 40000000, hoverPoint: null });

			if (closestPoint.hoverPoint) {
				this.$emit('point-hover', { ...closestPoint.hoverPoint, color, user_agent: device.user_agent });
			}
		}
	},
}
</script>

<style lang="scss">
.tooltip-device-wrapper {
	padding: 6px;
	border-radius: 3px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
}
.popup-device-wrapper .action-btn {
	display: block;
	width: 100%;
	padding: 8px;
	border: none;
	background: transparent;
	text-align: left;
	cursor: pointer;
}
.popup-device-wrapper .action-btn:hover {
	background: var(--color-background-hover);
}
</style>