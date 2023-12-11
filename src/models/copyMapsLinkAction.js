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

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'

export default class CopyMapsLinkAction {

	// internal state
	_share

	get id() {
		return 'maps_copy_maps_link'
	}

	get shareType() {
		return [OC.Share.SHARE_TYPE_LINK]
	}

	data({ share, fileInfo }) {
		this._share = share
		return {
			is: NcActionButton,
			ariaLabel: t('maps', 'Copy link to map'),
			icon: 'icon-clippy',
			title: t('maps', 'Copy link to map'),
		}
	}

	get handlers() {
		// Using arrow methods to keep `this` context
		return {
			click: async () => {
				const url = window.location.origin + generateUrl('/apps/maps/s/') + this._share.token
				try {
					await navigator.clipboard.writeText(url)
					showSuccess(t('maps', 'Link copied'))
				} catch (error) {
					console.debug(error)
					showError(t('maps', 'Link {url} could not be copied to clipboard.', { url }))
				}
			},
		}
	}

}
