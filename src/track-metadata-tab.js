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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
import { FileType, registerSidebarTab } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'
import { defineCustomElement } from 'vue'

import RoadIcon from '../img/road.svg?raw'

const tagName = 'maps-track-metadata-sidebar-tab'

registerSidebarTab({
	id: 'maps-track-metadata',
	tagName,
	displayName: t('maps', 'Metadata'),
	iconSvgInline: RoadIcon,
	order: 10,
	enabled({ node }) {
		return node.type === FileType.File && node.mime === 'application/gpx+xml'
	},
	async onInit() {
		const { default: TrackMetadataSidebarTab } = await import('./views/TrackMetadataSidebarTab.vue')
		window.customElements.define(tagName, defineCustomElement(TrackMetadataSidebarTab, {
			shadowRoot: false,
		}))
	},
})

