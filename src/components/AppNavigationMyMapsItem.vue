<template>
	<NcAppNavigationItem
		:icon="loading ? 'icon-loading-small' : 'icon-maps-dark'"
		:name="t('maps', 'My maps')"
		:class="{ 'item-disabled': !enabled }"
		:allow-collapse="true"
		:open="open"
		:force-menu="false"
		@click="onClick"
		@update:open="onUpdateOpen">
		<template #counter>
			<NcCounterBubble v-if="enabled && myMaps.length" :count="myMaps.length ? myMaps.length : 0" />
		</template>
		<template v-if="enabled" #actions>
			<NcActionButton
				icon="icon-add"
				:close-after-click="true"
				@click="$emit('add', t('maps', 'New map'))">
				{{ t('maps', 'Add Map') }}
			</NcActionButton>
		</template>
		<template #default>
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
				@share="$emit('share', $event)"
				@color="$emit('color', $event)" />
		</template>
	</NcAppNavigationItem>
</template>

<script>
import { NcAppNavigationItem, NcActionButton, NcCounterBubble } from '@nextcloud/vue'
import AppNavigationMyMapItem from './AppNavigationMyMapItem.vue'
import optionsController from '../optionsController.js'

export default {
	name: 'AppNavigationMyMapsItem',

	components: {
		NcAppNavigationItem,
		NcActionButton,
		AppNavigationMyMapItem,
		NcCounterBubble,
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
	color: var(--color-primary-element);
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
