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
			<NcCounterBubble v-show="enabled && myMaps.length" :count="myMaps.length" />
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

<script setup>
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import AppNavigationMyMapItem from './AppNavigationMyMapItem.vue'
import { t } from '@nextcloud/l10n'
import { ref } from 'vue'
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
	myMaps: {
		type: Array,
		required: true,
	},
})

const emit = defineEmits([
	'my-maps-clicked',
	'add',
	'my-map-clicked',
	'rename',
	'delete',
	'share',
	'color',
])

const open = ref(optionsController.myMapListShow)

function onClick() {
	if (!props.enabled && !open.value) {
		open.value = true
		optionsController.saveOptionValues({ myMapListShow: 'true' })
	}
	emit('my-maps-clicked')
}

function onUpdateOpen(isOpen) {
	open.value = isOpen
	optionsController.saveOptionValues({ myMapListShow: isOpen ? 'true' : 'false' })
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
