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
		<template slot="counter">
			&nbsp;
			<span v-if="enabled && devices.length">{{ devices.length }}</span>
		</template>
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
			<ActionButton
				icon="icon-folder"
				@click="$emit('import')">
				{{ t('maps', 'Import devices') }}
			</ActionButton>
			<ActionButton
				icon="icon-delete"
				:close-after-click="true"
				@click="onDelete">
				{{ t('maps', 'Delete all') }}
			</ActionButton>
		</template>
		<template #default>
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
import AppNavigationDeviceItem from './AppNavigationDeviceItem'
import optionsController from '../optionsController'

export default {
	name: 'AppNavigationDevicesItem',

	components: {
		AppNavigationItem,
		ActionButton,
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
