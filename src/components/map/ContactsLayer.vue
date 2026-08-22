<!--
 - @copyright Copyright (c) 2019, Paul Schwörer <hello@paulschwoerer.de>
 -
 - @author Paul Schwörer <hello@paulschwoerer.de>
 -
 - @license GNU AGPL version 3 or any later version
 -
 - This program is free software: you can redistribute it and/or modify
 - it under the terms of the GNU Affero General Public License as
 - published by the Free Software Foundation, either version 3 of the
 - License, or (at your option) any later version.
 -
 - This program is distributed in the hope that it will be useful,
 - but WITHOUT ANY WARRANTY; without even the implied warranty of
 - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 - GNU Affero General Public License for more details.
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program.  If not, see <http://www.gnu.org/licenses/>.
 -->

<!--
	Ported from Leaflet to vue-maplibre-gl. Not ported yet, on purpose:
	spiderfying overlapping markers at max zoom.
-->
<template>
	<MglGeoJsonSource :source-id="SOURCE_ID"
		:data="geojson"
		:cluster="true"
		:cluster-radius="CLUSTER_RADIUS">
		<MglSymbolLayer :layer-id="CLUSTER_LAYER_ID"
			:source="SOURCE_ID"
			:filter="['has', 'point_count']"
			:layout="{ 'icon-image': CLUSTER_ICON_ID, 'icon-allow-overlap': true, 'icon-size': 1 }" />
		<MglSymbolLayer :layer-id="CLUSTER_COUNT_LAYER_ID"
			:source="SOURCE_ID"
			:filter="['has', 'point_count']"
			:layout="{ 'text-field': ['get', 'point_count_abbreviated'], 'text-size': 12, 'text-allow-overlap': true, 'text-ignore-placement': true }"
			:paint="{ 'text-color': '#ffffff' }" />
		<MglSymbolLayer :layer-id="POINT_LAYER_ID"
			:source="SOURCE_ID"
			:filter="['!', ['has', 'point_count']]"
			:layout="{ 'icon-image': ['get', 'iconId'], 'icon-allow-overlap': true, 'icon-size': 1 }" />
	</MglGeoJsonSource>
</template>

<script setup lang="ts">
import type { MapGeoJSONFeature, MapMouseEvent } from 'maplibre-gl'
import { Popup } from 'maplibre-gl'
import { getCurrentUser } from '@nextcloud/auth'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed, onBeforeUnmount, reactive, watch } from 'vue'
import { MglGeoJsonSource, MglSymbolLayer, useMap } from 'vue-maplibre-gl'
import { getToken, isPublic } from '../../utils/common.js'
import { escapeHtml } from '../../utils/domUtils.ts'
import { splitByNonEscapedComma } from '../../utils.js'
import { geoToLatLng } from '../../utils/mapUtils.js'

export interface Contact {
	UID: string
	URI: string
	BOOKURI: string
	FN?: string
	GEO: string
	GROUPS?: string
	ADR?: string
	ADRTYPE?: string | string[]
	URL?: string
	HAS_PHOTO?: boolean
	isDeletable: boolean
	isUpdateable: boolean
}

export interface ContactGroup {
	enabled: boolean
}

const props = defineProps<{
	contacts: Contact[]
	groups: Record<string, ContactGroup>
}>()

const emit = defineEmits<{
	'address-deleted': [contact: Contact]
	'add-to-map-contact': [contact: Contact]
}>()

const SOURCE_ID = 'contacts'
const CLUSTER_LAYER_ID = 'contacts-clusters'
const CLUSTER_COUNT_LAYER_ID = 'contacts-cluster-count'
const POINT_LAYER_ID = 'contacts-points'
// Matches the old marker view size (40px) + old cluster radius margin (10px)
const MARKER_SIZE = 40
const CLUSTER_RADIUS = 50
const PLACEHOLDER_ICON_ID = 'contact-avatar-placeholder'
const CLUSTER_ICON_ID = 'contact-cluster-icon'

const mapInstance = useMap()
const loadedIcons = reactive(new Set<string>())
const loadingIcons = new Set<string>()

/**
 * @param contact the contact to build an avatar URL for
 */
