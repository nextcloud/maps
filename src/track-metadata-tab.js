/**
 * @copyright Copyright (c) 2022 Arne Hamann <git@arne.email>
 *
 * @author Arne Hamann <git@arne.email>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { createApp } from 'vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import TrackMetadataTab from './views/TrackMetadataTab.vue'

let appInstance = null
let TabInstance = null

function registerTab() {
	const fileList = window.OCA?.Files?.App?.fileList

	if (!fileList?.registerSidebarTab) {
		return false
	}

	fileList.registerSidebarTab({
		id: 'maps-track-metadata',
		name: t('maps', 'Metadata'),
		icon: 'icon-info',

		async mount(el, fileInfo, context) {
			// Unmount the previous instance if it exists
			if (appInstance) {
				appInstance.unmount()
			}

			// Create a new Vue 3 app instance for the sidebar tab
			appInstance = createApp(TrackMetadataTab)
			
			// Replace Vue.prototype with globalProperties
			appInstance.config.globalProperties.t = t
			appInstance.config.globalProperties.n = n

			// Mount it to the provided DOM element
			TabInstance = appInstance.mount(el)

			if (TabInstance && typeof TabInstance.update === 'function') {
				await TabInstance.update(fileInfo.id)
			}
		},

		update(fileInfo) {
			if (TabInstance && typeof TabInstance.update === 'function') {
				TabInstance.update(fileInfo.id)
			}
		},

		destroy() {
			if (appInstance) {
				appInstance.unmount()
				appInstance = null
				TabInstance = null
			}
		},

		enabled(fileInfo) {
			return fileInfo?.mimetype === 'application/gpx+xml'
		},

		scrollBottomReached() {
			if (TabInstance && typeof TabInstance.onScrollBottomReached === 'function') {
				TabInstance.onScrollBottomReached()
			}
		},
	})

	return true
}

document.addEventListener('DOMContentLoaded', () => {
	const interval = setInterval(() => {
		if (registerTab()) {
			clearInterval(interval)
		}
	}, 100)
})