<!--
 - SPDX-FileCopyrightText: 2019 Paul Schwörer <hello@paulschwoerer.de>
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<MglGeoJsonSource :source-id="SOURCE_ID" :data="geojson">
		<MglLineLayer :layer-id="LINE_CASING_LAYER_ID"
			:source="SOURCE_ID"
			:filter="['==', ['geometry-type'], 'LineString']"
			:paint="{ 'line-color': '#000000', 'line-width': 6.4 }" />
		<MglLineLayer :layer-id="LINE_LAYER_ID"
			:source="SOURCE_ID"
			:filter="['==', ['geometry-type'], 'LineString']"
			:paint="{ 'line-color': ['get', 'color'], 'line-width': 4 }" />
		<MglSymbolLayer :layer-id="POINT_LAYER_ID"
			:source="SOURCE_ID"
			:filter="['==', ['geometry-type'], 'Point']"
			:layout="{ 'icon-image': ['get', 'iconId'], 'icon-allow-overlap': true, 'icon-size': 1 }" />
	</MglGeoJsonSource>
</template>

<script setup lang="ts">
import type { MapGeoJSONFeature, MapMouseEvent } from 'maplibre-gl'
import { Popup } from 'maplibre-gl'
import { translate as t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, reactive, watch } from 'vue'
import { MglGeoJsonSource, MglLineLayer, MglSymbolLayer, useMap } from 'vue-maplibre-gl'
import { isComputer } from '../../utils.js'
import { binSearch, isPublic } from '../../utils/common.js'
import { escapeHtml } from '../../utils/domUtils.ts'
import optionsController from '../../optionsController.js'

export interface DevicePoint {
	lat: number
	lng: number
	timestamp?: number
}

export interface Device {
	id: number
	user_agent: string
	color?: string
	enabled: boolean
	historyEnabled: boolean
	points: DevicePoint[]
}

const props = withDefaults(defineProps<{
	devices: Device[]
	start?: number
	end?: number
}>(), {
	start: 0,
	end: 0,
})

const emit = defineEmits<{
	click: [device: Device]
	'toggle-history': [device: Device]
	'change-color': [device: Device]
	export: [device: Device]
	'add-to-map-device': [device: Device]
}>()

const SOURCE_ID = 'devices'
const LINE_CASING_LAYER_ID = 'devices-lines-casing'
const LINE_LAYER_ID = 'devices-lines-fg'
const POINT_LAYER_ID = 'devices-points'
const MARKER_SIZE = 40
const DEFAULT_COLOR = '0082c9'

const mapInstance = useMap()
const loadedIcons = reactive(new Set<string>())

const displayedDevices = computed<Device[]>(() => {
	return props.devices.filter((d) => d.enabled && d.points?.length > 0)
})

/**
 * @param device the device to compute visible history points for
 */
function getDevicePoints(device: Device): DevicePoint[] {
	const lastNullIndex = binSearch(device.points, (p: DevicePoint) => !p.timestamp)
	const firstShownIndex = binSearch(device.points, (p: DevicePoint) => (p.timestamp || 0) < props.start) + 1
	const lastShownIndex = binSearch(device.points, (p: DevicePoint) => (p.timestamp || 0) < props.end)
	return [
		...device.points.slice(0, lastNullIndex + 1),
		...device.points.slice(firstShownIndex, lastShownIndex + 1),
	]
}

/**
 * @param color hex color (without '#')
 * @param isComputerDevice whether to draw the desktop or phone glyph
 */
function iconIdForDevice(color: string, isComputerDevice: boolean): string {
	return `device-marker-${color}-${isComputerDevice ? 'desktop' : 'phone'}`
}

