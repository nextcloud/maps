<!--
 - SPDX-FileCopyrightText: 2019 Paul Schwörer <hello@paulschwoerer.de>
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	Ported from Leaflet to vue-maplibre-gl. The point-hover event this
	used to emit while hovering a line is gone, but on purpose rather
	than deferred: nothing has ever listened for it since the
	Leaflet-to-vue-maplibre-gl port started.
-->
<template>
	<MglGeoJsonSource :source-id="SOURCE_ID" :data="geojson">
		<MglLineLayer :layer-id="LINE_CASING_LAYER_ID"
			:source="SOURCE_ID"
			:filter="['==', ['geometry-type'], 'LineString']"
			:paint="{ 'line-color': '#000000', 'line-width': 6.4 }" />
		<MglLineLayer :layer-id="LINE_LAYER_ID"
			:source="SOURCE_ID"
			:filter="['==', ['geometry-type'], 'LineString']"
			:paint="{ 'line-color': ['get', 'color'], 'line-width': 4 }" />
		<MglCircleLayer :layer-id="POINTS_LAYER_ID"
			:source="SOURCE_ID"
			:filter="['==', ['geometry-type'], 'Point']"
			:paint="{
				'circle-color': ['get', 'color'],
				'circle-radius': ['case', ['get', 'isFirstPoint'], 9, 6],
				'circle-stroke-color': '#ffffff',
				'circle-stroke-width': 2,
			}" />
	</MglGeoJsonSource>
</template>

<script setup lang="ts">
import type { MapGeoJSONFeature, MapMouseEvent } from 'maplibre-gl'
import { Popup } from 'maplibre-gl'
import moment from '@nextcloud/moment'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed, onBeforeUnmount, watch } from 'vue'
import { MglCircleLayer, MglGeoJsonSource, MglLineLayer, useMap } from 'vue-maplibre-gl'
import { binSearch, getToken, isPublic } from '../../utils/common.js'
import { escapeHtml } from '../../utils/domUtils.ts'

export interface TrackPoint {
	lat: number
	lng: number
	timestamp?: number
	name?: string
	desc?: string
	ele?: number
}

export interface TrackSegment {
	name?: string
	points: TrackPoint[]
}

export interface TrackFile {
	name?: string
	segments: TrackSegment[]
}

export interface Track {
	id: number
	color?: string
	data?: {
		tracks?: TrackFile[]
		routes?: TrackSegment[]
		waypoints?: TrackPoint[]
	}
	metadata?: { begin?: number, end?: number }
	file_name: string
	file_path: string
	enabled: boolean
	selected?: boolean
	isUpdateable: boolean
	isShareable: boolean
	hideDownload?: boolean
}

const props = withDefaults(defineProps<{
	tracks: Track[]
	start?: number
	end?: number
}>(), {
	start: 0,
	end: () => moment().unix(),
})

const emit = defineEmits<{
	click: [track: Track]
	'change-color': [track: Track]
	'display-elevation': [track: Track]
	'add-to-map-track': [track: Track]
}>()

const SOURCE_ID = 'tracks'
const LINE_CASING_LAYER_ID = 'tracks-lines-casing'
const LINE_LAYER_ID = 'tracks-lines-fg'
const POINTS_LAYER_ID = 'tracks-points-fg'
const DEFAULT_COLOR = '#0082c9'

const mapInstance = useMap()

const displayedTracks = computed<Track[]>(() => props.tracks.filter((track) => track.enabled))

/**
 * @param track the track to compute visible line segments for
 */
