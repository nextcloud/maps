import { registerFileAction } from '@nextcloud/files'
import { isPublicShare } from '@nextcloud/sharing/public'
import importDevices from './files-actions/import-devices'
import importFavorite from './files-actions/import-favorite'
import viewInMaps from './files-actions/view-in-maps'

if (!isPublicShare()) {
	registerFileAction(viewInMaps)
	registerFileAction(importDevices)
	registerFileAction(importFavorite)
}
