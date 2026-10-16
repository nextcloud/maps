/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { Map as MaplibreMap } from 'maplibre-gl'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'
import { reactive } from 'vue'
import { getToken } from './common.js'

export interface PhotoPreviewSource {
	path: string
	fileId: number
	hasPreview?: boolean
}

export interface PhotoWithDate {
	dateTaken?: number
}

export const PHOTO_PREVIEW_MARKER_SIZE = 40
export const PHOTO_PREVIEW_PLACEHOLDER_ICON_ID = 'photo-preview-placeholder'

/**
 * @param photo the photo to build a preview URL for
 */
export function getPhotoPreviewUrl(photo: PhotoPreviewSource): string {
	if (photo?.hasPreview) {
		const token = getToken()
		return token
			? generateUrl('apps/files_sharing/publicpreview/') + token + '?file=' + encodeURIComponent(photo.path) + '&x=341&y=256&a=1'
			: generateUrl('core') + '/preview?fileId=' + photo.fileId + '&x=341&y=256&a=1'
	}
	return generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2'
}

/**
 * @param url the preview URL to derive a sprite id for
 */
export function photoPreviewIconId(url: string): string {
	return 'photo-preview-' + btoa(url).replace(/[^a-zA-Z0-9]/g, '')
}

/**
 * @param photo the photo whose date should be formatted
 */
export function getPhotoFormattedDate(photo?: PhotoWithDate): string {
	if (!photo?.dateTaken) {
		return ''
	}
	const d = new Date(photo.dateTaken * 1000)
	const mom = moment.unix(photo.dateTaken + d.getTimezoneOffset() * 60)
	return mom.format('LL') + ' ' + mom.format('HH:mm:ss')
}

/**
 * Tracks which photo preview sprite images have already been registered on
 * the map, shared across every layer that renders photo thumbnails
 * (PhotosLayer, PhotoSuggestionsLayer) so the same preview is never fetched
 * or registered twice.
 */
export const loadedPhotoPreviewIcons = reactive(new Set<string>())
const loadingPhotoPreviewIcons = new Set<string>()

/**
 * @param size square size of the drawn icon
 * @param draw callback receiving the 2D context to draw into
 */
function drawIcon(size: number, draw: (ctx: CanvasRenderingContext2D) => void): ImageData | null {
	const canvas = document.createElement('canvas')
	canvas.width = size
	canvas.height = size
	const ctx = canvas.getContext('2d')
	if (!ctx) {
		return null
	}
	draw(ctx)
	return ctx.getImageData(0, 0, size, size)
}

/**
 * @param map the map to register the placeholder icon on
 */
export function ensurePhotoPreviewPlaceholderIcon(map: MaplibreMap): void {
	if (map.hasImage(PHOTO_PREVIEW_PLACEHOLDER_ICON_ID)) {
		return
	}
	const image = drawIcon(PHOTO_PREVIEW_MARKER_SIZE, (ctx) => {
		ctx.fillStyle = '#ffffff'
		ctx.fillRect(0, 0, PHOTO_PREVIEW_MARKER_SIZE, PHOTO_PREVIEW_MARKER_SIZE)
		ctx.strokeStyle = '#dddddd'
		ctx.lineWidth = 2
		ctx.strokeRect(1, 1, PHOTO_PREVIEW_MARKER_SIZE - 2, PHOTO_PREVIEW_MARKER_SIZE - 2)
	})
	if (image) {
		map.addImage(PHOTO_PREVIEW_PLACEHOLDER_ICON_ID, image)
	}
}

/**
 * @param url the preview image URL to fetch and draw to a square thumbnail
 */
function loadThumbnail(url: string): Promise<ImageData> {
	return new Promise((resolve, reject) => {
		const img = new Image()
		img.crossOrigin = 'anonymous'
		img.onload = () => {
			const canvas = document.createElement('canvas')
			canvas.width = PHOTO_PREVIEW_MARKER_SIZE
			canvas.height = PHOTO_PREVIEW_MARKER_SIZE
			const ctx = canvas.getContext('2d')
			if (!ctx) {
				reject(new Error('Could not get canvas context'))
				return
			}
			ctx.drawImage(img, 0, 0, PHOTO_PREVIEW_MARKER_SIZE, PHOTO_PREVIEW_MARKER_SIZE)
			resolve(ctx.getImageData(0, 0, PHOTO_PREVIEW_MARKER_SIZE, PHOTO_PREVIEW_MARKER_SIZE))
		}
		img.onerror = () => reject(new Error(`Failed to load preview ${url}`))
		img.src = url
	})
}

/**
 * @param map the map to register the sprite image on
 * @param url the preview URL to load and register as a GL sprite image
 */
export async function ensurePhotoPreviewIcon(map: MaplibreMap, url: string): Promise<void> {
	const id = photoPreviewIconId(url)
	if (loadedPhotoPreviewIcons.has(id) || loadingPhotoPreviewIcons.has(id) || map.hasImage(id)) {
		return
	}
	loadingPhotoPreviewIcons.add(id)
	try {
		const image = await loadThumbnail(url)
		if (!map.hasImage(id)) {
			map.addImage(id, image)
		}
		loadedPhotoPreviewIcons.add(id)
	} catch (error) {
		console.error(error)
	} finally {
		loadingPhotoPreviewIcons.delete(id)
	}
}
