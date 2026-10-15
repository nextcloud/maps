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
		<div v-show="visible"
			ref="panel"
			class="maps-routing-control"
			:class="{ mobile: isMobile, desktop: !isMobile }">
			<div class="maps-routing-control__header">
				<IconDirections :size="20" />
				<h2 class="maps-routing-control__title">
					{{ t('maps', 'Directions') }}
				</h2>
				<NcButton variant="tertiary"
					:title="t('maps', 'Close')"
					:aria-label="t('maps', 'Close')"
					@click="emit('close')">
					<template #icon>
						<IconClose :size="20" />
					</template>
				</NcButton>
			</div>

			<NcSelect v-if="routers.length > 1"
				class="maps-routing-control__router"
				:model-value="selectedRouter"
				:options="routers"
				:input-label="t('maps', 'Travel mode')"
				:clearable="false"
				:searchable="false"
				label="name"
				@update:model-value="setRouter($event.type)">
				<template #option="option">
					<span class="maps-routing-control__router-option">
						<component :is="option.icon" :size="20" />
						{{ option.name }}
					</span>
				</template>
				<template #selected-option="option">
					<span class="maps-routing-control__router-option">
						<component :is="option.icon" :size="20" />
						{{ option.name }}
					</span>
				</template>
			</NcSelect>
			<NcNoteCard v-else-if="routers.length === 0" type="warning">
				{{ t('maps', 'Routing is currently disabled.') }}
				<a v-if="isAdmin" :href="generateUrl('/settings/admin/additional#routing')" target="_blank">
					{{ t('maps', 'Add a routing service') }}
				</a>
			</NcNoteCard>

			<RoutingSteps :steps="steps"
				:search-data="searchData"
				@add-step="addRoutePoint"
				@step-selected="setRoutePoint"
				@delete-step="deleteRoutePoint"
				@reverse-steps="reverseWaypoints" />

			<template v-if="routeReady">
				<div class="maps-routing-control__summary">
					<div class="maps-routing-control__summary-text">
						<span class="maps-routing-control__duration">{{ summary.duration }}</span>
						<span class="maps-routing-control__distance">{{ summary.distance }}</span>
					</div>
					<NcButton variant="tertiary"
						:title="t('maps', 'Zoom to route')"
						:aria-label="t('maps', 'Zoom to route')"
						@click="onZoomRoute">
						<template #icon>
							<IconMagnifyPlus :size="20" />
						</template>
					</NcButton>
					<NcActions :aria-label="t('maps', 'Export route')"
						:title="t('maps', 'Export route')">
						<template #icon>
							<IconExport :size="20" />
						</template>
						<NcActionButton :close-after-click="true" @click="onExportRoute('track')">
							<template #icon>
								<IconMapMarkerPath :size="20" />
							</template>
							{{ t('maps', 'Export as GPX track') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="onExportRoute('route')">
							<template #icon>
								<IconRoad :size="20" />
							</template>
							{{ t('maps', 'Export as GPX route') }}
						</NcActionButton>
					</NcActions>
				</div>

				<ol v-if="instructions.length" class="maps-routing-control__instructions">
					<li v-for="(instr, i) in instructions" :key="i">
						<button type="button" @click="onInstructionClick(instr)">
							<span class="maps-routing-control__instruction-icon" aria-hidden="true">{{ instructionIcon(instr, i) }}</span>
							<span class="maps-routing-control__instruction-text">{{ formatInstructionText(instr, i) }}</span>
							<span v-if="instr.distance" class="maps-routing-control__instruction-distance">{{ metersToDistance(instr.distance) }}</span>
						</button>
					</li>
				</ol>
			</template>
		</div>
	</MglCustomControl>
</template>

<script setup lang="ts">
import type { Directions as DirectionsResponse, Route } from '@maplibre/maplibre-gl-directions'
import MapLibreGlDirections from '@maplibre/maplibre-gl-directions'
import { getCurrentUser } from '@nextcloud/auth'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'
import { NcActionButton, NcActions, NcButton, NcNoteCard, NcSelect, useIsMobile } from '@nextcloud/vue'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import type { Component } from 'vue'
import { MglCustomControl, useMap } from 'vue-maplibre-gl'
import IconBike from 'vue-material-design-icons/Bike.vue'
import IconCar from 'vue-material-design-icons/Car.vue'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconDirections from 'vue-material-design-icons/Directions.vue'
import IconExport from 'vue-material-design-icons/Export.vue'
import IconMagnifyPlus from 'vue-material-design-icons/MagnifyPlus.vue'
import IconMapMarkerPath from 'vue-material-design-icons/MapMarkerPath.vue'
import IconRoad from 'vue-material-design-icons/RoadVariant.vue'
import IconWalk from 'vue-material-design-icons/Walk.vue'
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
	icon: Component
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
const panel = ref<HTMLElement | null>(null)
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
	// values saved before the settings were trimmed can still carry stray whitespace, and the
	// profile is appended to the URL, so a trailing slash would end up doubled
	const cleanUrl = (url?: string) => url?.trim().replace(/\/+$/, '')
	const osrmCarURL = cleanUrl(optionValues.osrmCarURL)
	const osrmBikeURL = cleanUrl(optionValues.osrmBikeURL)
	const osrmFootURL = cleanUrl(optionValues.osrmFootURL)

	if (osrmCarURL) {
		result.push({ type: 'osrmCar', name: t('maps', 'By car (OSRM)'), icon: IconCar, api: osrmCarURL, profile: 'car', requestOptions: {} })
	}
	if (osrmBikeURL) {
		result.push({ type: 'osrmBike', name: t('maps', 'By bike (OSRM)'), icon: IconBike, api: osrmBikeURL, profile: 'bicycle', requestOptions: {} })
	}
	if (osrmFootURL) {
		result.push({ type: 'osrmFoot', name: t('maps', 'By foot (OSRM)'), icon: IconWalk, api: osrmFootURL, profile: 'foot', requestOptions: {} })
	}
	if (optionValues.mapboxAPIKEY) {
		const accessToken = optionValues.mapboxAPIKEY
		result.push({ type: 'mapbox/cycling', name: t('maps', 'By bike (Mapbox)'), icon: IconBike, api: 'https://api.mapbox.com/directions/v5', profile: 'mapbox/cycling', requestOptions: { access_token: accessToken } })
		result.push({ type: 'mapbox/walking', name: t('maps', 'By foot (Mapbox)'), icon: IconWalk, api: 'https://api.mapbox.com/directions/v5', profile: 'mapbox/walking', requestOptions: { access_token: accessToken } })
		result.push({ type: 'mapbox/driving-traffic', name: t('maps', 'By car with traffic (Mapbox)'), icon: IconCar, api: 'https://api.mapbox.com/directions/v5', profile: 'mapbox/driving-traffic', requestOptions: { access_token: accessToken } })
		result.push({ type: 'mapbox/driving', name: t('maps', 'By car without traffic (Mapbox)'), icon: IconCar, api: 'https://api.mapbox.com/directions/v5', profile: 'mapbox/driving', requestOptions: { access_token: accessToken } })
	}
	if (result.length === 0 && optionValues.osrmDEMO === '1') {
		result.push({ type: 'osrmDEMO', name: t('maps', 'By car (OSRM demo)'), icon: IconCar, api: 'https://router.project-osrm.org/route/v1', profile: 'car', requestOptions: {} })
	}
	return result
})

