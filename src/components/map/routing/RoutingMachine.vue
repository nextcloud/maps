<template>
	<template>
		<MglGeoJsonSource v-if="routeGeoJson"
			source-id="routing-route"
			:data="routeGeoJson">
			<MglLineLayer
				layer-id="routing-route-outline"
				:layout="{ 'line-join': 'round', 'line-cap': 'round' }"
				:paint="{ 'line-color': 'black', 'line-opacity': 0.15, 'line-width': 9 }" />
			<MglLineLayer
				layer-id="routing-route-white"
				:layout="{ 'line-join': 'round', 'line-cap': 'round' }"
				:paint="{ 'line-color': 'white', 'line-opacity': 0.8, 'line-width': 6 }" />
			<MglLineLayer
				layer-id="routing-route-blue"
				:layout="{ 'line-join': 'round', 'line-cap': 'round' }"
				:paint="{ 'line-color': 'blue', 'line-opacity': 1, 'line-width': 2 }" />
		</MglGeoJsonSource>
		<MglMarker v-for="(wpt, i) in validWaypoints"
			:key="'wpt-' + i"
			:coordinates="[wpt.lng, wpt.lat]"
			:draggable="true"
			@update:coordinates="(ll) => onWaypointMoved(i, ll)">
			<template #marker>
				<div :class="['route-waypoint', i === 0 ? 'route-begin-waypoint' : i === validWaypoints.length - 1 ? 'route-end-waypoint' : 'route-middle-waypoint']" />
			</template>
		</MglMarker>
	</template>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import moment from '@nextcloud/moment'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { MglGeoJsonSource, MglLineLayer, MglMarker } from '@indoorequal/vue-maplibre-gl'
import * as network from '../../../network.js'
import optionsController from '../../../optionsController.js'

const props = defineProps({
	map: {
		type: Object,
		required: true,
	},
	visible: {
		type: Boolean,
		default: true,
	},
})

const emit = defineEmits(['plan-ready-changed', 'routers-ready', 'routing-start', 'routing-error', 'routing-end', 'route-selected', 'track-added'])

const routers = ref({})
const selectedRouter = ref(null)
const waypoints = ref([null, null])
const routeGeoJson = ref(null)
const selectedRoute = ref(null)

const validWaypoints = computed(() => waypoints.value.filter((w) => w !== null))
const planIsReady = computed(() =>
	waypoints.value.length >= 2 && waypoints.value[0] !== null && waypoints.value[waypoints.value.length - 1] !== null,
)

watch(waypoints, () => {
	emit('plan-ready-changed', planIsReady.value)
	if (planIsReady.value) {
		route()
	}
}, { deep: true })

initRouting()

function initRouting() {
	const optionsValues = optionsController.optionValues
	const r = {}

	if ('osrmCarURL' in optionsValues && optionsValues.osrmCarURL !== '') {
		r.osrmCar = { name: '\u{1F697} ' + t('maps', 'By car (OSRM)'), url: optionsValues.osrmCarURL, profile: 'car', type: 'osrm' }
	}
	if ('osrmBikeURL' in optionsValues && optionsValues.osrmBikeURL !== '') {
		r.osrmBike = { name: '\u{1F6B2} ' + t('maps', 'By bike (OSRM)'), url: optionsValues.osrmBikeURL, profile: 'bicycle', type: 'osrm' }
	}
	if ('osrmFootURL' in optionsValues && optionsValues.osrmFootURL !== '') {
		r.osrmFoot = { name: '\u{1F6B6} ' + t('maps', 'By foot (OSRM)'), url: optionsValues.osrmFootURL, profile: 'foot', type: 'osrm' }
	}
	if (Object.keys(r).length === 0 && 'osrmDEMO' in optionsValues && optionsValues.osrmDEMO === '1') {
		r.osrmDEMO = { name: '\u{1F697} By car (OSRM demo)', url: 'https://router.project-osrm.org/route/v1', profile: 'car', type: 'osrm' }
	} else if (Object.keys(r).length === 0 && (getCurrentUser()?.isAdmin || Object.keys(r).length === 0)) {
		r.osrmDEMO = { name: '\u{1F697} By car (OSRM demo)', url: 'https://router.project-osrm.org/route/v1', profile: 'car', type: 'osrm' }
	}

	routers.value = r
	emit('routers-ready', r)

	if ('selectedRouter' in optionsValues && optionsValues.selectedRouter !== '' && optionsValues.selectedRouter in r) {
		selectedRouter.value = optionsValues.selectedRouter
	} else {
		selectedRouter.value = Object.keys(r)[0] || null
	}
}

