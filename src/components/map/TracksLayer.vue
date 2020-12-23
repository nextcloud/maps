<template>
	<LFeatureGroup>
		<TrackLayer v-for="track in displayedTracks"
			:key="track.id + track.color"
			:track="track"
			@click="$emit('click', $event)"
			@change-color="$emit('change-color', $event)"
			@display-elevation="$emit('display-elevation', $event)" />
	</LFeatureGroup>
</template>

<script>
import L from 'leaflet'
import { LFeatureGroup } from 'vue2-leaflet'

import TrackLayer from './TrackLayer'

import optionsController from '../../optionsController'

export default {
	name: 'TracksLayer',
	components: {
		LFeatureGroup,
		TrackLayer,
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
			tooltipOptions: {
				className: 'leaflet-marker-contact-tooltip',
				direction: 'top',
				offset: L.point(0, 0),
			},
			popupOptions: {
				closeOnClick: false,
				className: 'popovermenu open popupMarker contactPopup',
				offset: L.point(-5, 10),
			},
		}
	},

	computed: {
		displayedTracks() {
			return this.tracks.filter((track) => {
				return track.enabled
			})
		},
	},

	methods: {
	},
}
</script>

<style lang="scss" scoped>
// nothing
</style>
