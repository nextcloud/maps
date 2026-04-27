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
import { translate as t } from '@nextcloud/l10n'

import TrackMetadataTab from './views/TrackMetadataTab.vue'

let tabApp = null
const trackMetadataTab = new OCA.Files.Sidebar.Tab({
	id: 'maps-track-metadata',
	name: t('maps', 'Metadata'),
	icon: 'icon-info',

	async mount(el, fileInfo, context) {
		if (tabApp) {
			tabApp.unmount()
			tabApp = null
		}
		tabApp = createApp(TrackMetadataTab)
		tabApp.config.globalProperties.t = window.t
		tabApp.config.globalProperties.n = window.n
		const instance = tabApp.mount(el)
		await instance.update(fileInfo.id)
	},
	update(fileInfo) {
		if (tabApp) {
			tabApp._instance?.proxy?.update(fileInfo.id)
		}
	},
	destroy() {
		if (tabApp) {
			tabApp.unmount()
			tabApp = null
		}
	},
	enabled(fileInfo) {
		return ['application/gpx+xml'].includes(fileInfo.mimetype)
	},
	scrollBottomReached() {
		if (tabApp) {
			tabApp._instance?.proxy?.onScrollBottomReached()
		}
	},
})

window.addEventListener('DOMContentLoaded', function() {
	if (OCA.Files && OCA.Files.Sidebar) {
		OCA.Files.Sidebar.registerTab(trackMetadataTab)
	}
})