const geojson = computed(() => {
	const features: object[] = []
	displayedDevices.value.forEach((device, deviceIndex) => {
		const color = device.color || DEFAULT_COLOR
		const points = getDevicePoints(device)
		if (points.length === 0) {
			return
		}

		if (device.historyEnabled && points.length >= 2) {
			features.push({
				type: 'Feature' as const,
				geometry: { type: 'LineString' as const, coordinates: points.map((p) => [p.lng, p.lat]) },
				properties: { deviceIndex, color },
			})
		}

		const lastPoint = points[points.length - 1]
		features.push({
			type: 'Feature' as const,
			geometry: { type: 'Point' as const, coordinates: [lastPoint.lng, lastPoint.lat] },
			properties: {
				deviceIndex,
				iconId: iconIdForDevice(color, isComputer(device.user_agent)),
				userAgent: device.user_agent,
			},
		})
	})
	return { type: 'FeatureCollection' as const, features }
})

const usedIcons = computed<Array<{ color: string, isComputerDevice: boolean }>>(() => {
	const seen = new Map<string, { color: string, isComputerDevice: boolean }>()
	displayedDevices.value.forEach((device) => {
		const isComputerDevice = isComputer(device.user_agent)
		const color = device.color || DEFAULT_COLOR
		seen.set(iconIdForDevice(color, isComputerDevice), { color, isComputerDevice })
	})
	return [...seen.values()]
})

/**
 * @param color hex color (without '#') for the circle background
 * @param isComputerDevice whether to draw the desktop or phone glyph
 */
