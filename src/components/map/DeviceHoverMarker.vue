<template>
	<div class="tooltip-device-wrapper"
		:style="'border: 2px solid ' + point.color">
		<b>{{ t('maps', 'Device') }}:</b>
		<span>{{ point.user_agent }}</span>
		<div v-if="date">
			<b>{{ t('maps', 'Date') }}:</b>
			<span>{{ date }}</span>
		</div>
		<div v-if="altitude">
			<b>{{ t('maps', 'Altitude') }}:</b>
			<span>{{ altitude }}</span>
		</div>
		<div v-if="battery">
			<b>{{ t('maps', 'Battery') }}:</b>
			<span>{{ battery }}</span>
		</div>
		<div v-if="accuracy">
			<b>{{ t('maps', 'Accuracy') }}:</b>
			<span>{{ accuracy }}</span>
		</div>
	</div>
</template>

<script>
import L from 'leaflet'
import moment from '@nextcloud/moment'

export default {
	name: 'DeviceHoverMarker',

	props: {
		point: { type: Object, required: true },
		map: { type: Object, required: true }, // Added map prop
	},

	data() {
		return {
			tooltipOptions: {
				sticky: false,
				className: 'leaflet-marker-device-tooltip',
				direction: 'top',
				offset: L.point(0, 0),
			},
		}
	},

	computed: {
		icon() {
			return L.divIcon({
				html: `<div class="device-over-marker" style="background-color: ${this.point.color};"></div>`,
				className: 'device-over-marker-wrapper',
				iconSize: [16, 16],
				iconAnchor: [8, 8],
			})
		},
		date() {
			return this.point.timestamp 
				? moment.unix(this.point.timestamp).format('LL') + ' ' + moment.unix(this.point.timestamp).format('HH:mm:ss') 
				: null
		},
		altitude() { return this.point.altitude ? this.point.altitude + ' m' : null },
		battery() { return this.point.battery ? this.point.battery + ' %' : null },
		accuracy() { return this.point.accuracy ? this.point.accuracy + ' m' : null },
	},

	created() {
		this.marker = null; // Non-reactive leaflet object
	},

	watch: {
		point: {
			handler(newPoint) {
				if (this.marker) {
					this.marker.setLatLng([newPoint.lat, newPoint.lng])
					this.marker.setIcon(this.icon)
					this.marker.setTooltipContent(this.$el) // Force update DOM
				}
			},
			deep: true
		}
	},

	mounted() {
		this.marker = L.marker([this.point.lat, this.point.lng], { 
			icon: this.icon, 
			interactive: false 
		}).addTo(this.map)

		this.marker.bindTooltip(this.$el, this.tooltipOptions)
		this.marker.openTooltip()
	},

	beforeDestroy() {
		if (this.marker && this.map) {
			this.map.removeLayer(this.marker)
		}
	},
}
</script>

<style lang="scss" scoped>
.tooltip-device-wrapper {
	padding: 6px;
	border-radius: 3px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
}
</style>