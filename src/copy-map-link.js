/**
 * @copyright Copyright (c) 2023 Arne Hamann <git@arne.email>
 *
 * @author Arne Hamann <git@arne.email>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import { FileType } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { ShareType } from '@nextcloud/sharing'
import { registerSidebarInlineAction } from '@nextcloud/sharing/ui'
// eslint-disable-next-line import/no-unresolved -- resolved by Vite's ?raw loader
import svgMap from '@mdi/svg/svg/map-outline.svg?raw'

// Offers the public link of a shared map folder as a link to the maps app
registerSidebarInlineAction({
	id: 'maps-copy-map-link',
	order: 0,
	iconSvg: svgMap,
	label: () => t('maps', 'Copy link to map'),
	enabled: (share, node) => share.type === ShareType.Link && !!share.token && node.type === FileType.Folder,
	async exec(share) {
		const url = window.location.origin + generateUrl('/apps/maps/s/{token}', { token: share.token })
		try {
			await navigator.clipboard.writeText(url)
			showSuccess(t('maps', 'Link copied'))
		} catch (error) {
			console.error(error)
			showError(t('maps', 'Link {url} could not be copied to clipboard.', { url }))
		}
	},
})