function calculateLines(track: Track): TrackSegment[] {
	const trkSegments: TrackSegment[] = []
	const begin = track.metadata?.begin ?? 0
	const end = track.metadata?.end ?? 0
	if ((begin > 0 && begin >= props.end) || (end > 0 && end <= props.start)) {
		return [...(track.data?.routes || []), ...trkSegments]
	} else if ((!begin || begin >= props.start) && (!end || end <= props.end)) {
		(track.data?.tracks || []).forEach((trk) => {
			trk.segments.forEach((segment) => {
				trkSegments.push({ ...segment, name: trk.name })
			})
		})
	} else {
		(track.data?.tracks || []).forEach((trk) => {
			trk.segments.forEach((segment) => {
				const lastNullIndex = binSearch(segment.points, (p: TrackPoint) => !p.timestamp)
				const firstShownIndex = binSearch(segment.points, (p: TrackPoint) => (p.timestamp || -1) < props.start) + 1
				const lastShownIndex = binSearch(segment.points, (p: TrackPoint) => (p.timestamp || -1) < props.end)
				const points = [
					...segment.points.slice(0, lastNullIndex + 1),
					...segment.points.slice(firstShownIndex, lastShownIndex + 1),
				]
				trkSegments.push({ ...segment, name: trk.name, points })
			})
		})
	}
	return [...(track.data?.routes || []), ...trkSegments]
}

/**
 * @param track the track to find the earliest point of
 */
function calculateFirstPoint(track: Track): TrackPoint | null {
	let firstPoint: TrackPoint | null = null
	if ((track.data?.tracks?.[0]?.segments?.[0]?.points?.length ?? 0) > 0) {
		firstPoint = track.data!.tracks![0].segments[0].points[0]
	}
	const firstRoutePoint = track.data?.routes?.[0]?.points?.[0]
	if (firstRoutePoint && (firstPoint === null || (!firstPoint.timestamp && firstRoutePoint.timestamp) || (firstRoutePoint.timestamp && firstPoint.timestamp && firstRoutePoint.timestamp < firstPoint.timestamp))) {
		firstPoint = firstRoutePoint
	}
	const firstWaypoint = track.data?.waypoints?.[0]
	if (firstWaypoint && (firstPoint === null || (!firstPoint.timestamp && firstWaypoint.timestamp) || (firstWaypoint.timestamp && firstPoint.timestamp && firstWaypoint.timestamp < firstPoint.timestamp))) {
		firstPoint = firstWaypoint
	}
	return firstPoint
}

/**
 * @param track the track to compute visible waypoints for
 * @param firstPoint the track's already-computed first point, excluded if it's a waypoint itself
 */
function calculateWaypoints(track: Track, firstPoint: TrackPoint | null): TrackPoint[] {
	if (!track.data?.waypoints) {
		return []
	}
	const points = firstPoint === track.data.waypoints[0] ? track.data.waypoints.slice(1) : track.data.waypoints
	const begin = track.metadata?.begin ?? 0
	const end = track.metadata?.end ?? -1

	if (begin >= props.end || (end >= 0 && end <= props.start)) {
		return []
	} else if (begin >= props.start && end <= props.end) {
		return points
	}
	const lastNullIndex = binSearch(points, (p: TrackPoint) => !p.timestamp)
	const firstShownIndex = binSearch(points, (p: TrackPoint) => (p.timestamp || -1) < props.start) + 1
	const lastShownIndex = binSearch(points, (p: TrackPoint) => (p.timestamp || -1) < props.end)
	return [
		...points.slice(0, lastNullIndex + 1),
		...points.slice(firstShownIndex, lastShownIndex + 1),
	]
}

/**
 * @param point the track point to build a GeoJSON feature for
 * @param track the track the point belongs to
 * @param trackIndex the track's index in displayedTracks
 * @param color the track's display color
 * @param isFirstPoint whether this is the track's first (bigger) point rather than a waypoint
 */
function pointFeature(point: TrackPoint, track: Track, trackIndex: number, color: string, isFirstPoint: boolean) {
	return {
		type: 'Feature' as const,
		geometry: { type: 'Point' as const, coordinates: [point.lng, point.lat] },
		properties: {
			trackIndex,
			color,
			isFirstPoint,
			fileName: track.file_name,
			name: point.name || '',
			desc: point.desc || '',
			ele: point.ele ?? null,
			begin: isFirstPoint ? (track.metadata?.begin ?? null) : null,
		},
	}
}

