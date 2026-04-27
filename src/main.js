/**
 * @copyright Copyright (c) 2020, Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { createApp } from 'vue'
import App from './views/App.vue'
import './bootstrap.js'
import optionsController from './optionsController.js'
import './css/style.scss'

import { emit } from '@nextcloud/event-bus'
import { TooltipDirective as Tooltip } from 'floating-vue'

import { generateUrl } from '@nextcloud/router'

import L from 'leaflet'
import 'lrm-graphhopper'
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'
import { isPublic } from './utils/common.js'
delete L.Icon.Default.prototype._getIconUrl

L.Icon.Default.mergeOptions({
	iconRetinaUrl: markerIcon2x,
	iconUrl: markerIcon,
	shadowUrl: markerShadow,
})

// Vue
// eslint-disable-next-line
'use strict'

// Maps actions registrations (other apps that want to receive a position)
if (!window.OCA.Maps) {
	window.OCA.Maps = {
		mapActions: [],
	}
}

window.OCA.Maps.registerMapsAction = ({ label, callback, icon }) => {
	const mapAction = {
		label,
		callback,
		icon,
	}

	window.OCA.Maps.mapActions.push(mapAction)
}

// SIDEBAR
if (!window.OCA.Files) {
	window.OCA.Files = {}
}
// register unused client for the sidebar to have access to its parser methods
if (!window.OCA.Files.Sidebar) {
	Object.assign(window.OCA.Files, {
		Sidebar: {
			state: {
				file: '',
			},
			open: (path) => {
				emit('files:sidebar:opened')
			},
			close: () => {
				emit('files:sidebar:closed')
			},
			setFullScreenMode: () => {}, // SIDEBARFULLSCREEN,
		},
	}, window.OCA.Files)
}

if (window.navigator.registerProtocolHandler) {
	window.navigator.registerProtocolHandler('geo', generateUrl('/apps/maps/openGeoLink/') + '%s', 'Nextcloud Maps')
}

document.addEventListener('DOMContentLoaded', (event) => {
	optionsController.restoreOptions(main)
})

function main() {
	const app = createApp(App)
	app.directive('tooltip', Tooltip)
	app.config.globalProperties.t = window.t
	app.config.globalProperties.n = window.n
	app.mount('#content')
}
