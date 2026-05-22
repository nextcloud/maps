<template>
	<div v-if="track"
		id="track-tab">
		<TrackTable
			ref="table"
			:track="track" />
	</div>
</template>

<script setup>
import TrackTable from './TrackTable.vue'

defineProps({
	track: {
		required: true,
		validator: prop => typeof prop === 'object' || prop === null,
	},
})
</script>

<style lang="scss" scoped>
#track-tab {
	padding: 0 10px 0 10px;
}
</style>
