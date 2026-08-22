<!--
 - SPDX-FileCopyrightText: 2019 Paul Schwörer <hello@paulschwoerer.de>
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	Ported from Leaflet (PoiMarker.vue) to vue-maplibre-gl. Renders
	Nominatim search results as markers. Unlike the old PoiMarker.vue,
	all results share one generic pin icon rather than Nominatim's
	per-category icon images - avoids an extra per-poi image fetch and
	CSP allowance for a purely cosmetic difference.

	Not ported: "Add contact address" - it depended on a "place an
	existing contact by address" mode that isn't implemented yet in the
	new architecture. Only "Add to favorites" survived.
-->
<template>
	<MglGeoJsonSource :source-id="SOURCE_ID" :data="geojson">
		<MglSymbolLayer :layer-id="POINT_LAYER_ID"
			:source="SOURCE_ID"
			:layout="{ 'icon-image': ICON_ID, 'icon-allow-overlap': true, 'icon-size': 1 }" />
	</MglGeoJsonSource>
</template>

<script setup lang="ts">
import type { MapGeoJSONFeature, MapMouseEvent } from 'maplibre-gl'
import { Popup } from 'maplibre-gl'
import { translate as t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, reactive, watch } from 'vue'
import { MglGeoJsonSource, MglSymbolLayer, useMap } from 'vue-maplibre-gl'
import { formatAddress } from '../../utils.js'
import { escapeHtml } from '../../utils/domUtils.ts'

export interface Poi {
	lat: string
	lon: string
	place_id: number
	display_name: string
	address: Record<string, string>
	namedetails?: Record<string, string>
}

const props = defineProps<{
	pois: Poi[]
}>()

const emit = defineEmits<{
	'add-favorite': [favorite: { latLng: { lat: number, lng: number }, name: string, formattedAddress: string }]
}>()

const SOURCE_ID = 'search-pois'
const POINT_LAYER_ID = 'search-pois-points'
const ICON_ID = 'poi-marker'
const MARKER_SIZE = 32

const mapInstance = useMap()

/**
 * @param poi the Nominatim result to describe
 */
function road(poi: Poi): string {
	const r = poi.address?.road || ''
	return r && poi.address?.house_number ? `${r} ${poi.address.house_number}` : r
}

/**
 * @param poi the Nominatim result to describe
 */
function city(poi: Poi): string {
	return poi.address?.city || poi.address?.town || poi.address?.village || ''
}

/**
 * @param poi the Nominatim result to describe
 */
function header(poi: Poi): string {
	return poi.namedetails?.name || road(poi) || city(poi)
}

/**
 * @param poi the Nominatim result to describe
 */
function description(poi: Poi): string {
	const parts = []
	if (poi.namedetails?.name && road(poi)) {
		parts.push(road(poi))
	}
	if (poi.address?.postcode) {
		parts.push(poi.address.postcode)
	}
	if (city(poi)) {
		parts.push(city(poi))
	}
	return parts.join(', ')
}

const geojson = computed(() => ({
	type: 'FeatureCollection' as const,
	features: props.pois.map((poi, poiIndex) => ({
		type: 'Feature' as const,
		geometry: { type: 'Point' as const, coordinates: [parseFloat(poi.lon), parseFloat(poi.lat)] },
		properties: { poiIndex },
	})),
}))

const loadedIcons = reactive(new Set<string>())

function ensureIcon(): void {
	const map = mapInstance.map
	if (!map || loadedIcons.has(ICON_ID) || map.hasImage(ICON_ID)) {
		return
	}
	const size = MARKER_SIZE
	const canvas = document.createElement('canvas')
	canvas.width = size
	canvas.height = size
	const ctx = canvas.getContext('2d')
	if (!ctx) {
		return
	}

	ctx.beginPath()
	ctx.arc(size / 2, size / 2, size / 2 - 2, 0, Math.PI * 2)
	ctx.fillStyle = '#e9762b'
	ctx.fill()
	ctx.lineWidth = 2
	ctx.strokeStyle = '#ffffff'
	ctx.stroke()

	ctx.beginPath()
	ctx.arc(size / 2, size / 2, size * 0.12, 0, Math.PI * 2)
	ctx.fillStyle = '#ffffff'
	ctx.fill()

	map.addImage(ICON_ID, ctx.getImageData(0, 0, size, size))
	loadedIcons.add(ICON_ID)
}

watch(() => mapInstance.isLoaded, (loaded) => {
	if (loaded) {
		ensureIcon()
	}
}, { immediate: true })

let actionPopup: Popup | null = null

/**
 * @param poi the clicked Nominatim result
 * @param coordinates the point's [lng, lat]
 */
function openPopup(poi: Poi, coordinates: [number, number]): void {
	const map = mapInstance.map
	if (!map) {
		return
	}
	const container = document.createElement('div')
	container.className = 'popup-poi-wrapper'
	container.innerHTML = `
		<h3 class="poi-header">${escapeHtml(header(poi))}</h3>
		<p class="poi-desc">${escapeHtml(description(poi))}</p>
		<button class="poi-add-favorite" data-action="add-favorite">${escapeHtml(t('maps', 'Add to favorites'))}</button>
	`
	container.addEventListener('click', (domEvent) => {
		if ((domEvent.target as HTMLElement).getAttribute('data-action') === 'add-favorite') {
			emit('add-favorite', {
				latLng: { lat: parseFloat(poi.lat), lng: parseFloat(poi.lon) },
				name: header(poi),
				formattedAddress: formatAddress(poi.address),
			})
			actionPopup?.remove()
		}
	})

	actionPopup?.remove()
	actionPopup = new Popup({ closeButton: true, className: 'popovermenu open popupMarker poiPopup' })
		.setLngLat(coordinates)
		.setDOMContent(container)
		.addTo(map)
}

/**
 * @param event the underlying MapLibre mouse event
 */
function onPointClick(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const feature = event.features?.[0]
	const poi = feature && props.pois[feature.properties.poiIndex]
	if (!poi) {
		return
	}
	openPopup(poi, (feature.geometry as { coordinates: [number, number] }).coordinates)
}

function onPointMouseEnter(): void {
	const map = mapInstance.map
	if (map) {
		map.getCanvas().style.cursor = 'pointer'
	}
}

function onPointMouseLeave(): void {
	const map = mapInstance.map
	if (map) {
		map.getCanvas().style.cursor = ''
	}
}

let listenersBound = false

watch(() => mapInstance.isLoaded, (loaded) => {
	const map = mapInstance.map
	if (!loaded || !map || listenersBound) {
		return
	}
	listenersBound = true
	map.on('click', POINT_LAYER_ID, onPointClick)
	map.on('mouseenter', POINT_LAYER_ID, onPointMouseEnter)
	map.on('mouseleave', POINT_LAYER_ID, onPointMouseLeave)
}, { immediate: true })

onBeforeUnmount(() => {
	const map = mapInstance.map
	actionPopup?.remove()
	if (!map || !listenersBound) {
		return
	}
	map.off('click', POINT_LAYER_ID, onPointClick)
	map.off('mouseenter', POINT_LAYER_ID, onPointMouseEnter)
	map.off('mouseleave', POINT_LAYER_ID, onPointMouseLeave)
})
</script>

<style lang="scss">
.popup-poi-wrapper {
	padding: 8px;
	max-width: 220px;

	.poi-header {
		margin: 0 0 4px 0;
	}

	.poi-desc {
		margin: 0 0 8px 0;
		color: var(--color-text-maxcontrast);
	}

	.poi-add-favorite {
		width: 100%;
	}
}
</style>
