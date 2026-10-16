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

import { defineCustomElement } from 'vue'
import { registerSidebarTab } from '@nextcloud/files'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
// eslint-disable-next-line import/no-unresolved -- resolved by Vite's ?raw loader
import svgInformation from '@mdi/svg/svg/information-outline.svg?raw'
import TrackMetadataTab from './views/TrackMetadataTab.vue'

const TAG_NAME = 'maps-track-metadata-sidebar-tab'

registerSidebarTab({
	id: 'maps-track-metadata',
	displayName: t('maps', 'Metadata'),
	iconSvgInline: svgInformation,
	order: 90,
	tagName: TAG_NAME,
	enabled({ node }) {
		return node.mime === 'application/gpx+xml'
	},
	async onInit() {
		if (customElements.get(TAG_NAME)) {
			return
		}
		customElements.define(TAG_NAME, defineCustomElement(TrackMetadataTab, {
			// render in the light DOM so the app's global styles apply
			shadowRoot: false,
			configureApp(app) {
				app.config.globalProperties.t = t
				app.config.globalProperties.n = n
			},
		}))
	},
})
