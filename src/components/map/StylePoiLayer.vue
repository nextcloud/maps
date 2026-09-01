<!--
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	Makes the base style's own POI icons (restaurants, shops, etc. -
	rendered by the style itself, not by anything in this app) clickable,
	the same way search results are: a popup with a name and an "Add to
	favorites" action. The lookup itself lives in utils/stylePoi.ts,
	shared with Map.vue's click-anywhere-to-search handler (a click on
	one of these icons opens this popup instead of a search one).

	Not ported (doesn't apply): drag-to-edit, right-click menu - these
	are the base style's own decorative markers, not app-owned data.
-->
<template>
	<div />
</template>

<script setup lang="ts">
import type { MapGeoJSONFeature, MapMouseEvent, Point } from 'maplibre-gl'
import { Popup } from 'maplibre-gl'
import { translate as t } from '@nextcloud/l10n'
import { onBeforeUnmount, watch } from 'vue'
import { useMap } from 'vue-maplibre-gl'
import { escapeHtml } from '../../utils/domUtils.ts'
import { stylePoiFeatureAt } from '../../utils/stylePoi.ts'

const emit = defineEmits<{
	'add-favorite': [favorite: { latLng: { lat: number, lng: number }, name: string, formattedAddress: string }]
}>()

const mapInstance = useMap()

/**
 * @param point pixel coordinates within the map viewport
 */
function poiFeatureAt(point: Point): MapGeoJSONFeature | undefined {
	const map = mapInstance.map
	return map ? stylePoiFeatureAt(map, point) : undefined
}

/**
 * @param feature the base style's own poi feature
 */
function nameOf(feature: MapGeoJSONFeature): string {
	const props = feature.properties
	return props.name_en || props.name || props.subclass || props.class || t('maps', 'Unnamed place')
}

let actionPopup: Popup | null = null

/**
 * @param event the underlying MapLibre mouse event
 */
function onClick(event: MapMouseEvent): void {
	const map = mapInstance.map
	const feature = poiFeatureAt(event.point)
	if (!map || !feature) {
		return
	}
	const name = nameOf(feature)

	const container = document.createElement('div')
	container.className = 'popup-poi-wrapper'
	container.innerHTML = `
		<h3 class="poi-header">${escapeHtml(name)}</h3>
		<button class="poi-add-favorite" data-action="add-favorite">${escapeHtml(t('maps', 'Add to favorites'))}</button>
	`
	container.addEventListener('click', (domEvent) => {
		if ((domEvent.target as HTMLElement).getAttribute('data-action') === 'add-favorite') {
			emit('add-favorite', { latLng: event.lngLat, name, formattedAddress: name })
			actionPopup?.remove()
		}
	})

	actionPopup?.remove()
	actionPopup = new Popup({ closeButton: true, className: 'popovermenu open popupMarker poiPopup' })
		.setLngLat((feature.geometry as { coordinates: [number, number] }).coordinates)
		.setDOMContent(container)
		.addTo(map)
}

/**
 * @param event the underlying MapLibre mouse event
 */
function onMouseMove(event: MapMouseEvent): void {
	const map = mapInstance.map
	if (map) {
		map.getCanvas().style.cursor = poiFeatureAt(event.point) ? 'pointer' : ''
	}
}

let listenersBound = false

watch(() => mapInstance.isLoaded, (loaded) => {
	const map = mapInstance.map
	if (!loaded || !map || listenersBound) {
		return
	}
	listenersBound = true
	map.on('click', onClick)
	map.on('mousemove', onMouseMove)
}, { immediate: true })

onBeforeUnmount(() => {
	const map = mapInstance.map
	actionPopup?.remove()
	if (!map || !listenersBound) {
		return
	}
	map.off('click', onClick)
	map.off('mousemove', onMouseMove)
})
</script>
