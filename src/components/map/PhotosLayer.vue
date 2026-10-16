<!--
 - SPDX-FileCopyrightText: 2019 Paul Schwörer <hello@paulschwoerer.de>
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
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
			:layout="{ 'text-field': ['get', 'point_count_abbreviated'], 'text-size': 12, 'text-allow-overlap': true, 'text-ignore-placement': true }"
			:paint="{ 'text-color': '#ffffff', 'text-halo-color': '#000000', 'text-halo-width': 1 }" />
		<MglSymbolLayer :layer-id="POINT_LAYER_ID"
			:source="SOURCE_ID"
			:filter="['!', ['has', 'point_count']]"
			:layout="{ 'icon-image': ['get', 'iconId'], 'icon-allow-overlap': true, 'icon-size': 1 }" />
	</MglGeoJsonSource>
</template>

<script setup lang="ts">
import type { MapGeoJSONFeature, MapMouseEvent } from 'maplibre-gl'
import { Popup } from 'maplibre-gl'
import { getDialogBuilder } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { basename } from '@nextcloud/paths'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { MglGeoJsonSource, MglSymbolLayer, useMap } from 'vue-maplibre-gl'
import { binSearch, isPublic } from '../../utils/common.js'
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

export interface Photo {
	path: string
	fileId: number
	lat: number
	lng: number
	dateTaken?: number
	hasPreview?: boolean
	isUpdateable?: boolean
}

const props = withDefaults(defineProps<{
	photos: Photo[]
	dateFilterEnabled: boolean
	dateFilterStart: number
	dateFilterEnd: number
	draggable?: boolean
}>(), {
	draggable: false,
})

const emit = defineEmits<{
	'open-sidebar': [path: string]
	'coords-reset': [paths: string[]]
	'add-to-map-photo': [photo: Photo]
	'photo-moved': [photo: Photo, latLng: { lat: number, lng: number }]
}>()

const SOURCE_ID = 'photos'
const CLUSTER_LAYER_ID = 'photos-clusters'
const CLUSTER_COUNT_LAYER_ID = 'photos-cluster-count'
const POINT_LAYER_ID = 'photos-points'
// Marker size plus a 10px margin
const CLUSTER_RADIUS = PHOTO_PREVIEW_MARKER_SIZE + 10
// Picks one representative child's iconId for each cluster (not necessarily the first one -
// supercluster aggregates bottom-up, so it's whichever leaf the reduction visits last).
const CLUSTER_PROPERTIES = { iconId: [['get', 'iconId'], ['get', 'iconId']] }

const mapInstance = useMap()

const filteredPhotos = computed<Photo[]>(() => {
	if (!props.dateFilterEnabled) {
		return props.photos
	}
	const lastNullIndex = binSearch(props.photos, (p: Photo) => !p.dateTaken)
	const firstShownIndex = binSearch(props.photos, (p: Photo) => (p.dateTaken || 0) < props.dateFilterStart) + 1
	const lastShownIndex = binSearch(props.photos, (p: Photo) => (p.dateTaken || 0) < props.dateFilterEnd)
	return [
		...props.photos.slice(0, lastNullIndex + 1),
		...props.photos.slice(firstShownIndex, lastShownIndex + 1),
	]
})

/** Live position while a marker is being dragged, so it follows the cursor without a parent round-trip. */
const draggingOverride = ref<{ index: number, lat: number, lng: number } | null>(null)

const geojson = computed(() => ({
	type: 'FeatureCollection' as const,
	features: filteredPhotos.value.map((photo, index) => {
		const iconId = photoPreviewIconId(getPhotoPreviewUrl(photo))
		const dragged = draggingOverride.value?.index === index ? draggingOverride.value : null
		return {
			type: 'Feature' as const,
			geometry: { type: 'Point' as const, coordinates: [dragged?.lng ?? photo.lng, dragged?.lat ?? photo.lat] },
			properties: {
				index,
				iconId: loadedPhotoPreviewIcons.has(iconId) ? iconId : PHOTO_PREVIEW_PLACEHOLDER_ICON_ID,
			},
		}
	}),
}))