const geojson = computed(() => {
	const features: object[] = []
	displayedTracks.value.forEach((track, trackIndex) => {
		const color = track.color || DEFAULT_COLOR

		calculateLines(track).forEach((line) => {
			if (!line.points || line.points.length < 2) {
				return
			}
			features.push({
				type: 'Feature' as const,
				geometry: { type: 'LineString' as const, coordinates: line.points.map((p) => [p.lng, p.lat]) },
				properties: { trackIndex, color },
			})
		})

		const firstPoint = calculateFirstPoint(track)
		if (firstPoint) {
			features.push(pointFeature(firstPoint, track, trackIndex, color, true))
		}
		calculateWaypoints(track, firstPoint).forEach((point) => {
			features.push(pointFeature(point, track, trackIndex, color, false))
		})
	})
	return { type: 'FeatureCollection' as const, features }
})

let hoverPopup: Popup | null = null

/**
 * @param event the underlying MapLibre mouse event
 */
function onPointMouseEnter(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const map = mapInstance.map
	const feature = event.features?.[0]
	if (!map || !feature) {
		return
	}
	map.getCanvas().style.cursor = 'pointer'

	const { fileName, name, desc, ele, begin, color } = feature.properties
	let html = `<b>${t('maps', 'File')}:</b> <span>${escapeHtml(fileName)}</span>`
	if (begin) {
		html += `<br><b>${t('maps', 'Begins at')}:</b> <span>${escapeHtml(moment.unix(begin).format('LLL'))}</span>`
	}
	if (name) {
		html += `<br><b>${t('maps', 'Name')}:</b> <span>${escapeHtml(name)}</span>`
	}
	if (desc) {
		html += `<br><b>${t('maps', 'Description')}:</b> <span>${escapeHtml(desc)}</span>`
	}
	if (ele) {
		html += `<br><b>${t('maps', 'Altitude')}:</b> <span>${escapeHtml(String(ele))} m</span>`
	}

	const container = document.createElement('div')
	container.className = 'tooltip-track-wrapper'
	container.style.border = `2px solid ${color}`
	container.innerHTML = html

	hoverPopup?.remove()
	hoverPopup = new Popup({ closeButton: false, closeOnClick: false, offset: 12 })
		.setLngLat((feature.geometry as { coordinates: [number, number] }).coordinates)
		.setDOMContent(container)
		.addTo(map)
}

function onPointMouseLeave(): void {
	const map = mapInstance.map
	if (map) {
		map.getCanvas().style.cursor = ''
	}
	hoverPopup?.remove()
	hoverPopup = null
}

/**
 * @param event the underlying MapLibre mouse event
 */
function onFeatureClick(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const feature = event.features?.[0]
	if (!feature) {
		return
	}
	const track = displayedTracks.value[feature.properties.trackIndex]
	if (track) {
		emit('click', track)
	}
}

let actionPopup: Popup | null = null

/**
 * @param track the track to build a download URL for
 */
function downloadUrl(track: Track): string {
	const filesClient = window.OCA?.Files?.App?.fileList?.filesClient
	return filesClient?.getBaseUrl
		? filesClient.getBaseUrl() + track.file_path
		: generateUrl('s/' + getToken() + '/download' + '?path=/&files=' + track.file_name)
}

/**
 * @param event the underlying MapLibre mouse event
 */
