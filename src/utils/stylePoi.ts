/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { LayerSpecification, Map as MaplibreMap, MapGeoJSONFeature, PointLike } from 'maplibre-gl'

/**
 * @param layer a style layer to check
 */
export function isPoiLayer(layer: LayerSpecification): boolean {
	return layer.type === 'symbol' && 'source-layer' in layer && layer['source-layer'] === 'poi'
}

/**
 * Finds the base style's own POI feature (a restaurant, shop, ... rendered by the
 * style itself, not by anything in this app) at a point, if any. The style's symbol
 * layers for these are found dynamically by their vector source-layer ("poi", from
 * the OpenMapTiles schema OpenFreeMap Liberty uses) rather than by hardcoding layer
 * IDs like "poi_r7" - those are rank/zoom-threshold splits specific to the current
 * style version and not meant to be depended on externally. The lookup re-runs on
 * every call rather than once at bind time, so it stays correct across style
 * switches without needing to re-bind.
 *
 * @param map the map to query
 * @param point pixel coordinates within the map viewport
 */
export function stylePoiFeatureAt(map: MaplibreMap, point: PointLike): MapGeoJSONFeature | undefined {
	const layers = map.getStyle()?.layers?.filter(isPoiLayer).map((l) => l.id) ?? []
	if (!layers.length) {
		return undefined
	}
	return map.queryRenderedFeatures(point, { layers })[0]
}
