<template>
	<NcAppNavigationItem
		:name="device.user_agent"
		:class="{ 'subitem-disabled': !device.enabled }"
		:allow-collapse="false"
		:force-menu="false"
		@click="$emit('click', device)">
		<template #icon>
			<div v-if="device.loading"
				class="app-navigation-entry-icon icon-loading-small " />
			<div v-else
				class="icon icon-group"
				:style="'background-image: url(' + iconUrl + ');'" />
			<input v-if="device.isUpdateable"
				v-show="false"
				ref="col"
				type="color"
				class="color-input"
				:value="device.color || '#0082c9'"
				@change="updateDeviceColor"
				@click.stop="">
		</template>
		<template #counter>
			&nbsp;
		</template>
		<template #actions>
			<NcActionButton v-if="parentEnabled && device.enabled"
				icon="icon-category-monitoring"
				:close-after-click="true"
				@click="$emit('toggle-history', device)">
				{{ device.historyEnabled ? t('maps', 'Hide history') : t('maps', 'Show history') }}
			</NcActionButton>
			<NcActionButton v-if="parentEnabled && device.enabled && mapIsUpdatable"
				:close-after-click="false"
				@click="onChangeColorClick">
				<template #icon>
					<div class="icon-colorpicker" />
				</template>
				{{ t('maps', 'Change color') }}
			</NcActionButton>
			<NcActionButton v-if="parentEnabled && device.enabled"
				icon="icon-search"
				:close-after-click="true"
				@click="$emit('zoom', device)">
				{{ t('maps', 'Zoom on area') }}
			</NcActionButton>
			<NcActionButton v-if="parentEnabled && device.enabled"
				icon="icon-file"
				:close-after-click="true"
				@click="$emit('export', device)">
				{{ t('maps', 'Export') }}
			</NcActionButton>
			<NcActionButton v-if="parentEnabled && device.enabled && device.isShareable && !isPublic()"
				icon="icon-share"
				:close-after-click="true"
				@click="$emit('add-to-map-device', device)">
				{{ t('maps', 'Link to map') }}
			</NcActionButton>
			<NcActionButton v-if="parentEnabled && device.enabled && device.isDeletable"
				icon="icon-delete"
				:close-after-click="true"
				@click="$emit('delete', device)">
				{{ t('maps', 'Delete') }}
			</NcActionButton>
		</template>
	</NcAppNavigationItem>
</template>

<script setup>
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { ref, computed } from 'vue'
import { isComputer } from '../utils.js'
import { isPublic } from '../utils/common.js'
import optionsController from '../optionsController.js'

const props = defineProps({
	device: {
		type: Object,
		required: true,
	},
	parentEnabled: {
		type: Boolean,
		default: true,
	},
})

const emit = defineEmits(['click', 'toggle-history', 'zoom', 'export', 'add-to-map-device', 'delete', 'color'])

const col = ref(null)

const iconUrl = computed(() => {
	const color = props.device.color || '#0082c9'
	return isComputer(props.device.user_agent)
		? generateUrl('/svg/core/clients/desktop?color=' + color.replace('#', ''))
		: generateUrl('/svg/core/clients/phone?color=' + color.replace('#', ''))
})

const mapIsUpdatable = computed(() => {
	return optionsController.optionValues?.isUpdateable
})

function onChangeColorClick() {
	col.value.click()
}

function updateDeviceColor(e) {
	emit('color', { device: props.device, color: e.target.value })
}

defineExpose({ onChangeColorClick })
</script>

<style lang="scss" scoped>
.subitem-disabled {
	opacity: 0.5;
}

.icon-colorpicker {
	opacity: 1;
	mask: url('../../img/color_picker.svg') no-repeat;
	-webkit-mask: url('../../img/color_picker.svg') no-repeat;
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
	width: 44px;
	height: 44px;
}
</style>