function onFeatureContextMenu(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const map = mapInstance.map
	const feature = event.features?.[0]
	const track = feature && displayedTracks.value[feature.properties.trackIndex]
	if (!map || !feature || !track) {
		return
	}
	event.preventDefault()

	const container = document.createElement('div')
	container.className = 'popup-track-wrapper'
	container.innerHTML = `
		${track.isUpdateable ? `<button class="action-btn" data-action="color">${escapeHtml(t('maps', 'Change color'))}</button>` : ''}
		<button class="action-btn" data-action="elevation">${escapeHtml(t('maps', 'Display elevation'))}</button>
		${!isPublic() && track.isShareable ? `<button class="action-btn" data-action="copy">${escapeHtml(t('maps', 'Copy to map'))}</button>` : ''}
		${(!isPublic() || !track.hideDownload) ? `<a class="action-btn" href="${downloadUrl(track)}" target="_self">${escapeHtml(t('maps', 'Download track'))}</a>` : ''}
	`
	container.addEventListener('click', (domEvent) => {
		const action = (domEvent.target as HTMLElement).getAttribute('data-action')
		if (action === 'color') {
			emit('change-color', track)
		} else if (action === 'elevation') {
			emit('display-elevation', track)
		} else if (action === 'copy') {
			emit('add-to-map-track', track)
		} else {
			// let the anchor handle the native download
			return
		}
		actionPopup?.remove()
	})

	hoverPopup?.remove()
	actionPopup?.remove()
	actionPopup = new Popup({ closeButton: false, className: 'popovermenu open popupMarker trackPopup' })
		.setLngLat(event.lngLat)
		.setDOMContent(container)
		.addTo(map)
}

function onLineMouseEnter(): void {
	const map = mapInstance.map
	if (map) {
		map.getCanvas().style.cursor = 'pointer'
	}
}

function onLineMouseLeave(): void {
	const map = mapInstance.map
	if (map) {
		map.getCanvas().style.cursor = ''
	}
}

let listenersBound = false

watch(() => mapInstance.isLoaded, (loaded) => {
	const map = mapInstance.map
	if (!loaded || !map || listenersBound) {
		return
	}
	listenersBound = true
	map.on('click', LINE_CASING_LAYER_ID, onFeatureClick)
	map.on('click', POINTS_LAYER_ID, onFeatureClick)
	map.on('contextmenu', LINE_CASING_LAYER_ID, onFeatureContextMenu)
	map.on('contextmenu', POINTS_LAYER_ID, onFeatureContextMenu)
	map.on('mouseenter', LINE_CASING_LAYER_ID, onLineMouseEnter)
	map.on('mouseleave', LINE_CASING_LAYER_ID, onLineMouseLeave)
	map.on('mouseenter', POINTS_LAYER_ID, onPointMouseEnter)
	map.on('mouseleave', POINTS_LAYER_ID, onPointMouseLeave)
}, { immediate: true })

onBeforeUnmount(() => {
	const map = mapInstance.map
	hoverPopup?.remove()
	actionPopup?.remove()
	if (!map || !listenersBound) {
		return
	}
	map.off('click', LINE_CASING_LAYER_ID, onFeatureClick)
	map.off('click', POINTS_LAYER_ID, onFeatureClick)
	map.off('contextmenu', LINE_CASING_LAYER_ID, onFeatureContextMenu)
	map.off('contextmenu', POINTS_LAYER_ID, onFeatureContextMenu)
	map.off('mouseenter', LINE_CASING_LAYER_ID, onLineMouseEnter)
	map.off('mouseleave', LINE_CASING_LAYER_ID, onLineMouseLeave)
	map.off('mouseenter', POINTS_LAYER_ID, onPointMouseEnter)
	map.off('mouseleave', POINTS_LAYER_ID, onPointMouseLeave)
})
</script>

<style lang="scss">
.tooltip-track-wrapper {
	padding: 6px;
	border-radius: 3px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
}

.popup-track-wrapper .action-btn {
	display: block;
	width: 100%;
	padding: 8px;
	border: none;
	background: transparent;
	text-align: left;
	text-decoration: none;
	color: var(--color-main-text);
	cursor: pointer;

	&:hover {
		background: var(--color-background-hover);
	}
}
</style>
