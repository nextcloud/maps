import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { type IFileAction, Permission } from '@nextcloud/files'
import { n, t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

import svgMapMarker from '@mdi/svg/svg/map-marker.svg?raw'

const action: IFileAction = {
    id: 'maps:import-as-favorite',
    displayName: (content) => t('maps', 'Import as favorites in Maps'),
    enabled: (content) => {
        const files = content.contents
        if (files.length !== 1) {
			return false
		}

		const [file] = files
		if (!(file.permissions & Permission.READ)) {
			return false
		}
		return [
			'application/geo+json',
			'application/gpx+xml',
			'application/vnd.google-earth.kmz',
			'application/vnd.google-earth.kml+xml',
		].includes(file.mime)
    },
    exec: async (content) => {
        const [file] = content.contents
        const path = file.path
		const url = generateUrl('/apps/maps/import/favorites')
		try {
			const { data } = await axios.post(url, { path })
			const number = typeof data === 'number' ? data : data.nbImported
			showSuccess(n('maps', 'One favorite imported', '%n favorites imported', number))
		} catch (error) {
			showError(t('maps', 'Failed to import favorites'))
			console.error('Failed to import favorites', { error })
		}
		return null
    },
    iconSvgInline: () => svgMapMarker
}

export default action