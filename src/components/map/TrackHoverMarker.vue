<template>
	<LFeatureGroup>
		<LMarker
			:icon="icon"
			:lat-lng="[point.lat, point.lng]">
			<LTooltip :options="tooltipOptions">
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
			</LTooltip>
		</LMarker>
	</LFeatureGroup>
</template>

<script>
import L from 'leaflet'
import { LMarker, LTooltip, LFeatureGroup } from 'vue2-leaflet'

import moment from '@nextcloud/moment'

export default {
	name: 'TrackHoverMarker',
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
			return this.point.ele
				? this.point.ele + ' m'
				: null
		},
		trackName() {
			return this.point.track_name
				? this.point.track_name
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