function ensureIcon(color: string, isComputerDevice: boolean): void {
	const id = iconIdForDevice(color, isComputerDevice)
	const map = mapInstance.map
	if (!map || loadedIcons.has(id) || map.hasImage(id)) {
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
	ctx.fillStyle = `#${color}`
	ctx.fill()
	ctx.lineWidth = 2
	ctx.strokeStyle = '#ffffff'
	ctx.stroke()

	ctx.fillStyle = '#ffffff'
	if (isComputerDevice) {
		// simple monitor glyph
		ctx.fillRect(size * 0.28, size * 0.32, size * 0.44, size * 0.28)
		ctx.fillRect(size * 0.42, size * 0.62, size * 0.16, size * 0.08)
	} else {
		// simple phone glyph
		const radius = size * 0.05
		const x = size * 0.36
		const y = size * 0.24
		const w = size * 0.28
		const h = size * 0.52
		ctx.beginPath()
		ctx.roundRect(x, y, w, h, radius)
		ctx.fill()
	}

	map.addImage(id, ctx.getImageData(0, 0, size, size))
	loadedIcons.add(id)
}

watch([() => mapInstance.isLoaded, usedIcons], () => {
	if (!mapInstance.isLoaded) {
		return
	}
	usedIcons.value.forEach(({ color, isComputerDevice }) => ensureIcon(color, isComputerDevice))
}, { immediate: true })

let hoverPopup: Popup | null = null

/**
 * @param event the underlying MapLibre mouse event
 */
function onPointMouseEnter(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const map = mapInstance.map
	const feature = event.features?.[0]
	if (!map || !feature) {
		return
	}
	map.getCanvas().style.cursor = 'pointer'

	const container = document.createElement('div')
	container.className = 'tooltip-device-wrapper'
	container.innerHTML = `<b>${t('maps', 'Name')}:</b> <span>${escapeHtml(feature.properties.userAgent)}</span>`

	hoverPopup?.remove()
	hoverPopup = new Popup({ closeButton: false, closeOnClick: false, offset: 12 })
		.setLngLat((feature.geometry as { coordinates: [number, number] }).coordinates)
		.setDOMContent(container)
		.addTo(map)
}

function onPointMouseLeave(): void {
	const map = mapInstance.map
	if (map) {
		map.getCanvas().style.cursor = ''
	}
	hoverPopup?.remove()
	hoverPopup = null
}

/**
 * @param event the underlying MapLibre mouse event
 */
function onFeatureClick(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const feature = event.features?.[0]
	if (!feature) {
		return
	}
	const device = displayedDevices.value[feature.properties.deviceIndex]
	if (device) {
		emit('click', device)
	}
}

let actionPopup: Popup | null = null

/**
 * @param event the underlying MapLibre mouse event
 */
function onFeatureContextMenu(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const map = mapInstance.map
	const feature = event.features?.[0]
	const device = feature && displayedDevices.value[feature.properties.deviceIndex]
	if (!map || !feature || !device) {
		return
	}
	event.preventDefault()
	const mapIsUpdatable = !!optionsController.optionValues?.isUpdateable

	const container = document.createElement('div')
	container.className = 'popup-device-wrapper'
	container.innerHTML = `
		<button class="action-btn" data-action="history">${escapeHtml(device.historyEnabled ? t('maps', 'Hide history') : t('maps', 'Show history'))}</button>
		${mapIsUpdatable ? `<button class="action-btn" data-action="color">${escapeHtml(t('maps', 'Change color'))}</button>` : ''}
		<button class="action-btn" data-action="export">${escapeHtml(t('maps', 'Export'))}</button>
		${!isPublic() ? `<button class="action-btn" data-action="link">${escapeHtml(t('maps', 'Link to map'))}</button>` : ''}
	`
	container.addEventListener('click', (domEvent) => {
		const action = (domEvent.target as HTMLElement).getAttribute('data-action')
		if (action === 'history') {
			emit('toggle-history', device)
		} else if (action === 'color') {
			emit('change-color', device)
		} else if (action === 'export') {
			emit('export', device)
		} else if (action === 'link') {
			emit('add-to-map-device', device)
		}
		actionPopup?.remove()
	})

	hoverPopup?.remove()
	actionPopup?.remove()
	actionPopup = new Popup({ closeButton: false, className: 'popovermenu open popupMarker devicePopup' })
		.setLngLat(event.lngLat)
		.setDOMContent(container)
		.addTo(map)
}

function onLineMouseEnter(): void {
	const map = mapInstance.map
	if (map) {
		map.getCanvas().style.cursor = 'pointer'
	}
}

function onLineMouseLeave(): void {
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
	map.on('click', LINE_CASING_LAYER_ID, onFeatureClick)
	map.on('click', POINT_LAYER_ID, onFeatureClick)
	map.on('contextmenu', LINE_CASING_LAYER_ID, onFeatureContextMenu)
	map.on('contextmenu', POINT_LAYER_ID, onFeatureContextMenu)
	map.on('mouseenter', LINE_CASING_LAYER_ID, onLineMouseEnter)
	map.on('mouseleave', LINE_CASING_LAYER_ID, onLineMouseLeave)
	map.on('mouseenter', POINT_LAYER_ID, onPointMouseEnter)
	map.on('mouseleave', POINT_LAYER_ID, onPointMouseLeave)
}, { immediate: true })

onBeforeUnmount(() => {
	const map = mapInstance.map
	hoverPopup?.remove()
	actionPopup?.remove()
	if (!map || !listenersBound) {
		return
	}
	map.off('click', LINE_CASING_LAYER_ID, onFeatureClick)
	map.off('click', POINT_LAYER_ID, onFeatureClick)
	map.off('contextmenu', LINE_CASING_LAYER_ID, onFeatureContextMenu)
	map.off('contextmenu', POINT_LAYER_ID, onFeatureContextMenu)
	map.off('mouseenter', LINE_CASING_LAYER_ID, onLineMouseEnter)
	map.off('mouseleave', LINE_CASING_LAYER_ID, onLineMouseLeave)
	map.off('mouseenter', POINT_LAYER_ID, onPointMouseEnter)
	map.off('mouseleave', POINT_LAYER_ID, onPointMouseLeave)
})
</script>

<style lang="scss">
.tooltip-device-wrapper {
	padding: 6px;
	border-radius: 3px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
}

.popup-device-wrapper .action-btn {
	display: block;
	width: 100%;
	padding: 8px;
	border: none;
	background: transparent;
	text-align: left;
	cursor: pointer;

	&:hover {
		background: var(--color-background-hover);
	}
}
</style>
