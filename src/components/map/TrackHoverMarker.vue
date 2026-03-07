<template>
	<div class="tooltip-device-wrapper"
		:style="'border: 2px solid ' + point.color">
		<b>{{ t('maps', 'File') }}:</b>
		<span>{{ point.file_name }}</span>
		<div v-if="trackName">
			<b>{{ t('maps', 'Track/Route') }}:</b>
			<span>{{ trackName }}</span>
		</div>
		<div v-if="date">
			<b>{{ t('maps', 'Date') }}:</b>
			<span>{{ date }}</span>
		</div>
		<div v-if="altitude">
			<b>{{ t('maps', 'Altitude') }}:</b>
			<span>{{ altitude }}</span>
		</div>
	</div>
</template>

<script>
import L from 'leaflet'
import moment from '@nextcloud/moment'

export default {
	name: 'TrackHoverMarker',

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
		altitude() { return this.point.ele ? this.point.ele + ' m' : null },
		trackName() { return this.point.track_name ? this.point.track_name : null },
	},

	created() {
		this.marker = null;
	},

	watch: {
		point: {
			handler(newPoint) {
				if (this.marker) {
					this.marker.setLatLng([newPoint.lat, newPoint.lng])
					this.marker.setIcon(this.icon)
					this.marker.setTooltipContent(this.$el)
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

	beforeUnmount() {
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