<template>
	<div class="track-table">
		{{ track.file_name }}
		<table class="track-table">
			<tr v-for="l in tableLines"
				:key="l.title">
				<td>
					{{ l.title }}
				</td>
				<td>
					{{ l.value }}
				</td>
			</tr>
		</table>
	</div>
</template>

<script>

export default {
	name: 'TrackTable',

	components: {
	},

	props: {
		track: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
		tableLines() {
			const meta = this.track.metadata
			const lines = []
			lines.push({
				title: t('maps', 'Distance'),
				value: meta.distance || '???',
			})
			lines.push({
				title: t('maps', 'Duration'),
				value: meta.duration || '0',
			})
			return lines
		},
	},

	watch: {
	},

	methods: {
	},
}
</script>

<style lang="scss" scoped>
// plop
</style>
