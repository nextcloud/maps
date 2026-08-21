<!--
 - SPDX-FileCopyrightText: 2019 Paul Schwörer <hello@paulschwoerer.de>
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	Ported from Leaflet to vue-maplibre-gl. Not ported yet, on purpose:
	the right-click popup (open sidebar, remove geo data, copy to map,
	spiderfy/zoom on a cluster), and drag-to-edit. The chunked cluster
	loading progress events are gone too, but on purpose rather than
	deferred: MapLibre's clustering is synchronous and fast even for many
	thousands of points, so there's no "in progress" state left to report.
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
			:layout="{ 'icon-image': ['coalesce', ['get', 'iconId'], PLACEHOLDER_ICON_ID], 'icon-allow-overlap': true, 'icon-size': 1 }" />
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
import moment from '@nextcloud/moment'
import { basename } from '@nextcloud/paths'
import { generateUrl } from '@nextcloud/router'
import { computed, onBeforeUnmount, reactive, watch } from 'vue'
import { MglGeoJsonSource, MglSymbolLayer, useMap } from 'vue-maplibre-gl'
import { binSearch, getToken } from '../../utils/common.js'
import { escapeHtml } from '../../utils/domUtils.ts'

export interface Photo {
	path: string
	fileId: number
	lat: number
	lng: number
	dateTaken?: number
	hasPreview?: boolean
	isUpdateable?: boolean
}

const props = defineProps<{
	photos: Photo[]
	dateFilterEnabled: boolean
	dateFilterStart: number
	dateFilterEnd: number
}>()

const emit = defineEmits<{
	'open-sidebar': [path: string]
}>()

const SOURCE_ID = 'photos'
const CLUSTER_LAYER_ID = 'photos-clusters'
const CLUSTER_COUNT_LAYER_ID = 'photos-cluster-count'
const POINT_LAYER_ID = 'photos-points'
// Matches the old marker view size (40px) + old cluster radius margin (10px)
const MARKER_SIZE = 40
const CLUSTER_RADIUS = 50
const PLACEHOLDER_ICON_ID = 'photo-preview-placeholder'
// Picks one representative child's iconId for each cluster (not necessarily the first one -
// supercluster aggregates bottom-up, so it's whichever leaf the reduction visits last).
const CLUSTER_PROPERTIES = { iconId: [['get', 'iconId'], ['get', 'iconId']] }

const mapInstance = useMap()
const loadedIcons = reactive(new Set<string>())
const loadingIcons = new Set<string>()

/**
 * @param photo the photo to build a preview URL for
 */
function getPreviewUrl(photo: Photo): string {
	if (photo?.hasPreview) {
		const token = getToken()
		return token
			? generateUrl('apps/files_sharing/publicpreview/') + token + '?file=' + encodeURIComponent(photo.path) + '&x=341&y=256&a=1'
			: generateUrl('core') + '/preview?fileId=' + photo.fileId + '&x=341&y=256&a=1'
	}
	return generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2'
}

/**
 * @param url the preview URL to derive a sprite id for
 */
function iconIdForUrl(url: string): string {
	return 'photo-preview-' + btoa(url).replace(/[^a-zA-Z0-9]/g, '')
}

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

const geojson = computed(() => ({
	type: 'FeatureCollection' as const,
	features: filteredPhotos.value.map((photo, index) => {
		const iconId = iconIdForUrl(getPreviewUrl(photo))
		return {
			type: 'Feature' as const,
			geometry: { type: 'Point' as const, coordinates: [photo.lng, photo.lat] },
			properties: {
				index,
				iconId: loadedIcons.has(iconId) ? iconId : PLACEHOLDER_ICON_ID,
			},
		}
	}),
}))

const usedPreviewUrls = computed<string[]>(() => {
	return [...new Set(filteredPhotos.value.map((p) => getPreviewUrl(p)))]
})

/**
 * @param size square size of the drawn icon
 * @param draw callback receiving the 2D context to draw into
 */
function drawIcon(size: number, draw: (ctx: CanvasRenderingContext2D) => void): ImageData | null {
	const canvas = document.createElement('canvas')
	canvas.width = size
	canvas.height = size
	const ctx = canvas.getContext('2d')
	if (!ctx) {
		return null
	}
	draw(ctx)
	return ctx.getImageData(0, 0, size, size)
}

