<template>
	<div v-if="track">
		<TrackTable
			ref="table"
			:track="track" />
	</div>
	<div v-else>
		<p>
			{{ t('maps', 'Select a track.') }}
		</p>
	</div>
</template>

<script>
import TrackTable from './TrackTable'

export default {
	name: 'TrackSidebarTab',

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
// nothing
</style>
