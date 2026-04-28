<template>
	<MglGeoJsonSource source-id="heatmap-source" :data="geoJsonData">
		<MglHeatmapLayer layer-id="heatmap-layer" :paint="heatmapPaint" />
	</MglGeoJsonSource>
</template>

<script>
import { MglGeoJsonSource, MglHeatmapLayer } from '@indoorequal/vue-maplibre-gl'

export default {
	name: 'LHeatMap',
	components: {
		MglGeoJsonSource,
		MglHeatmapLayer,
	},

	props: {
		initialPoints: {
			type: Array,
			required: false,
			default() { return [] },
		},
		options: {
			type: Object,
			default() { return {} },
		},
	},

	computed: {
		geoJsonData() {
			return {
				type: 'FeatureCollection',
				features: this.initialPoints.map((p) => {
					const lat = Array.isArray(p) ? p[0] : p.lat
					const lng = Array.isArray(p) ? p[1] : p.lng
					const weight = Array.isArray(p) ? (p[2] || 1) : 1
					return {
						type: 'Feature',
						geometry: { type: 'Point', coordinates: [lng, lat] },
						properties: { weight },
					}
				}),
			}
		},
		heatmapPaint() {
			return {
				'heatmap-weight': ['get', 'weight'],
				'heatmap-intensity': this.options.minOpacity ?? 1,
				'heatmap-radius': this.options.radius ?? 25,
				'heatmap-opacity': 0.8,
			}
		},
	},
}
</script>
