/* jshint esversion: 6 */

/**
 * Nextcloud - Maps
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

import Vue from 'vue'
import './bootstrap'
import App from './App'
// import vueAwesomeCountdown from 'vue-awesome-countdown'
// import VueClipboard from 'vue-clipboard2'
// import Transitions from 'vue2-transitions'
import * as network from './network'

// Vue.use(vueAwesomeCountdown, 'vac')
// Vue.use(VueClipboard)
// Vue.use(Transitions)

// eslint-disable-next-line
'use strict'

let mapsOptions = {}

function restoreOptions() {
	network.getOptionValues(getOptionValuesSuccess)
}

function getOptionValuesSuccess(response) {
	mapsOptions = response.values
	main()
}

document.addEventListener('DOMContentLoaded', function(event) {
	restoreOptions()
})

function main() {
	// eslint-disable-next-line
	new Vue({
		el: '#content',
		render: h => h(App),
	})
}
