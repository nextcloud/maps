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

const attributionESRI = 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN...'

export const LayerTypes = {
	Base: 'base',
	Overlay: 'overlay',
}

export const LayerIds = {
	OSM: 'osm',
	RoadsOverlay: 'roads-overlay',
	ESRI: 'esri',
	ESRITopo: 'esri-topo',
	OpenTopo: 'open-topo',
	Watercolor: 'watercolor',
}

export const Layers = [
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
		},
		opacity: 0.7,
	},
	{
		id: LayerIds.OSM,
		name: 'Street map',
		type: LayerTypes.Base,
		url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
		attribution: '&copy; <a href="https://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>',
		options: {
			id: 'Open Street Map',
			noWrap: false,
			detectRetina: false,
			maxZoom: 19,
		},
	},
	{
		id: LayerIds.ESRI,
		name: 'Satellite map',
		type: LayerTypes.Base,
		url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
		attribution: attributionESRI,
		options: {
			id: 'ESRI',
			noWrap: false,
			detectRetina: false,
			maxZoom: 19,
		},
	},
	{
		id: LayerIds.ESRITopo,
		name: 'Topographic',
		type: LayerTypes.Base,
		url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
		attribution: attributionESRI,
		options: {
			id: 'ESRI topo',
			noWrap: false,
			detectRetina: false,
			maxZoom: 19,
		},
	},
	{
		id: LayerIds.Watercolor,
		name: 'Watercolor',
		type: LayerTypes.Base,
		url: 'https://stamen-tiles-{s}.a.ssl.fastly.net/watercolor/{z}/{x}/{y}.{ext}',
		attribution: '<a href="https://leafletjs.com" title="A JS library for interactive maps">Leaflet</a> | © Map tiles by <a href="https://stamen.com">Stamen Design</a>, under <a href="https://creativecommons.org/licenses/by/3.0">CC BY 3.0</a>, Data by <a href="https://openstreetmap.org">OpenStreetMap</a>, under <a href="https://creativecommons.org/licenses/by-sa/3.0">CC BY SA</a>.',
		options: {
			id: 'Watercolor',
			noWrap: false,
			detectRetina: false,
			maxZoom: 18,
			ext: 'jpg',
			subdomains: 'abcd',
		},
	},
]

export const baseLayersByName = {}
export const overlayLayersByName = {}
Layers.forEach((l) => {
	if (l.type === LayerTypes.Base) {
		baseLayersByName[l.options.id] = l
	} else {
		overlayLayersByName[l.options.id] = l
	}
})