function getContactAvatar(contact: Contact): string {
	if (contact.HAS_PHOTO) {
		return generateUrl('/remote.php/dav/addressbooks/users/' + getCurrentUser()?.uid + '/' + encodeURIComponent(contact.BOOKURI) + '/' + encodeURIComponent(contact.URI) + '?photo').replace(/index\.php\//, '')
	}
	return generateUrl('/apps/maps' + (isPublic() ? '/s/' + getToken() : '') + '/contacts-avatar?name=' + encodeURIComponent(contact.FN ?? ''))
}

/**
 * @param url the avatar URL to derive a sprite id for
 */
function iconIdForUrl(url: string): string {
	return 'contact-avatar-' + btoa(url).replace(/[^a-zA-Z0-9]/g, '')
}

const displayedContacts = computed<Contact[]>(() => {
	return props.contacts.filter((c) => {
		if (c.GROUPS) {
			try {
				return splitByNonEscapedComma(c.GROUPS).some((g: string) => props.groups[g]?.enabled)
			} catch (error) {
				console.error(error)
				return false
			}
		}
		return !!props.groups['0']?.enabled
	})
})

const geojson = computed(() => ({
	type: 'FeatureCollection' as const,
	features: displayedContacts.value.map((contact, index) => {
		const [lat, lng] = geoToLatLng(contact.GEO)
		const avatarUrl = getContactAvatar(contact)
		const iconId = iconIdForUrl(avatarUrl)
		return {
			type: 'Feature' as const,
			geometry: { type: 'Point' as const, coordinates: [Number(lng), Number(lat)] },
			properties: {
				index,
				iconId: loadedIcons.has(iconId) ? iconId : PLACEHOLDER_ICON_ID,
			},
		}
	}),
}))

const usedAvatarUrls = computed<string[]>(() => {
	return [...new Set(displayedContacts.value.map((c) => getContactAvatar(c)))]
})

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

function ensureFallbackIcons(): void {
	const map = mapInstance.map
	if (!map) {
		return
	}
	if (!map.hasImage(PLACEHOLDER_ICON_ID)) {
		const image = drawIcon(MARKER_SIZE, (ctx) => {
			ctx.beginPath()
			ctx.arc(MARKER_SIZE / 2, MARKER_SIZE / 2, MARKER_SIZE / 2 - 2, 0, Math.PI * 2)
			ctx.fillStyle = '#dddddd'
			ctx.fill()
			ctx.lineWidth = 2
			ctx.strokeStyle = '#ffffff'
			ctx.stroke()
		})
		if (image) {
			map.addImage(PLACEHOLDER_ICON_ID, image)
		}
	}
	if (!map.hasImage(CLUSTER_ICON_ID)) {
		const image = drawIcon(MARKER_SIZE, (ctx) => {
			ctx.beginPath()
			ctx.arc(MARKER_SIZE / 2, MARKER_SIZE / 2, MARKER_SIZE / 2 - 2, 0, Math.PI * 2)
			ctx.fillStyle = '#0082c9'
			ctx.fill()
			ctx.lineWidth = 2
			ctx.strokeStyle = '#ffffff'
			ctx.stroke()
		})
		if (image) {
			map.addImage(CLUSTER_ICON_ID, image)
		}
	}
}

/**
 * @param url the avatar image URL to fetch and clip to a circle
 */
function loadCircularAvatar(url: string): Promise<ImageData> {
	return new Promise((resolve, reject) => {
		const img = new Image()
		img.crossOrigin = 'anonymous'
		img.onload = () => {
			const canvas = document.createElement('canvas')
			canvas.width = MARKER_SIZE
			canvas.height = MARKER_SIZE
			const ctx = canvas.getContext('2d')
			if (!ctx) {
				reject(new Error('Could not get canvas context'))
				return
			}
			ctx.beginPath()
			ctx.arc(MARKER_SIZE / 2, MARKER_SIZE / 2, MARKER_SIZE / 2 - 1, 0, Math.PI * 2)
			ctx.closePath()
			ctx.clip()
			ctx.drawImage(img, 0, 0, MARKER_SIZE, MARKER_SIZE)
			resolve(ctx.getImageData(0, 0, MARKER_SIZE, MARKER_SIZE))
		}
		img.onerror = () => reject(new Error(`Failed to load avatar ${url}`))
		img.src = url
	})
}

/**
 * @param url the avatar URL to load and register as a GL sprite image
 */
async function ensureAvatarIcon(url: string): Promise<void> {
	const id = iconIdForUrl(url)
	const map = mapInstance.map
	if (!map || loadedIcons.has(id) || loadingIcons.has(id) || map.hasImage(id)) {
		return
	}
	loadingIcons.add(id)
	try {
		const image = await loadCircularAvatar(url)
		if (mapInstance.map && !mapInstance.map.hasImage(id)) {
			mapInstance.map.addImage(id, image)
		}
		loadedIcons.add(id)
	} catch (error) {
		console.error(error)
	} finally {
		loadingIcons.delete(id)
	}
}

watch(() => mapInstance.isLoaded, (loaded) => {
	if (loaded) {
		ensureFallbackIcons()
	}
}, { immediate: true })

watch([() => mapInstance.isLoaded, usedAvatarUrls], () => {
	if (!mapInstance.isLoaded) {
		return
	}
	usedAvatarUrls.value.forEach((url) => ensureAvatarIcon(url))
}, { immediate: true })

/**
 * @param contact the contact to build address/type HTML for
 */
function buildAddressHtml(contact: Contact): { adrTypesHtml: string, addressHtml: string } {
	// The backend returns ADRTYPE as a plain string for single-address contacts, an array for multi-address ones.
	const adrTypes = Array.isArray(contact.ADRTYPE) ? contact.ADRTYPE : (contact.ADRTYPE ? [contact.ADRTYPE] : [])
	const adrTypesHtml = adrTypes.map((adrType) => {
		const type = adrType.toLowerCase()
		const label = type === 'home' ? t('maps', 'Home') : type === 'work' ? t('maps', 'Work') : t('maps', type)
		return `<span class="tooltip-contact-address-type">${escapeHtml(label)}</span>`
	}).join(' ')

	const adrTab = (contact.ADR || '').split(';').map((s) => s.trim())
	const formattedLines: string[] = []
	if (adrTab.length > 6) {
		if (adrTab[2]) {
			formattedLines.push(adrTab[2])
		}
		formattedLines.push(adrTab[5] + ' ' + adrTab[3])
		formattedLines.push(adrTab[4] + ' ' + adrTab[6])
	}
	const addressHtml = formattedLines.filter((s) => s).map((l) => `<p class="tooltip-contact-address">${escapeHtml(l)}</p>`).join('')

	return { adrTypesHtml, addressHtml }
}

let hoverPopup: Popup | null = null
let actionPopup: Popup | null = null

/**
 * @param event the underlying MapLibre mouse event
 */
function onPointMouseEnter(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const map = mapInstance.map
	const feature = event.features?.[0]
	if (!map || !feature) {
		return
	}
	const contact = displayedContacts.value[feature.properties.index]
	if (!contact) {
		return
	}
	map.getCanvas().style.cursor = 'pointer'

	const avatarUrl = getContactAvatar(contact)
	const { adrTypesHtml, addressHtml } = buildAddressHtml(contact)
	const html = document.createElement('div')
	html.className = 'tooltip-contact-wrapper'
	html.innerHTML = `
		<img class="tooltip-contact-avatar" src="${avatarUrl}">
		<div class="tooltip-contact-content">
			<h3 class="tooltip-contact-name">${escapeHtml(contact.FN || '')}</h3>
			<p class="tooltip-contact-address-type">${adrTypesHtml}</p>
			${addressHtml}
		</div>
	`

	hoverPopup?.remove()
	hoverPopup = new Popup({ closeButton: false, closeOnClick: false, offset: 20 })
		.setLngLat((feature.geometry as { coordinates: [number, number] }).coordinates)
		.setDOMContent(html)
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
 * @param contact the contact whose location should be removed
 */
function onDeleteAddressClick(contact: Contact): void {
	if (contact.ADR && contact.GEO) {
		delete (contact as { GEO?: string }).GEO
	}
	emit('address-deleted', contact)
	actionPopup?.remove()
	actionPopup = null
}

/**
 * @param event the underlying MapLibre mouse event
 */
function onPointClick(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const map = mapInstance.map
	const feature = event.features?.[0]
	if (!map || !feature) {
		return
	}
	const contact = displayedContacts.value[feature.properties.index]
	if (!contact) {
		return
	}

	const avatarUrl = getContactAvatar(contact)
	const { adrTypesHtml, addressHtml } = buildAddressHtml(contact)
	const contactUrl = contact.URL || generateUrl('/apps/contacts/direct/contact/' + encodeURIComponent(contact.UID + '~contacts'))

	const popupContent = document.createElement('div')
	popupContent.className = 'popup-contact-wrapper'
	popupContent.innerHTML = `
		<div class="left-contact-popup">
			<img class="tooltip-contact-avatar" src="${avatarUrl}">
			${contact.isDeletable ? `<button class="icon icon-delete" data-action="delete" title="${contact.ADR ? escapeHtml(t('maps', 'Delete this address')) : escapeHtml(t('maps', 'Delete this location'))}"></button>` : ''}
			${!isPublic() ? `<button class="icon icon-share" data-action="copy" title="${escapeHtml(t('maps', 'Copy to map'))}"></button>` : ''}
		</div>
		<div class="tooltip-contact-content">
			<h3 class="tooltip-contact-name">${escapeHtml(contact.FN || '')}</h3>
			<p class="tooltip-contact-address-type">${adrTypesHtml}</p>
			${addressHtml}
			${contact.UID && contact.URI ? `<a target="_blank" href="${contactUrl}">${escapeHtml(t('maps', 'Open in Contacts'))}</a>` : ''}
		</div>
	`
	popupContent.addEventListener('click', (domEvent) => {
		const action = (domEvent.target as HTMLElement).getAttribute('data-action')
		if (action === 'delete') {
			onDeleteAddressClick(contact)
		}
		if (action === 'copy') {
			emit('add-to-map-contact', contact)
		}
	})

	hoverPopup?.remove()
	actionPopup?.remove()
	actionPopup = new Popup({ closeButton: false, className: 'popovermenu open popupMarker contactPopup', offset: 20 })
		.setLngLat((feature.geometry as { coordinates: [number, number] }).coordinates)
		.setDOMContent(popupContent)
		.addTo(map)
}

/**
 * @param event the underlying MapLibre mouse event
 */
function onPointContextMenu(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	event.preventDefault()
	onPointClick(event)
}

/**
 * @param event the underlying MapLibre mouse event
 */
function onClusterClick(event: MapMouseEvent & { features?: MapGeoJSONFeature[] }): void {
	const feature = event.features?.[0]
	const map = mapInstance.map
	if (!feature || !map) {
		return
	}
	const source = map.getSource(SOURCE_ID)
	const clusterId = feature.properties.cluster_id
	// @ts-expect-error getClusterExpansionZoom exists on GeoJSONSource but isn't in this narrowed type
	source?.getClusterExpansionZoom(clusterId).then((zoom: number) => {
		map.easeTo({
			center: (feature.geometry as { coordinates: [number, number] }).coordinates,
			zoom,
		})
	}).catch(() => {})
}

function onClusterMouseEnter(): void {
	const map = mapInstance.map
	if (map) {
		map.getCanvas().style.cursor = 'pointer'
	}
}

function onClusterMouseLeave(): void {
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
	map.on('click', POINT_LAYER_ID, onPointClick)
	map.on('click', CLUSTER_LAYER_ID, onClusterClick)
	map.on('contextmenu', POINT_LAYER_ID, onPointContextMenu)
	map.on('mouseenter', POINT_LAYER_ID, onPointMouseEnter)
	map.on('mouseleave', POINT_LAYER_ID, onPointMouseLeave)
	map.on('mouseenter', CLUSTER_LAYER_ID, onClusterMouseEnter)
	map.on('mouseleave', CLUSTER_LAYER_ID, onClusterMouseLeave)
}, { immediate: true })

onBeforeUnmount(() => {
	const map = mapInstance.map
	hoverPopup?.remove()
	actionPopup?.remove()
	if (!map || !listenersBound) {
		return
	}
	map.off('click', POINT_LAYER_ID, onPointClick)
	map.off('click', CLUSTER_LAYER_ID, onClusterClick)
	map.off('contextmenu', POINT_LAYER_ID, onPointContextMenu)
	map.off('mouseenter', POINT_LAYER_ID, onPointMouseEnter)
	map.off('mouseleave', POINT_LAYER_ID, onPointMouseLeave)
	map.off('mouseenter', CLUSTER_LAYER_ID, onClusterMouseEnter)
	map.off('mouseleave', CLUSTER_LAYER_ID, onClusterMouseLeave)
})
</script>

<style lang="scss">
.tooltip-contact-wrapper {
	display: flex;
	gap: 8px;
	padding: 6px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	border-radius: 3px;
}

.tooltip-contact-avatar {
	width: 40px;
	height: 40px;
	border-radius: 50%;
	object-fit: cover;
}

.popup-contact-wrapper {
	display: flex;
}

.left-contact-popup {
	display: flex;
	flex-direction: column;
	align-items: center;

	button {
		width: 44px;
		height: 44px !important;
		background-color: transparent;
		border: 0;
		cursor: pointer;

		&:hover {
			background-color: var(--color-background-hover);
		}
	}
}
</style>