const selectedRouterType = ref<string>('')
const selectedRouter = computed(() => routers.value.find((r) => r.type === selectedRouterType.value) ?? null)
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
		const hadRoute = !!latestDirections.value?.routes?.length
		latestDirections.value = event.data.directions ?? null
		if (!hadRoute) {
			onZoomRoute()
		}
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
	const preferred = newRouters.find((r) => r.type === optionValues.selectedRouter)
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
		return { duration: '', distance: '' }
	}
	const totalMinutes = Math.round(duration / 60)
	const hours = Math.floor(totalMinutes / 60)
	const minutes = totalMinutes % 60
	return {
		duration: hours > 0
			? t('maps', '{hours} h {minutes} min', { hours, minutes })
			: t('maps', '{minutes} min', { minutes }),
		distance: metersToDistance(distance),
	}
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
	const map = mapInstance.map
	if (!map) {
		return
	}
	// keep the route clear of the panel, which covers the top-left of the map
	const padding = { top: 30, right: 30, bottom: 30, left: 30 }
	if (panel.value && !isMobile.value) {
		padding.left += panel.value.getBoundingClientRect().right - map.getContainer().getBoundingClientRect().left
	}
	map.fitBounds(boundsOf(coords), { padding })
}

/**
 * @param type whether to export the route as a GPX track or a GPX route
 */
