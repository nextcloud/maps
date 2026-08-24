<!--
 - SPDX-FileCopyrightText: 2022 Arne Hamann <kontakt+github@arne.email>
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	Ported from Leaflet to vue-maplibre-gl, following the same clustered
	GL symbol layer approach as PhotosLayer.vue (sharing its preview-icon
	drawing via utils/photoMarker.ts). Not ported, on the same grounds as
	PhotosLayer.vue: spiderfying a cluster (no MapLibre equivalent - the
	right-click menu's "Display pictures"/"Zoom on bounds" cover the same
	need) and the chunked cluster loading progress events (MapLibre's
	clustering is synchronous and fast, so there's no "in progress" state
	left to report).

	Unlike PhotosLayer.vue, a marker's index is its position in the full,
	unfiltered photoSuggestions array - the sidebar tab's selection list
	and photoSuggestionsSelectedIndices are keyed against that same array,
	not against whatever subset is currently shown on the map.
-->
<template>
	<MglGeoJsonSource :source-id="SOURCE_ID"
		:data="geojson"
		:cluster="true"
		:cluster-radius="CLUSTER_RADIUS"
		:cluster-properties="CLUSTER_PROPERTIES">
		<MglSymbolLayer :layer-id="CLUSTER_LAYER_ID"
			:source="SOURCE_ID"
			:filter="['has', 'point_count']"
			:layout="{ 'icon-image': ['coalesce', ['get', 'iconId'], PHOTO_PREVIEW_PLACEHOLDER_ICON_ID], 'icon-allow-overlap': true, 'icon-size': 1 }" />
		<MglSymbolLayer :layer-id="CLUSTER_COUNT_LAYER_ID"
			:source="SOURCE_ID"
			:filter="['has', 'point_count']"
			:layout="{ 'text-field': ['case', ['>', ['get', 'selectedCount'], 0], ['concat', ['get', 'selectedCount'], '/', ['get', 'point_count_abbreviated']], ['get', 'point_count_abbreviated']], 'text-size': 12, 'text-allow-overlap': true, 'text-ignore-placement': true }"
			:paint="{ 'text-color': ['case', ['>', ['get', 'selectedCount'], 0], '#ffcc00', '#ffffff'], 'text-halo-color': '#000000', 'text-halo-width': 1 }" />
		<MglSymbolLayer :layer-id="POINT_LAYER_ID"
			:source="SOURCE_ID"
			:filter="['!', ['has', 'point_count']]"
			:layout="{ 'icon-image': ['get', 'iconId'], 'icon-allow-overlap': true, 'icon-size': 1 }" />
	</MglGeoJsonSource>
</template>

<script setup lang="ts">
import type { MapGeoJSONFeature, MapMouseEvent } from 'maplibre-gl'
import { Popup } from 'maplibre-gl'
import { translate as t } from '@nextcloud/l10n'
import { basename } from '@nextcloud/paths'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { MglGeoJsonSource, MglSymbolLayer, useMap } from 'vue-maplibre-gl'
import { binSearch } from '../../utils/common.js'
import { escapeHtml } from '../../utils/domUtils.ts'
import {
	ensurePhotoPreviewIcon,
	ensurePhotoPreviewPlaceholderIcon,
	getPhotoFormattedDate,
	getPhotoPreviewUrl,
	loadedPhotoPreviewIcons,
	PHOTO_PREVIEW_MARKER_SIZE,
	PHOTO_PREVIEW_PLACEHOLDER_ICON_ID,
	photoPreviewIconId,
} from '../../utils/photoMarker.ts'

export interface PhotoSuggestion {
	path: string
	fileId: number
	lat: number
	lng: number
	dateTaken?: number
	hasPreview?: boolean
	trackOrDeviceId: string
}

export interface TrackOrDevice {
	enabled: boolean
	visible: boolean
}

const props = withDefaults(defineProps<{
	photoSuggestions: PhotoSuggestion[]
	photoSuggestionsTracksAndDevices: Record<string, TrackOrDevice>
	photoSuggestionsSelectedIndices: number[]
	dateFilterEnabled: boolean
	dateFilterStart: number
	dateFilterEnd: number
	draggable?: boolean
}>(), {
	draggable: false,
})

const emit = defineEmits<{
	'open-sidebar': [path: string]
	'photo-suggestion-selected': [index: number]
	'photo-suggestion-moved': [index: number, latLng: { lat: number, lng: number }]
}>()

