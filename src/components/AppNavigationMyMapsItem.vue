<template>
	<AppNavigationItem
		:icon="loading ? 'icon-loading-small' : 'icon-maps-dark'"
		:title="t('maps', 'My maps')"
		:class="{ 'item-disabled': !enabled }"
		:allow-collapse="true"
		:open="open"
		:force-menu="false"
		@click="onClick"
		@update:open="onUpdateOpen">
		<CounterBubble v-show="enabled && myMaps.length"
			slot="counter">
			{{ myMaps.length > 99 ? '99+' : myMaps.length }}
		</CounterBubble>
		<template v-if="enabled" slot="actions">
			<ActionButton
				icon="icon-add"
				:close-after-click="true"
				@click="$emit('add', t('maps', 'New map'))">
				{{ t('maps', 'Add Map') }}
			</ActionButton>
		</template>
		<template slot="default">
			<b v-show="false">dummy</b>
			<AppNavigationMyMapItem
				v-for="myMap in myMaps"
				:key="myMap.id"
				:ref="'myMapItem' + myMap.id"
				:my-map="myMap"
				:parent-enabled="enabled && myMaps.length > 1"
				@click="$emit('my-map-clicked', $event)"
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
import CounterBubble from '@nextcloud/vue/dist/Components/CounterBubble'

export default {
	name: 'AppNavigationMyMapsItem',

	components: {
		AppNavigationItem,
		ActionButton,
		AppNavigationMyMapItem,
		CounterBubble,
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
			this.$emit('my-maps-clicked')
		},
		onUpdateOpen(isOpen) {
			this.open = isOpen
			optionsController.saveOptionValues({ myMapListShow: isOpen ? 'true' : 'false' })
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

::v-deep .icon-maps-dark {
	background-color: var(--color-main-text);
	mask: url('../../img/maps-dark.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('../../img/maps-dark.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
}
</style>
