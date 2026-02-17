<template>
	<LFeatureGroup
		ref="featgroup"
		@ready="onFGReady"
		@click="$emit('click', track)"
		@contextmenu="onFGRightClick">
		<LPopup :options="popupOptions"
			class="popup-track-wrapper">
			<NcActionButton v-if="track.isUpdateable"
				icon="icon-colorpicker"
				@click="$emit('change-color', track)">
				{{ t('maps', 'Change color') }}
			</NcActionButton>
			<NcActionButton icon="icon-category-monitoring" @click="$emit('display-elevation', track)">
				{{ t('maps', 'Display elevation') }}
			</NcActionButton>
			<NcActionButton v-if="!isPublic() && track.isShareable"
				icon="icon-share"
				@click="$emit('add-to-map-track', track)">
				{{ t('maps', 'Copy to map') }}
			</NcActionButton>
			<NcActionLink v-if="!isPublic()"
				:href="downloadTrackUrl"
				target="_self"
				icon="icon-download"
				:close-after-click="true"
				@click="closeafterclickworkaround">
				<!--
				looks like close-after-click not working in this popovermenu
				therefore added workaround closeafterclickworkaround
				-->
				{{ t('maps', 'Download track') }}
			</NcActionLink>
			<NcActionLink v-if="isPublic() && !(track.hideDownload)"
				target="_self"
				:href="downloadTrackShareUrl"
				icon="icon-download"
				:close-after-click="true"
				@click="closeafterclickworkaround">
				<!--
				looks like close-after-click not working in this popovermenu
				therefore added workaround closeafterclickworkaround
				-->
				{{ t('maps', 'Download track') }}
			</NcActionLink>
		</LPopup>
		<LMarker v-if="firstPoint"
			:icon="firstPointMarkerIcon"
			:lat-lng="firstPoint" >
			<LTooltip :options="tooltipOptions">
        		<div class="tooltip-track-wrapper"
					:style="'border: 2px solid ' + color">					
					<b>{{ t('maps', 'File') }}:</b>
					<span>{{ track.file_name }}</span>
					<br v-if="dateBegin">
					<b v-if="dateBegin">{{ t('maps', 'Begins at') }}:</b>
					<span v-if="dateBegin">{{ dateBegin }}</span>
					<br v-if="firstPoint.name">
					<b v-if="firstPoint.name">{{ t('maps', 'Name') }}:</b>
					<span v-if="firstPoint.name">{{ firstPoint.name }}</span>
					<br v-if="firstPoint.desc">
					<b v-if="firstPoint.desc">{{ t('maps', 'Description') }}:</b>
					<span v-if="firstPoint.desc">{{ firstPoint.desc }}</span>
					<br v-if="firstPoint.ele">
					<b v-if="firstPoint.ele">{{ t('maps', 'Altitude') }}:</b>
					<span v-if="firstPoint.ele">{{ firstPoint.ele }} m</span>					
				</div>
    		</LTooltip>
		</LMarker>
		<LMarker v-for="(point, i) in wayPoints"
			:key="'waypoint:'.concat(i)"
			:icon="wayPointMarkerIcon"
			:lat-lng="point">
			<LTooltip :options="tooltipOptions">
        		<div class="tooltip-track-wrapper"
					:style="'border: 2px solid ' + color">					
					<b>{{ t('maps', 'File') }}:</b>
					<span>{{ track.file_name }}</span>
					<br v-if="point.name">
					<b v-if="point.name">{{ t('maps', 'Name') }}:</b>
					<span v-if="point.name">{{ point.name }}</span>
					<br v-if="point.desc">
					<b v-if="point.desc">{{ t('maps', 'Description') }}:</b>
					<span v-if="point.desc">{{ point.desc }}</span>
					<br v-if="point.ele">
					<b v-if="point.ele">{{ t('maps', 'Altitude') }}:</b>
					<span v-if="point.ele">{{ point.ele }} m</span>
				</div>
    		</LTooltip>
		</LMarker>
		<LFeatureGroup v-for="(line, i) in lines"
			:key="'line'.concat(i)"
			@mouseover="trackLineMouseover($event, line)">
			<LPolyline
				color="black"
				:opacity="1"
				:weight="4 * 1.6"
				:lat-lngs="line.points" />
			<LPolyline
				:color="color"
				:opacity="1"
				:weight="4"
				:lat-lngs="line.points" />
		</LFeatureGroup>
	</LFeatureGroup>
