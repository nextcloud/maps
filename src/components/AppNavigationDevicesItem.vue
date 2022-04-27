<template>
	<AppNavigationItem
		:icon="loading ? 'icon-loading-small' : 'icon-phone'"
		:title="t('maps', 'My devices')"
		:class="{ 'item-disabled': !enabled }"
		:allow-collapse="true"
		:open="open"
		:force-menu="false"
		@click="onClick"
		@update:open="onUpdateOpen">
		<CounterBubble v-show="enabled && devices.length"
			slot="counter">
			{{ devices.length > 99 ? '99+' : devices.length }}
		</CounterBubble>
		<template v-if="enabled" slot="actions">
			<ActionButton
				icon="icon-download"
				:close-after-click="true"
				@click="$emit('refresh-positions')">
				{{ t('maps', 'Refresh positions') }}
			</ActionButton>
			<ActionButton
				icon="icon-checkmark"
				@click="$emit('toggle-all')">
				{{ t('maps', 'Toggle all') }}
			</ActionButton>
			<ActionButton
				icon="icon-edit"
				@click="$emit('export-all')">
				{{ t('maps', 'Export all') }}
			</ActionButton>
			<ActionButton v-if="isCreatable"
				icon="icon-folder"
				@click="$emit('import')">
				{{ t('maps', 'Import devices') }}
			</ActionButton>
			<ActionButton v-if="allDeletable"
				icon="icon-delete"
				:close-after-click="true"
				@click="onDelete">
				{{ t('maps', 'Delete all') }}
			</ActionButton>
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
				@color="$emit('color', $event)" />
		</template>
	</AppNavigationItem>
</template>

<script>
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import CounterBubble from '@nextcloud/vue/dist/Components/CounterBubble'

import AppNavigationDeviceItem from './AppNavigationDeviceItem'
import optionsController from '../optionsController'

export default {
	name: 'AppNavigationDevicesItem',

	components: {
		AppNavigationItem,
		ActionButton,
		CounterBubble,
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
		}
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
	color: var(--color-primary);
}
</style>
