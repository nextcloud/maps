<template>
	<LFeatureGroup>
		<TrackLayer v-for="track in displayedTracks"
			:key="track.id + track.color"
			:track="track"
			:start="start"
			:end="end"
			@click="$emit('click', $event)"
			@add-to-map-track="$emit('add-to-map-track', $event)"
			@change-color="$emit('change-color', $event)"
			@display-elevation="$emit('display-elevation', $event)"
			@point-hover="onPointHover" />
		<TrackHoverMarker
			v-if="hoverPoint"
			:point="hoverPoint" />
	</LFeatureGroup>
</template>

<script>
import { LFeatureGroup } from 'vue2-leaflet'

import TrackLayer from './TrackLayer.vue'
import TrackHoverMarker from './TrackHoverMarker.vue'

import optionsController from '../../optionsController.js'
import moment from '@nextcloud/moment'

export default {
	name: 'TracksLayer',
	components: {
		LFeatureGroup,
		TrackLayer,
		TrackHoverMarker,
	},

	props: {
		tracks: {
			type: Array,
			required: true,
		},
		map: {
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
			hoverPoint: null,
		}
	},

	computed: {
		displayedTracks() {
			return this.tracks.filter((track) => {
				return track.enabled
			})
		},
	},

	watch: {
		tracks: {
			handler() {
				this.hoverPoint = null
			},
			deep: true,
		},
	},

	methods: {
		onPointHover(point) {
			this.hoverPoint = point
		},
	},
}
</script>

<style lang="scss" scoped>
// nothing
</style>
