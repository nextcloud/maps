/**
 * @copyright Copyright (c) 2019, Paul Schwörer <hello@paulschwoerer.de>
 *
 * @author Paul Schwörer <hello@paulschwoerer.de>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
import type { RasterDEMSourceSpecification, StyleSpecification } from 'maplibre-gl'

export const OSM_ATTRIBUTION = '&copy; <a href="https://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>'
export const ESRI_ATTRIBUTION = 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN...'

export const BaseStyleIds = {
	Street: 'street',
	Satellite: 'satellite',
} as const

export type BaseStyleId = typeof BaseStyleIds[keyof typeof BaseStyleIds]

/**
 * The available base styles for the (ported) main map. Support for switching
 * between many base layers (topographic, watercolor, a roads overlay, ...)
 * was dropped as part of the port to vue-maplibre-gl; only a vector street
 * map and satellite imagery remain.
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
	tiles: ['https://s3.amazonaws.com/elevation-tiles-prod/terrarium/{z}/{x}/{y}.png'],
	tileSize: 256,
	maxzoom: 15,
	encoding: 'terrarium',
	attribution: 'Elevation data &copy; <a href="https://github.com/tilezen/joerd/blob/master/docs/attribution.md">Mapzen, tilezen, and contributors</a>',
}

/*
 * Everything below is the pre-port, Leaflet-flavoured layer catalogue.
 * `Map.vue` no longer uses it, but `MapContainer.vue` (public favorite
 * share page) hasn't been ported to vue-maplibre-gl yet and still renders
 * its own Leaflet map with these layers, so they stay until that follow-up
 * step lands.
 */

export const LayerTypes = {
	Base: 'base',
	Overlay: 'overlay',
} as const

export type LayerType = typeof LayerTypes[keyof typeof LayerTypes]

export const LayerIds = {
	OSM: 'osm',
	RoadsOverlay: 'roads-overlay',
	ESRI: 'esri',
	ESRITopo: 'esri-topo',
	OpenTopo: 'open-topo',
	Watercolor: 'watercolor',
} as const

export interface MapLayerOptions {
	id: string
	noWrap: boolean
	detectRetina: boolean
	maxZoom: number
	referrerPolicy: string
	ext?: string
	subdomains?: string
}

export interface MapLayer {
	id: string
	name: string
	type: LayerType
	/** XYZ tile URL template, using Leaflet's `{s}`/`{z}`/`{x}`/`{y}` placeholders. */
	url: string
	attribution: string
	options: MapLayerOptions
	opacity?: number
}

export const Layers: MapLayer[] = [
	{
		id: LayerIds.RoadsOverlay,
		name: 'Roads Overlay',
		type: LayerTypes.Overlay,
		url: 'https://{s}.tile.openstreetmap.se/hydda/roads_and_labels/{z}/{x}/{y}.png',
		attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN...',
		options: {
			id: 'Roads Overlay',
			noWrap: false,
			detectRetina: false,
			maxZoom: 18,
			referrerPolicy: 'origin',
		},
		opacity: 0.7,
	},
	{
		id: LayerIds.OSM,
		name: 'Street map',
		type: LayerTypes.Base,
		url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
		attribution: OSM_ATTRIBUTION,
		options: {
			id: 'Open Street Map',
			noWrap: false,
			detectRetina: false,
			maxZoom: 19,
			referrerPolicy: 'origin',
		},
	},
	{
		id: LayerIds.ESRI,
		name: 'Satellite map',
		type: LayerTypes.Base,
		url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
		attribution: ESRI_ATTRIBUTION,
		options: {
			id: 'ESRI',
			noWrap: false,
			detectRetina: false,
			maxZoom: 19,
			referrerPolicy: 'origin',
		},
	},
	{
		id: LayerIds.ESRITopo,
		name: 'Topographic',
		type: LayerTypes.Base,
		url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
		attribution: ESRI_ATTRIBUTION,
		options: {
			id: 'ESRI topo',
			noWrap: false,
			detectRetina: false,
			maxZoom: 19,
			referrerPolicy: 'origin',
		},
	},
	{
		id: LayerIds.Watercolor,
		name: 'Watercolor',
		type: LayerTypes.Base,
		url: 'https://tiles.stadiamaps.com/tiles/stamen_watercolor/{z}/{x}/{y}.{ext}',
		attribution: '<a href="https://leafletjs.com" title="A JS library for interactive maps">Leaflet</a> | © Map tiles by <a href="https://stamen.com">Stamen Design</a>, under <a href="https://creativecommons.org/licenses/by/3.0">CC BY 3.0</a>, Data by <a href="https://openstreetmap.org">OpenStreetMap</a>, under <a href="https://creativecommons.org/licenses/by-sa/3.0">CC BY SA</a>.',
		options: {
			id: 'Watercolor',
			noWrap: false,
			detectRetina: false,
			maxZoom: 18,
			ext: 'jpg',
			subdomains: 'abcd',
			referrerPolicy: 'origin',
		},
	},
]

export const baseLayersByName: Record<string, MapLayer> = {}
export const overlayLayersByName: Record<string, MapLayer> = {}
Layers.forEach((l) => {
	if (l.type === LayerTypes.Base) {
		baseLayersByName[l.options.id] = l
	} else {
		overlayLayersByName[l.options.id] = l
	}
})
