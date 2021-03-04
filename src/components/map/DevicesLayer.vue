<template>
	<LFeatureGroup>
		<DeviceLayer v-for="device in displayedDevices"
			:key="device.id + device.color"
			:device="device"
			@click="$emit('click', $event)"
			@toggle-history="$emit('toggle-history', $event)"
			@change-color="$emit('change-color', $event)" />
	</LFeatureGroup>
</template>

<script>
import { LFeatureGroup } from 'vue2-leaflet'

import DeviceLayer from './DeviceLayer'

import optionsController from '../../optionsController'

export default {
	name: 'DevicesLayer',
	components: {
		LFeatureGroup,
		DeviceLayer,
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
	},

	data() {
		return {
			optionValues: optionsController.optionValues,
		}
	},

	computed: {
		displayedDevices() {
			return this.devices.filter(d => d.enabled)
		},
	},

	methods: {
	},
}
</script>

<style lang="scss" scoped>
// nothing
</style>
