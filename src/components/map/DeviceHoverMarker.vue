<template>
	<LFeatureGroup>
		<LMarker
			:icon="icon"
			:lat-lng="[point.lat, point.lng]">
			<LTooltip :options="tooltipOptions">
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
			</LTooltip>
		</LMarker>
	</LFeatureGroup>
</template>

<script>
import L from 'leaflet'
import { LMarker, LTooltip, LFeatureGroup } from 'vue2-leaflet'

import moment from '@nextcloud/moment'

export default {
	name: 'DeviceHoverMarker',
	components: {
		LFeatureGroup,
		LMarker,
		LTooltip,
	},

	props: {
		point: {
			type: Object,
			required: true,
		},
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
			return L.divIcon(L.extend({
				html: '<div class="device-over-marker" style="background-color: ' + this.point.color + ';"></div>',
				className: 'device-over-marker-wrapper',
			}, null, {
				iconSize: [16, 16],
				iconAnchor: [8, 8],
			}))
		},
		date() {
			if (this.point.timestamp) {
				const mom = moment.unix(this.point.timestamp)
				return mom.format('LL') + ' ' + mom.format('HH:mm:ss')
			} else {
				return null
			}
		},
		altitude() {
			return this.point.altitude
				? this.point.altitude + ' m'
				: null
		},
		battery() {
			return this.point.battery
				? this.point.battery + ' %'
				: null
		},
		accuracy() {
			return this.point.accuracy
				? this.point.accuracy + ' m'
				: null
		},
	},

	methods: {
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
