<template>
	<NcAppNavigationItem
		:icon="'icon-phone'"
		:class="{ 'item-disabled': !device.enabled }"
		:name="device.name"
		:open="false"
		:force-menu="false"
		@click="$emit('device-click')">
		<template #counter>
			<NcCounterBubble :count="device.suggestionCount" />
		</template>
	</NcAppNavigationItem>
</template>

<script>

import { NcAppNavigationItem, NcCounterBubble } from '@nextcloud/vue'

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
