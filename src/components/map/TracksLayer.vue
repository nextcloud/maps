<template>
	<LFeatureGroup>
		<TrackLayer v-for="track in displayedTracks"
			:key="track.id + track.color"
			:track="track"
			@click="$emit('click', $event)"
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

import TrackLayer from './TrackLayer'
import TrackHoverMarker from './TrackHoverMarker'

import optionsController from '../../optionsController'

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