const SOURCE_ID = 'photo-suggestions'
const CLUSTER_LAYER_ID = 'photo-suggestions-clusters'
const CLUSTER_COUNT_LAYER_ID = 'photo-suggestions-cluster-count'
const POINT_LAYER_ID = 'photo-suggestions-points'
// Matches the old marker view size (40px) + old cluster radius margin (10px)
const CLUSTER_RADIUS = PHOTO_PREVIEW_MARKER_SIZE + 10
// Picks one representative child's iconId for each cluster (not necessarily the first one -
// supercluster aggregates bottom-up, so it's whichever leaf the reduction visits last), and
// sums how many of a cluster's children are currently selected.
const CLUSTER_PROPERTIES = {
	iconId: [['get', 'iconId'], ['get', 'iconId']],
	selectedCount: [['+', ['accumulated'], ['get', 'selectedCount']], ['case', ['get', 'selected'], 1, 0]],
}

const mapInstance = useMap()

interface VisibleSuggestion {
	photo: PhotoSuggestion
	index: number
}

const visibleSuggestions = computed<VisibleSuggestion[]>(() => {
	const suggestions = props.photoSuggestions
	const list: VisibleSuggestion[] = []

	const lastNullIndex = props.dateFilterEnabled ? binSearch(suggestions, (p: PhotoSuggestion) => !p?.dateTaken) : -1
	const firstShownIndex = props.dateFilterEnabled ? binSearch(suggestions, (p: PhotoSuggestion) => (p?.dateTaken || 0) < props.dateFilterStart) + 1 : 0
	const lastShownIndex = props.dateFilterEnabled ? binSearch(suggestions, (p: PhotoSuggestion) => (p?.dateTaken || 0) < props.dateFilterEnd) : suggestions.length - 1

	suggestions.forEach((photo, index) => {
		if (!photo) {
			return
		}
		if (props.dateFilterEnabled) {
			if (index > lastNullIndex && index < firstShownIndex) {
				return
			}
			if (index > lastShownIndex) {
				return
			}
		}
		const parentTod = props.photoSuggestionsTracksAndDevices[photo.trackOrDeviceId]
		if (!parentTod?.enabled) {
			return
		}
		list.push({ photo, index })
	})
	return list
})

/** Live position while a marker is being dragged, so it follows the cursor without a parent round-trip. */
const draggingOverride = ref<{ index: number, lat: number, lng: number } | null>(null)

const geojson = computed(() => ({
	type: 'FeatureCollection' as const,
	features: visibleSuggestions.value.map(({ photo, index }) => {
		const iconId = photoPreviewIconId(getPhotoPreviewUrl(photo))
		const dragged = draggingOverride.value?.index === index ? draggingOverride.value : null
		return {
			type: 'Feature' as const,
			geometry: { type: 'Point' as const, coordinates: [dragged?.lng ?? photo.lng, dragged?.lat ?? photo.lat] },
			properties: {
				index,
				iconId: loadedPhotoPreviewIcons.has(iconId) ? iconId : PHOTO_PREVIEW_PLACEHOLDER_ICON_ID,
				selected: props.photoSuggestionsSelectedIndices.includes(index),
			},
		}
	}),
}))

const usedPreviewUrls = computed<string[]>(() => {
	return [...new Set(visibleSuggestions.value.map(({ photo }) => getPhotoPreviewUrl(photo)))]
})

watch(() => mapInstance.isLoaded, (loaded) => {
	if (loaded && mapInstance.map) {
		ensurePhotoPreviewPlaceholderIcon(mapInstance.map)
	}
}, { immediate: true })

watch([() => mapInstance.isLoaded, usedPreviewUrls], () => {
	const map = mapInstance.map
	if (!mapInstance.isLoaded || !map) {
		return
	}
	usedPreviewUrls.value.forEach((url) => ensurePhotoPreviewIcon(map, url))
}, { immediate: true })

/**
 * @param photo the photo to open
 */
