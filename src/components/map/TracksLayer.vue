<template>
	<template>
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
	</template>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import TrackLayer from './TrackLayer.vue'
import TrackHoverMarker from './TrackHoverMarker.vue'
import moment from '@nextcloud/moment'

const props = defineProps({
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
		default: 0,
	},
	end: {
		type: Number,
		default: () => moment().unix(),
	},
})

defineEmits(['click', 'add-to-map-track', 'change-color', 'display-elevation'])

const hoverPoint = ref(null)

const displayedTracks = computed(() => props.tracks.filter((track) => track.enabled))

watch(() => props.tracks, () => {
	hoverPoint.value = null
}, { deep: true })

function onPointHover(point) {
	hoverPoint.value = point
}
</script>
