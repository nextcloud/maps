<template>
	<NcAppNavigationItem
		:icon="'icon-phone'"
		:class="{ 'item-disabled': !device.enabled }"
		:name="device.name"
		:open="false"
		:force-menu="false"
		@click="$emit('device-click')">
		<NcCounterBubble slot="counter">
			{{ device.suggestionCount > 99 ? '99+' : device.suggestionCount }}
		</NcCounterBubble>
	</NcAppNavigationItem>
</template>

<script>

import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcCounterBubble from '@nextcloud/vue/dist/Components/NcCounterBubble.js'

export default {
	name: 'PhotoSideBarTabDeviceItem',

	components: {
		NcAppNavigationItem,
		NcCounterBubble,
	},

	props: {
		device: {
			required: true,
			type: Object,
		},
	},

	computed: {
	},

	methods: {
	},
}
</script>

<style scoped>

::v-deep .item-disabled {
	opacity: 0.5;
}

</style>
