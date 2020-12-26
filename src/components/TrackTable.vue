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
import moment from '@nextcloud/moment'

import { metersToDistance, formatTimeSeconds, metersToElevation, kmphToSpeed, minPerKmToPace } from '../utils'

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
				value: meta.distance ? metersToDistance(meta.distance) : '???',
			})
			lines.push({
				title: t('maps', 'Duration'),
				value: formatTimeSeconds(meta.duration || 0),
			})
			lines.push({
				title: t('maps', 'Moving time'),
				value: formatTimeSeconds(meta.movtime || 0),
			})
			lines.push({
				title: t('maps', 'Pause time'),
				value: formatTimeSeconds(meta.stptime || 0),
			})
			lines.push({
				title: t('maps', 'Begin'),
				value: moment.unix(meta.begin).format('L HH:mm:ss (Z)'),
			})
			lines.push({
				title: t('maps', 'End'),
				value: moment.unix(meta.end).format('L HH:mm:ss (Z)'),
			})
			lines.push({
				title: t('maps', 'Cumulative elevation gain'),
				value: meta.posel ? metersToElevation(meta.posel) : 'NA',
			})
			lines.push({
				title: t('maps', 'Cumulative elevation loss'),
				value: meta.negel ? metersToElevation(meta.negel) : 'NA',
			})
			lines.push({
				title: t('maps', 'Minimum elevation'),
				value: meta.minel ? metersToElevation(meta.minel) : 'NA',
			})
			lines.push({
				title: t('maps', 'Maximum elevation'),
				value: meta.maxel ? metersToElevation(meta.maxel) : 'NA',
			})
			lines.push({
				title: t('maps', 'Maximum speed'),
				value: meta.maxspd ? kmphToSpeed(meta.maxspd) : 'NA',
			})
			lines.push({
				title: t('maps', 'Average speed'),
				value: meta.avgspd ? kmphToSpeed(meta.avgspd) : 'NA',
			})
			lines.push({
				title: t('maps', 'Moving average speed'),
				value: meta.movavgspd ? kmphToSpeed(meta.movavgspd) : 'NA',
			})
			lines.push({
				title: t('maps', 'Moving average pace'),
				value: meta.movpace ? minPerKmToPace(meta.movpace) : 'NA',
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
