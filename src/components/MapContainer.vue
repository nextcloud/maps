<!--
 - SPDX-FileCopyrightText: 2019 Paul Schwörer <hello@paulschwoerer.de>
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	Ported from Leaflet to vue-maplibre-gl. Favorites are rendered the same
	way FavoritesLayer.vue renders them for the main map (a clustered GL
	symbol layer, sharing its marker icon drawing via
	utils/favoriteMarkerIcon.ts), but this component stays self-contained
	rather than reusing FavoritesLayer.vue itself: this page's interactions
	(click selects the favorite for the page's sidebar, no drag-to-edit,
	no right-click menu)
	are different enough from the main map's that reusing it would mean
	fighting its own click/drag/context-menu behavior instead.

	ClickPopup.vue is a full Vue form, not raw HTML like the other layers'
	right-click menus, so there's no vue-maplibre-gl primitive to bind it
	to a marker the way Leaflet's bindPopup() did. It's mounted into a
	plain maplibregl.Popup's DOM content as its own small Vue app instance
	instead, with the current Vuex store attached (it reads the map's
	"adding favorites" mode from it).
-->
<template>
	<div class="map-container">
		<MglMap :map-style="BaseStyles[BaseStyleIds.Street]"
			:center="[0, 0]"
			:zoom="2"
			:min-zoom="2"
			:max-zoom="19"
			@map:click="onMapClick">
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

			<MglNavigationControl position="bottom-right" :show-compass="false" />
			<MglScaleControl position="bottom-left" :unit="scaleUnit" />
		</MglMap>
	</div>
</template>

<script setup lang="ts">
import type { App } from 'vue'
import type { GeoJSONSource, MapGeoJSONFeature, MapMouseEvent } from 'maplibre-gl'
import { Popup } from 'maplibre-gl'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { computed, createApp, onBeforeUnmount, watch } from 'vue'
import { useStore } from 'vuex'
import { MglGeoJsonSource, MglMap, MglNavigationControl, MglScaleControl, MglSymbolLayer, useMap } from 'vue-maplibre-gl'
import 'maplibre-gl/dist/maplibre-gl.css'
import 'vue-maplibre-gl/dist/vue-maplibre-gl.css'
import ClickPopup from './map/ClickPopup.vue'
import { BaseStyleIds, BaseStyles } from '../data/mapLayers.ts'
import { isPublicShare as isRunningAsPublicShare } from '../utils/common.js'
import { PUBLIC_FAVORITES_NAMESPACE } from '../store/modules/publicFavorites.js'
import { ensureFavoriteMarkerIcon, favoriteMarkerIconId } from '../utils/favoriteMarkerIcon.ts'
import { getThemingColorFromCategoryKey } from '../utils/favoritesUtils.js'
import { getShouldMapUseImperial } from '../utils/mapUtils.js'

interface Favorite {
	id: number
	lat: number
	lng: number
	category: string
	name?: string | null
	comment?: string | null
}

const props = withDefaults(defineProps<{
	favoriteCategories: Record<string, Favorite[]>
	isPublicShare?: boolean
	allowFavoriteEdits?: boolean
}>(), {
	isPublicShare: false,
	allowFavoriteEdits: false,
})

const emit = defineEmits<{
	'add-favorite': [data: { lat: number, lng: number, name: string, category: string | null, comment: string }]
	'update-favorite': [data: { id: number, name: string, category: string, comment: string, lat: number, lng: number }]
	'delete-favorite': [data: { id: number }]
}>()

const SOURCE_ID = 'public-favorites'
const CLUSTER_LAYER_ID = 'public-favorites-clusters'
const CLUSTER_COUNT_LAYER_ID = 'public-favorites-cluster-count'
const POINT_LAYER_ID = 'public-favorites-points'
// Matches the old cluster-marker view size (36px) + old cluster radius margin (10px)
const CLUSTER_RADIUS = 46
// The old code disabled clustering entirely from this zoom level on, so a
// favorite selected from the sidebar is always shown as its own marker.
const CLUSTER_MAX_ZOOM_LEVEL = 14

const store = useStore()
const mapInstance = useMap()
const scaleUnit = getShouldMapUseImperial() ? 'imperial' : 'metric'

const allFavorites = computed<Favorite[]>(() => Object.values(props.favoriteCategories).flat())

const usedColors = computed<string[]>(() => {
	const seen = new Set<string>(Object.keys(props.favoriteCategories).map((key) => getThemingColorFromCategoryKey(key)))
	if (seen.size === 0) {
		seen.add(getThemingColorFromCategoryKey(''))
	}
	return [...seen]
})

const clusterIconId = computed(() => favoriteMarkerIconId(usedColors.value[0]))

const geojson = computed(() => ({
	type: 'FeatureCollection' as const,
	features: allFavorites.value.map((f) => ({
		type: 'Feature' as const,
		geometry: { type: 'Point' as const, coordinates: [f.lng, f.lat] },
		properties: {
			id: f.id,
			iconId: favoriteMarkerIconId(getThemingColorFromCategoryKey(f.category)),
			name: f.name || '',
			category: f.category,
			comment: f.comment || '',
		},
	})),
}))

watch([() => mapInstance.isLoaded, usedColors], () => {
	const map = mapInstance.map
	if (!mapInstance.isLoaded || !map) {
		return
	}
	usedColors.value.forEach((color) => ensureFavoriteMarkerIcon(map, color))
}, { immediate: true })

/**
 * Matches the old app's behavior of re-fitting the view to all favorites
 * whenever the favorite list changes, including on the initial load.
 */
