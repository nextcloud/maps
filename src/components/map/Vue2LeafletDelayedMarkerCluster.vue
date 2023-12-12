<template>
	<div style="display: none;">
		<slot v-if="ready" />
	</div>
</template>

<script>
import { MarkerClusterGroup } from 'leaflet.markercluster'
import { findRealParent, propsBinder } from 'vue2-leaflet'
import { DomEvent } from 'leaflet'

const props = {
	options: {
		type: Object,
		default() { return {} },
	},
	delay: {
		type: Number,
		default: 1,
	},
}

export default {
	props,
	data() {
		return {
			ready: false,
		}
	},
	mounted() {
		this.lastChange = Date.now()
		this.addLayerCache = {}
		this.removeLayerCache = {}
		this.caching = false
		this.mapObject = new MarkerClusterGroup(this.options)
		DomEvent.on(this.mapObject, this.$listeners)
		propsBinder(this, this.mapObject, props)
		this.ready = true
		this.parentContainer = findRealParent(this.$parent)
		this.parentContainer.addLayer(this)
		this.$nextTick(() => {
			this.$emit('ready', this.mapObject)
		})
	},
	beforeDestroy() {
		this.parentContainer.removeLayer(this)
	},
	methods: {
		addLayer(layer, alreadyAdded) {
			if (!alreadyAdded) {
				this.lastChange = Date.now()
				delete this.removeLayerCache[layer._uid]
				this.addLayerCache[layer._uid] = layer.mapObject
				if (!this.caching) {
					this.scheduleUpdate()
				}
			}
		},
		removeLayer(layer, alreadyRemoved) {
			if (!alreadyRemoved) {
				this.lastChange = Date.now()
				delete this.addLayerCache[layer._uid]
				this.removeLayerCache[layer._uid] = layer.mapObject
				if (!this.caching) {
					this.scheduleUpdate()
				}
			}
		},
		scheduleUpdate() {
			setTimeout(function() {
				this.update()
			}.bind(this),
			this.delay + 1,
			)
		},
		update() {
			if ((Date.now() - this.lastChange) >= this.delay) {
				this.mapObject.addLayers(
					Object.values(this.addLayerCache),
				)
				this.mapObject.removeLayers(
					Object.values(this.removeLayerCache),
				)
				this.caching = false
				this.addLayerCache = {}
				this.removeLayerCache = {}
			} else {
				this.scheduleUpdate()
			}
		},
	},
}
</script>
