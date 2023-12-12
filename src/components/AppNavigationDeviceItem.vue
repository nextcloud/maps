<template>
	<NcAppNavigationItem
		:name="device.user_agent"
		:class="{ 'subitem-disabled': !device.enabled }"
		:allow-collapse="false"
		:force-menu="false"
		@click="$emit('click', device)">
		<template slot="icon">
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
		<template slot="counter">
			&nbsp;
		</template>
		<template slot="actions">
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

<script>
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import { generateUrl } from '@nextcloud/router'
import { isComputer } from '../utils.js'
import { isPublic } from '../utils/common.js'

import optionsController from '../optionsController.js'

export default {
	name: 'AppNavigationDeviceItem',

	components: {
		NcAppNavigationItem,
		NcActionButton,
	},

	props: {
		device: {
			type: Object,
			required: true,
		},
		parentEnabled: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
		iconUrl() {
			const color = this.device.color || '#0082c9'
			return isComputer(this.device.user_agent)
				? generateUrl('/svg/core/clients/desktop?color=' + color.replace('#', ''))
				: generateUrl('/svg/core/clients/phone?color=' + color.replace('#', ''))
		},
		mapIsUpdatable() {
			return optionsController.optionValues?.isUpdateable
		},
	},

	methods: {
		onChangeColorClick() {
			this.$refs.col.click()
		},
		updateDeviceColor(e) {
			this.$emit('color', { device: this.device, color: e.target.value })
		},
		isPublic() {
			return isPublic()
		},
	},
}
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
