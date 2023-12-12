<template>
	<div class="track-table">
		<h2>{{ track.file_name }}</h2>
		<table class="track-table">
			<tr v-for="l in tableLines"
				:key="l.title">
				<td>
					<span v-if="l.iconClass" :class="'table-img-icon table-icon ' + l.iconClass" />
					<span v-else :class="'table-icon ' + l.icon" />
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

import { metersToDistance, formatTimeSeconds, metersToElevation, kmphToSpeed, minPerKmToPace } from '../utils.js'

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
				iconClass: 'distance',
			})
			lines.push({
				title: t('maps', 'Duration'),
				value: formatTimeSeconds(meta.duration || 0),
				iconClass: 'clock',
			})
			lines.push({
				title: t('maps', 'Moving time'),
				value: formatTimeSeconds(meta.movtime || 0),
				iconClass: 'clock',
			})
			lines.push({
				title: t('maps', 'Pause time'),
				value: formatTimeSeconds(meta.stptime || 0),
				iconClass: 'clock',
			})
			lines.push({
				title: t('maps', 'Begin'),
				value: moment.unix(meta.begin).format('L HH:mm:ss (Z)'),
				icon: 'icon-calendar-dark',
			})
			lines.push({
				title: t('maps', 'End'),
				value: moment.unix(meta.end).format('L HH:mm:ss (Z)'),
				icon: 'icon-calendar-dark',
			})
			lines.push({
				title: t('maps', 'Cumulative elevation gain'),
				value: meta.posel ? metersToElevation(meta.posel) : 'NA',
				iconClass: 'chart-line',
			})
			lines.push({
				title: t('maps', 'Cumulative elevation loss'),
				value: meta.negel ? metersToElevation(meta.negel) : 'NA',
				iconClass: 'chart-line',
			})
			lines.push({
				title: t('maps', 'Minimum elevation'),
				value: meta.minel ? metersToElevation(meta.minel) : 'NA',
				iconClass: 'chart-area',
			})
			lines.push({
				title: t('maps', 'Maximum elevation'),
				value: meta.maxel ? metersToElevation(meta.maxel) : 'NA',
				iconClass: 'chart-area',
			})
			lines.push({
				title: t('maps', 'Maximum speed'),
				value: meta.maxspd ? kmphToSpeed(meta.maxspd) : 'NA',
				iconClass: 'speed',
			})
			lines.push({
				title: t('maps', 'Average speed'),
				value: meta.avgspd ? kmphToSpeed(meta.avgspd) : 'NA',
				iconClass: 'speed',
			})
			lines.push({
				title: t('maps', 'Moving average speed'),
				value: meta.movavgspd ? kmphToSpeed(meta.movavgspd) : 'NA',
				iconClass: 'speed',
			})
			lines.push({
				title: t('maps', 'Moving average pace'),
				value: meta.movpace ? minPerKmToPace(meta.movpace) : 'NA',
				iconClass: 'speed',
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
.track-table {
	width: 100%;
	h2 {
		text-align: center;
	}
	tr {
		&:hover {
			background-color: var(--color-background-hover);
		}
		td {
			border: 1px solid var(--color-border);
			padding: 0 5px 0 5px;
		}
	}

	.distance {
		mask: url('../../img/distance.svg') no-repeat;
		-webkit-mask: url('../../img/distance.svg') no-repeat;
	}
	.clock {
		mask: url('../../img/clock.svg') no-repeat;
		-webkit-mask: url('../../img/clock.svg') no-repeat;
	}
	.speed {
		mask: url('../../img/speed.svg') no-repeat;
		-webkit-mask: url('../../img/speed.svg') no-repeat;
	}
	.chart-line {
		mask: url('../../img/chart-line.svg') no-repeat;
		-webkit-mask: url('../../img/chart-line.svg') no-repeat;
	}
	.chart-area {
		mask: url('../../img/chart-area.svg') no-repeat;
		-webkit-mask: url('../../img/chart-area.svg') no-repeat;
	}
	.table-icon {
		display: inline-block;
		min-width: 30px !important;
		min-height: 16px !important;
	}
	.table-img-icon {
		opacity: 1;
		background-color: var(--color-main-text);
		padding: 0 !important;
		mask-size: 16px auto;
		mask-position: center;
		-webkit-mask-size: 16px auto;
		-webkit-mask-position: center;
	}
}
</style>
