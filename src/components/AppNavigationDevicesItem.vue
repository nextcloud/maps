<template>
	<NcAppNavigationItem
		:icon="loading ? 'icon-loading-small' : 'icon-phone'"
		:name="t('maps', 'My devices')"
		:class="{ 'item-disabled': !enabled }"
		:allow-collapse="true"
		:open="open"
		:force-menu="false"
		@click="onClick"
		@update:open="onUpdateOpen">
		<NcCounterBubble v-show="enabled && devices.length"
			slot="counter">
			{{ devices.length > 99 ? '99+' : devices.length }}
		</NcCounterBubble>
		<template v-if="enabled" slot="actions">
			<NcActionButton
				icon="icon-download"
				:close-after-click="true"
				@click="$emit('refresh-positions')">
				{{ t('maps', 'Refresh positions') }}
			</NcActionButton>
			<NcActionButton
				icon="icon-checkmark"
				@click="$emit('toggle-all')">
				{{ t('maps', 'Toggle all') }}
			</NcActionButton>
			<NcActionButton
				icon="icon-edit"
				@click="$emit('export-all')">
				{{ t('maps', 'Export all') }}
			</NcActionButton>
			<NcActionButton v-if="isCreatable"
				icon="icon-folder"
				@click="$emit('import')">
				{{ t('maps', 'Import devices') }}
			</NcActionButton>
			<NcActionButton v-if="allDeletable"
				icon="icon-delete"
				:close-after-click="true"
				@click="onDelete">
				{{ t('maps', 'Delete all') }}
			</NcActionButton>
		</template>
		<template slot="default">
			<b v-show="false">dummy</b>
			<AppNavigationDeviceItem
				v-for="device in sortedDevices"
				:key="device.id"
				:ref="'deviceItem' + device.id"
				:device="device"
				:parent-enabled="enabled && devices.length > 0"
				@click="$emit('device-clicked', $event)"
				@zoom="$emit('zoom', $event)"
				@rename="$emit('rename', $event)"
				@export="$emit('export', $event)"
				@delete="$emit('delete', $event)"
				@toggle-history="$emit('toggle-history', $event)"
				@color="$emit('color', $event)"
				@add-to-map-device="$emit('add-to-map-device', $event)" />
		</template>
	</NcAppNavigationItem>
</template>

<script>
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcCounterBubble from '@nextcloud/vue/dist/Components/NcCounterBubble.js'

import AppNavigationDeviceItem from './AppNavigationDeviceItem.vue'
import optionsController from '../optionsController.js'

export default {
	name: 'AppNavigationDevicesItem',

	components: {
		NcAppNavigationItem,
		NcActionButton,
		NcCounterBubble,
		AppNavigationDeviceItem,
	},

	props: {
		enabled: {
			type: Boolean,
			required: true,
		},
		loading: {
			type: Boolean,
			default: false,
		},
		devices: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
			open: optionsController.deviceListShow,
		}
	},

	computed: {
		sortedDevices() {
			return this.devices.slice().sort((a, b) => {
				const nameA = a.user_agent.toLowerCase()
				const nameB = b.user_agent.toLowerCase()
				return nameA.localeCompare(nameB)
			})
		},
		isCreatable() {
			return optionsController.myMapId === null || optionsController.myMapId === ''
		},
		allDeletable() {
			return this.devices.every((d) => d.isDeletable)
		},
	},

	methods: {
		onClick() {
			if (!this.enabled && !this.open) {
				this.open = true
				optionsController.saveOptionValues({ deviceListShow: 'true' })
			}
			this.$emit('devices-clicked')
		},
		onUpdateOpen(isOpen) {
			this.open = isOpen
			optionsController.saveOptionValues({ deviceListShow: isOpen ? 'true' : 'false' })
		},
		changeDeviceColor(device) {
			console.debug(this.$refs)
			this.$refs['deviceItem' + device.id][0].onChangeColorClick()
		},
		onDelete(device) {
		},
	},
}
</script>

<style lang="scss" scoped>
.item-disabled {
	opacity: 0.5;
}

::v-deep .no-color {
	color: var(--color-primary-element);
}
</style>
