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

	The turn-by-turn instructions list is rebuilt from the raw OSRM/
	Mapbox response ourselves (../../../utils/routingInstructions.ts),
	since maplibre-gl-directions only draws the route and doesn't decode
	it into textual steps.

	Not ported: the per-position (begin/via/end) waypoint marker icons -
	the library's default marker styling is used instead.
-->
<template>
	<MglCustomControl position="top-left">
		<div v-show="visible" class="maps-routing-control" :class="{ mobile: isMobile, desktop: !isMobile }">
			<div class="routing-header">
				<Directions :size="20" />
				<span class="title">{{ t('maps', 'Find directions') }}</span>
				<NcButton variant="tertiary" :aria-label="t('maps', 'Close')" @click="emit('close')">
					<template #icon>
						<CloseIcon :size="20" />
					</template>
				</NcButton>
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
				:plan-ready="routeReady"
				@add-step="addRoutePoint"
				@step-selected="setRoutePoint"
				@delete-step="deleteRoutePoint"
				@export-route="onExportRoute"
				@zoom-route="onZoomRoute"
				@reverse-steps="reverseWaypoints" />

			<p v-if="summary" class="routing-summary">
				{{ summary }}
			</p>

			<ol v-if="instructions.length" class="routing-instructions">
				<li v-for="(instr, i) in instructions" :key="i" @click="onInstructionClick(instr)">
					<span class="instruction-icon">{{ instructionIcon(instr, i) }}</span>
					<span class="instruction-text">{{ formatInstructionText(instr, i) }}</span>
					<span v-if="instr.distance" class="instruction-distance">{{ metersToDistance(instr.distance) }}</span>
				</li>
			</ol>
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
import { NcButton, useIsMobile } from '@nextcloud/vue'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { MglCustomControl, useMap } from 'vue-maplibre-gl'
import Directions from 'vue-material-design-icons/Directions.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import { metersToDistance } from '../../../utils.js'
import * as network from '../../../network.js'
import optionsController from '../../../optionsController.js'
import { buildInstructions, formatInstructionText, instructionIcon } from '../../../utils/routingInstructions.ts'
import type { Instruction, RawRoute } from '../../../utils/routingInstructions.ts'
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
const latestDirections = ref<DirectionsResponse | null>(null)

const waypointCoords = computed<[number, number][]>(() => {
	return steps.value.filter((s) => s.latLng).map((s) => [s.latLng!.lng, s.latLng!.lat])
})

const hasWaypoints = computed(() => waypointCoords.value.length >= 2)
const routeReady = computed(() => !!latestDirections.value?.routes?.length)

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

/**
 * @param routerType one of `routers`' `type` values
 */
function buildDirections(routerType: string): MapLibreGlDirections | null {
	const map = mapInstance.map
	const router = routers.value.find((r) => r.type === routerType)
	if (!mapInstance.isLoaded || !map || !router) {
		return null
	}
	const instance = new MapLibreGlDirections(map, {
		api: router.api,
		profile: router.profile,
		requestOptions: { ...router.requestOptions, geometries: 'geojson', overview: 'full', steps: 'true' },
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

watch([() => mapInstance.isLoaded, routers], ([loaded, newRouters]) => {
	if (!loaded || newRouters.some((r) => r.type === selectedRouterType.value)) {
		return
	}
	const optionValues = optionsController.optionValues as Record<string, string>
	const preferred = optionValues.selectedRouter && newRouters.find((r) => r.type === optionValues.selectedRouter)
	setRouter((preferred ?? newRouters[0])?.type ?? '')
}, { immediate: true })

function syncWaypoints() {
	if (!directions.value) {
		return
	}
	if (hasWaypoints.value) {
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

const instructions = computed<Instruction[]>(() => {
	const route = latestDirections.value?.routes?.[0] as RawRoute | undefined
	return route?.legs ? buildInstructions(route) : []
})

/**
 * @param instr the clicked instruction
 */
function onInstructionClick(instr: Instruction) {
	if (instr.location) {
		mapInstance.map?.flyTo({ center: instr.location, zoom: 16 })
	}
}

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
	margin-left: 56px !important;
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

	.routing-instructions {
		max-height: 250px;
		margin: 5px 0 0 0;
		padding: 0;
		overflow-y: auto;
		list-style: none;

		li {
			display: flex;
			align-items: baseline;
			gap: 8px;
			padding: 4px 2px;
			cursor: pointer;
			border-top: 1px solid var(--color-border);

			&:hover {
				background-color: var(--color-background-hover);
			}
		}

		.instruction-icon {
			flex: 0 0 1.2em;
			text-align: center;
		}

		.instruction-text {
			flex-grow: 1;
		}

		.instruction-distance {
			flex: 0 0 auto;
			color: var(--color-text-maxcontrast);
			font-size: 12px;
		}
	}
}
</style>

<style lang="scss">
/*
 * MapLibre's own .maplibregl-ctrl-group chrome (white background,
 * border-radius, box-shadow) is meant for small icon toolbars, not a
 * full panel with its own border/background - it can't be reached
 * with a scoped :deep() selector since it's an ANCESTOR of this
 * component's content, not a descendant. :has() targets it globally
 * without also affecting other controls' groups.
 */
.maplibregl-ctrl-group:has(> .maps-routing-control) {
	background: transparent;
	border-radius: 0;
	box-shadow: none;
}
</style>
