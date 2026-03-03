import { DefaultType, FileAction, Permission } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

import svgMapMarker from '@mdi/svg/svg/map-marker.svg?raw'

export default new FileAction({
	id: 'maps:view-map',
	default: DefaultType.DEFAULT,

	displayName() {
		return t('maps', 'View in Maps')
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
			'application/x-nextcloud-maps',
		].includes(file.mime)
	},

	async exec(file) {
		if (file.mime === 'application/x-nextcloud-maps') {
			const url = generateUrl('apps/maps/m/{mapId}', { mapId: file.fileid })
			window.open(url, '_self')
		} else {
			const url = generateUrl('apps/maps/?track={path}', { path: file.path })
			window.open(url, '_self')
		}

		return null
	},

	iconSvgInline() {
		return svgMapMarker
	},
})
