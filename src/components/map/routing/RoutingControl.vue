<!--
 - SPDX-FileCopyrightText: 2020 Julien Veyssier <eneiluj@posteo.net>
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	Ported from Leaflet (leaflet-routing-machine) to
	@maplibre/maplibre-gl-directions. That library only speaks the
	OSRM and Mapbox-Directions-API request/response formats, so
	GraphHopper routing support was dropped rather than ported (the
	admin setting was removed too).

	MapLibreGlDirections' own click-to-add/drag-to-move waypoint
	interactivity is intentionally left disabled: the step list below
	already provides full waypoint CRUD, and mixing the two models
	doesn't work since this library has no concept of an empty/unset
	waypoint slot the way leaflet-routing-machine's plan did. It's used
	here purely as a "given these coordinates, draw the route" renderer.

	Not ported: the turn-by-turn instructions panel (this library only
	draws the route, it doesn't decode it into textual steps) and the
	per-position (begin/via/end) waypoint marker icons - the library's
	default marker styling is used instead.
-->
<template>
	<MglCustomControl position="top-left">
		<div v-show="visible" class="maps-routing-control" :class="{ mobile: isMobile, desktop: !isMobile }">
			<div class="routing-header">
				<Directions :size="20" />
				<span class="title">{{ t('maps', 'Find directions') }}</span>
				<button type="button" @click="emit('close')">
					<CloseIcon :size="20" />
				</button>
			</div>

			<select v-if="routers.length > 1"
				:value="selectedRouterType"
				class="router-select"
				@change="setRouter($event.target.value)">
				<option v-for="router in routers" :key="router.type" :value="router.type">
					{{ router.name }}
				</option>
			</select>
			<p v-else-if="routers.length === 0 && isAdmin" class="no-router">
				{{ t('maps', 'Routing is currently disabled.') }}
				<a :href="generateUrl('/settings/admin/additional#routing')" target="_blank">
					{{ t('maps', 'Add a routing service') }}
				</a>
			</p>

			<RoutingSteps
				:steps="steps"
				:search-data="searchData"
				:plan-ready="planReady"
				@add-step="addRoutePoint"
				@step-selected="setRoutePoint"
				@delete-step="deleteRoutePoint"
				@export-route="onExportRoute"
				@zoom-route="onZoomRoute"
				@reverse-steps="reverseWaypoints" />

			<p v-if="summary" class="routing-summary">
				{{ summary }}
			</p>
		</div>
	</MglCustomControl>
</template>

<script setup lang="ts">
import type { Directions as DirectionsResponse, Route } from '@maplibre/maplibre-gl-directions'
import MapLibreGlDirections from '@maplibre/maplibre-gl-directions'
import { getCurrentUser } from '@nextcloud/auth'
import { getDialogBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'
import { useIsMobile } from '@nextcloud/vue'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { MglCustomControl, useMap } from 'vue-maplibre-gl'
import Directions from 'vue-material-design-icons/Directions.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import * as network from '../../../network.js'
import optionsController from '../../../optionsController.js'
import RoutingSteps from './RoutingSteps.vue'

interface Step {
	latLng: { lat: number, lng: number } | null
	name: string
}

interface RouterOption {
	type: string
	name: string
	api: string
	profile: string
	requestOptions: Record<string, string>
}

defineProps<{
	visible: boolean
	searchData: unknown[]
}>()

const emit = defineEmits<{
	close: []
	'track-added': [track: unknown]
}>()

const isMobile = useIsMobile()
const isAdmin = getCurrentUser()?.isAdmin ?? false
const mapInstance = useMap()

const emptyStep = (): Step => ({ latLng: null, name: '' })
const steps = ref<Step[]>([emptyStep(), emptyStep()])

const routers = computed<RouterOption[]>(() => {
	const optionValues = optionsController.optionValues as Record<string, string>
	const result: RouterOption[] = []

	if (optionValues.osrmCarURL) {
		result.push({ type: 'osrmCar', name: '🚗 ' + t('maps', 'By car (OSRM)'), api: optionValues.osrmCarURL, profile: 'car', requestOptions: {} })
	}
	if (optionValues.osrmBikeURL) {
		result.push({ type: 'osrmBike', name: '🚲 ' + t('maps', 'By bike (OSRM)'), api: optionValues.osrmBikeURL, profile: 'bicycle', requestOptions: {} })
	}
	if (optionValues.osrmFootURL) {
		result.push({ type: 'osrmFoot', name: '🚶 ' + t('maps', 'By foot (OSRM)'), api: optionValues.osrmFootURL, profile: 'foot', requestOptions: {} })
	}
	if (optionValues.mapboxAPIKEY) {
		const accessToken = optionValues.mapboxAPIKEY
		result.push({ type: 'mapbox/cycling', name: '🚲 ' + t('maps', 'By bike (Mapbox)'), api: 'https://api.mapbox.com/directions/v5', profile: 'mapbox/cycling', requestOptions: { access_token: accessToken } })
		result.push({ type: 'mapbox/walking', name: '🚶 ' + t('maps', 'By foot (Mapbox)'), api: 'https://api.mapbox.com/directions/v5', profile: 'mapbox/walking', requestOptions: { access_token: accessToken } })
		result.push({ type: 'mapbox/driving-traffic', name: '🚗 ' + t('maps', 'By car with traffic (Mapbox)'), api: 'https://api.mapbox.com/directions/v5', profile: 'mapbox/driving-traffic', requestOptions: { access_token: accessToken } })
		result.push({ type: 'mapbox/driving', name: '🚗 ' + t('maps', 'By car without traffic (Mapbox)'), api: 'https://api.mapbox.com/directions/v5', profile: 'mapbox/driving', requestOptions: { access_token: accessToken } })
	}
	if (result.length === 0 && optionValues.osrmDEMO === '1') {
		result.push({ type: 'osrmDEMO', name: '🚗 ' + t('maps', 'By car (OSRM demo)'), api: 'https://router.project-osrm.org/route/v1', profile: 'car', requestOptions: {} })
	}
	return result
})

const selectedRouterType = ref<string>('')
const directions = ref<MapLibreGlDirections | null>(null)
const latestDirections = ref<DirectionsResponse | null>(null)

/**
 * @param routerType one of `routers`' `type` values
 */
function buildDirections(routerType: string): MapLibreGlDirections | null {
	const map = mapInstance.map
	const router = routers.value.find((r) => r.type === routerType)
	if (!map || !router) {
		return null
	}
	const instance = new MapLibreGlDirections(map, {
		api: router.api,
		profile: router.profile,
		requestOptions: { ...router.requestOptions, geometries: 'geojson', overview: 'full' },
	})
	instance.interactive = false
	instance.on('fetchroutesend', (event) => {
		latestDirections.value = event.data.directions ?? null
	})
	return instance
}

/**
 * @param routerType one of `routers`' `type` values
 */
function setRouter(routerType: string) {
	directions.value?.destroy()
	selectedRouterType.value = routerType
	optionsController.saveOptionValues({ selectedRouter: routerType })
	directions.value = buildDirections(routerType)
	syncWaypoints()
}

watch(routers, (newRouters) => {
	if (newRouters.some((r) => r.type === selectedRouterType.value)) {
		return
	}
	const optionValues = optionsController.optionValues as Record<string, string>
	const preferred = optionValues.selectedRouter && newRouters.find((r) => r.type === optionValues.selectedRouter)
	setRouter((preferred ?? newRouters[0])?.type ?? '')
}, { immediate: true })

const waypointCoords = computed<[number, number][]>(() => {
	return steps.value.filter((s) => s.latLng).map((s) => [s.latLng!.lng, s.latLng!.lat])
})

const planReady = computed(() => waypointCoords.value.length >= 2)

function syncWaypoints() {
	if (!directions.value) {
		return
	}
	if (planReady.value) {
		directions.value.setWaypoints(waypointCoords.value)
	} else {
		directions.value.clear()
		latestDirections.value = null
	}
}

watch(waypointCoords, syncWaypoints)

const summary = computed(() => {
	const route = latestDirections.value?.routes?.[0] as Route | undefined
	const distance = route?.distance
	const duration = route?.duration
	if (typeof distance !== 'number' || typeof duration !== 'number') {
		return ''
	}
	const km = (distance / 1000).toFixed(1)
	const minutes = Math.round(duration / 60)
	return t('maps', '{km} km, {minutes} min', { km, minutes })
})

/**
 * @param step the step to insert; defaults to an empty one
 */
function addRoutePoint(step: Step = emptyStep()) {
	steps.value.splice(steps.value.length - 1, 0, step)
}

/**
 * @param i index of the step to remove
 * @param step step to remove; defaults to an empty one
 */
function deleteRoutePoint(i: number) {
	if (steps.value.length <= 2) {
		steps.value[i] = emptyStep()
	} else {
		steps.value.splice(i, 1)
	}
}

/**
 * @param i index of the step to update
 * @param step the new step value
 */
function setRoutePoint(i: number, step: Step) {
	steps.value[i] = step
}

function reverseWaypoints() {
	steps.value = [...steps.value].reverse()
}

/**
 * @param coords route geometry coordinates as [lng, lat] pairs
 */
function boundsOf(coords: [number, number][]): [[number, number], [number, number]] {
	const lngs = coords.map((c) => c[0])
	const lats = coords.map((c) => c[1])
	return [[Math.min(...lngs), Math.min(...lats)], [Math.max(...lngs), Math.max(...lats)]]
}

function onZoomRoute() {
	const route = latestDirections.value?.routes?.[0] as Route | undefined
	const coords = (route?.geometry as { coordinates?: [number, number][] } | undefined)?.coordinates
	if (!coords?.length) {
		return
	}
	mapInstance.map?.fitBounds(boundsOf(coords), { padding: 30 })
}

function onExportRoute() {
	const route = latestDirections.value?.routes?.[0] as Route | undefined
	const coords = (route?.geometry as { coordinates?: [number, number][] } | undefined)?.coordinates
	if (!coords?.length) {
		return
	}
	getDialogBuilder(t('maps', 'Export as'))
		.addButton({ label: t('maps', 'GPX route'), callback: () => exportRoute('route', coords, route) })
		.addButton({ label: t('maps', 'GPX track'), callback: () => exportRoute('track', coords, route), variant: 'primary' })
		.build()
		.show()
}

/**
 * @param type whether to export as a GPX track or route
 * @param coords route geometry coordinates as [lng, lat] pairs
 * @param route the selected route, for its distance/duration summary
 */
function exportRoute(type: 'track' | 'route', coords: [number, number][], route: Route) {
	const points = coords.map(([lng, lat]) => ({ lat, lng }))
	const name = type === 'route'
		? t('maps', 'Route {date}', { date: moment().format('LLL:ss') }).replaceAll(':', '')
		: t('maps', 'Track {date}', { date: moment().format('LLL:ss') }).replaceAll(':', '')
	const totDist = route.distance as number
	const totTime = route.duration as number

	network.exportRoute(type, points, name, totDist, totTime, optionsController.myMapId).then((response) => {
		showSuccess(type === 'route'
			? t('maps', 'Route exported to {path}.', { path: response.data.path })
			: t('maps', 'Track exported to {path}.', { path: response.data.path }))
		emit('track-added', response.data)
	}).catch((error) => {
		showError(type === 'route' ? t('maps', 'Failed to export route') : t('maps', 'Failed to export track'))
		console.error(error)
	})
}

onBeforeUnmount(() => {
	directions.value?.destroy()
})
</script>

<style lang="scss" scoped>
.desktop {
	width: 350px;
	background-color: var(--color-main-background);
	padding: 5px;
	border: 2px solid var(--color-border-dark);
	border-radius: var(--border-radius-large);
}
.mobile {
	width: 100vw;
	background: var(--color-main-background);
	padding: 10px 8px 7px 8px;
}
.maps-routing-control {
	.routing-header {
		display: flex;
		align-items: center;
		margin-bottom: 5px;

		.title {
			flex-grow: 1;
			margin: 0 0 0 5px;
			font-size: 20px;
		}
		> button {
			padding: 0;
			margin: 0;
			width: 34px;
			background-color: transparent;
			border: 0;
		}
	}

	.router-select {
		width: 100%;
		margin-bottom: 5px;
	}

	.no-router {
		margin-bottom: 5px;
	}

	.routing-summary {
		margin: 5px 0 0 0;
		font-weight: bold;
	}
}
</style>
