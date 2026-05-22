<template>
	<template>
		<MglGeoJsonSource v-for="(line, i) in lines"
			:key="'outline-' + track.id + '-' + i"
			:source-id="'track-outline-' + track.id + '-' + i"
			:data="lineGeoJson(line)">
			<MglLineLayer
				:layer-id="'track-outline-layer-' + track.id + '-' + i"
				:layout="{ 'line-join': 'round', 'line-cap': 'round' }"
				:paint="{ 'line-color': '#000000', 'line-width': 6.4, 'line-opacity': 1 }"
				@click.stop="onLineClick"
				@contextmenu.stop="onLineRightClick"
				@mousemove="(e) => trackLineMouseover(e, line)" />
		</MglGeoJsonSource>
		<MglGeoJsonSource v-for="(line, i) in lines"
			:key="'line-' + track.id + '-' + i"
			:source-id="'track-line-' + track.id + '-' + i"
			:data="lineGeoJson(line)">
			<MglLineLayer
				:layer-id="'track-line-layer-' + track.id + '-' + i"
				:layout="{ 'line-join': 'round', 'line-cap': 'round' }"
				:paint="{ 'line-color': color, 'line-width': 4, 'line-opacity': 1 }" />
		</MglGeoJsonSource>
		<MglMarker v-if="firstPoint"
			:coordinates="[firstPoint.lng, firstPoint.lat]">
			<template #marker>
				<div class="track-start-marker"
					:style="'background-color: ' + color + '; border-color: ' + color"
					@click.stop="onLineClick"
					@contextmenu.stop="onLineRightClick" />
			</template>
			<MglPopup v-if="showPopup" :close-button="false" anchor="bottom" @close="showPopup = false">
				<NcActionButton v-if="track.isUpdateable" icon="icon-colorpicker" @click="$emit('change-color', track)">
					{{ t('maps', 'Change color') }}
				</NcActionButton>
				<NcActionButton icon="icon-category-monitoring" @click="$emit('display-elevation', track)">
					{{ t('maps', 'Display elevation') }}
				</NcActionButton>
				<NcActionButton v-if="!isPublicVal && track.isShareable" icon="icon-share" @click="$emit('add-to-map-track', track)">
					{{ t('maps', 'Copy to map') }}
				</NcActionButton>
				<NcActionLink v-if="!isPublicVal" :href="downloadTrackUrl" target="_self" icon="icon-download" :close-after-click="true">
					{{ t('maps', 'Download track') }}
				</NcActionLink>
				<NcActionLink v-if="isPublicVal && !(track.hideDownload)" target="_self" :href="downloadTrackShareUrl" icon="icon-download" :close-after-click="true">
					{{ t('maps', 'Download track') }}
				</NcActionLink>
			</MglPopup>
		</MglMarker>
		<MglMarker v-for="(point, i) in wayPoints"
			:key="'waypoint:'.concat(i)"
			:coordinates="[point.lng, point.lat]">
			<template #marker>
				<div class="track-waypoint-marker"
					:style="'background-color: ' + color + '; border-color: ' + color" />
			</template>
		</MglMarker>
	</template>
</template>

<script setup>
import { ref, computed } from 'vue'
import { t } from '@nextcloud/l10n'
import { MglMarker, MglPopup, MglGeoJsonSource, MglLineLayer } from '@indoorequal/vue-maplibre-gl'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'
import { getRemoteURL, getRootPath } from '@nextcloud/files/dav'
import { binSearch, isPublic, getToken } from '../../utils/common.js'

const props = defineProps({
	track: {
		type: Object,
		required: true,
	},
	start: {
		type: Number,
		default: 0,
	},
	end: {
		type: Number,
		default: () => moment().unix(),
	},
})

const emit = defineEmits(['click', 'change-color', 'display-elevation', 'add-to-map-track', 'point-hover'])

const showPopup = ref(false)

const isPublicVal = computed(() => isPublic())
const downloadTrackUrl = computed(() => getRemoteURL() + getRootPath() + props.track.file_path)
const downloadTrackShareUrl = computed(() => generateUrl('s/' + getToken() + '/download' + '?path=/&files=' + props.track.file_name))
const color = computed(() => props.track.color || '#0082c9')