const usedPreviewUrls = computed<string[]>(() => {
	return [...new Set(filteredPhotos.value.map((p) => getPhotoPreviewUrl(p)))]
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
function viewPhoto(photo: Photo): void {
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
	const photo = filteredPhotos.value[feature.properties.index]
	if (!photo) {
		return
	}
	map.getCanvas().style.cursor = props.draggable ? 'grab' : 'pointer'

	if (props.draggable && photo.isUpdateable) {
		// dragging is the priority interaction here - don't obscure the marker with a tooltip
		return
	}

	const html = document.createElement('div')
	html.className = 'tooltip-photo-wrapper'
	html.innerHTML = `
		<img class="photo-tooltip" src="${escapeHtml(getPhotoPreviewUrl(photo))}">
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
	if (!feature) {
		return
	}
	const photo = filteredPhotos.value[feature.properties.index]
	if (photo) {
		viewPhoto(photo)
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
	const pointCount = feature.properties.point_count
	const coordinates = (feature.geometry as { coordinates: [number, number] }).coordinates

	if (pointCount > 10 && map.getZoom() !== map.getMaxZoom()) {
		// @ts-expect-error getClusterExpansionZoom exists on GeoJSONSource but isn't in this narrowed type
		source?.getClusterExpansionZoom(clusterId).then((zoom: number) => {
			map.easeTo({ center: coordinates, zoom })
		}).catch(() => {})
		return
	}

	// @ts-expect-error getClusterLeaves exists on GeoJSONSource but isn't in this narrowed type
	source?.getClusterLeaves(clusterId, pointCount, 0).then((leaves: MapGeoJSONFeature[]) => {
		const photoList = leaves
			.map((leaf) => filteredPhotos.value[leaf.properties?.index])
			.filter((p): p is Photo => !!p)
			.sort((a, b) => (a.dateTaken ?? 0) - (b.dateTaken ?? 0))
		if (photoList.length === 0) {
			return
		}
		if (window.OCA?.Viewer?.open) {
			window.OCA.Viewer.open({ path: photoList[0].path, list: photoList })
		} else {
			emit('open-sidebar', photoList[0].path)
		}
	}).catch(() => {})
}

let draggedPhoto: Photo | null = null
let draggedIndex = -1

/**
 * @param event the underlying MapLibre mouse event
 */
function onPointMouseDown(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const map = mapInstance.map
	const feature = event.features?.[0]
	const index = feature?.properties.index
	const photo = index !== undefined ? filteredPhotos.value[index] : undefined
	if (!map || !props.draggable || !photo?.isUpdateable) {
		return
	}
	event.preventDefault()
	hoverPopup?.remove()
	draggedPhoto = photo
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
	if (!draggedPhoto) {
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
	if (draggedPhoto && draggingOverride.value) {
		emit('photo-moved', draggedPhoto, { lat: draggingOverride.value.lat, lng: draggingOverride.value.lng })
	}
	draggedPhoto = null
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
	const photo = feature && filteredPhotos.value[feature.properties.index]
	if (!map || !feature || !photo) {
		return
	}
	event.preventDefault()

	const container = document.createElement('div')
	container.className = 'popup-photo-wrapper'
	container.innerHTML = `
		<button class="action-btn" data-action="sidebar">${escapeHtml(t('maps', 'Open in Sidebar'))}</button>
		<button class="action-btn" data-action="display">${escapeHtml(t('maps', 'Display picture'))}</button>
		${photo.isUpdateable ? `<button class="action-btn" data-action="reset">${escapeHtml(t('maps', 'Remove geo data'))}</button>` : ''}
		${!isPublic() ? `<button class="action-btn" data-action="copy">${escapeHtml(t('maps', 'Copy to map'))}</button>` : ''}
	`
	container.addEventListener('click', (domEvent) => {
		const action = (domEvent.target as HTMLElement).getAttribute('data-action')
		if (action === 'sidebar') {
			emit('open-sidebar', photo.path)
		} else if (action === 'display') {
			viewPhoto(photo)
		} else if (action === 'reset') {
			emit('coords-reset', [photo.path])
		} else if (action === 'copy') {
			emit('add-to-map-photo', photo)
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
		const photoList = leaves
			.map((leaf) => filteredPhotos.value[leaf.properties?.index])
			.filter((p): p is Photo => !!p)
			.sort((a, b) => (a.dateTaken ?? 0) - (b.dateTaken ?? 0))
		if (photoList.length === 0) {
			return
		}
		const canReset = photoList.some((p) => p.isUpdateable)

		const container = document.createElement('div')
		container.className = 'popup-photo-wrapper'
		container.innerHTML = `
			<button class="action-btn" data-action="display">${escapeHtml(t('maps', 'Display pictures'))}</button>
			<button class="action-btn" data-action="zoom">${escapeHtml(t('maps', 'Zoom on bounds'))}</button>
			${canReset ? `<button class="action-btn" data-action="reset">${escapeHtml(t('maps', 'Remove geo data'))}</button>` : ''}
		`
		container.addEventListener('click', (domEvent) => {
			const action = (domEvent.target as HTMLElement).getAttribute('data-action')
			if (action === 'display') {
				if (window.OCA?.Viewer?.open) {
					window.OCA.Viewer.open({ path: photoList[0].path, list: photoList })
				} else {
					emit('open-sidebar', photoList[0].path)
				}
				actionPopup?.remove()
			} else if (action === 'zoom') {
				// @ts-expect-error getClusterExpansionZoom exists on GeoJSONSource but isn't in this narrowed type
				source.getClusterExpansionZoom(clusterId).then((zoom: number) => {
					map.easeTo({ center: coordinates, zoom })
				}).catch(() => {})
				actionPopup?.remove()
			} else if (action === 'reset') {
				getDialogBuilder(t('maps', 'Remove geo data'))
					.setText(t('maps', 'Are you sure you want to remove geo data of {nb} photos?', { nb: photoList.length }))
					.addButton({ label: t('maps', 'Cancel'), callback: () => {} })
					.addButton({
						label: t('maps', 'Yes'),
						variant: 'error',
						callback: () => emit('coords-reset', photoList.map((p) => p.path)),
					})
					.build()
					.show()
				actionPopup?.remove()
			}
		})

		actionPopup?.remove()
		actionPopup = new Popup({ closeButton: false, className: 'popovermenu open popupMarker photoPopup' })
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

<style lang="scss">
.tooltip-photo-wrapper {
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 6px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	border-radius: 3px;
}

.popup-photo-wrapper .action-btn {
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

.photo-tooltip {
	max-width: 200px;
	max-height: 150px;
}
</style>
