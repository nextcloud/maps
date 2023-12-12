<template>
	<div v-if="track"
		id="track-tab">
		<TrackTable
			ref="table"
			:track="track" />
	</div>
</template>

<script>
import TrackTable from './TrackTable.vue'

export default {
	name: 'TrackMetadataTab',

	components: {
		TrackTable,
	},

	props: {
		track: {
			required: true,
			validator: prop => typeof prop === 'object' || prop === null,
		},
	},

	data() {
		return {
		}
	},

	computed: {
	},

	watch: {
	},

	methods: {
	},
}
</script>

<style lang="scss" scoped>
#track-tab {
	padding: 0 10px 0 10px;
}
</style>
