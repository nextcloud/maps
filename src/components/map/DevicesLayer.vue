<template>
	<template>
		<DeviceLayer v-for="device in displayedDevices"
			:key="device.id + device.color"
			:device="device"
			:start="start"
			:end="end"
			@click="$emit('click', $event)"
			@add-to-map-device="$emit('add-to-map-device', $event)"
			@export="$emit('export', $event)"
			@toggle-history="$emit('toggle-history', $event)"
			@change-color="$emit('change-color', $event)"
			@point-hover="onPointHover" />
		<DeviceHoverMarker
			v-if="hoverPoint"
			:point="hoverPoint" />
		<LHeatMap v-if="points.length >= 2500"
			:initial-points="points"
			:options="optionsHeatMap" />
	</template>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import DeviceLayer from './DeviceLayer.vue'
import DeviceHoverMarker from './DeviceHoverMarker.vue'
import LHeatMap from './LHeatMap.vue'
import moment from '@nextcloud/moment'
import { binSearch } from '../../utils/common.js'

const props = defineProps({
	devices: {
		type: Array,
		required: true,
	},
	map: {
		type: Object,
		required: true,
	},
	start: {
		type: Number,
		default: 0,
	},
	end: {
		type: Number,
		default: () => moment.unix(),
	},
})

defineEmits(['click', 'add-to-map-device', 'export', 'toggle-history', 'change-color'])

const hoverPoint = ref(null)

const displayedDevices = computed(() => props.devices.filter(d => d.enabled && d.points.length > 0))
const enabledDevices = computed(() => props.devices.map(d => d.enabled))

const points = computed(() =>
	props.devices.reduce((acc, device) => {
		if (device.enabled && device.historyEnabled) {
			const lastNullIndex = binSearch(device.points, (p) => !p.timestamp)
			const firstShownIndex = binSearch(device.points, (p) => (p.timestamp || 0) < props.start) + 1
			const lastShownIndex = binSearch(device.points, (p) => (p.timestamp || 0) < props.end)
			if (lastNullIndex + 1 + lastShownIndex - firstShownIndex + 1 > 2500) {
				acc = acc.concat([
					...device.points.slice(0, lastNullIndex + 1),
					...device.points.slice(firstShownIndex, lastShownIndex + 1),
				])
			}
		}
		return acc
	}, []),
)

watch(enabledDevices, () => {
	hoverPoint.value = null
})

function onPointHover(point) {
	hoverPoint.value = point
}
</script>
