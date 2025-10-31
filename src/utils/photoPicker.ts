/*!
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { DialogBuilder, FilePickerBuilder } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import { placePhotos } from '../network.js'

interface LotLong {
	lat: number
	lng: number
}

const dialogBuilder = new DialogBuilder(t('maps', 'What do you want to place'))

/**
 * Place photos or a photo folder on a given map and location.
 *
 * @param latLong - The geo location where to place the photos
 * @param myMapId - The map to place the photos
 */
export async function placeFileOrFolder(latLong: LotLong, myMapId: number) {
	const { promise, resolve } = Promise.withResolvers<unknown>()
	const dialog = dialogBuilder
		.setButtons([
			{
				label: t('maps', 'Photo folder'),
				callback() {
					resolve(placeFolder(latLong, myMapId))
				},
			},
			{
				label: t('maps', 'Photo files'),
				callback() {
					resolve(placeFiles(latLong, myMapId))
				},
				variant: 'primary',
			},
		])
		.build()

	await dialog.show()
	return promise
}

/**
 * Callback to select and place a folder.
 *
 * @param latLong - The location where to place
 * @param myMapId - The map to place photos to
 */
async function placeFolder(latLong: LotLong, myMapId: number) {
	const filePickerBuilder = new FilePickerBuilder(t('maps', 'Choose directory of photos to place'))
	const filePicker = filePickerBuilder.allowDirectories(true)
		.setMimeTypeFilter(['httpd/unix-directory'])
		.setButtonFactory((nodes) => [{
			callback: () => {},
			label: nodes.length === 1
				? t('maps', 'Select {photo}', { photo: nodes[0].displayname }, { escape: false })
				: (nodes.length === 0
					? t('maps', 'Select folder')
					: n('maps', 'Select %n folder', 'Select %n folders', nodes.length)
				),
			disabled: nodes.length === 0,
			variant: 'primary',
		}])
		.setMultiSelect(false)
		.build()

	try {
		const folder = await filePicker.pick()
		return placePhotos([folder], [latLong.lat], [latLong.lng], true, myMapId)
	} catch {
		// cancelled picking
	}
}

/**
 * Callback to select and place on or multiple photo files.
 *
 * @param latLong - The location where to place
 * @param myMapId - The map to place photos to
 */
async function placeFiles(latLong: LotLong, myMapId: number) {
	const filePickerBuilder = new FilePickerBuilder(t('maps', 'Choose photos to place'))
	const filePicker = filePickerBuilder
		.setMimeTypeFilter(['image/jpeg', 'image/tiff'])
		.setButtonFactory((nodes) => [{
			callback: () => {},
			label: nodes.length === 1
				? t('maps', 'Select {photo}', { photo: nodes[0].displayname }, { escape: false })
				: (nodes.length === 0
					? t('maps', 'Select photo')
					: n('maps', 'Select %n photo', 'Select %n photos', nodes.length)
				),
			disabled: nodes.length === 0,
			variant: 'primary',
		}])
		.setMultiSelect(true)
		.build()

	try {
		const nodes = await filePicker.pick()
		return placePhotos(nodes, [latLong.lat], [latLong.lng], false, myMapId)
	} catch {
		// cancelled picking
	}
}
