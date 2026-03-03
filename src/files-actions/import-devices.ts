import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { FileAction, Permission } from '@nextcloud/files'
import { n, t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

import svgMapMarker from '@mdi/svg/svg/map-marker.svg?raw'

export default new FileAction({
	id: 'maps:import-device',

	displayName() {
		return t('maps', 'Import as devices in Maps')
	},

	enabled(files) {
		if (files.length !== 1) {
			return false
		}

		const [file] = files
		if (!(file.permissions & Permission.READ)) {
			return false
		}
		return [
			'application/gpx+xml',
			'application/vnd.google-earth.kmz',
			'application/vnd.google-earth.kml+xml',
		].includes(file.mime)
	},

	async exec(file) {
		const path = file.path
		const url = generateUrl('/apps/maps/import/devices')
		try {
			const { data } = await axios.post(url, { path })
			const number = typeof data === 'number' ? data : data.nbImported
			showSuccess(n('maps', 'One device imported', '%n devices imported', number))
		} catch (error) {
			showError(t('maps', 'Failed to import devices'))
			console.error('Failed to import devices', { error })
		}
		return null
	},

	iconSvgInline() {
		return svgMapMarker
	},
})
