<template>
	<LFeatureGroup>
		<DeviceLayer v-for="device in displayedDevices"
			:key="device.id + device.color"
			:device="device"
			:start="start"
			:end="end"
			@click="$emit('click', $event)"
			@export="$emit('export', $event)"
			@toggle-history="$emit('toggle-history', $event)"
			@change-color="$emit('change-color', $event)"
			@point-hover="onPointHover" />
		<DeviceHoverMarker
			v-if="hoverPoint"
			:point="hoverPoint" />
	</LFeatureGroup>
</template>

<script>
import { LFeatureGroup } from 'vue2-leaflet'

import DeviceLayer from './DeviceLayer'
import DeviceHoverMarker from './DeviceHoverMarker'

import optionsController from '../../optionsController'
import moment from '@nextcloud/moment'

export default {
	name: 'DevicesLayer',
	components: {
		LFeatureGroup,
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
			optionValues: optionsController.optionValues,
			hoverPoint: null,
		}
	},

	computed: {
		displayedDevices() {
			return this.devices.filter(d => d.enabled)
		},
	},

	watch: {
		devices: {
			handler() {
				this.hoverPoint = null
			},
			deep: true,
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
