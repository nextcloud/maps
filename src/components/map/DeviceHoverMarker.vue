<template>
	<MglMarker :coordinates="[point.lng, point.lat]">
		<template #default>
			<div class="device-over-marker-wrapper">
				<div class="device-over-marker" :style="'background-color: ' + point.color" />
			</div>
			<MglPopup :close-button="false" anchor="bottom">
				<div class="tooltip-device-wrapper" :style="'border: 2px solid ' + point.color">
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
			</MglPopup>
		</template>
	</MglMarker>
</template>

<script>
import { MglMarker, MglPopup } from '@indoorequal/vue-maplibre-gl'
import moment from '@nextcloud/moment'

export default {
	name: 'DeviceHoverMarker',
	components: {
		MglMarker,
		MglPopup,
	},

	props: {
		point: {
			type: Object,
			required: true,
		},
	},

	computed: {
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
}
</script>

<style lang="scss" scoped>
.device-over-marker-wrapper {
	width: 16px;
	height: 16px;
}
.device-over-marker {
	width: 16px;
	height: 16px;
	border-radius: 50%;
}
.tooltip-device-wrapper {
	padding: 6px;
	border-radius: 3px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
}
</style>
