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

import Vue from 'vue'
import App from './views/App.vue'
import './bootstrap.js'
import optionsController from './optionsController.js'
import '../css/style.scss'

import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'
import { emit } from '@nextcloud/event-bus'

import { generateUrl } from '@nextcloud/router'

// Fixing Some leaflet webpack stuff See https://vue2-leaflet.netlify.app/faq/#my-map-and-or-markers-don-t-fully-render-what-gives
import L from 'leaflet'
import 'lrm-graphhopper'
import { isPublic } from './utils/common.js'
delete L.Icon.Default.prototype._getIconUrl

L.Icon.Default.mergeOptions({
	iconRetinaUrl: require('leaflet/dist/images/marker-icon-2x.png'),
	iconUrl: require('leaflet/dist/images/marker-icon.png'),
	shadowUrl: require('leaflet/dist/images/marker-shadow.png'),
})

// Vue
Vue.directive('tooltip', Tooltip)

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
	Object.assign(window.OCA.Files, {
		App: { fileList: { filesClient: OC.Files.getClient() } },
	}, window.OCA.Files)
	optionsController.restoreOptions(main)
})

function main() {
	// eslint-disable-next-line
	new Vue({
		el: '#content',
		render: h => h(App),
	})
}
