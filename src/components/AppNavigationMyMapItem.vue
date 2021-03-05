<template>
	<AppNavigationItem
		:title="myMap.name"
		:class="{ 'subitem-disabled': !myMap.enabled }"
		:allow-collapse="false"
		:force-menu="false"
		@click="$emit('click', myMap)">
		<template slot="icon">
			<div class="icon icon-location" />
			<input v-show="false"
				ref="col"
				type="color"
				class="color-input"
				:value="myMap.color || '#0082c9'"
				@change="updateMyMapColor"
				@click.stop="">
		</template>
		<template slot="counter">
			&nbsp;
		</template>
		<template slot="actions">
			<ActionButton v-if="parentEnabled"
				icon="icon-rename"
				:close-after-click="true"
				@click="$emit('rename', myMap)">
				{{ t('maps', 'Rename') }}
			</ActionButton>
			<ActionButton v-if="parentEnabled"
				:close-after-click="false"
				@click="onChangeColorClick">
				<template #icon>
					<div class="icon-colorpicker" />
				</template>
				{{ t('maps', 'Change color') }}
			</ActionButton>
			<ActionButton v-if="parentEnabled"
				icon="icon-delete"
				:close-after-click="true"
				@click="$emit('delete', myMap)">
				{{ t('maps', 'Delete') }}
			</ActionButton>
		</template>
	</AppNavigationItem>
</template>

<script>
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'

export default {
	name: 'AppNavigationMyMapItem',

	components: {
		AppNavigationItem,
		ActionButton,
	},

	props: {
		myMap: {
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
	},

	methods: {
		onChangeColorClick() {
		},
		updateMyMapColor(e) {
			this.$emit('color', { myMap: this.myMap, color: e.target.value })
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
