<template>
	<div style="display: none;">
		<slot v-if="ready" />
	</div>
</template>

<script>
import { MarkerClusterGroup } from 'leaflet.markercluster'
import { inject, onMounted, onBeforeUnmount, ref, provide } from 'vue'
import { InjectionKeys } from '@vue-leaflet/vue-leaflet'
const { AddLayerInjection, RemoveLayerInjection } = InjectionKeys

export default {
	props: {
		options: {
			type: Object,
			default() { return {} },
		},
		delay: {
			type: Number,
			default: 1,
		},
	},
	emits: ['ready'],
	setup(props, { emit }) {
		const addLayer = inject(AddLayerInjection)
		const removeLayer = inject(RemoveLayerInjection)
		const ready = ref(false)

		let mapObject = null
		let lastChange = Date.now()
		let addLayerCache = {}
		let removeLayerCache = {}
		let caching = false

		function scheduleUpdate() {
			setTimeout(() => update(), props.delay + 1)
		}

		function update() {
			if ((Date.now() - lastChange) >= props.delay) {
				mapObject.addLayers(Object.values(addLayerCache))
				mapObject.removeLayers(Object.values(removeLayerCache))
				caching = false
				addLayerCache = {}
				removeLayerCache = {}
			} else {
				scheduleUpdate()
			}
		}

		function addChildLayer({ leafletObject }) {
			if (!leafletObject) return
			const id = leafletObject._leaflet_id
			lastChange = Date.now()
			delete removeLayerCache[id]
			addLayerCache[id] = leafletObject
			if (!caching) scheduleUpdate()
		}

		function removeChildLayer({ leafletObject }) {
			if (!leafletObject) return
			const id = leafletObject._leaflet_id
			lastChange = Date.now()
			delete addLayerCache[id]
			removeLayerCache[id] = leafletObject
			if (!caching) scheduleUpdate()
		}

		provide(AddLayerInjection, addChildLayer)
		provide(RemoveLayerInjection, removeChildLayer)

		onMounted(() => {
			mapObject = new MarkerClusterGroup(props.options)
			ready.value = true
			if (addLayer) addLayer({ leafletObject: mapObject })
			emit('ready', mapObject)
		})

		onBeforeUnmount(() => {
			if (removeLayer && mapObject) removeLayer({ leafletObject: mapObject })
		})

		return { ready }
	},
}
</script>
