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
		<template #counter>
			<NcCounterBubble v-show="enabled && devices.length" :count="devices.length" />
		</template>
		<template v-if="enabled" #actions>
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
		<template #default>
			<b v-show="false">dummy</b>
			<AppNavigationDeviceItem
				v-for="device in sortedDevices"
				:key="device.id"
				:ref="(el) => setDeviceItemRef(device.id, el)"
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

<script setup>
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import AppNavigationDeviceItem from './AppNavigationDeviceItem.vue'
import { t } from '@nextcloud/l10n'
import { ref, computed } from 'vue'
import optionsController from '../optionsController.js'

const props = defineProps({
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
})

const emit = defineEmits([
	'devices-clicked',
	'refresh-positions',
	'toggle-all',
	'export-all',
	'import',
	'device-clicked',
	'zoom',
	'rename',
	'export',
	'delete',
	'toggle-history',
	'color',
	'add-to-map-device',
])

const open = ref(optionsController.deviceListShow)

const deviceItemRefs = {}

function setDeviceItemRef(id, el) {
	if (el) {
		deviceItemRefs[id] = el
	} else {
		delete deviceItemRefs[id]
	}
}

const sortedDevices = computed(() => {
	return props.devices.slice().sort((a, b) => {
		const nameA = a.user_agent.toLowerCase()
		const nameB = b.user_agent.toLowerCase()
		return nameA.localeCompare(nameB)
	})
})

const isCreatable = computed(() => {
	return optionsController.myMapId === null || optionsController.myMapId === ''
})

const allDeletable = computed(() => {
	return props.devices.every((d) => d.isDeletable)
})

function onClick() {
	if (!props.enabled && !open.value) {
		open.value = true
		optionsController.saveOptionValues({ deviceListShow: 'true' })
	}
	emit('devices-clicked')
}

function onUpdateOpen(isOpen) {
	open.value = isOpen
	optionsController.saveOptionValues({ deviceListShow: isOpen ? 'true' : 'false' })
}

function changeDeviceColor(device) {
	console.debug(deviceItemRefs)
	deviceItemRefs[device.id]?.onChangeColorClick()
}

function onDelete() {
}

defineExpose({ changeDeviceColor })
</script>

<style lang="scss" scoped>
.item-disabled {
	opacity: 0.5;
}

::v-deep .no-color {
	color: var(--color-primary-element);
}
</style>
