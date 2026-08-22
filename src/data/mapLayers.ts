/**
 * SPDX-FileCopyrightText: 2019 Paul Schwörer <hello@paulschwoerer.de>
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { RasterDEMSourceSpecification, StyleSpecification } from 'maplibre-gl'

export const ESRI_ATTRIBUTION = 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN...'

export const BaseStyleIds = {
	Street: 'street',
	Satellite: 'satellite',
} as const

export type BaseStyleId = typeof BaseStyleIds[keyof typeof BaseStyleIds]

/**
 * The available base styles, used by the main map (which can switch between
 * them) and by MapContainer.vue (the public favorite share page, which
 * always uses the street style). Support for switching between many base
 * layers (topographic, watercolor, a roads overlay, ...) was dropped as
 * part of the port to vue-maplibre-gl; only a vector street map and
 * satellite imagery remain.
 *
 * The street style is OpenFreeMap's "Liberty" style - a full vector style
 * served for free, no API key required. See https://openfreemap.org/.
 */
export const BaseStyles: Record<BaseStyleId, StyleSpecification | string> = {
	[BaseStyleIds.Street]: 'https://tiles.openfreemap.org/styles/liberty',
	[BaseStyleIds.Satellite]: {
		version: 8,
		sources: {
			satellite: {
				type: 'raster',
				tiles: ['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'],
				tileSize: 256,
				maxzoom: 19,
				attribution: ESRI_ATTRIBUTION,
			},
		},
		layers: [
			{ id: 'satellite', type: 'raster', source: 'satellite' },
		],
	},
}

export const TERRAIN_SOURCE_ID = 'terrain-dem'

/**
 * Public-domain elevation tiles (Mapzen Terrarium, hosted by AWS Open Data)
 * used to drive MapLibre's native 3D terrain rendering. No API key required.
 */
export const TERRAIN_SOURCE: RasterDEMSourceSpecification = {
	type: 'raster-dem',
	tiles: ['https://elevation-tiles-prod.s3.amazonaws.com/terrarium/{z}/{x}/{y}.png'],
	tileSize: 256,
	maxzoom: 15,
	encoding: 'terrarium',
	attribution: 'Elevation data &copy; <a href="https://github.com/tilezen/joerd/blob/master/docs/attribution.md">Mapzen, tilezen, and contributors</a>',
}
