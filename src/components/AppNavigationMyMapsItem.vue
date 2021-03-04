<template>
	<AppNavigationItem
		:icon="loading ? 'icon-loading-small' : 'icon-maps'"
		:title="t('maps', 'My Maps')"
		:class="{ 'item-disabled': !enabled }"
		:allow-collapse="true"
		:open="open"
		:force-menu="false"
		@click="onClick"
		@update:open="onUpdateOpen">
		<template slot="counter">
			&nbsp;
			<span v-if="enabled && myMaps.length">{{ myMaps.length }}</span>
		</template>
		<template v-if="enabled" slot="actions">
			<ActionButton
				icon="icon-add"
				:close-after-click="true"
				@click="onAddMyMap">
				{{ t('maps', 'Add Map') }}
			</ActionButton>
		</template>
		<template #default>
			<AppNavigationMyMapItem
				v-for="myMap in myMaps"
				:key="myMap.id"
				:ref="'myMapItem' + myMap.id"
				:map="myMap"
				:parent-enabled="enabled && myMaps.length > 0"
				@click="$emit('myMap-clicked', $event)"
				@rename="$emit('rename', $event)"
				@delete="$emit('delete', $event)"
				@color="$emit('color', $event)" />
		</template>
	</AppNavigationItem>
</template>

<script>
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigationMyMapItem from './AppNavigationMyMapItem'
import optionsController from '../optionsController'

export default {
	name: 'AppNavigationMyMapsItem',

	components: {
		AppNavigationItem,
		ActionButton,
		AppNavigationMyMapItem,
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
		myMaps: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
			open: optionsController.myMapListShow,
			currentMap: optionsController.myMapId,
		}
	},

	methods: {
		onClick() {
			if (!this.enabled && !this.open) {
				this.open = true
				optionsController.saveOptionValues({ myMapListShow: 'true' })
			}
			this.$emit('myMaps-clicked')
		},
		onUpdateOpen(isOpen) {
			this.open = isOpen
			optionsController.saveOptionValues({ myMapListShow: isOpen ? 'true' : 'false' })
		},
		onAddMyMap() {

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
