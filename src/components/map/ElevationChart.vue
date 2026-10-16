<!--
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	Distance/elevation chart of a track, with a gap between its segments.
-->
<template>
	<div class="maps-elevation-chart">
		<div class="elevation-header">
			<span class="elevation-title">{{ track.file_name }}</span>
			<span v-if="summary" class="elevation-summary">{{ summary }}</span>
			<NcButton variant="tertiary" :aria-label="t('maps', 'Close')" @click="emit('close')">
				<template #icon>
					<Close :size="20" />
				</template>
			</NcButton>
		</div>
		<p v-if="!hasElevationData" class="elevation-empty">
			{{ t('maps', 'No elevation data available for this track.') }}
		</p>
		<div v-else class="elevation-canvas-wrapper">
			<canvas ref="canvas" />
		</div>
	</div>
</template>

<script setup lang="ts">
import { Chart } from 'chart.js/auto'
import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import Close from 'vue-material-design-icons/Close.vue'
import type { Track, TrackPoint } from './TracksLayer.vue'

const props = defineProps<{
	track: Track
}>()

const emit = defineEmits<{
	close: []
}>()

interface ElevationDatum {
	x: number
	y: number
}

const EARTH_RADIUS_METERS = 6371000

/**
 * @param a first point
 * @param b second point
 */
function distanceMeters(a: TrackPoint, b: TrackPoint): number {
	const toRad = (deg: number) => deg * Math.PI / 180
	const dLat = toRad(b.lat - a.lat)
	const dLng = toRad(b.lng - a.lng)
	const h = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.sin(dLng / 2) ** 2
	return 2 * EARTH_RADIUS_METERS * Math.asin(Math.sqrt(h))
}

/**
 * @param track the track to build a distance/elevation series for
 */
function buildSeries(track: Track): Array<ElevationDatum | null> {
	const lines: TrackPoint[][] = []
	;(track.data?.routes ?? []).forEach((route) => lines.push(route.points))
	;(track.data?.tracks ?? []).forEach((file) => file.segments.forEach((segment) => lines.push(segment.points)))

	const series: Array<ElevationDatum | null> = []
	let cumulative = 0
	lines.forEach((points, lineIndex) => {
		if (lineIndex > 0 && series.length > 0) {
			series.push(null)
		}
		let previous: TrackPoint | null = null
		points.forEach((point) => {
			if (previous) {
				cumulative += distanceMeters(previous, point)
			}
			if (typeof point.ele === 'number') {
				series.push({ x: cumulative / 1000, y: point.ele })
			}
			previous = point
		})
	})
	return series
}

const series = computed(() => buildSeries(props.track))
const hasElevationData = computed(() => series.value.some((point) => point !== null))

const summary = computed(() => {
	const points = series.value.filter((point): point is ElevationDatum => point !== null)
	if (points.length === 0) {
		return ''
	}
	let gain = 0
	let lastElevation: number | null = null
	for (const point of series.value) {
		if (point === null) {
			lastElevation = null
			continue
		}
		if (lastElevation !== null && point.y > lastElevation) {
			gain += point.y - lastElevation
		}
		lastElevation = point.y
	}
	const totalDistance = points[points.length - 1].x
	return t('maps', '{distance} km, +{gain} m', { distance: totalDistance.toFixed(1), gain: Math.round(gain) })
})

const canvas = ref<HTMLCanvasElement | null>(null)
let chart: Chart | null = null

function renderChart(): void {
	chart?.destroy()
	chart = null
	if (!canvas.value || !hasElevationData.value) {
		return
	}
	const style = getComputedStyle(document.documentElement)
	const textColor = style.getPropertyValue('--color-main-text').trim() || '#000000'
	const gridColor = style.getPropertyValue('--color-border').trim() || '#dddddd'
	const lineColor = props.track.color || '#0082c9'

	chart = new Chart(canvas.value, {
		type: 'line',
		data: {
			datasets: [{
				data: series.value,
				borderColor: lineColor,
				backgroundColor: lineColor + '33',
				fill: true,
				pointRadius: 0,
				borderWidth: 2,
				spanGaps: false,
			}],
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			animation: false,
			interaction: { mode: 'index', intersect: false },
			scales: {
				x: {
					type: 'linear',
					title: { display: true, text: t('maps', 'Distance (km)'), color: textColor },
					ticks: { color: textColor },
					grid: { color: gridColor },
				},
				y: {
					title: { display: true, text: t('maps', 'Elevation (m)'), color: textColor },
					ticks: { color: textColor },
					grid: { color: gridColor },
				},
			},
			plugins: {
				legend: { display: false },
				tooltip: {
					callbacks: {
						title: (items) => t('maps', '{distance} km', { distance: items[0].parsed.x.toFixed(2) }),
						label: (item) => t('maps', '{ele} m', { ele: Math.round(item.parsed.y) }),
					},
				},
			},
		},
	})
}

watch(() => props.track, () => nextTick(renderChart), { immediate: true })

onBeforeUnmount(() => {
	chart?.destroy()
})
</script>

<style lang="scss" scoped>
.maps-elevation-chart {
	position: absolute;
	bottom: 20px;
	left: 20px;
	width: 700px;
	max-width: calc(100% - 40px);
	padding: 8px;
	background-color: var(--color-main-background);
	border: 2px solid var(--color-border-dark);
	border-radius: var(--border-radius-large);
	z-index: 5;
}

.elevation-header {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 4px;

	.elevation-title {
		flex-grow: 1;
		overflow: hidden;
		font-weight: bold;
		white-space: nowrap;
		text-overflow: ellipsis;
	}

	.elevation-summary {
		color: var(--color-text-maxcontrast);
		font-size: 12px;
	}
}

.elevation-empty {
	margin: 8px 0;
	color: var(--color-text-maxcontrast);
}

.elevation-canvas-wrapper {
	position: relative;
	height: 150px;
}
</style>
