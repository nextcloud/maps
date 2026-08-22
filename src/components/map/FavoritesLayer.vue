<!--
 - SPDX-FileCopyrightText: 2019 Paul Schwörer <hello@paulschwoerer.de>
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	Ported from Leaflet to vue-maplibre-gl. Not ported yet, on purpose:
	spiderfying overlapping markers at max zoom - MapLibre's clustering
	has no equivalent, and clusters are rare enough at max zoom that
	zoom-to-bounds (still available from the cluster's right-click menu)
	covers the same need.
-->
<template>
	<MglGeoJsonSource :source-id="SOURCE_ID"
		:data="geojson"
		:cluster="true"
		:cluster-radius="CLUSTER_RADIUS">
		<MglSymbolLayer :layer-id="CLUSTER_LAYER_ID"
			:source="SOURCE_ID"
			:filter="['has', 'point_count']"
			:layout="{ 'icon-image': clusterIconId, 'icon-allow-overlap': true, 'icon-size': 1 }" />
		<MglSymbolLayer :layer-id="CLUSTER_COUNT_LAYER_ID"
			:source="SOURCE_ID"
			:filter="['has', 'point_count']"
			:layout="{ 'text-field': ['get', 'point_count_abbreviated'], 'text-size': 12, 'text-allow-overlap': true, 'text-ignore-placement': true }"
			:paint="{ 'text-color': '#ffffff' }" />
		<MglSymbolLayer :layer-id="POINT_LAYER_ID"
			:source="SOURCE_ID"
			:filter="['!', ['has', 'point_count']]"
			:layout="{ 'icon-image': ['get', 'iconId'], 'icon-allow-overlap': true, 'icon-size': 1 }" />
	</MglGeoJsonSource>
</template>

<script setup lang="ts">
import type { GeoJSONSource, MapGeoJSONFeature, MapMouseEvent } from 'maplibre-gl'
import { Popup } from 'maplibre-gl'
import { translate as t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { MglGeoJsonSource, MglSymbolLayer, useMap } from 'vue-maplibre-gl'
import { isPublic } from '../../utils/common.js'
import { escapeHtml } from '../../utils/domUtils.ts'
import { ensureFavoriteMarkerIcon, favoriteMarkerIconId } from '../../utils/favoriteMarkerIcon.ts'

export interface Favorite {
	id: number
	lat: number
	lng: number
	category: string
	name?: string
	comment?: string
	isUpdateable: boolean
	isDeletable: boolean
	selected?: boolean
}

export interface FavoriteCategory {
	color?: string
	enabled: boolean
}

const props = withDefaults(defineProps<{
	favorites: Record<string, Favorite>
	categories: Record<string, FavoriteCategory>
	draggable?: boolean
}>(), {
	draggable: false,
})

const emit = defineEmits<{
	click: [favorite: Favorite]
	edit: [favorite: Favorite]
	delete: [id: number]
	'delete-favorites': [ids: number[]]
	'add-to-map-favorite': [favorite: Favorite]
}>()

const SOURCE_ID = 'favorites'
const CLUSTER_LAYER_ID = 'favorites-clusters'
const CLUSTER_COUNT_LAYER_ID = 'favorites-cluster-count'
const POINT_LAYER_ID = 'favorites-points'
// Matches the old cluster-marker view size (36px) + old cluster radius margin (10px)
const CLUSTER_RADIUS = 46
const DEFAULT_COLOR = '0082c9'

const mapInstance = useMap()

const clusterIconId = favoriteMarkerIconId(DEFAULT_COLOR, false)

const displayedFavorites = computed<Favorite[]>(() => {
	return Object.values(props.favorites).filter((f) => props.categories[f.category]?.enabled)
})

/** Live position while a marker is being dragged, so it follows the cursor without a parent round-trip. */
const draggingOverride = ref<{ id: number, lat: number, lng: number } | null>(null)

const geojson = computed(() => ({
	type: 'FeatureCollection' as const,
	features: displayedFavorites.value.map((f) => {
		const color = props.categories[f.category]?.color || DEFAULT_COLOR
		const dragged = draggingOverride.value?.id === f.id ? draggingOverride.value : null
		return {
			type: 'Feature' as const,
			geometry: { type: 'Point' as const, coordinates: [dragged?.lng ?? f.lng, dragged?.lat ?? f.lat] },
			properties: {
				id: f.id,
				iconId: favoriteMarkerIconId(color, !!f.selected),
				name: f.name || '',
				category: f.category,
				comment: f.comment || '',
			},
		}
	}),
}))

const usedIconColors = computed<Array<{ color: string, selected: boolean }>>(() => {
	const seen = new Map<string, { color: string, selected: boolean }>()
	seen.set(DEFAULT_COLOR, { color: DEFAULT_COLOR, selected: false })
	displayedFavorites.value.forEach((f) => {
		const color = props.categories[f.category]?.color || DEFAULT_COLOR
		const selected = !!f.selected
		seen.set(favoriteMarkerIconId(color, selected), { color, selected })
	})
	return [...seen.values()]
})

watch([() => mapInstance.isLoaded, usedIconColors], () => {
	const map = mapInstance.map
	if (!mapInstance.isLoaded || !map) {
		return
	}
	usedIconColors.value.forEach(({ color, selected }) => ensureFavoriteMarkerIcon(map, color, selected))
}, { immediate: true })

let hoverPopup: Popup | null = null

/**
 * @param event the underlying MapLibre mouse event
 */
function onPointClick(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const feature = event.features?.[0]
	if (!feature) {
		return
	}
	const favorite = props.favorites[feature.properties.id]
	if (favorite) {
		emit('click', favorite)
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
	const source = map.getSource(SOURCE_ID) as GeoJSONSource | undefined
	const clusterId = feature.properties.cluster_id
	source?.getClusterExpansionZoom(clusterId).then((zoom) => {
		map.easeTo({
			center: (feature.geometry as { coordinates: [number, number] }).coordinates,
			zoom,
		})
	}).catch(() => {})
}

/**
 * @param event the underlying MapLibre mouse event
 */
function onPointMouseEnter(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const map = mapInstance.map
	const feature = event.features?.[0]
	if (!map || !feature) {
		return
	}
	map.getCanvas().style.cursor = props.draggable ? 'grab' : 'pointer'

	const favorite = props.favorites[feature.properties.id]
	if (props.draggable && favorite?.isUpdateable) {
		// dragging is the priority interaction here - don't obscure the marker with a tooltip
		return
	}

	const { name, category, comment } = feature.properties
	const html = document.createElement('div')
	html.className = 'favorite-marker-tooltip'
	html.innerHTML = `
		<b>${t('maps', 'Name')}:</b> <span>${escapeHtml(name || t('maps', 'No name'))}</span><br>
		<b>${t('maps', 'Category')}:</b> <span>${escapeHtml(category)}</span>
		${comment ? `<br><b>${t('maps', 'Comment')}:</b> <span>${escapeHtml(comment)}</span>` : ''}
	`

	hoverPopup?.remove()
	hoverPopup = new Popup({ closeButton: false, closeOnClick: false, offset: 18 })
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

let draggedFavorite: Favorite | null = null

/**
 * @param event the underlying MapLibre mouse event
 */
function onPointMouseDown(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const map = mapInstance.map
	const feature = event.features?.[0]
	const favorite = feature && props.favorites[feature.properties.id]
	if (!map || !props.draggable || !favorite?.isUpdateable) {
		return
	}
	event.preventDefault()
	hoverPopup?.remove()
	draggedFavorite = favorite
	map.dragPan.disable()
	map.getCanvas().style.cursor = 'grabbing'
	map.on('mousemove', onDragMove)
	map.on('mouseup', onDragEnd)
}

/**
 * @param event the underlying MapLibre mouse event
 */
function onDragMove(event: MapMouseEvent): void {
	if (!draggedFavorite) {
		return
	}
	draggingOverride.value = { id: draggedFavorite.id, lat: event.lngLat.lat, lng: event.lngLat.lng }
}

function onDragEnd(): void {
	const map = mapInstance.map
	map?.off('mousemove', onDragMove)
	map?.off('mouseup', onDragEnd)
	map?.dragPan.enable()
	if (map) {
		map.getCanvas().style.cursor = 'grab'
	}
	if (draggedFavorite && draggingOverride.value) {
		emit('edit', { ...draggedFavorite, lat: draggingOverride.value.lat, lng: draggingOverride.value.lng })
	}
	draggedFavorite = null
	draggingOverride.value = null
}

let actionPopup: Popup | null = null

/**
 * @param event the underlying MapLibre mouse event
 */
function onPointContextMenu(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const map = mapInstance.map
	const feature = event.features?.[0]
	const favorite = feature && props.favorites[feature.properties.id]
	if (!map || !favorite) {
		return
	}
	event.preventDefault()

	const container = document.createElement('div')
	container.className = 'popup-favorite-wrapper'
	container.innerHTML = `
		${favorite.isDeletable ? `<button class="action-btn" data-action="delete">${escapeHtml(t('maps', 'Delete favorite'))}</button>` : ''}
		${!isPublic() ? `<button class="action-btn" data-action="copy">${escapeHtml(t('maps', 'Copy to map'))}</button>` : ''}
	`
	container.addEventListener('click', (domEvent) => {
		const action = (domEvent.target as HTMLElement).getAttribute('data-action')
		if (action === 'delete') {
			emit('delete', favorite.id)
		} else if (action === 'copy') {
			emit('add-to-map-favorite', favorite)
		}
		actionPopup?.remove()
	})

	hoverPopup?.remove()
	actionPopup?.remove()
	actionPopup = new Popup({ closeButton: false, className: 'popovermenu open popupMarker favoritePopup' })
		.setLngLat(event.lngLat)
		.setDOMContent(container)
		.addTo(map)
}

/**
 * @param event the underlying MapLibre mouse event
 */
function onClusterContextMenu(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const map = mapInstance.map
	const feature = event.features?.[0]
	const source = map?.getSource(SOURCE_ID) as GeoJSONSource | undefined
	if (!map || !feature || !source) {
		return
	}
	event.preventDefault()
	const clusterId = feature.properties.cluster_id

	source.getClusterLeaves(clusterId, feature.properties.point_count, 0).then((leaves) => {
		const favorites = leaves
			.map((leaf) => props.favorites[(leaf.properties as { id: number }).id])
			.filter((f): f is Favorite => !!f)
		const readOnly = !favorites.some((f) => f.isUpdateable)

		const container = document.createElement('div')
		container.className = 'popup-favorite-wrapper'
		container.innerHTML = `
			${!readOnly ? `<button class="action-btn" data-action="delete">${escapeHtml(t('maps', 'Delete favorites'))}</button>` : ''}
			<button class="action-btn" data-action="zoom">${escapeHtml(t('maps', 'Zoom on bounds'))}</button>
		`
		container.addEventListener('click', (domEvent) => {
			const action = (domEvent.target as HTMLElement).getAttribute('data-action')
			if (action === 'delete') {
				emit('delete-favorites', favorites.map((f) => f.id))
			} else if (action === 'zoom') {
				source.getClusterExpansionZoom(clusterId).then((zoom) => {
					map.easeTo({ center: (feature.geometry as { coordinates: [number, number] }).coordinates, zoom })
				}).catch(() => {})
			}
			actionPopup?.remove()
		})

		actionPopup?.remove()
		actionPopup = new Popup({ closeOnClick: false, className: 'popovermenu open popupMarker favoritePopup' })
			.setLngLat(event.lngLat)
			.setDOMContent(container)
			.addTo(map)
	}).catch(() => {})
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
	map.on('mousedown', POINT_LAYER_ID, onPointMouseDown)
	map.on('contextmenu', POINT_LAYER_ID, onPointContextMenu)
	map.on('contextmenu', CLUSTER_LAYER_ID, onClusterContextMenu)
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
	map.off('mouseenter', POINT_LAYER_ID, onPointMouseEnter)
	map.off('mouseleave', POINT_LAYER_ID, onPointMouseLeave)
	map.off('mouseenter', CLUSTER_LAYER_ID, onClusterMouseEnter)
	map.off('mouseleave', CLUSTER_LAYER_ID, onClusterMouseLeave)
	map.off('mousedown', POINT_LAYER_ID, onPointMouseDown)
	map.off('contextmenu', POINT_LAYER_ID, onPointContextMenu)
	map.off('contextmenu', CLUSTER_LAYER_ID, onClusterContextMenu)
	map.off('mousemove', onDragMove)
	map.off('mouseup', onDragEnd)
})
</script>

<style lang="scss">
.favorite-marker-tooltip {
	padding: 6px;
	border-radius: 3px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	max-width: 300px;
}

.popup-favorite-wrapper .action-btn {
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
