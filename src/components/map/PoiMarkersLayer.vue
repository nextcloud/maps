<!--
 - SPDX-FileCopyrightText: 2019 Paul Schwörer <hello@paulschwoerer.de>
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	Search result markers, using the base style's sprite icon named after
	the result's OSM type, or a generic pin.
-->
<template>
	<MglGeoJsonSource :source-id="SOURCE_ID" :data="geojson">
		<MglSymbolLayer :layer-id="POINT_LAYER_ID"
			:source="SOURCE_ID"
			:filter="['==', ['geometry-type'], 'Point']"
			:layout="{ 'icon-image': ['get', 'icon'], 'icon-anchor': ['get', 'iconAnchor'], 'icon-allow-overlap': true, 'icon-size': 1.5 }" />
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
	/** The OSM tag value, e.g. "restaurant" - matches a sprite icon name when the base style has one. */
	type?: string
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
const MARKER_WIDTH = 32
const MARKER_HEIGHT = 42

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

/**
 * @param poi the Nominatim result to pick an icon for
 */
function iconFor(poi: Poi): { icon: string, iconAnchor: 'center' | 'bottom' } {
	if (poi.type && mapInstance.map?.hasImage(poi.type)) {
		return { icon: poi.type, iconAnchor: 'center' }
	}
	return { icon: ICON_ID, iconAnchor: 'bottom' }
}

const geojson = computed(() => ({
	type: 'FeatureCollection' as const,
	features: props.pois.map((poi, poiIndex) => ({
		type: 'Feature' as const,
		geometry: { type: 'Point' as const, coordinates: [parseFloat(poi.lon), parseFloat(poi.lat)] },
		properties: { poiIndex, ...iconFor(poi) },
	})),
}))

const loadedIcons = reactive(new Set<string>())

function ensureIcon(): void {
	const map = mapInstance.map
	if (!map || loadedIcons.has(ICON_ID) || map.hasImage(ICON_ID)) {
		return
	}
	const width = MARKER_WIDTH
	const height = MARKER_HEIGHT
	const canvas = document.createElement('canvas')
	canvas.width = width
	canvas.height = height
	const ctx = canvas.getContext('2d')
	if (!ctx) {
		return
	}

	// Teardrop pin: a circular head narrowing to a point at the bottom,
	// which sits at the marker's coordinate (icon-anchor: bottom).
	const cx = width / 2
	const cy = width / 2
	const r = width / 2 - 2
	ctx.beginPath()
	ctx.arc(cx, cy, r, Math.PI * 0.75, Math.PI * 0.25)
	ctx.lineTo(cx, height - 2)
	ctx.closePath()
	ctx.fillStyle = '#e9762b'
	ctx.fill()
	ctx.lineWidth = 2
	ctx.strokeStyle = '#ffffff'
	ctx.stroke()

	ctx.beginPath()
	ctx.arc(cx, cy, r * 0.4, 0, Math.PI * 2)
	ctx.fillStyle = '#ffffff'
	ctx.fill()

	map.addImage(ICON_ID, ctx.getImageData(0, 0, width, height))
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