async function route() {
	if (!planIsReady.value || !selectedRouter.value) return

	const router = routers.value[selectedRouter.value]
	if (!router) return

	emit('routing-start')

	const coords = waypoints.value.filter(Boolean).map((w) => w.lng + ',' + w.lat).join(';')
	const url = router.url + '/' + router.profile + '/' + coords + '?overview=full&geometries=geojson&steps=true'

	try {
		const response = await fetch(url)
		const data = await response.json()

		if (data.code !== 'Ok' || !data.routes || data.routes.length === 0) {
			showError(t('maps', 'Routing error:') + ' ' + (data.message || data.code))
			emit('routing-error')
			emit('plan-ready-changed', false)
			return
		}

		selectedRoute.value = data.routes[0]
		routeGeoJson.value = { type: 'Feature', geometry: data.routes[0].geometry }
		emit('routing-end')
		emit('plan-ready-changed', true)
		emit('route-selected')
	} catch (error) {
		showError(t('maps', 'Routing error:') + ' ' + error.message)
		emit('routing-error')
		emit('plan-ready-changed', false)
	}
}

function onWaypointMoved(i, lngLat) {
	const updated = [...waypoints.value]
	updated[i] = { lat: lngLat.lat, lng: lngLat.lng }
	waypoints.value = updated
}

function setRouteFrom(latlng) {
	const updated = [...waypoints.value]
	updated[0] = { lat: latlng.lat, lng: latlng.lng }
	waypoints.value = updated
}

function setRouteTo(latlng) {
	const updated = [...waypoints.value]
	updated[updated.length - 1] = { lat: latlng.lat, lng: latlng.lng }
	waypoints.value = updated
}

function addRoutePoint(latlng) {
	const updated = [...waypoints.value]
	updated.splice(updated.length - 1, 0, { lat: latlng.lat, lng: latlng.lng })
	waypoints.value = updated
}

function deleteRoutePoint(i) {
	const updated = [...waypoints.value]
	if (updated.length <= 2) {
		updated[i] = null
	} else {
		updated.splice(i, 1)
	}
	waypoints.value = updated
}

function setRoutePoint(i, latlng) {
	const updated = [...waypoints.value]
	updated[i] = { lat: latlng.lat, lng: latlng.lng }
	waypoints.value = updated
}

function reverseWaypoints() {
	waypoints.value = [...waypoints.value].reverse()
}

function setRouter(routerType) {
	selectedRouter.value = routerType
	if (planIsReady.value) {
		route()
	}
}

function onExportRoute() {
	if (selectedRoute.value?.geometry?.coordinates?.length > 0) {
		OC.dialogs.confirmDestructive(
			'',
			t('maps', 'Export as'),
			{
				type: OC.dialogs.YES_NO_BUTTONS,
				confirm: t('maps', 'GPX track'),
				confirmClasses: '',
				cancel: t('maps', 'GPX route'),
			},
			(result) => {
				if (result) {
					exportRoute('track')
				} else {
					exportRoute('route')
				}
			},
			true,
		)
	}
}

function exportRoute(type = 'route') {
	const coords = selectedRoute.value.geometry.coordinates.map((c) => ({ lat: c[1], lng: c[0] }))
	const name = type === 'route'
		? t('maps', 'Route {date}', { date: moment().format('LLL:ss') }).replaceAll(':', '')
		: t('maps', 'Track {date}', { date: moment().format('LLL:ss') }).replaceAll(':', '')
	const totDist = selectedRoute.value.distance
	const totTime = selectedRoute.value.duration

	network.exportRoute(type, coords, name, totDist, totTime, optionsController.myMapId).then((response) => {
		showSuccess(type === 'route'
			? t('maps', 'Route exported to {path}.', { path: response.data.path })
			: t('maps', 'Track exported to {path}.', { path: response.data.path }),
		)
		emit('track-added', response.data)
	}).catch((error) => {
		showError(type === 'route'
			? t('maps', 'Failed to export route')
			: t('maps', 'Failed to export track'))
		console.error(error)
	})
}

function onZoomRoute() {
	if (!selectedRoute.value?.geometry?.coordinates?.length) return
	const coords = selectedRoute.value.geometry.coordinates
	const lngs = coords.map((c) => c[0])
	const lats = coords.map((c) => c[1])
	const bounds = [[Math.min(...lngs), Math.min(...lats)], [Math.max(...lngs), Math.max(...lats)]]
	props.map.fitBounds(bounds, { padding: 40 })
}

defineExpose({ setRouteFrom, setRouteTo, addRoutePoint, deleteRoutePoint, setRoutePoint, reverseWaypoints, setRouter, onExportRoute, onZoomRoute })
</script>

<style lang="scss" scoped>
// nothing
</style>
