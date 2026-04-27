<template>
	<div style="display: none;">
		<slot v-if="ready" />
	</div>
</template>

<script>
import 'leaflet.heat/dist/leaflet-heat.js'
import { inject, onMounted, onBeforeUnmount, watch } from 'vue'
import { InjectionKeys } from '@vue-leaflet/vue-leaflet'
const { AddLayerInjection, RemoveLayerInjection } = InjectionKeys

export default {
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
	emits: ['ready'],
	setup(props, { emit }) {
		const addLayer = inject(AddLayerInjection)
		const removeLayer = inject(RemoveLayerInjection)

		let mapObject = null
		let points = [...props.initialPoints]

		watch(() => props.options, (newOptions) => {
			if (mapObject) mapObject.setOptions(newOptions)
		}, { deep: true })

		watch(() => props.initialPoints, (newPoints) => {
			points = newPoints
			if (mapObject) mapObject.setLatLngs(newPoints)
		}, { deep: true })

		onMounted(() => {
			mapObject = L.heatLayer(points, props.options)
			if (addLayer) addLayer({ mapObject })
			emit('ready', mapObject)
		})

		onBeforeUnmount(() => {
			if (removeLayer && mapObject) removeLayer({ mapObject })
		})

		return {
			addLatLng(latlng) { mapObject?.addLatLng(latlng) },
			setLatLngs(latlngs) { mapObject?.setLatLngs(latlngs) },
			redraw() { mapObject?.redraw() },
		}
	},
}
</script>
