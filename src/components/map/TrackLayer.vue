<template>
	<LFeatureGroup
		ref="featgroup"
		@ready="onFGReady"
		@click="$emit('click', track)"
		@contextmenu="onFGRightClick">
		<LPopup :options="popupOptions"
			class="popup-track-wrapper">
			<ActionButton icon="icon-colorpicker" @click="$emit('change-color', track)">
				{{ t('maps', 'Change color') }}
			</ActionButton>
			<ActionButton icon="icon-category-monitoring" @click="$emit('display-elevation', track)">
				{{ t('maps', 'Display elevation') }}
			</ActionButton>
		</LPopup>
		<LTooltip :options="tooltipOptions">
			<div class="tooltip-track-wrapper"
				:style="'border: 2px solid ' + color">
				<b>{{ t('maps', 'Name') }}:</b>
				<span>{{ track.file_name }}</span>
			</div>
		</LTooltip>
		<LMarker
			:icon="markerIcon"
			:lat-lng="firstPoint" />
		<LFeatureGroup v-for="(line, i) in lines"
			:key="i">
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

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'

import optionsController from '../../optionsController'

const TRACK_MARKER_VIEW_SIZE = 40

export default {
	name: 'TrackLayer',
	components: {
		LMarker,
		LTooltip,
		LPopup,
		LFeatureGroup,
		LPolyline,
		ActionButton,
	},

	props: {
		track: {
			type: Object,
			required: true,
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
		lines() {
			const trkSegments = []
			this.track.data.tracks.forEach((trk) => {
				trkSegments.push(...trk.segments)
			})
			return [...this.track.data.routes, ...trkSegments]
		},
		color() {
			return this.track.color || '#0082c9'
		},
		markerIcon() {
			return L.divIcon(L.extend({
				html: '<div class="thumbnail-wrapper" style="--custom-color: ' + this.color + '; border-color: ' + this.color + ';">'
					+ '<div class="thumbnail" style="background-color: ' + this.color + ';"></div></div>​',
				className: 'leaflet-marker-track track-marker',
			}, null, {
				iconSize: [TRACK_MARKER_VIEW_SIZE, TRACK_MARKER_VIEW_SIZE],
				iconAnchor: [TRACK_MARKER_VIEW_SIZE / 2, TRACK_MARKER_VIEW_SIZE],
			}))
		},
		firstPoint() {
			let firstPoint = null
			if (this.track.data.tracks.length > 0
				&& this.track.data.tracks[0].segments.length > 0
				&& this.track.data.tracks[0].segments[0].points.length > 0
				&& this.track.data.tracks[0].segments[0].points[0].timestamp) {
				firstPoint = this.track.data.tracks[0].segments[0].points[0]
			}
			if (this.track.data.routes.length > 0
				&& this.track.data.routes[0].points.length > 0
				&& this.track.data.routes[0].points[0].timestamp
				&& (firstPoint === null || this.track.data.routes[0].points[0].timestamp < firstPoint.timestamp)) {
				firstPoint = this.track.data.routes[0].points[0]
			}
			if (this.track.data.waypoints.length > 0
				&& this.track.data.waypoints[0].timestamp
				&& this.track.data.waypoints[0].timestamp < firstPoint.timestamp) {
				firstPoint = this.track.data.waypoints[0]
			}
			return firstPoint
		},
	},

	created() {
	},

	methods: {
		onFGReady(f) {
			// avoid left click popup
			L.DomEvent.on(f, 'click', (ev) => {
				f.closePopup()
			})
		},
		onFGRightClick(e) {
			this.$refs.featgroup.mapObject.openPopup()
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