function viewPhoto(photo: PhotoSuggestion): void {
	if (window.OCA?.Viewer?.open) {
		window.OCA.Viewer.open({ path: photo.path, list: [photo] })
	}
}

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
	const photo = props.photoSuggestions[feature.properties.index]
	if (!photo) {
		return
	}
	map.getCanvas().style.cursor = props.draggable ? 'grab' : 'pointer'

	if (props.draggable) {
		// dragging is the priority interaction here - don't obscure the marker with a tooltip
		return
	}

	const html = document.createElement('div')
	html.className = 'tooltip-photo-wrapper'
	html.innerHTML = `
		<img class="photo-tooltip" src="${getPhotoPreviewUrl(photo)}">
		<p class="tooltip-photo-date">${escapeHtml(getPhotoFormattedDate(photo))}</p>
		<p class="tooltip-photo-name">${escapeHtml(basename(photo.path))}</p>
	`

	hoverPopup?.remove()
	hoverPopup = new Popup({ closeButton: false, closeOnClick: false, offset: 20 })
		.setLngLat((feature.geometry as { coordinates: [number, number] }).coordinates)
		.setDOMContent(html)
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
function onPointClick(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const feature = event.features?.[0]
	if (feature) {
		emit('photo-suggestion-selected', feature.properties.index)
	}
}

/**
 * @param event the underlying MapLibre mouse event
 */
function onClusterClick(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const feature = event.features?.[0]
	const map = mapInstance.map
	if (!feature || !map) {
		return
	}
	const source = map.getSource(SOURCE_ID)
	const clusterId = feature.properties.cluster_id
	const coordinates = (feature.geometry as { coordinates: [number, number] }).coordinates
	// @ts-expect-error getClusterExpansionZoom exists on GeoJSONSource but isn't in this narrowed type
	source?.getClusterExpansionZoom(clusterId).then((zoom: number) => {
		map.easeTo({ center: coordinates, zoom })
	}).catch(() => {})
}

let draggedIndex = -1

/**
 * @param event the underlying MapLibre mouse event
 */
function onPointMouseDown(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const map = mapInstance.map
	const feature = event.features?.[0]
	const index = feature?.properties.index
	if (!map || !props.draggable || index === undefined) {
		return
	}
	event.preventDefault()
	hoverPopup?.remove()
	draggedIndex = index
	map.dragPan.disable()
	map.getCanvas().style.cursor = 'grabbing'
	map.on('mousemove', onDragMove)
	map.on('mouseup', onDragEnd)
}

/**
 * @param event the underlying MapLibre mouse event
 */
function onDragMove(event: MapMouseEvent): void {
	if (draggedIndex === -1) {
		return
	}
	draggingOverride.value = { index: draggedIndex, lat: event.lngLat.lat, lng: event.lngLat.lng }
}

function onDragEnd(): void {
	const map = mapInstance.map
	map?.off('mousemove', onDragMove)
	map?.off('mouseup', onDragEnd)
	map?.dragPan.enable()
	if (map) {
		map.getCanvas().style.cursor = 'grab'
	}
	if (draggedIndex !== -1 && draggingOverride.value) {
		emit('photo-suggestion-moved', draggedIndex, { lat: draggingOverride.value.lat, lng: draggingOverride.value.lng })
	}
	draggedIndex = -1
	draggingOverride.value = null
}

let actionPopup: Popup | null = null

/**
 * @param event the underlying MapLibre mouse event
 */
function onPointContextMenu(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const map = mapInstance.map
	const feature = event.features?.[0]
	const photo = feature && props.photoSuggestions[feature.properties.index]
	if (!map || !feature || !photo) {
		return
	}
	event.preventDefault()

	const container = document.createElement('div')
	container.className = 'popup-photo-wrapper'
	container.innerHTML = `<button class="action-btn" data-action="display">${escapeHtml(t('maps', 'Display picture'))}</button>`
	container.addEventListener('click', (domEvent) => {
		const action = (domEvent.target as HTMLElement).getAttribute('data-action')
		if (action === 'display') {
			viewPhoto(photo)
		}
		actionPopup?.remove()
	})

	hoverPopup?.remove()
	actionPopup?.remove()
	actionPopup = new Popup({ closeButton: false, className: 'popovermenu open popupMarker photoPopup' })
		.setLngLat((feature.geometry as { coordinates: [number, number] }).coordinates)
		.setDOMContent(container)
		.addTo(map)
}

/**
 * @param event the underlying MapLibre mouse event
 */