function onExportRoute(type: 'track' | 'route') {
	const route = latestDirections.value?.routes?.[0] as Route | undefined
	const coords = (route?.geometry as { coordinates?: [number, number][] } | undefined)?.coordinates
	if (!route || !coords?.length) {
		return
	}
	exportRoute(type, coords, route)
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
// Same place as the search field: right of the navigation toggle
.maps-routing-control {
	--maps-routing-offset: calc(2 * var(--default-grid-baseline));
	display: flex;
	flex-direction: column;
	gap: calc(2 * var(--default-grid-baseline));
	max-height: calc(100vh - 150px);
	margin-inline-start: calc(var(--default-clickable-area) + var(--maps-routing-offset));
	padding: calc(2 * var(--default-grid-baseline));
	overflow-y: auto;
	border-radius: var(--border-radius-container-large, var(--border-radius-large));
	background-color: var(--color-main-background);
	box-shadow: 0 2px 8px var(--color-box-shadow);

	&.desktop {
		width: 380px;
	}

	&.mobile {
		width: calc(100vw - var(--default-clickable-area) - 3 * var(--maps-routing-offset));
	}

	&__header {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
		padding-inline-start: var(--default-grid-baseline);
	}

	&__title {
		flex-grow: 1;
		margin: 0;
		font-size: 1.2rem;
		font-weight: bold;
	}

	// more specific than NcSelect's own .nc-select.v-select.select margin
	&__router.nc-select {
		width: 100%;
		margin: 0;
	}

	&__router-option {
		display: flex;
		align-items: center;
		gap: calc(2 * var(--default-grid-baseline));
	}

	&__summary {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
		padding-block-start: calc(2 * var(--default-grid-baseline));
		border-top: 1px solid var(--color-border);
	}

	&__summary-text {
		display: flex;
		flex-grow: 1;
		align-items: baseline;
		gap: calc(2 * var(--default-grid-baseline));
		padding-inline-start: var(--default-grid-baseline);
	}

	&__duration {
		font-size: 1.2rem;
		font-weight: bold;
	}

	&__distance {
		color: var(--color-text-maxcontrast);
	}

	&__instructions {
		max-height: 300px;
		overflow-y: auto;

		button {
			display: flex;
			align-items: baseline;
			gap: calc(2 * var(--default-grid-baseline));
			width: 100%;
			// the server's global button styles give buttons a fixed height, long instructions wrap
			height: auto;
			min-height: var(--default-clickable-area);
			margin: 0;
			padding: var(--default-grid-baseline) calc(2 * var(--default-grid-baseline));
			border: none;
			border-radius: var(--border-radius-element);
			background: none;
			font-weight: normal;
			line-height: 1.4;
			text-align: start;
			white-space: normal;
			cursor: pointer;

			&:hover,
			&:focus-visible {
				background-color: var(--color-background-hover);
			}
		}
	}

	&__instruction-icon {
		flex: 0 0 1.2em;
		text-align: center;
	}

	&__instruction-text {
		flex-grow: 1;
	}

	&__instruction-distance {
		flex-shrink: 0;
		color: var(--color-text-maxcontrast);
		font-size: var(--font-size-small, 13px);
	}
}
</style>

<style lang="scss">
// More specific than MapLibre's own .maplibregl-ctrl-group:not(:empty), which draws a frame around the control
.maplibregl-map .maplibregl-ctrl.maplibregl-ctrl-group:has(> .maps-routing-control) {
	margin: calc(2 * var(--default-grid-baseline)) 0 0 calc(2 * var(--default-grid-baseline));
	background: transparent;
	border-radius: 0;
	box-shadow: none;
}

// The search field and the routing panel take turns in the top-left corner, the hidden one must not push the other down
.maplibregl-map .maplibregl-ctrl.maplibregl-ctrl-group:has(> .maps-routing-control[style*="display: none"]) {
	margin: 0;
}
</style>