function fitBoundsToFavorites(): void {
	const map = mapInstance.map
	if (!map || allFavorites.value.length === 0) {
		return
	}
	const lngs = allFavorites.value.map((f) => f.lng)
	const lats = allFavorites.value.map((f) => f.lat)
	map.fitBounds([
		[Math.min(...lngs) - 0.001, Math.min(...lats) - 0.001],
		[Math.max(...lngs) + 0.001, Math.max(...lats) + 0.001],
	], { padding: 30 })
}

watch([() => mapInstance.isLoaded, allFavorites], ([loaded]) => {
	if (loaded) {
		fitBoundsToFavorites()
	}
}, { immediate: true })

let popup: Popup | null = null
let popupApp: App | null = null

function closePopup(): void {
	popup?.remove()
}

/**
 * Mounts a popup form component (ClickPopup) into a plain
 * maplibregl.Popup, as its own Vue app instance sharing the page's store.
 *
 * @param component the popup form component to mount
 * @param componentProps props (including "on"-prefixed event listeners) for the component
 * @param lngLat where to anchor the popup
 */
function openPopup(component: typeof ClickPopup, componentProps: Record<string, unknown>, lngLat: [number, number]): void {
	closePopup()
	const map = mapInstance.map
	if (!map) {
		return
	}

	const container = document.createElement('div')
	const app = createApp(component, componentProps)
	app.config.globalProperties.t = t
	app.config.globalProperties.n = n
	app.use(store)
	app.mount(container)
	popupApp = app

	// on() returns a subscription, not the popup, so it cannot be chained
	popup = new Popup({ closeOnClick: false, maxWidth: 'none' })
		.setLngLat(lngLat)
		.setDOMContent(container)
	popup.on('close', handlePopupClose)
	popup.addTo(map)
}

function handlePopupClose(): void {
	popupApp?.unmount()
	popupApp = null
	popup = null
}

/**
 * @param latLng where on the map was clicked
 */
function openClickPopup(latLng: { lat: number, lng: number }): void {
	openPopup(ClickPopup, {
		isVisible: true,
		latLng,
		allowCategoryCustomization: !props.isPublicShare,
		allowEdits: props.allowFavoriteEdits,
		onClose: () => closePopup(),
		onAddFavorite: (data: { lat: number, lng: number, name: string, category: string | null, comment: string }) => {
			emit('add-favorite', data)
		},
	}, [latLng.lng, latLng.lat])
}

/**
 * @param wrapper the underlying MapLibre mouse event, wrapped by vue-maplibre-gl
 */
function onMapClick(wrapper: { event: MapMouseEvent }): void {
	const map = mapInstance.map
	if (!map) {
		return
	}
	const hits = map.queryRenderedFeatures(wrapper.event.point, { layers: [POINT_LAYER_ID, CLUSTER_LAYER_ID] })
	if (hits.length) {
		// handled by onPointClick/onClusterClick instead
		return
	}
	openClickPopup({ lat: wrapper.event.lngLat.lat, lng: wrapper.event.lngLat.lng })
}

/**
 * @param event the underlying MapLibre mouse event
 */
function onPointClick(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const feature = event.features?.[0]
	const favorite = feature && allFavorites.value.find((f) => f.id === feature.properties.id)
	if (favorite) {
		// shown in the favorite sidebar of the page
		store.dispatch(`${PUBLIC_FAVORITES_NAMESPACE}/selectFavorite`, favorite.id)
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
		map.easeTo({ center: (feature.geometry as { coordinates: [number, number] }).coordinates, zoom })
	}).catch(() => {})
}

/**
 * @param event the underlying MapLibre mouse event
 */
function onMarkerMouseEnter(event: MapMouseEvent): void {
	event.target.getCanvas().style.cursor = 'pointer'
}

/**
 * @param event the underlying MapLibre mouse event
 */
function onMarkerMouseLeave(event: MapMouseEvent): void {
	event.target.getCanvas().style.cursor = ''
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
	map.on('mouseenter', POINT_LAYER_ID, onMarkerMouseEnter)
	map.on('mouseleave', POINT_LAYER_ID, onMarkerMouseLeave)
	map.on('mouseenter', CLUSTER_LAYER_ID, onMarkerMouseEnter)
	map.on('mouseleave', CLUSTER_LAYER_ID, onMarkerMouseLeave)
}, { immediate: true })

const selectedFavoriteId = computed<number | null>(() => (
	isRunningAsPublicShare() ? store.state[PUBLIC_FAVORITES_NAMESPACE]?.selectedFavoriteId ?? null : null
))

watch(selectedFavoriteId, (id) => {
	const map = mapInstance.map
	const favorite = id !== null ? allFavorites.value.find((f) => f.id === id) : undefined
	if (!map || !favorite) {
		return
	}
	map.easeTo({ center: [favorite.lng, favorite.lat], zoom: Math.max(map.getZoom(), CLUSTER_MAX_ZOOM_LEVEL) })
})

onBeforeUnmount(() => {
	popupApp?.unmount()
	popup?.remove()
	const map = mapInstance.map
	if (!map || !listenersBound) {
		return
	}
	map.off('click', POINT_LAYER_ID, onPointClick)
	map.off('click', CLUSTER_LAYER_ID, onClusterClick)
	map.off('mouseenter', POINT_LAYER_ID, onMarkerMouseEnter)
	map.off('mouseleave', POINT_LAYER_ID, onMarkerMouseLeave)
	map.off('mouseenter', CLUSTER_LAYER_ID, onMarkerMouseEnter)
	map.off('mouseleave', CLUSTER_LAYER_ID, onMarkerMouseLeave)
})
</script>

<style lang="scss" scoped>
.map-container {
	position: relative;
	width: 100%;
	height: 100%;
}

:deep(.maplibregl-map) {
	width: 100%;
	height: 100%;
}
</style>