function ensureFallbackIcons(): void {
	const map = mapInstance.map
	if (!map) {
		return
	}
	if (!map.hasImage(PLACEHOLDER_ICON_ID)) {
		const image = drawIcon(MARKER_SIZE, (ctx) => {
			ctx.fillStyle = '#ffffff'
			ctx.fillRect(0, 0, MARKER_SIZE, MARKER_SIZE)
			ctx.strokeStyle = '#dddddd'
			ctx.lineWidth = 2
			ctx.strokeRect(1, 1, MARKER_SIZE - 2, MARKER_SIZE - 2)
		})
		if (image) {
			map.addImage(PLACEHOLDER_ICON_ID, image)
		}
	}
}

/**
 * @param url the preview image URL to fetch and draw to a square thumbnail
 */
function loadThumbnail(url: string): Promise<ImageData> {
	return new Promise((resolve, reject) => {
		const img = new Image()
		img.crossOrigin = 'anonymous'
		img.onload = () => {
			const canvas = document.createElement('canvas')
			canvas.width = MARKER_SIZE
			canvas.height = MARKER_SIZE
			const ctx = canvas.getContext('2d')
			if (!ctx) {
				reject(new Error('Could not get canvas context'))
				return
			}
			ctx.drawImage(img, 0, 0, MARKER_SIZE, MARKER_SIZE)
			resolve(ctx.getImageData(0, 0, MARKER_SIZE, MARKER_SIZE))
		}
		img.onerror = () => reject(new Error(`Failed to load preview ${url}`))
		img.src = url
	})
}

/**
 * @param url the preview URL to load and register as a GL sprite image
 */
async function ensurePreviewIcon(url: string): Promise<void> {
	const id = iconIdForUrl(url)
	const map = mapInstance.map
	if (!map || loadedIcons.has(id) || loadingIcons.has(id) || map.hasImage(id)) {
		return
	}
	loadingIcons.add(id)
	try {
		const image = await loadThumbnail(url)
		if (mapInstance.map && !mapInstance.map.hasImage(id)) {
			mapInstance.map.addImage(id, image)
		}
		loadedIcons.add(id)
	} catch (error) {
		console.error(error)
	} finally {
		loadingIcons.delete(id)
	}
}

watch(() => mapInstance.isLoaded, (loaded) => {
	if (loaded) {
		ensureFallbackIcons()
	}
}, { immediate: true })

watch([() => mapInstance.isLoaded, usedPreviewUrls], () => {
	if (!mapInstance.isLoaded) {
		return
	}
	usedPreviewUrls.value.forEach((url) => ensurePreviewIcon(url))
}, { immediate: true })

/**
 * @param photo the photo whose date should be formatted
 */
function getPhotoFormattedDate(photo: Photo): string {
	if (!photo?.dateTaken) {
		return ''
	}
	const d = new Date(photo.dateTaken * 1000)
	const mom = moment.unix(photo.dateTaken + d.getTimezoneOffset() * 60)
	return mom.format('LL') + ' ' + mom.format('HH:mm:ss')
}

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
	map.getCanvas().style.cursor = 'pointer'

	const html = document.createElement('div')
	html.className = 'tooltip-photo-wrapper'
	html.innerHTML = `
		<img class="photo-tooltip" src="${getPreviewUrl(photo)}">
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
	map.on('mouseenter', POINT_LAYER_ID, onPointMouseEnter)
	map.on('mouseleave', POINT_LAYER_ID, onPointMouseLeave)
	map.on('mouseenter', CLUSTER_LAYER_ID, onClusterMouseEnter)
	map.on('mouseleave', CLUSTER_LAYER_ID, onClusterMouseLeave)
}, { immediate: true })

onBeforeUnmount(() => {
	const map = mapInstance.map
	hoverPopup?.remove()
	if (!map || !listenersBound) {
		return
	}
	map.off('click', POINT_LAYER_ID, onPointClick)
	map.off('click', CLUSTER_LAYER_ID, onClusterClick)
	map.off('mouseenter', POINT_LAYER_ID, onPointMouseEnter)
	map.off('mouseleave', POINT_LAYER_ID, onPointMouseLeave)
	map.off('mouseenter', CLUSTER_LAYER_ID, onClusterMouseEnter)
	map.off('mouseleave', CLUSTER_LAYER_ID, onClusterMouseLeave)
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
.photo-tooltip {
	max-width: 200px;
	max-height: 150px;
}
</style>
