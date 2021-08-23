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
import './bootstrap'
import optionsController from './optionsController'
import '../css/style.scss'

import VueClipboard from 'vue-clipboard2'

import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
Vue.directive('tooltip', Tooltip)
Vue.use(VueClipboard)

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

document.addEventListener('DOMContentLoaded', (event) => {
	// SIDEBAR
	if (!window.OCA.Files) {
		window.OCA.Files = {}
	}
	// register unused client for the sidebar to have access to its parser methods
	Object.assign(window.OCA.Files, { App: { fileList: { filesClient: OC.Files.getClient() } } }, window.OCA.Files)

	optionsController.restoreOptions(main)
})

function main() {
	// eslint-disable-next-line
	new Vue({
		el: '#content',
		render: h => h(App),
	})
}