const lines = computed(() => {
	const trkSegments = []
	const m = props.track.metadata
	if ((m?.begin && m?.begin > 0 && m.begin >= props.end) || (m?.end && m?.end > 0 && m?.end <= props.start)) {
		return [...props.track.data.routes, trkSegments]
	} else if ((!m?.begin || m?.begin < 0 || m?.begin >= props.start) && (!m?.end || !m?.end < 0 || m?.end <= props.end)) {
		props.track.data.tracks.forEach((trk) => {
			trk.segments.forEach((segment) => {
				trkSegments.push({ ...segment, name: trk.name })
			})
		})
	} else {
		props.track.data.tracks.forEach((trk) => {
			trk.segments.forEach((segment) => {
				const lastNullIndex = binSearch(segment.points, (p) => !p.timestamp)
				const firstShownIndex = binSearch(segment.points, (p) => (p.timestamp || -1) < props.start) + 1
				const lastShownIndex = binSearch(segment.points, (p) => (p.timestamp || -1) < props.end)
				const points = [
					...segment.points.slice(0, lastNullIndex + 1),
					...segment.points.slice(firstShownIndex, lastShownIndex + 1),
				]
				trkSegments.push({ ...segment, name: trk.name, points })
			})
		})
	}
	return [...props.track.data.routes, ...trkSegments]
})

const firstPoint = computed(() => {
	let fp = null
	if (props.track.data.tracks.length > 0
		&& props.track.data.tracks[0].segments.length > 0
		&& props.track.data.tracks[0].segments[0].points.length > 0) {
		fp = props.track.data.tracks[0].segments[0].points[0]
	}
	if (props.track.data.routes.length > 0
		&& props.track.data.routes[0].points.length > 0
		&& (fp === null
			|| (!fp.timestamp && props.track.data.routes[0].points[0].timestamp)
			|| (props.track.data.routes[0].points[0].timestamp && fp.timestamp
				&& props.track.data.routes[0].points[0].timestamp < fp.timestamp))) {
		fp = props.track.data.routes[0].points[0]
	}
	if (props.track.data.waypoints.length > 0
		&& (fp === null
			|| (!fp.timestamp && props.track.data.waypoints[0].timestamp)
			|| (props.track.data.waypoints[0].timestamp && fp.timestamp
				&& props.track.data.waypoints[0].timestamp < fp.timestamp))) {
		fp = props.track.data.waypoints[0]
	}
	return fp
})

const wayPoints = computed(() => {
	let points = firstPoint.value === props.track.data.waypoints[0]
		? props.track.data.waypoints.slice(1)
		: props.track.data.waypoints
	const m = props.track.metadata
	if (m?.begin >= props.end || (m?.end >= 0 && m?.end <= props.start)) {
		return []
	} else if (m?.begin >= props.start && m?.end <= props.end) {
		return points
	} else {
		const lastNullIndex = binSearch(points, (p) => !p.timestamp)
		const firstShownIndex = binSearch(points, (p) => (p.timestamp || -1) < props.start) + 1
		const lastShownIndex = binSearch(points, (p) => (p.timestamp || -1) < props.end)
		return [
			...points.slice(0, lastNullIndex + 1),
			...points.slice(firstShownIndex, lastShownIndex + 1),
		]
	}
})

function lineGeoJson(line) {
	return {
		type: 'Feature',
		geometry: {
			type: 'LineString',
			coordinates: line.points.map(p => [p.lng, p.lat]),
		},
	}
}

function onLineClick() {
	emit('click', props.track)
}

function onLineRightClick() {
	showPopup.value = !showPopup.value
}

function trackLineMouseover(e, line) {
	const lngLat = e.lngLat
	let minDist = 40000000
	let closestI = -1
	for (let i = 0; i < line.points.length; i++) {
		const dx = lngLat.lng - line.points[i].lng
		const dy = lngLat.lat - line.points[i].lat
		const dist = Math.sqrt(dx * dx + dy * dy)
		if (dist < minDist) {
			minDist = dist
			closestI = i
		}
	}
	if (closestI !== -1) {
		emit('point-hover', {
			...line.points[closestI],
			color: color.value,
			file_name: props.track.file_name,
			track_name: line.name,
		})
	}
}
</script>

<style lang="scss" scoped>
.track-start-marker {
	width: 40px;
	height: 40px;
	border-radius: 50%;
	border: 2px solid;
	cursor: pointer;
}

.track-waypoint-marker {
	width: 30px;
	height: 30px;
	border-radius: 50%;
	border: 2px solid;
	cursor: pointer;
}

::v-deep .icon-colorpicker {
	opacity: 1;
	mask: url('../../../img/color_picker.svg') no-repeat;
	-webkit-mask: url('../../../img/color_picker.svg') no-repeat;
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
	width: 44px;
	height: 44px;
}
</style>
