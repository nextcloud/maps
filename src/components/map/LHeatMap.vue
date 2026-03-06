<template>
	<div style="display: none;"></div>
</template>

<script>
import L from 'leaflet'
import 'leaflet.heat/dist/leaflet-heat.js'

export default {
	name: 'LHeatMap',
	props: {
		map: {
			type: Object,
			required: true,
		},
		initialPoints: {
			type: Array,
			required: false,
			default: () => [],
		},
		options: {
			type: Object,
			default: () => ({}),
		},
	},
	data() {
		return {
			ready: false,
		}
	},
	watch: {
		options: {
			handler(newOptions) {
				if (this.heatLayer) {
					this.heatLayer.setOptions(newOptions)
				}
			},
			deep: true,
		},
		initialPoints: {
			handler(newPoints) {
				if (this.heatLayer) {
					this.heatLayer.setLatLngs(newPoints)
				}
			},
			deep: true,
		},
	},
	created() {
		this.heatLayer = null; // Non-reactive leaflet object
	},
	mounted() {
		this.heatLayer = L.heatLayer(this.initialPoints, this.options)
		this.heatLayer.addTo(this.map)
		this.ready = true
		this.$nextTick(() => {
			this.$emit('ready', this.heatLayer)
		})
	},
	beforeDestroy() {
		if (this.heatLayer && this.map) {
			this.map.removeLayer(this.heatLayer)
		}
	},
	methods: {
		addLatLng(latlng) {
			if (this.heatLayer) this.heatLayer.addLatLng(latlng)
		},
		setLatLngs(latlngs) {
			if (this.heatLayer) this.heatLayer.setLatLngs(latlngs)
		},
		redraw() {
			if (this.heatLayer) this.heatLayer.redraw()
		},
	},
}
</script>