</template>

<script>
import L from 'leaflet'
import { LMarker, LTooltip, LPopup, LFeatureGroup, LPolyline } from 'vue2-leaflet'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionLink from '@nextcloud/vue/dist/Components/NcActionLink.js'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'

import optionsController from '../../optionsController.js'
import { binSearch, isPublic, getToken } from '../../utils/common.js'

const TRACK_MARKER_VIEW_SIZE = 40
const WAYPOINT_MARKER_VIEW_SIZE = 30

export default {
	name: 'TrackLayer',
	components: {
		LMarker,
		LTooltip,
		LPopup,
		LFeatureGroup,
		LPolyline,
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
			lineOptions: {
				weight: 4,
				opacity: 1,
			},
			tooltipOptions: {
				sticky: true,
				className: 'leaflet-marker-track-tooltip',
				direction: 'top',
				offset: L.point(0, 0),
			},
			popupOptions: {
				closeButton: false,
				closeOnClick: false,
				className: 'popovermenu open popupMarker trackPopup',
				offset: L.point(-5, -20),
			},
		}
	},

	computed: {
		downloadTrackUrl() {
			return OCA.Files.App.fileList.filesClient.getBaseUrl() + this.track.file_path
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
						// add track name to each segment
						trkSegments.push({
							...segment,
							name: trk.name,
						})
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
						// add track name to each segment
						trkSegments.push({
							...segment,
							name: trk.name,
							points,
						})
					})
				})
			}
			return [...this.track.data.routes, ...trkSegments]
		},
		color() {
			return this.track.color || '#0082c9'
		},
		firstPointMarkerIcon() {
			const selectedClass = this.track.selected
				? 'selected'
				: ''
			return L.divIcon(L.extend({
				html: '<div class="thumbnail-wrapper" style="--custom-color: ' + this.color + '; border-color: ' + this.color + ';">'
					+ '<div class="thumbnail" style="background-color: ' + this.color + ';"></div></div>​',
				className: 'leaflet-marker-track track-marker ' + selectedClass,
			}, null, {
				iconSize: [TRACK_MARKER_VIEW_SIZE, TRACK_MARKER_VIEW_SIZE],
				iconAnchor: [TRACK_MARKER_VIEW_SIZE / 2, TRACK_MARKER_VIEW_SIZE],
			}))
		},
		wayPointMarkerIcon() {
			return L.divIcon(L.extend({
				html: '<div class="thumbnail-wrapper" style="--custom-color: ' + this.color + '; border-color: ' + this.color + ';">'
					+ '<div class="thumbnail" style="background-color: ' + this.color + ';"></div></div>​',
				className: 'leaflet-marker-track track-waypoint ',
			}, null, {
				iconSize: [WAYPOINT_MARKER_VIEW_SIZE, WAYPOINT_MARKER_VIEW_SIZE],
				iconAnchor: [WAYPOINT_MARKER_VIEW_SIZE / 2, WAYPOINT_MARKER_VIEW_SIZE],
			}))
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

	created() {
	},

	methods: {
		// looks like close-after-click not working for NcAcionLink
		// added working closeafterclickworkaround
		closeafterclickworkaround() {
			this.$refs.featgroup.mapObject.closePopup()
		},
		onFGReady(f) {
			// avoid left click popup
			L.DomEvent.on(f, 'click', (ev) => {
				f.closePopup()
			})
		},
		onFGRightClick(e) {
			this.$refs.featgroup.mapObject.openPopup()
		},
		trackLineMouseover(e, line) {
			console.debug(this.track)
			const overLatLng = e.layer._map.layerPointToLatLng(e.layerPoint)
			let minDist = 40000000
			let tmpDist
			let closestI = -1
			for (let i = 0; i < line.points.length; i++) {
				tmpDist = e.layer._map.distance(overLatLng, line.points[i])
				if (tmpDist < minDist) {
					minDist = tmpDist
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
		isPublic() {
			return isPublic()
		},
	},
}
</script>

<style lang="scss" scoped>
// nothing
.tooltip-track-wrapper {
	padding: 6px;
	border-radius: 3px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
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
