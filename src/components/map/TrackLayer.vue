<template>
	<LFeatureGroup>
		<LPopup :options="popupOptions">
			{{ track.file_name }}
		</LPopup>
		<LTooltip :options="tooltipOptions">
			{{ track.file_name }}
		</LTooltip>
		<LFeatureGroup v-for="(line, i) in lines"
			:key="line.name + i">
			<LPolyline
				color="black"
				:opacity="1"
				:weight="4 * 1.6"
				:lat-lngs="line.points" />
			<LPolyline
				:color="track.color || '#0082c9'"
				:opacity="1"
				:weight="4"
				:lat-lngs="line.points" />
		</LFeatureGroup>
	</LFeatureGroup>
</template>

<script>
import L from 'leaflet'
import { LMarker, LTooltip, LPopup, LFeatureGroup, LPolyline } from 'vue2-leaflet'

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
	},

	created() {
		console.debug('TTTT ' + this.track.file_name)
	},

	methods: {
	},
}
</script>

<style lang="scss" scoped>
// nothing
</style>
