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

<script setup>
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'

defineProps({
	device: {
		required: true,
		type: Object,
	},
})

defineEmits(['device-click'])
</script>

<style scoped>

::v-deep .item-disabled {
	opacity: 0.5;
}

</style>
