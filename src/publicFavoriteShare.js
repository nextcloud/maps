/**
 * @copyright Copyright (c) 2019, Paul Schwörer <hello@paulschwoerer.de>
 *
 * @author Paul Schwörer <hello@paulschwoerer.de>
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
import PublicFavoriteShare from './views/PublicFavoriteShare.vue'

// Keep this to load the Leaflet icon paths
import './bootstrap.js' 

import store from './store/publicFavoriteShareStore.js'

const app = createApp(PublicFavoriteShare)

// Inject the Vuex store
app.use(store)

// Make Nextcloud global variables accessible within your Vue components
app.config.globalProperties.t = window.t
app.config.globalProperties.n = window.n
app.config.globalProperties.OC = window.OC
app.config.globalProperties.OCA = window.OCA

// Mount to the DOM
app.mount('#content')