/**
 * @copyright Copyright (c) 2019, Paul Schwörer <hello@paulschwoerer.de>
 *
 * @author Paul Schwörer <hello@paulschwoerer.de>
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
import App from './App'
import { Icon } from 'leaflet'

import store from './store'

Vue.prototype.t = window.t
Vue.prototype.n = window.n
Vue.prototype.OC = window.OC
Vue.prototype.OCA = window.OCA

if (process && process.env.NODE_ENV === 'development') {
	Vue.config.devtools = true
}

// this part resolve an issue where the markers would not appear
delete Icon.Default.prototype._getIconUrl

Icon.Default.mergeOptions({
	iconRetinaUrl: require('leaflet/dist/images/marker-icon-2x.png'),
	iconUrl: require('leaflet/dist/images/marker-icon.png'),
	shadowUrl: require('leaflet/dist/images/marker-shadow.png'),
})

new Vue({
	render: h => h(App),
	store,
}).$mount('#content')
