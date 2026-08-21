<!--
 - @copyright Copyright (c) 2019, Paul Schwörer <hello@paulschwoerer.de>
 -
 - @author Paul Schwörer <hello@paulschwoerer.de>
 -
 - @license GNU AGPL version 3 or any later version
 -
 - This program is free software: you can redistribute it and/or modify
 - it under the terms of the GNU Affero General Public License as
 - published by the Free Software Foundation, either version 3 of the
 - License, or (at your option) any later version.
 -
 - This program is distributed in the hope that it will be useful,
 - but WITHOUT ANY WARRANTY; without even the implied warranty of
 - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 - GNU Affero General Public License for more details.
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program.  If not, see <http://www.gnu.org/licenses/>.
 -->

<!--
	Ported from Leaflet to vue-maplibre-gl. Not ported yet, on purpose:
	drag-to-edit, the right-click context menu (delete/copy-to-map,
	delete-multiple/zoom-to-bounds), and spiderfying overlapping markers at
	max zoom - these will come back once the rest of the interaction model
	(shared with tracks/devices/contacts/photos) is redesigned for MapLibre.
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
import { computed, onBeforeUnmount, watch } from 'vue'
import { MglGeoJsonSource, MglSymbolLayer, useMap } from 'vue-maplibre-gl'

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

const props = defineProps<{
	favorites: Record<string, Favorite>
	categories: Record<string, FavoriteCategory>
}>()

const emit = defineEmits<{
	click: [favorite: Favorite]
}>()

const SOURCE_ID = 'favorites'
const CLUSTER_LAYER_ID = 'favorites-clusters'
const CLUSTER_COUNT_LAYER_ID = 'favorites-cluster-count'
const POINT_LAYER_ID = 'favorites-points'
// Matches the old cluster-marker view size (36px) + old cluster radius margin (10px)
const CLUSTER_RADIUS = 46
const DEFAULT_COLOR = '0082c9'
const SELECTED_COLOR = 'ffcc00'

const mapInstance = useMap()
const loadedIcons = new Set<string>()

/**
 * @param color hex color (without '#')
 * @param selected whether this is the "selected" variant
 */
function iconIdForColor(color: string, selected: boolean): string {
	return `favorite-marker-${color}${selected ? '-selected' : ''}`
}

const clusterIconId = iconIdForColor(DEFAULT_COLOR, false)

const displayedFavorites = computed<Favorite[]>(() => {
	return Object.values(props.favorites).filter((f) => props.categories[f.category]?.enabled)
})

const geojson = computed(() => ({
	type: 'FeatureCollection' as const,
	features: displayedFavorites.value.map((f) => {
		const color = props.categories[f.category]?.color || DEFAULT_COLOR
		return {
			type: 'Feature' as const,
			geometry: { type: 'Point' as const, coordinates: [f.lng, f.lat] },
			properties: {
				id: f.id,
				iconId: iconIdForColor(color, !!f.selected),
				name: f.name || '',
				category: f.category,
				comment: f.comment || '',
			},
		}
	}),
}))

/**
 * Draw a colored circle with a white star on top - matching the old
 * Leaflet div-icon look closely enough - and register it as a MapLibre GL
 * sprite image. GL symbol layers can only use raster icon images, not the
 * CSS classes the Leaflet version relied on.
 *
 * @param color hex color (without '#') for the circle background
 * @param selected whether to draw the "selected" (highlighted) variant
 */
function ensureIcon(color: string, selected: boolean): void {
	const id = iconIdForColor(color, selected)
	const map = mapInstance.map
	if (!map || loadedIcons.has(id) || map.hasImage(id)) {
		return
	}
	const size = 32
	const canvas = document.createElement('canvas')
	canvas.width = size
	canvas.height = size
	const ctx = canvas.getContext('2d')
	if (!ctx) {
		return
	}

	ctx.beginPath()
	ctx.arc(size / 2, size / 2, size / 2 - 2, 0, Math.PI * 2)
	ctx.fillStyle = selected ? `#${SELECTED_COLOR}` : `#${color}`
	ctx.fill()
	ctx.lineWidth = 2
	ctx.strokeStyle = '#ffffff'
	ctx.stroke()

	drawStar(ctx, size / 2, size / 2, size / 2 - 9, (size / 2 - 9) / 2.5)
	ctx.fillStyle = '#ffffff'
	ctx.fill()

	map.addImage(id, ctx.getImageData(0, 0, size, size))
	loadedIcons.add(id)
}

/**
 * @param ctx canvas context to draw into
 * @param cx star center x
 * @param cy star center y
 * @param outerRadius outer point radius
 * @param innerRadius inner point radius
 */
function drawStar(ctx: CanvasRenderingContext2D, cx: number, cy: number, outerRadius: number, innerRadius: number): void {
	const spikes = 5
	let rotation = (Math.PI / 2) * 3
	const step = Math.PI / spikes
	ctx.beginPath()
	ctx.moveTo(cx, cy - outerRadius)
	for (let i = 0; i < spikes; i++) {
		let x = cx + Math.cos(rotation) * outerRadius
		let y = cy + Math.sin(rotation) * outerRadius
		ctx.lineTo(x, y)
		rotation += step

		x = cx + Math.cos(rotation) * innerRadius
		y = cy + Math.sin(rotation) * innerRadius
		ctx.lineTo(x, y)
		rotation += step
	}
	ctx.lineTo(cx, cy - outerRadius)
	ctx.closePath()
}

const usedIconColors = computed<Array<{ color: string, selected: boolean }>>(() => {
	const seen = new Map<string, { color: string, selected: boolean }>()
	seen.set(DEFAULT_COLOR, { color: DEFAULT_COLOR, selected: false })
	displayedFavorites.value.forEach((f) => {
		const color = props.categories[f.category]?.color || DEFAULT_COLOR
		const selected = !!f.selected
		seen.set(iconIdForColor(color, selected), { color, selected })
	})
	return [...seen.values()]
})

watch([() => mapInstance.isLoaded, usedIconColors], () => {
	if (!mapInstance.isLoaded) {
		return
	}
	usedIconColors.value.forEach(({ color, selected }) => ensureIcon(color, selected))
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
	map.getCanvas().style.cursor = 'pointer'

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

/**
 * @param unsafe raw text to embed as HTML
 */
function escapeHtml(unsafe: string): string {
	const div = document.createElement('div')
	div.textContent = unsafe
	return div.innerHTML
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
.favorite-marker-tooltip {
	padding: 6px;
	border-radius: 3px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	max-width: 300px;
}
</style>
