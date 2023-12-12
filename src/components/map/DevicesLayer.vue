<template>
	<LFeatureGroup>
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
			ref="devicesHeatMap"
			:initial-points="points"
			:options="optionsHeatMap" />
	</LFeatureGroup>
</template>

<script>
import { LFeatureGroup } from 'vue2-leaflet'

import DeviceLayer from './DeviceLayer.vue'
import DeviceHoverMarker from './DeviceHoverMarker.vue'

import optionsController from '../../optionsController.js'
import moment from '@nextcloud/moment'
import { binSearch } from '../../utils/common.js'
import LHeatMap from './LHeatMap.vue'

export default {
	name: 'DevicesLayer',
	components: {
		LFeatureGroup,
		LHeatMap,
		DeviceLayer,
		DeviceHoverMarker,
	},

	props: {
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
			required: false,
			default: 0,
		},
		end: {
			type: Number,
			required: false,
			default: moment.unix(),
		},
	},

	data() {
		return {
			optionsHeatMap: {
				// minOpacity: null,
				// maxZoom: null,
				radius: 15,
				blur: 10,
				gradient: { 0.4: 'blue', 0.65: 'lime', 1: 'red' },
			},
			optionValues: optionsController.optionValues,
			hoverPoint: null,
		}
	},

	computed: {
		displayedDevices() {
			return this.devices.filter(d => d.enabled && d.points.length > 0)
		},
		enabledDevices() {
			return this.devices.map(d => d.enabled)
		},
		displayedDevicesHistories() {
			return this.devices.map(d => d.enabled && d.historyEnabled)
		},
		points() {
			return this.devices.reduce((points, device) => {
				if (device.enabled && device.historyEnabled) {
					const lastNullIndex = binSearch(device.points, (p) => !p.timestamp)
					const firstShownIndex = binSearch(device.points, (p) => (p.timestamp || 0) < this.start) + 1
					const lastShownIndex = binSearch(device.points, (p) => (p.timestamp || 0) < this.end)
					if (lastNullIndex + 1 + lastShownIndex - firstShownIndex + 1 > 2500) {
						const filteredDevicePoints = [
							...device.points.slice(0, lastNullIndex + 1),
							...device.points.slice(firstShownIndex, lastShownIndex + 1),
						]
						const deviceLatLngs = filteredDevicePoints.map((p) => [p.lat, p.lng])
						points = points.concat(deviceLatLngs)
					}
				}
				return points
			}, [])
		},
	},

	watch: {
		enabledDevices() {
			this.hoverPoint = null
		},
		displayedDevicesHistories() {
			if (this.$refs.devicesHeatMap) {
				this.$refs.devicesHeatMap.setLatLngs(this.points)
			}
		},
		start() {
			if (this.$refs.devicesHeatMap) {
				this.$refs.devicesHeatMap.setLatLngs(this.points)
			}
		},
		end() {
			if (this.$refs.devicesHeatMap) {
				this.$refs.devicesHeatMap.setLatLngs(this.points)
			}
		},
	},

	methods: {
		onPointHover(point) {
			this.hoverPoint = point
		},
	},
}
</script>

<style lang="scss" scoped>
// nothing
</style>
