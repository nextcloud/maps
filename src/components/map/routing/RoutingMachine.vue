<template>
	<div />
</template>

<script>
import { getLocale } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import moment from '@nextcloud/moment'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { isMobile } from '@nextcloud/vue'

import L from 'leaflet'
import 'leaflet-control-geocoder/dist/Control.Geocoder.js'
import 'leaflet-control-geocoder/dist/Control.Geocoder.css'
import 'leaflet-routing-machine/dist/leaflet-routing-machine.js'
import 'leaflet-routing-machine/dist/leaflet-routing-machine.css'

import * as network from '../../../network.js'
import optionsController from '../../../optionsController.js'

export default {
	name: 'RoutingMachine',

	mixins: [isMobile],

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
			control: null,
		}
	},

	watch: {
		visible() {
			this.toggleRouting()
		},
	},

	created() {
		this.initRouting()
	},

	methods: {
		initRouting() {
			if (this.nbRouters > 0 || getCurrentUser()?.isAdmin) {
				this.initRoutingControl()
			}
		},
		initRoutingControl() {
			const optionsValues = optionsController.optionValues

			this.beginIcon = L.divIcon({
				iconAnchor: [12, 25],
				className: 'route-waypoint route-begin-waypoint',
				html: '',
			})
			this.middleIcon = L.divIcon({
				iconAnchor: [12, 25],
				className: 'route-waypoint route-middle-waypoint',
				html: '',
			})
			this.endIcon = L.divIcon({
				iconAnchor: [12, 25],
				className: 'route-waypoint route-end-waypoint',
				html: '',
			})

			// this is for all routing engines except OSRM
			L.Routing.Localization[this.locale] = {
				directions: {
					N: t('maps', 'north'),
					NE: t('maps', 'northeast'),
					E: t('maps', 'east'),
					SE: t('maps', 'southeast'),
					S: t('maps', 'south'),
					SW: t('maps', 'southwest'),
					W: t('maps', 'west'),
					NW: t('maps', 'northwest'),
					SlightRight: t('maps', 'slight right'),
					Right: t('maps', 'right'),
					SharpRight: t('maps', 'sharp right'),
					SlightLeft: t('maps', 'slight left'),
					Left: t('maps', 'left'),
					SharpLeft: t('maps', 'sharp left'),
					Uturn: t('maps', 'Turn around'),
				},
				instructions: {
					// instruction, postfix if the road is named
					Head: [
						t('maps', 'Head {dir}'),
						t('maps', ' on {road}'),
					],
					Continue: [t('maps', 'Continue {dir}')],
					TurnAround: [t('maps', 'Turn around')],
					WaypointReached: [t('maps', 'Waypoint reached')],
					Roundabout: [
						t('maps', 'Take the {exitStr} exit in the roundabout'),
						t('maps', ' onto {road}'),
					],
					DestinationReached: [t('maps', 'Destination reached')],
					Fork: [
						t('maps', 'At the fork, turn {modifier}'),
						t('maps', ' onto {road}'),
					],
					Merge: [
						t('maps', 'Merge {modifier}'),
						t('maps', ' onto {road}'),
					],
					OnRamp: [
						t('maps', 'Turn {modifier} on the ramp'),
						t('maps', ' onto {road}'),
					],
					OffRamp: [
						t('maps', 'Take the ramp on the {modifier}'),
						t('maps', ' onto {road}'),
					],
					EndOfRoad: [
						t('maps', 'Turn {modifier} at the end of the road'),
						t('maps', ' onto {road}'),
					],
					Onto: t('maps', 'onto {road}'),
				},
				ui: {
					startPlaceholder: t('maps', 'Start'),
					viaPlaceholder: t('maps', 'Via {viaNumber}'),
					endPlaceholder: t('maps', 'Destination'),
				},
				formatOrder(n) {
					return n + 'º'
				},
				units: {
					meters: t('maps', 'm'),
					kilometers: t('maps', 'km'),
					yards: t('maps', 'yd'),
					miles: t('maps', 'mi'),
					hours: t('maps', 'h'),
					minutes: t('maps', 'min'),
					seconds: t('maps', 's'),
				},
			}
			this.routers.osrmDEMO = {
				name: '🚗 ' + t('maps', 'By car (OSRM demo)'),
				router: L.Routing.osrmv1({
					serviceUrl: 'https://router.project-osrm.org/route/v1',
					// profile: 'driving', // works with demo server
					profile: 'car', // works with demo server
					// profile: 'bicycle', // does not work with demo server...
					// profile: 'foot', // does not work with demo server...
					suppressDemoServerWarning: true,
					// this makes OSRM use our local translations
					// otherwise it uses osrm-text-instructions which requires to import another lib
					stepToText(e) {
					},
				}),
			}
			this.control = L.Routing.control({
				router: this.routers.osrmDEMO.router,
				position: 'topleft',
				routeWhileDragging: true,
				reverseWaypoints: true,
				geocoder: L.Control.Geocoder.nominatim(),
				language: this.locale,
				lineOptions: {
					styles: [
						{ color: 'black', opacity: 0.15, weight: 9 },
						{ color: 'white', opacity: 0.8, weight: 6 },
						{ color: 'blue', opacity: 1, weight: 2 },
					],
				},
				pointMarkerStyle: { radius: 5, color: '#03f', fillColor: 'white', opacity: 1, fillOpacity: 0.7 },
				createMarker: this.createMarker,
			})
				.on('routingerror', this.onRoutingError)
				.on('routingstart', this.onRoutingStart)
				.on('routesfound', this.onRoutingEnd)
				.on('routeselected', this.onRouteSelected)

			// add routers from options values
			let nbRoutersAdded = 0
			if ('osrmCarURL' in optionsValues && optionsValues.osrmCarURL !== '') {
				this.addRouter('osrmCar', '🚗 ' + t('maps', 'By car (OSRM)'), optionsValues.osrmCarURL, null)
				nbRoutersAdded++
			}
			if ('osrmBikeURL' in optionsValues && optionsValues.osrmBikeURL !== '') {
				this.addRouter('osrmBike', '🚲 ' + t('maps', 'By bike (OSRM)'), optionsValues.osrmBikeURL, null)
				nbRoutersAdded++
			}
			if ('osrmFootURL' in optionsValues && optionsValues.osrmFootURL !== '') {
				this.addRouter('osrmFoot', '🚶 ' + t('maps', 'By foot (OSRM)'), optionsValues.osrmFootURL, null)
				nbRoutersAdded++
			}
			if ('mapboxAPIKEY' in optionsValues && optionsValues.mapboxAPIKEY !== '') {
				this.addRouter('mapbox/cycling', '🚲 ' + t('maps', 'By bike (Mapbox)'), null, optionsValues.mapboxAPIKEY)
				this.addRouter('mapbox/walking', '🚶 ' + t('maps', 'By foot (Mapbox)'), null, optionsValues.mapboxAPIKEY)
				this.addRouter('mapbox/driving-traffic', '🚗 ' + t('maps', 'By car with traffic (Mapbox)'), null, optionsValues.mapboxAPIKEY)
				this.addRouter('mapbox/driving', '🚗 ' + t('maps', 'By car without traffic (Mapbox)'), null, optionsValues.mapboxAPIKEY)
				nbRoutersAdded++
			}
			if (('graphhopperURL' in optionsValues && optionsValues.graphhopperURL !== '')
				|| ('graphhopperAPIKEY' in optionsValues && optionsValues.graphhopperAPIKEY !== '')) {
				let apikey
				if ('graphhopperAPIKEY' in optionsValues && optionsValues.graphhopperAPIKEY !== '') {
					apikey = optionsValues.graphhopperAPIKEY
				}
				this.addRouter('graphhopperCar', '🚗 ' + t('maps', 'By car (GraphHopper)'), optionsValues.graphhopperURL, apikey)
				this.addRouter('graphhopperBike', '🚲 ' + t('maps', 'By bike (GraphHopper)'), optionsValues.graphhopperURL, apikey)
				this.addRouter('graphhopperFoot', '🚶 ' + t('maps', 'By Foot (GraphHopper)'), optionsValues.graphhopperURL, apikey)
				nbRoutersAdded++
			}
			if (nbRoutersAdded === 0 && 'osrmDEMO' in optionsValues && optionsValues.osrmDEMO === '1') {
				this.addRouter('osrmDEMO', '🚗 ' + 'By car (OSRM demo)', null, null)
			} else {
				delete this.routers.osrmDEMO
			}
			if ('selectedRouter' in optionsValues && optionsValues.selectedRouter !== '') {
				this.selectedRouter = optionsValues.selectedRouter
				this.setRouter(optionsValues.selectedRouter)
			} else {
				let fallback = null
				for (const type in this.routers) {
					fallback = type
					if (fallback) {
						break
					}
				}
				this.setRouter(fallback)
			}

			if (this.visible) {
				this.control.addTo(this.map)
			}
		},
		addRouter(type, name, url, apikey) {
			if (type === 'graphhopperBike' || type === 'graphhopperCar' || type === 'graphhopperFoot') {
				const options = {}
				if (type === 'graphhopperCar') {
					options.urlParameters = {
						// available ones : car, foot, bike, bike2, mtb, racingbike, motorcycle
						vehicle: 'car',
					}
				} else if (type === 'graphhopperBike') {
					options.urlParameters = {
						vehicle: 'bike',
					}
				} else if (type === 'graphhopperFoot') {
					options.urlParameters = {
						vehicle: 'foot',
					}
				}
				if (url) {
					options.serviceUrl = url
				}
				this.routers[type] = {
					name,
					router: L.Routing.graphHopper(apikey, options),
				}
			} else if (type === 'osrmBike' || type === 'osrmCar' || type === 'osrmFoot') {
				const options = {
					serviceUrl: url,
					suppressDemoServerWarning: true,
					// this makes OSRM use our local translations
					// otherwise it uses osrm-text-instructions which requires to import another lib
					stepToText(e) {
					},
				}
				if (type === 'osrmCar') {
					options.profile = 'car'
				} else if (type === 'osrmBike') {
					options.profile = 'bicycle'
				} else if (type === 'osrmFoot') {
					options.profile = 'foot'
				}
				this.routers[type] = {
					name,
					router: L.Routing.osrmv1(options),
				}
			} else if (type === 'mapbox/cycling' || type === 'mapbox/driving-traffic' || type === 'mapbox/driving' || type === 'mapbox/walking') {
				const options = {
					profile: type,
				}
				this.routers[type] = {
					name,
					router: L.Routing.mapbox(apikey, options),
				}
			}
		},
		setRouter(routerType) {
			if (routerType in this.routers) {
				const router = this.routers[routerType].router
				this.control._router = router
				this.control.options.router = router
			}
		},
		createMarker(i, wpt, n) {
			const icon = i === 0
				? this.beginIcon
				: i === n - 1
					? this.endIcon
					: this.middleIcon
			return L.marker(wpt.latLng, { icon, draggable: true })
		},
		onRoutingError(e) {
			let msg = e.error.target.responseText
			try {
				const json = JSON.parse(e.error.target.responseText)
				if (json.message) {
					msg = json.message
				}
			} catch (e) {
			}
			showError(t('maps', 'Routing error:') + ' ' + msg)
			this.onRoutingEnd()
			// document.querySelector('.exportCurrentRoute').style.display = 'none'
			this.$emit('plan-ready-changed', false)
			this.$emit('routing-error')
		},

		onRoutingStart(e) {
			const rev = document.querySelector('.leaflet-routing-reverse-waypoints')
			if (rev) {
				rev.classList.add('icon-loading-small')
			}
			this.$emit('routing-start')
		},

		onRoutingEnd(e) {
			// document.querySelector('.exportCurrentRoute').style.display = 'block'
			// document.querySelector('.leaflet-routing-reverse-waypoints').classList.remove('icon-loading-small')
			// TODO understand why routingstart is sometimes triggered after routesfound
			// just in case routingstart is triggered again (weird):
			setTimeout(() => {
				const rev = document.querySelector('.leaflet-routing-reverse-waypoints')
				if (rev) {
					rev.classList.remove('icon-loading-small')
				}
			}, 5000)
			this.$emit('routing-end')
			this.$emit('plan-ready-changed', true)
		},
		onRouteSelected(e) {
			this.$emit('route-selected')
		},
		setRouteFrom(latlng) {
			if (this.control) {
				this.control.spliceWaypoints(0, 1, latlng)
			}
		},
		setRouteTo(latlng) {
			if (this.control) {
				this.control.spliceWaypoints(this.control.getWaypoints().length - 1, 1, latlng)
			}
		},
		addRoutePoint(latlng) {
			if (this.control) {
				this.control.spliceWaypoints(this.control.getWaypoints().length - 1, 0, latlng)
			}
		},
		deleteRoutePoint(i) {
			if (this.control) {
				// don't delete first and last steps, just clear them
				if (this.control.getWaypoints().length <= 2) {
					this.control.spliceWaypoints(i, 1, null)
				} else {
					this.control.spliceWaypoints(i, 1)
				}
			}
		},
		setRoutePoint(i, latlng) {
			if (this.control) {
				this.control.spliceWaypoints(i, 1, latlng)
			}
		},
		reverseWaypoints() {
			const points = this.control.getWaypoints()
			points.reverse()
			this.control.setWaypoints(points)
		},
		onPlanChanged(e) {
			console.debug('control plan changed')
			console.debug(e)
			this.$emit('plan-changed', e.waypoints)
			this.$emit('plan-ready-changed', this.control.getPlan().isReady())
			/* if (!this.control.getPlan().isReady()) {
				document.querySelector('.exportCurrentRoute').style.display = 'none'
			} */
		},
		onPlanSpliced(e) {
			console.debug('control plan spliced')
			console.debug(e)
			this.$emit('plan-spliced', e)
		},
		toggleRouting() {
			if (!this.visible) {
				this.control.remove()
			} else {
				this.control.addTo(this.map)
				const routingContainer = document.querySelector('.leaflet-routing-container')
				routingContainer.classList.add(this.isMobile ? 'leaflet-routing-container-mobile' : 'leaflet-routing-container-desktop')
				// routingContainer.querySelector('.leaflet-routing-geocoder input').focus()

				// get event when plan is changing
				this.control.getPlan().addEventListener('waypointschanged', (e) => {
					this.onPlanChanged(e)
				})
				this.control.getPlan().addEventListener('waypointsspliced', (e) => {
					this.onPlanSpliced(e)
				})

				routingContainer.querySelector('.leaflet-routing-reverse-waypoints').setAttribute('title', t('maps', 'Reverse steps order'))
				routingContainer.querySelector('.leaflet-routing-add-waypoint').setAttribute('title', t('maps', 'Add step'))
				// trick to make this button stop listening to click event...sorry for that
				const el = routingContainer.querySelector('.leaflet-routing-add-waypoint')
				const elClone = el.cloneNode(true)
				el.parentNode.replaceChild(elClone, el)

				// add a waypoint before the last one (and not at the end like done by default)
				routingContainer.querySelector('.leaflet-routing-add-waypoint').addEventListener('click', (e) => {
					this.control.spliceWaypoints(this.control.getWaypoints().length - 1, 0, null)
				})

				// add router selector
				const select = document.createElement('select')
				select.setAttribute('id', 'router-select')
				const selectDiv = document.createElement('div')
				selectDiv.classList.add('router-container')
				selectDiv.appendChild(select)

				for (const r in this.routers) {
					const router = this.routers[r]
					const option = document.createElement('option')
					option.setAttribute('value', r)
					option.textContent = router.name
					option.selected = r === this.selectedRouter
					select.appendChild(option)
				}

				// select router
				select.addEventListener('change', (e) => {
					const type = e.target.value
					this.selectedRouter = type
					this.setRouter(type)
					optionsController.saveOptionValues({ selectedRouter: type })
					this.control.route()
				})

				document.querySelector('.leaflet-routing-container').prepend(selectDiv)

				if (!this.isMobile && this.nbRouters === 0 && OC.isUserAdmin()) {
					const p = document.createElement('p')
					p.textContent = t('maps', 'Routing is currently disabled.')
					const a = document.createElement('a')
					a.setAttribute('href', generateUrl('/settings/admin/additional#routing'))
					a.setAttribute('title', t('maps', 'Nextcloud additional settings'))
					a.setAttribute('target', '_blank')
					a.style.width = '100%'
					a.textContent = t('maps', 'Add a routing service')
					p.appendChild(a)
					document.querySelector('.leaflet-routing-container').prepend(p)
				}
			}
		},
		onExportRoute() {
			if (this.control._selectedRoute?.coordinates
				&& this.control._selectedRoute.coordinates.length > 0
			) {
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
			const latLngCoords = this.control._selectedRoute.coordinates
			const coords = latLngCoords.map((ll) => {
				return {
					lat: ll.lat,
					lng: ll.lng,
				}
			})
			const name = type === 'route'
				? t('maps', 'Route {date}', { date: moment().format('LLL:ss') }).replaceAll(':', '')
				: t('maps', 'Track {date}', { date: moment().format('LLL:ss') }).replaceAll(':', '')
			const totDist = this.control._selectedRoute.summary.totalDistance
			const totTime = this.control._selectedRoute.summary.totalTime

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
			}).then(() => {
			})
		},
		onZoomRoute() {
			const latLngCoords = this.control._selectedRoute.coordinates
			const coords = latLngCoords.map((ll) => {
				return [ll.lat, ll.lng]
			})
			this.map.fitBounds(L.latLngBounds(coords))
		},
	},
}
</script>

<style lang="scss" scoped>
// nothing
</style>
