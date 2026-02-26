import { Permission, type IFileAction } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

import svgMapMarker from '@mdi/svg/svg/map-marker.svg?raw'

const iFileAction: IFileAction = {
    id: 'maps:view-map',
    displayName: (context) => t('maps', 'View in Maps'),
    enabled: (context) => {
        if(context.contents.length !== 1) {
            return false
        }
        const [file] = context.contents
		if (!(file.permissions & Permission.READ)) {
			return false
		}
		return [
			'application/gpx+xml',
			'application/x-nextcloud-maps',
		].includes(file.mime)
    },
    exec: async (context) => {
        const [file] = context.contents
        if (file.mime === 'application/x-nextcloud-maps') {
			const url = generateUrl('apps/maps/m/{mapId}', { mapId: file.id })
            console.log("log url : " + url)
			window.open(url, '_self')
		} else {
			const url = generateUrl('apps/maps/?track={path}', { path: file.path })
            console.log("log url : " + url)
			window.open(url, '_self')
		}
        return null
    },
    iconSvgInline: (context) => svgMapMarker
} 

export default iFileAction
