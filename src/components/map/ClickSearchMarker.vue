<!--
 - SPDX-FileCopyrightText: 2019 Paul Schwörer <hello@paulschwoerer.de>
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	Ported from Leaflet (ClickSearchPopup.vue) to vue-maplibre-gl. Used
	both for the search box's "coordinate" result (typing raw lat,lng
	text) and for a plain click on empty map (Map.vue's onMapClick sets
	the same clickSearchLatLng either way).

	Not ported: "Add contact address" and the window.OCA.Maps.mapActions
	extension point, for the same reason as PoiMarkersLayer.vue - they
	depended on a "place an existing contact/entity by address" mode
	that isn't implemented yet.
-->
<template>
	<div />
</template>

<script setup lang="ts">
import { Marker, Popup } from 'maplibre-gl'
import { translate as t } from '@nextcloud/l10n'
import { onBeforeUnmount, watch } from 'vue'
import { useMap } from 'vue-maplibre-gl'
import { formatAddress } from '../../utils.js'
import { escapeHtml } from '../../utils/domUtils.ts'
import { geocode } from '../../network.js'

const props = defineProps<{
	latLng: { lat: number, lng: number }
}>()

const emit = defineEmits<{
	'add-favorite': [favorite: { latLng: { lat: number, lng: number }, name: string, formattedAddress: string }]
	close: []
}>()

const mapInstance = useMap()

let marker: Marker | null = null
let popup: Popup | null = null
// guards against a slow geocode answer for a previous position opening a popup
let requestId = 0

/**
 * @param formattedAddress the address of the clicked place
 */
function buildPopupContent(formattedAddress: string): HTMLElement {
	const container = document.createElement('div')
	container.className = 'popup-click-search-wrapper'
	container.innerHTML = `
		<h3 class="click-search-title">${escapeHtml(t('maps', 'This place'))}</h3>
		<p class="click-search-address">${escapeHtml(formattedAddress)}</p>
		<button class="click-search-add-favorite" data-action="add-favorite">${escapeHtml(t('maps', 'Add to favorites'))}</button>
	`
	container.querySelector('[data-action="add-favorite"]')?.addEventListener('click', () => {
		emit('add-favorite', {
			latLng: props.latLng,
			name: formattedAddress,
			formattedAddress,
		})
		emit('close')
	})
	return container
}

function teardown(): void {
	popup?.remove()
	marker?.remove()
	popup = null
	marker = null
}

watch(() => [mapInstance.map, props.latLng] as const, async ([map, latLng]) => {
	teardown()
	const currentRequest = ++requestId
	if (!map || !latLng) {
		return
	}

	let formattedAddress = ''
	try {
		const response = await geocode(latLng.lat, latLng.lng)
		formattedAddress = formatAddress(response.data.address).trim()
	} catch (error) {
		console.error(error)
	}
	if (currentRequest !== requestId) {
		return
	}
	if (!formattedAddress) {
		// e.g. open sea, where there is no address to show or save
		emit('close')
		return
	}

	popup = new Popup({ closeOnClick: false, className: 'popovermenu open popupMarker clickSearchPopup' })
		.setDOMContent(buildPopupContent(formattedAddress))
	marker = new Marker({ opacity: 0 })
		.setLngLat([latLng.lng, latLng.lat])
		.setPopup(popup)
		.addTo(map)
	marker.togglePopup()
}, { immediate: true })

onBeforeUnmount(() => {
	teardown()
})
</script>

<style lang="scss">
.popup-click-search-wrapper {
	padding: 8px;
	max-width: 220px;
	text-align: center;

	.click-search-add-favorite {
		width: 100%;
	}
}
</style>
