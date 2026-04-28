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
			<template #default>
				<div :class="['route-waypoint', i === 0 ? 'route-begin-waypoint' : i === validWaypoints.length - 1 ? 'route-end-waypoint' : 'route-middle-waypoint']" />
			</template>
		</MglMarker>
	</template>
</template>

<script>
import { getLocale } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import moment from '@nextcloud/moment'
import { showSuccess, showError } from '@nextcloud/dialogs'

import { MglGeoJsonSource, MglLineLayer, MglMarker } from '@indoorequal/vue-maplibre-gl'

import * as network from '../../../network.js'
import optionsController from '../../../optionsController.js'

export default {
	name: 'RoutingMachine',

	components: {
		MglGeoJsonSource,
		MglLineLayer,
		MglMarker,
	},

	props: {
		map: {
			type: Object,
			required: true,
		},
		visible: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			locale: getLocale(),
			nbRouters: optionsController.nbRouters,
			routers: {},
			selectedRouter: null,
			waypoints: [null, null],
			routeGeoJson: null,
			selectedRoute: null,
		}
	},

	computed: {
		validWaypoints() {
			return this.waypoints.filter((w) => w !== null)
		},
		planIsReady() {
			return this.waypoints.length >= 2 && this.waypoints[0] !== null && this.waypoints[this.waypoints.length - 1] !== null
		},
	},

	watch: {
		waypoints: {
			deep: true,
			handler() {
				this.$emit('plan-ready-changed', this.planIsReady)
				if (this.planIsReady) {
					this.route()
				}
			},
		},
	},

	created() {
		this.initRouting()
	},

	methods: {
		initRouting() {
			const optionsValues = optionsController.optionValues
			const routers = {}

			if ('osrmCarURL' in optionsValues && optionsValues.osrmCarURL !== '') {
				routers.osrmCar = { name: '\u{1F697} ' + t('maps', 'By car (OSRM)'), url: optionsValues.osrmCarURL, profile: 'car', type: 'osrm' }
			}
			if ('osrmBikeURL' in optionsValues && optionsValues.osrmBikeURL !== '') {
				routers.osrmBike = { name: '\u{1F6B2} ' + t('maps', 'By bike (OSRM)'), url: optionsValues.osrmBikeURL, profile: 'bicycle', type: 'osrm' }
			}
			if ('osrmFootURL' in optionsValues && optionsValues.osrmFootURL !== '') {
				routers.osrmFoot = { name: '\u{1F6B6} ' + t('maps', 'By foot (OSRM)'), url: optionsValues.osrmFootURL, profile: 'foot', type: 'osrm' }
			}
			if (Object.keys(routers).length === 0 && 'osrmDEMO' in optionsValues && optionsValues.osrmDEMO === '1') {
				routers.osrmDEMO = { name: '\u{1F697} By car (OSRM demo)', url: 'https://router.project-osrm.org/route/v1', profile: 'car', type: 'osrm' }
			} else if (Object.keys(routers).length === 0 && (getCurrentUser()?.isAdmin || Object.keys(routers).length === 0)) {
				routers.osrmDEMO = { name: '\u{1F697} By car (OSRM demo)', url: 'https://router.project-osrm.org/route/v1', profile: 'car', type: 'osrm' }
			}

			this.routers = routers
			this.$emit('routers-ready', routers)

			if ('selectedRouter' in optionsValues && optionsValues.selectedRouter !== '' && optionsValues.selectedRouter in routers) {
				this.selectedRouter = optionsValues.selectedRouter
			} else {
				this.selectedRouter = Object.keys(routers)[0] || null
			}
		},

		async route() {
			if (!this.planIsReady || !this.selectedRouter) return

			const router = this.routers[this.selectedRouter]
			if (!router) return

			this.$emit('routing-start')

			const coords = this.waypoints.filter(Boolean).map((w) => w.lng + ',' + w.lat).join(';')
			const url = router.url + '/' + router.profile + '/' + coords + '?overview=full&geometries=geojson&steps=true'

			try {
				const response = await fetch(url)
				const data = await response.json()

				if (data.code !== 'Ok' || !data.routes || data.routes.length === 0) {
					showError(t('maps', 'Routing error:') + ' ' + (data.message || data.code))
					this.$emit('routing-error')
					this.$emit('plan-ready-changed', false)
					return
				}

				this.selectedRoute = data.routes[0]
				this.routeGeoJson = {
					type: 'Feature',
					geometry: data.routes[0].geometry,
				}
				this.$emit('routing-end')
				this.$emit('plan-ready-changed', true)
				this.$emit('route-selected')
			} catch (error) {
				showError(t('maps', 'Routing error:') + ' ' + error.message)
				this.$emit('routing-error')
				this.$emit('plan-ready-changed', false)
			}
		},

		onWaypointMoved(i, lngLat) {
			const updated = [...this.waypoints]
			updated[i] = { lat: lngLat.lat, lng: lngLat.lng }
			this.waypoints = updated
		},

		setRouteFrom(latlng) {
			const updated = [...this.waypoints]
			updated[0] = { lat: latlng.lat, lng: latlng.lng }
			this.waypoints = updated
		},

		setRouteTo(latlng) {
			const updated = [...this.waypoints]
			updated[updated.length - 1] = { lat: latlng.lat, lng: latlng.lng }
			this.waypoints = updated
		},

		addRoutePoint(latlng) {
			const updated = [...this.waypoints]
			updated.splice(updated.length - 1, 0, { lat: latlng.lat, lng: latlng.lng })
			this.waypoints = updated
		},

		deleteRoutePoint(i) {
			const updated = [...this.waypoints]
			if (updated.length <= 2) {
				updated[i] = null
			} else {
				updated.splice(i, 1)
			}
			this.waypoints = updated
		},

		setRoutePoint(i, latlng) {
			const updated = [...this.waypoints]
			updated[i] = { lat: latlng.lat, lng: latlng.lng }
			this.waypoints = updated
		},

		reverseWaypoints() {
			this.waypoints = [...this.waypoints].reverse()
		},

		setRouter(routerType) {
			this.selectedRouter = routerType
			if (this.planIsReady) {
				this.route()
			}
		},

		onExportRoute() {
			if (this.selectedRoute?.geometry?.coordinates?.length > 0) {
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
							this.exportRoute('track')
						} else {
							this.exportRoute('route')
						}
					},
					true,
				)
			}
		},

		exportRoute(type = 'route') {
			const coords = this.selectedRoute.geometry.coordinates.map((c) => ({
				lat: c[1],
				lng: c[0],
			}))
			const name = type === 'route'
				? t('maps', 'Route {date}', { date: moment().format('LLL:ss') }).replaceAll(':', '')
				: t('maps', 'Track {date}', { date: moment().format('LLL:ss') }).replaceAll(':', '')
			const totDist = this.selectedRoute.distance
			const totTime = this.selectedRoute.duration

			network.exportRoute(type, coords, name, totDist, totTime, optionsController.myMapId).then((response) => {
				showSuccess(type === 'route'
					? t('maps', 'Route exported to {path}.', { path: response.data.path })
					: t('maps', 'Track exported to {path}.', { path: response.data.path }),
				)
				this.$emit('track-added', response.data)
			}).catch((error) => {
				showError(type === 'route'
					? t('maps', 'Failed to export route')
					: t('maps', 'Failed to export track'))
				console.error(error)
			})
		},

		onZoomRoute() {
			if (!this.selectedRoute?.geometry?.coordinates?.length) return
			const coords = this.selectedRoute.geometry.coordinates
			const lngs = coords.map((c) => c[0])
			const lats = coords.map((c) => c[1])
			const bounds = [[Math.min(...lngs), Math.min(...lats)], [Math.max(...lngs), Math.max(...lats)]]
			this.map.fitBounds(bounds, { padding: 40 })
		},
	},
}
</script>

<style lang="scss" scoped>
// nothing
</style>
