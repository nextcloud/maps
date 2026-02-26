import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { type IFileAction, type IFile, type ActionContext } from '@nextcloud/files'
import { t, n } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import svgMapMarker from '@mdi/svg/svg/map-marker.svg?raw'

const importDevicesAction: IFileAction = {
    id: 'maps:import-device',

    displayName() {
        return t('maps', 'Import as devices in Maps')
    },

    enabled(context: ActionContext) {
        const files = context.contents
        if (!files || files.length !== 1) {
            return false
        }

        const file = files[0] as IFile
        return [
            'application/gpx+xml',
            'application/vnd.google-earth.kmz',
            'application/vnd.google-earth.kml+xml',
        ].includes(file.mime)
    },

    async exec(context: ActionContext) {
        const file = context.contents[0] as IFile
        const url = generateUrl('/apps/maps/import/devices')

        try {
            const { data } = await axios.post(url, { path: file.path })
            const number = typeof data === 'number' ? data : data.nbImported
            showSuccess(
                n('maps', 'One device imported', '%n devices imported', number)
            )
        } catch (error) {
            showError(t('maps', 'Failed to import devices'))
            console.error('Failed to import devices', { error })
        }

        return null
    },

    iconSvgInline() {
        return svgMapMarker
    },
}

export default importDevicesAction