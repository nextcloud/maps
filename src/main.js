/**
 * @copyright Copyright (c) 2020, Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
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

import VueClipboard from 'vue-clipboard2'

import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
Vue.directive('tooltip', Tooltip)
Vue.use(VueClipboard)

// eslint-disable-next-line
'use strict'

document.addEventListener('DOMContentLoaded', (event) => {
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
