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
			<template #default>
				<div class="track-start-marker"
					:style="'background-color: ' + color + '; border-color: ' + color"
					@click.stop="onLineClick"
					@contextmenu.stop="onLineRightClick" />
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
			</template>
		</MglMarker>
		<MglMarker v-for="(point, i) in wayPoints"
			:key="'waypoint:'.concat(i)"
			:coordinates="[point.lng, point.lat]">
			<template #default>
				<div class="track-waypoint-marker"
					:style="'background-color: ' + color + '; border-color: ' + color" />
			</template>
		</MglMarker>
	</template>
</template>

<script>
import {
	MglMarker,
	MglPopup,
	MglGeoJsonSource,
	MglLineLayer,
} from '@indoorequal/vue-maplibre-gl'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'
import { getRemoteURL, getRootPath } from '@nextcloud/files/dav'

import optionsController from '../../optionsController.js'
import { binSearch, isPublic, getToken } from '../../utils/common.js'

export default {
	name: 'TrackLayer',
	components: {
		MglMarker,
		MglPopup,
		MglGeoJsonSource,
		MglLineLayer,
		NcActionButton,
		NcActionLink,
	},

	props: {
		track: {
			type: Object,
			required: true,
		},
		start: {
			type: Number,
			required: false,
			default: 0,
		},
		end: {
			type: Number,
			required: false,
			default: moment().unix(),
		},
	},

	data() {
		return {
			optionValues: optionsController.optionValues,
			showPopup: false,
		}
	},

	computed: {
		isPublicVal() {
			return isPublic()
		},
		downloadTrackUrl() {
			return getRemoteURL() + getRootPath() + this.track.file_path
		},
		downloadTrackShareUrl() {
			return generateUrl('s/' + getToken() + '/download' + '?path=/&files=' + this.track.file_name)
		},
		dateBegin() {
			return this.track.metadata?.begin
				? moment.unix(this.track.metadata.begin).format('LLL')
				: ''
		},
		lines() {
			const trkSegments = []
			if ((this.metadata?.begin && this.metadata?.begin > 0 && this.track.metadata.begin >= this.end) || (this.track.metadata?.end && this.track.metadata?.end > 0 && this.track.metadata?.end <= this.start)) {
				return [...this.track.data.routes, trkSegments]
			} else if ((!this.track.metadata?.begin || this.metadata?.begin < 0 || this.track.metadata?.begin >= this.start) && (!this.track.metadata?.end || !this.track.metadata?.end < 0 || this.track.metadata?.end <= this.end)) {
				this.track.data.tracks.forEach((trk) => {
					trk.segments.forEach((segment) => {
						trkSegments.push({ ...segment, name: trk.name })
					})
				})
			} else {
				this.track.data.tracks.forEach((trk) => {
					trk.segments.forEach((segment) => {
						const lastNullIndex = binSearch(segment.points, (p) => !p.timestamp)
						const firstShownIndex = binSearch(segment.points, (p) => (p.timestamp || -1) < this.start) + 1
						const lastShownIndex = binSearch(segment.points, (p) => (p.timestamp || -1) < this.end)
						const points = [
							...segment.points.slice(0, lastNullIndex + 1),
							...segment.points.slice(firstShownIndex, lastShownIndex + 1),
						]
						trkSegments.push({ ...segment, name: trk.name, points })
					})
				})
			}
			return [...this.track.data.routes, ...trkSegments]
		},
		color() {
			return this.track.color || '#0082c9'
		},
		firstPoint() {
			let firstPoint = null
			if (this.track.data.tracks.length > 0
				&& this.track.data.tracks[0].segments.length > 0
				&& this.track.data.tracks[0].segments[0].points.length > 0) {
				firstPoint = this.track.data.tracks[0].segments[0].points[0]
			}
			if (this.track.data.routes.length > 0
				&& this.track.data.routes[0].points.length > 0
				&& (firstPoint === null
					|| (!firstPoint.timestamp && this.track.data.routes[0].points[0].timestamp)
					|| (this.track.data.routes[0].points[0].timestamp && firstPoint.timestamp
						&& this.track.data.routes[0].points[0].timestamp < firstPoint.timestamp))) {
				firstPoint = this.track.data.routes[0].points[0]
			}
			if (this.track.data.waypoints.length > 0
				&& (firstPoint === null
					|| (!firstPoint.timestamp && this.track.data.waypoints[0].timestamp)
					|| (this.track.data.waypoints[0].timestamp && firstPoint.timestamp
						&& this.track.data.waypoints[0].timestamp < firstPoint.timestamp))) {
				firstPoint = this.track.data.waypoints[0]
			}
			return firstPoint
		},
		wayPoints() {
			let points = []
			if (this.firstPoint === this.track.data.waypoints[0]) {
				points = this.track.data.waypoints.slice(1)
			} else {
				points = this.track.data.waypoints
			}
			if (this.track.metadata?.begin >= this.end || (this.track.metadata?.end >= 0 && this.track.metadata?.end <= this.start)) {
				return []
			} else if (this.track.metadata?.begin >= this.start && this.track.metadata?.end <= this.end) {
				return points
			} else {
				const lastNullIndex = binSearch(points, (p) => !p.timestamp)
				const firstShownIndex = binSearch(points, (p) => (p.timestamp || -1) < this.start) + 1
				const lastShownIndex = binSearch(points, (p) => (p.timestamp || -1) < this.end)
				return [
					...points.slice(0, lastNullIndex + 1),
					...points.slice(firstShownIndex, lastShownIndex + 1),
				]
			}
		},
	},

	methods: {
		lineGeoJson(line) {
			return {
				type: 'Feature',
				geometry: {
					type: 'LineString',
					coordinates: line.points.map(p => [p.lng, p.lat]),
				},
			}
		},
		onLineClick() {
			this.$emit('click', this.track)
		},
		onLineRightClick() {
			this.showPopup = !this.showPopup
		},
		trackLineMouseover(e, line) {
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
				const hoverPoint = {
					...line.points[closestI],
					color: this.color,
					file_name: this.track.file_name,
					track_name: line.name,
				}
				this.$emit('point-hover', hoverPoint)
			}
		},
	},
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
