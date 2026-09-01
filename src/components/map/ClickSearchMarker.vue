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
import { onBeforeUnmount, ref, watch } from 'vue'
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
const loading = ref(true)
const formattedAddress = ref('')
let address: Record<string, string> | null = null

let marker: Marker | null = null
let popup: Popup | null = null

/**
 * @param container the popup's DOM content, rebuilt whenever the address changes
 */
function renderPopup(container: HTMLElement): void {
	container.innerHTML = loading.value
		? '<span class="icon icon-loading-small"></span>'
		: `
			<h3 class="click-search-title">${escapeHtml(t('maps', 'This place'))}</h3>
			<p class="click-search-address">${escapeHtml(formattedAddress.value)}</p>
			<button class="click-search-add-favorite" data-action="add-favorite">${escapeHtml(t('maps', 'Add to favorites'))}</button>
		`
	container.querySelector('[data-action="add-favorite"]')?.addEventListener('click', () => {
		emit('add-favorite', {
			latLng: props.latLng,
			name: formattedAddress.value,
			formattedAddress: formattedAddress.value,
		})
		emit('close')
	})
}

function teardown(): void {
	popup?.remove()
	marker?.remove()
	popup = null
	marker = null
}

watch(() => [mapInstance.map, props.latLng] as const, ([map, latLng]) => {
	teardown()
	if (!map || !latLng) {
		return
	}

	loading.value = true
	address = null
	formattedAddress.value = ''

	const container = document.createElement('div')
	container.className = 'popup-click-search-wrapper'
	renderPopup(container)

	popup = new Popup({ closeOnClick: false, className: 'popovermenu open popupMarker clickSearchPopup' })
		.setDOMContent(container)
	marker = new Marker({ opacity: 0 })
		.setLngLat([latLng.lng, latLng.lat])
		.setPopup(popup)
		.addTo(map)
	marker.togglePopup()

	geocode(latLng.lat, latLng.lng).then((response) => {
		address = response.data.address
		formattedAddress.value = formatAddress(address)
	}).catch((error) => {
		console.error(error)
	}).then(() => {
		loading.value = false
		renderPopup(container)
	})
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
