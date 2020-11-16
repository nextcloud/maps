<template>
	<div />
</template>

<script>
import { getLocale } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import L from 'leaflet'
import 'leaflet-control-geocoder/dist/Control.Geocoder'
import 'leaflet-control-geocoder/dist/Control.Geocoder.css'
import 'leaflet-routing-machine/dist/leaflet-routing-machine'
import 'leaflet-routing-machine/dist/leaflet-routing-machine.css'
import optionsController from '../../optionsController'

export default {
	name: 'RoutingControl',

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
			nbRouters: 0,
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
			const optionsValues = optionsController.optionValues
			this.nbRouters = 0
			if ('osrmCarURL' in optionsValues && optionsValues.osrmCarURL !== '') {
				this.nbRouters++
			}
			if ('osrmBikeURL' in optionsValues && optionsValues.osrmBikeURL !== '') {
				this.nbRouters++
			}
			if ('osrmFootURL' in optionsValues && optionsValues.osrmFootURL !== '') {
				this.nbRouters++
			}
			if ('mapboxAPIKEY' in optionsValues && optionsValues.mapboxAPIKEY !== '') {
				this.nbRouters++
			}
			if (('graphhopperURL' in optionsValues && optionsValues.graphhopperURL !== '')
				|| ('graphhopperAPIKEY' in optionsValues && optionsValues.graphhopperAPIKEY !== '')) {
				this.nbRouters++
			}
			if (this.nbRouters === 0 && !OC.isUserAdmin()) {
				// // disable routing and hide it to the user
				// // search bar
				// $('#route-submit').hide();
				// $('#search-submit').css('right', '10px');
				// // context menu: remove routing related items
				// mapController.map.contextmenu.removeItem(mapController.map.contextmenu._items[mapController.map.contextmenu._items.length-1].el);
				// mapController.map.contextmenu.removeItem(mapController.map.contextmenu._items[mapController.map.contextmenu._items.length-1].el);
				// mapController.map.contextmenu.removeItem(mapController.map.contextmenu._items[mapController.map.contextmenu._items.length-1].el);
				// mapController.map.contextmenu.removeItem(mapController.map.contextmenu._items[mapController.map.contextmenu._items.length-1].el);
				// // and we don't init routingController
			} else {
				this.initRoutingControl()
			}
		},
		initRoutingControl() {
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

			if (this.visible) {
				this.control.addTo(this.map)
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
			OC.Notification.showTemporary(t('maps', 'Routing error:') + ' ' + msg)
			this.onRoutingEnd()
			// $('.exportCurrentRoute').hide()
			document.querySelector('.exportCurrentRoute').style.display = 'none'
			this.$emit('routing-error')
		},

		onRoutingStart(e) {
			document.querySelector('.leaflet-routing-reverse-waypoints').classList.add('icon-loading-small')
			this.$emit('routing-start')
		},

		onRoutingEnd(e) {
			// $('.exportCurrentRoute').show();
			document.querySelector('.exportCurrentRoute').style.display = 'block'
			// $('.leaflet-routing-reverse-waypoints').removeClass('icon-loading-small');
			document.querySelector('.leaflet-routing-reverse-waypoints').classList.remove('icon-loading-small')
			// TODO understand why routingstart is sometimes triggered after routesfound
			// just in case routingstart is triggered again (weird):
			setTimeout(() => {
				// $('.leaflet-routing-reverse-waypoints').removeClass('icon-loading-small');
				document.querySelector('.leaflet-routing-reverse-waypoints').classList.remove('icon-loading-small')
			}, 5000)
			this.$emit('routing-end')
		},
		onCloseClick() {
			this.$emit('close')
		},
		toggleRouting() {
			if (!this.visible) {
				this.control.remove()
			} else {
				this.control.addTo(this.map)
				document.querySelector('.leaflet-routing-geocoder input').focus()

				// add router selector
				const select = document.createElement('select')
				select.setAttribute('id', 'router-select')

				for (const r in this.routers) {
					const router = this.routers[r]
					const option = document.createElement('option')
					option.setAttribute('value', r)
					option.textContent = router.name
					option.selected = r === this.selectedRouter
					select.appendChild(option)
				}

				const close = document.createElement('button')
				close.classList.add('icon-close')
				close.setAttribute('id', 'routing-close')
				close.addEventListener('click', this.onCloseClick)

				document.querySelector('.leaflet-routing-container').prepend(close)
				document.querySelector('.leaflet-routing-geocoders').appendChild(select)

				if (optionsController.nbRouters === 0 && OC.isUserAdmin()) {
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

				// export route button
				const exportTitle = t('maps', 'Export current route to GPX')
				// $('<button class="exportCurrentRoute" title="'+escapeHTML(exportTitle)+'">'+
				// '<span></span></button>').insertAfter('#router-select');
				const exportButton = document.createElement('button')
				exportButton.classList.add('exportCurrentRoute')
				exportButton.setAttribute('title', exportTitle)
				exportButton.appendChild(document.createElement('span'))

				select.parentNode.insertBefore(exportButton, select.nextSibling)
				exportButton.style.display = 'none'
			}
		},
	},
}
</script>