function onClusterContextMenu(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const map = mapInstance.map
	const feature = event.features?.[0]
	const source = map?.getSource(SOURCE_ID)
	if (!map || !feature || !source) {
		return
	}
	event.preventDefault()
	const clusterId = feature.properties.cluster_id
	const pointCount = feature.properties.point_count
	const coordinates = (feature.geometry as { coordinates: [number, number] }).coordinates

	// @ts-expect-error getClusterLeaves exists on GeoJSONSource but isn't in this narrowed type
	source.getClusterLeaves(clusterId, pointCount, 0).then((leaves: MapGeoJSONFeature[]) => {
		const children = leaves
			.map((leaf) => ({ index: leaf.properties?.index, photo: props.photoSuggestions[leaf.properties?.index] }))
			.filter((c): c is { index: number, photo: PhotoSuggestion } => !!c.photo)
		if (children.length === 0) {
			return
		}

		const container = document.createElement('div')
		container.className = 'popup-photo-wrapper'
		container.innerHTML = `
			<button class="action-btn" data-action="selectall">${escapeHtml(t('maps', 'Select All'))}</button>
			<button class="action-btn" data-action="display">${escapeHtml(t('maps', 'Display pictures'))}</button>
			<button class="action-btn" data-action="zoom">${escapeHtml(t('maps', 'Zoom on bounds'))}</button>
		`
		container.addEventListener('click', (domEvent) => {
			const action = (domEvent.target as HTMLElement).getAttribute('data-action')
			if (action === 'selectall') {
				children.forEach((c) => emit('photo-suggestion-selected', c.index))
			} else if (action === 'display') {
				const photoList = children.map((c) => c.photo).sort((a, b) => (a.dateTaken ?? 0) - (b.dateTaken ?? 0))
				emit('open-sidebar', photoList[0].path)
				if (window.OCA?.Viewer?.open) {
					window.OCA.Viewer.open({ path: photoList[0].path, list: photoList })
				}
			} else if (action === 'zoom') {
				// @ts-expect-error getClusterExpansionZoom exists on GeoJSONSource but isn't in this narrowed type
				source.getClusterExpansionZoom(clusterId).then((zoom: number) => {
					map.easeTo({ center: coordinates, zoom })
				}).catch(() => {})
			}
			actionPopup?.remove()
		})

		actionPopup?.remove()
		actionPopup = new Popup({ closeOnClick: false, className: 'popovermenu open popupMarker photoPopup' })
			.setLngLat(coordinates)
			.setDOMContent(container)
			.addTo(map)
	}).catch(() => {})
}

function onClusterMouseEnter(): void {
	const map = mapInstance.map
	if (map) {
		map.getCanvas().style.cursor = 'pointer'
	}
}

function onClusterMouseLeave(): void {
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
	map.on('click', CLUSTER_LAYER_ID, onClusterClick)
	map.on('contextmenu', POINT_LAYER_ID, onPointContextMenu)
	map.on('contextmenu', CLUSTER_LAYER_ID, onClusterContextMenu)
	map.on('mousedown', POINT_LAYER_ID, onPointMouseDown)
	map.on('mouseenter', POINT_LAYER_ID, onPointMouseEnter)
	map.on('mouseleave', POINT_LAYER_ID, onPointMouseLeave)
	map.on('mouseenter', CLUSTER_LAYER_ID, onClusterMouseEnter)
	map.on('mouseleave', CLUSTER_LAYER_ID, onClusterMouseLeave)
}, { immediate: true })

onBeforeUnmount(() => {
	const map = mapInstance.map
	hoverPopup?.remove()
	actionPopup?.remove()
	if (!map || !listenersBound) {
		return
	}
	map.off('click', POINT_LAYER_ID, onPointClick)
	map.off('click', CLUSTER_LAYER_ID, onClusterClick)
	map.off('contextmenu', POINT_LAYER_ID, onPointContextMenu)
	map.off('contextmenu', CLUSTER_LAYER_ID, onClusterContextMenu)
	map.off('mousedown', POINT_LAYER_ID, onPointMouseDown)
	map.off('mouseenter', POINT_LAYER_ID, onPointMouseEnter)
	map.off('mouseleave', POINT_LAYER_ID, onPointMouseLeave)
	map.off('mouseenter', CLUSTER_LAYER_ID, onClusterMouseEnter)
	map.off('mouseleave', CLUSTER_LAYER_ID, onClusterMouseLeave)
	map.off('mousemove', onDragMove)
	map.off('mouseup', onDragEnd)
})
</script>
