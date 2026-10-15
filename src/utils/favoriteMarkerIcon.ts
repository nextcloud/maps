/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { Map as MaplibreMap } from 'maplibre-gl'

const SELECTED_COLOR = 'ffcc00'
const SIZE = 32

/**
 * @param color hex color, with or without a leading '#'
 * @param selected whether this is the "selected" (highlighted) variant
 */
export function favoriteMarkerIconId(color: string, selected = false): string {
	return `favorite-marker-${color.replace('#', '')}${selected ? '-selected' : ''}`
}

/**
 * @param ctx canvas context to draw into
 * @param cx star center x
 * @param cy star center y
 * @param outerRadius outer point radius
 * @param innerRadius inner point radius
 */
function drawStar(ctx: CanvasRenderingContext2D, cx: number, cy: number, outerRadius: number, innerRadius: number): void {
	const spikes = 5
	let rotation = (Math.PI / 2) * 3
	const step = Math.PI / spikes
	ctx.beginPath()
	ctx.moveTo(cx, cy - outerRadius)
	for (let i = 0; i < spikes; i++) {
		let x = cx + Math.cos(rotation) * outerRadius
		let y = cy + Math.sin(rotation) * outerRadius
		ctx.lineTo(x, y)
		rotation += step

		x = cx + Math.cos(rotation) * innerRadius
		y = cy + Math.sin(rotation) * innerRadius
		ctx.lineTo(x, y)
		rotation += step
	}
	ctx.lineTo(cx, cy - outerRadius)
	ctx.closePath()
}

/**
 * Registers a colored circle with a white star as a map image, for favorite symbol layers.
 *
 * @param map the map to register the icon on
 * @param color hex color, with or without a leading '#', for the circle background
 * @param selected whether to draw the "selected" (highlighted) variant
 */
export function ensureFavoriteMarkerIcon(map: MaplibreMap, color: string, selected = false): void {
	const id = favoriteMarkerIconId(color, selected)
	if (map.hasImage(id)) {
		return
	}
	const canvas = document.createElement('canvas')
	canvas.width = SIZE
	canvas.height = SIZE
	const ctx = canvas.getContext('2d')
	if (!ctx) {
		return
	}

	ctx.beginPath()
	ctx.arc(SIZE / 2, SIZE / 2, SIZE / 2 - 2, 0, Math.PI * 2)
	ctx.fillStyle = selected ? `#${SELECTED_COLOR}` : `#${color.replace('#', '')}`
	ctx.fill()
	ctx.lineWidth = 2
	ctx.strokeStyle = '#ffffff'
	ctx.stroke()

	drawStar(ctx, SIZE / 2, SIZE / 2, SIZE / 2 - 9, (SIZE / 2 - 9) / 2.5)
	ctx.fillStyle = '#ffffff'
	ctx.fill()

	map.addImage(id, ctx.getImageData(0, 0, SIZE, SIZE))
}
