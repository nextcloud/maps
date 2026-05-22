<template>
	<MglGeoJsonSource source-id="heatmap-source" :data="geoJsonData">
		<MglHeatmapLayer layer-id="heatmap-layer" :paint="heatmapPaint" />
	</MglGeoJsonSource>
</template>

<script setup>
import { computed } from 'vue'
import { MglGeoJsonSource, MglHeatmapLayer } from '@indoorequal/vue-maplibre-gl'

const props = defineProps({
	initialPoints: {
		type: Array,
		default: () => [],
	},
	options: {
		type: Object,
		default: () => ({}),
	},
})

const geoJsonData = computed(() => ({
	type: 'FeatureCollection',
	features: props.initialPoints.map((p) => {
		const lat = Array.isArray(p) ? p[0] : p.lat
		const lng = Array.isArray(p) ? p[1] : p.lng
		const weight = Array.isArray(p) ? (p[2] || 1) : 1
		return {
			type: 'Feature',
			geometry: { type: 'Point', coordinates: [lng, lat] },
			properties: { weight },
		}
	}),
}))

const heatmapPaint = computed(() => ({
	'heatmap-weight': ['get', 'weight'],
	'heatmap-intensity': props.options.minOpacity ?? 1,
	'heatmap-radius': props.options.radius ?? 25,
	'heatmap-opacity': 0.8,
}))
</script>
