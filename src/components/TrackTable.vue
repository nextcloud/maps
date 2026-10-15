<template>
	<div class="track-table">
		<h2 v-if="track.file_name">
			{{ track.file_name }}
		</h2>

		<dl class="track-table__summary">
			<div v-for="tile in summary"
				:key="tile.title"
				class="track-table__tile">
				<dt>{{ tile.title }}</dt>
				<dd>{{ tile.value }}</dd>
			</div>
		</dl>

		<section v-for="section in sections"
			:key="section.title"
			class="track-table__section">
			<h3>{{ section.title }}</h3>
			<dl>
				<div v-for="line in section.lines"
					:key="line.title"
					class="track-table__line">
					<dt>
						<component :is="line.icon" :size="20" />
						{{ line.title }}
					</dt>
					<dd>{{ line.value }}</dd>
				</div>
			</dl>
		</section>
	</div>
</template>

<script setup lang="ts">
import type { Component } from 'vue'
import moment from '@nextcloud/moment'
import { translate as t } from '@nextcloud/l10n'
import { computed } from 'vue'
import ArrowCollapseDown from 'vue-material-design-icons/ArrowCollapseDown.vue'
import ArrowCollapseUp from 'vue-material-design-icons/ArrowCollapseUp.vue'
import Calendar from 'vue-material-design-icons/Calendar.vue'
import Pause from 'vue-material-design-icons/Pause.vue'
import Speedometer from 'vue-material-design-icons/Speedometer.vue'
import Timer from 'vue-material-design-icons/Timer.vue'
import TimerSand from 'vue-material-design-icons/TimerSand.vue'
import TrendingDown from 'vue-material-design-icons/TrendingDown.vue'
import { metersToDistance, formatTimeSeconds, metersToElevation, kmphToSpeed, minPerKmToPace } from '../utils.js'

/**
 * Track statistics as computed by the server, see TracksService::generateTrackMetadata()
 */
interface TrackMetadata {
	distance?: number
	duration?: number
	begin?: number
	end?: number
	posel?: number
	negel?: number
	minel?: number
	maxel?: number
	maxspd?: number
	avgspd?: number
	movtime?: number
	stptime?: number
	movavgspd?: number
	movpace?: number
}

interface Line {
	title: string
	value: string | null | undefined
	icon: Component
}

const props = defineProps<{
	track: {
		file_name?: string
		metadata?: TrackMetadata
	}
}>()

const MISSING = '—'

const meta = computed<TrackMetadata>(() => props.track.metadata ?? {})

const summary = computed(() => [
	{ title: t('maps', 'Distance'), value: meta.value.distance ? metersToDistance(meta.value.distance) : MISSING },
	{ title: t('maps', 'Duration'), value: meta.value.duration ? formatTimeSeconds(meta.value.duration) : MISSING },
	{ title: t('maps', 'Elevation gain'), value: meta.value.posel ? metersToElevation(meta.value.posel) : MISSING },
	{ title: t('maps', 'Average speed'), value: meta.value.avgspd ? kmphToSpeed(meta.value.avgspd) : MISSING },
])

const dateRange = computed(() => {
	const { begin, end } = meta.value
	if (!begin) {
		return null
	}
	const start = moment.unix(begin)
	if (!end) {
		return start.format('L LT')
	}
	const finish = moment.unix(end)
	return start.format('L LT') + ' → ' + finish.format(start.isSame(finish, 'day') ? 'LT' : 'L LT')
})

const sections = computed(() => {
	const { movtime, stptime, negel, minel, maxel, maxspd, movavgspd, movpace } = meta.value
	const all: { title: string, lines: Line[] }[] = [
		{
			title: t('maps', 'Time'),
			lines: [
				{ title: t('maps', 'Date'), value: dateRange.value, icon: Calendar },
				{ title: t('maps', 'Moving time'), value: movtime ? formatTimeSeconds(movtime) : null, icon: Timer },
				{ title: t('maps', 'Pause time'), value: stptime ? formatTimeSeconds(stptime) : null, icon: Pause },
			],
		},
		{
			title: t('maps', 'Elevation'),
			lines: [
				{ title: t('maps', 'Cumulative elevation loss'), value: negel ? metersToElevation(negel) : null, icon: TrendingDown },
				{ title: t('maps', 'Minimum elevation'), value: minel ? metersToElevation(minel) : null, icon: ArrowCollapseDown },
				{ title: t('maps', 'Maximum elevation'), value: maxel ? metersToElevation(maxel) : null, icon: ArrowCollapseUp },
			],
		},
		{
			title: t('maps', 'Speed'),
			lines: [
				{ title: t('maps', 'Maximum speed'), value: maxspd ? kmphToSpeed(maxspd) : null, icon: Speedometer },
				{ title: t('maps', 'Moving average speed'), value: movavgspd ? kmphToSpeed(movavgspd) : null, icon: Speedometer },
				{ title: t('maps', 'Moving average pace'), value: movpace ? minPerKmToPace(movpace) : null, icon: TimerSand },
			],
		},
	]
	return all
		.map((section) => ({ ...section, lines: section.lines.filter((line) => line.value) }))
		.filter((section) => section.lines.length > 0)
})
</script>

<style lang="scss" scoped>
.track-table {
	width: 100%;

	h2 {
		text-align: center;
	}

	dl,
	dd {
		margin: 0;
	}

	// server.css lays out every dt/dd as fixed-width inline blocks
	dt,
	dd {
		display: block;
		width: auto;
		padding: 0;
		white-space: normal;
		text-align: start;
	}

	&__summary {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 8px;
		margin-bottom: 16px !important;
	}

	&__tile {
		padding: 8px 12px;
		border-radius: var(--border-radius-large);
		background-color: var(--color-background-dark);

		dt {
			color: var(--color-text-maxcontrast);
			font-size: var(--font-size-small, 13px);
		}

		dd {
			font-size: 1.3em;
			font-weight: bold;
			font-variant-numeric: tabular-nums;
		}
	}

	&__section {
		margin-bottom: 12px;

		h3 {
			margin: 0 0 4px;
			font-size: 1em;
			font-weight: bold;
		}
	}

	&__line {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		min-height: 32px;
		padding: 0 4px;
		border-radius: var(--border-radius);

		&:hover {
			background-color: var(--color-background-hover);
		}

		dt {
			display: flex;
			align-items: center;
			gap: 8px;
			color: var(--color-text-maxcontrast);
		}

		dd {
			text-align: end;
			font-variant-numeric: tabular-nums;
		}
	}
}
</style>
