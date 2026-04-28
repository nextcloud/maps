<template>
	<MglMarker :coordinates="[point.lng, point.lat]">
		<template #default>
			<div class="device-over-marker-wrapper">
				<div class="device-over-marker" :style="'background-color: ' + point.color" />
			</div>
			<MglPopup :close-button="false" anchor="bottom">
				<div class="tooltip-device-wrapper" :style="'border: 2px solid ' + point.color">
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
			</MglPopup>
		</template>
	</MglMarker>
</template>

<script setup>
import { computed } from 'vue'
import { MglMarker, MglPopup } from '@indoorequal/vue-maplibre-gl'
import moment from '@nextcloud/moment'
import { t } from '@nextcloud/l10n'

const props = defineProps({
	point: {
		type: Object,
		required: true,
	},
})

const date = computed(() => {
	if (props.point.timestamp) {
		const mom = moment.unix(props.point.timestamp)
		return mom.format('LL') + ' ' + mom.format('HH:mm:ss')
	}
	return null
})

const altitude = computed(() => props.point.ele ? props.point.ele + ' m' : null)
const trackName = computed(() => props.point.track_name || null)
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
