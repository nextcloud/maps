<template>
	<AppNavigationItem
		:title="device.user_agent"
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
			<input v-show="false"
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
			<ActionButton v-if="parentEnabled && device.enabled"
				icon="icon-category-monitoring"
				:close-after-click="true"
				@click="$emit('toggle-history', device)">
				{{ t('maps', 'Toggle history') }}
			</ActionButton>
			<ActionButton v-if="parentEnabled && device.enabled"
				:close-after-click="false"
				@click="onChangeColorClick">
				<template #icon>
					<div class="icon-colorpicker" />
				</template>
				{{ t('maps', 'Change color') }}
			</ActionButton>
			<ActionButton v-if="parentEnabled && device.enabled"
				icon="icon-search"
				:close-after-click="true"
				@click="$emit('zoom', device)">
				{{ t('maps', 'Zoom on area') }}
			</ActionButton>
			<ActionButton v-if="parentEnabled && device.enabled"
				icon="icon-file"
				:close-after-click="true"
				@click="$emit('export', device)">
				{{ t('maps', 'Export') }}
			</ActionButton>
			<ActionButton v-if="parentEnabled && device.enabled"
				icon="icon-delete"
				:close-after-click="true"
				@click="$emit('delete', device)">
				{{ t('maps', 'Delete') }}
			</ActionButton>
		</template>
	</AppNavigationItem>
</template>

<script>
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import { generateUrl } from '@nextcloud/router'
import { isComputer } from '../utils'

export default {
	name: 'AppNavigationDeviceItem',

	components: {
		AppNavigationItem,
		ActionButton,
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
	},

	methods: {
		onChangeColorClick() {
			this.$refs.col.click()
		},
		updateDeviceColor(e) {
			this.$emit('color', { device: this.device, color: e.target.value })
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
