<!--
 - SPDX-FileCopyrightText: 2020 Julien Veyssier <eneiluj@posteo.net>
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	The popup needs Vue reactivity, so its template is teleported into a
	maplibregl.Popup.
-->
<template>
	<Teleport v-if="container" :to="container">
		<div class="place-contact-popup-content">
			<h3 id="place-popup-title">
				{{ t('maps', 'New contact address') }}
			</h3>
			<span v-if="addressLoading" class="icon icon-loading-small" />
			<textarea v-else
				id="placeContactPopupAddress"
				v-model="formattedAddress"
				@input="addressEdited = true" />
			<div class="contact-select">
				<label for="placeContactUserSelect">
					<span class="icon icon-user" />
				</label>
				<NcSelect id="placeContactUserSelect"
					v-model="selectedContact"
					class="contact-input"
					label="FN"
					:get-option-key="getContactKey"
					:aria-label-combobox="t('maps', 'Choose a contact')"
					:placeholder="t('maps', 'Choose a contact')"
					:loading="contactsLoading"
					:options="contactData">
					<template #option="option">
						<NcAvatar class="contact-avatar" :url="option.AVATAR_URL" :display-name="option.FN" />
						{{ option.FN }}
					</template>
				</NcSelect>
			</div>
			<div class="address-type">
				<label for="placeContactAddressType">
					<span class="icon icon-address" />
				</label>
				<select id="placeContactAddressType" v-model="addressType" :disabled="!selectedContact">
					<option value="home">
						🏠 {{ t('maps', 'Home') }}
					</option>
					<option value="work">
						🏢 {{ t('maps', 'Work') }}
					</option>
				</select>
			</div>
			<button class="submit-place-contact"
				:disabled="!selectedContact"
				:class="{ loading: searchingEditedAddress }"
				@click.stop="onValidate">
				<span class="icon-add" />
				{{ t('maps', 'Add address to contact') }}
			</button>
		</div>
	</Teleport>
</template>

<script setup lang="ts">
import type { Map as MaplibreMap } from 'maplibre-gl'
import { Marker, Popup } from 'maplibre-gl'
import accountIconUrl from '@mdi/svg/svg/account.svg'
import { getCurrentUser } from '@nextcloud/auth'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { NcAvatar, NcSelect } from '@nextcloud/vue'
import { onBeforeUnmount, ref, watch } from 'vue'
import { useMap } from 'vue-maplibre-gl'
import { formatAddress } from '../../utils.js'
import { geocode, searchAddress, searchContacts } from '../../network.js'

export interface AddressBookContact {
	BOOKID: string
	BOOKURI: string
	URI: string
	UID: string
	FN: string
	READONLY?: boolean
	HAS_PHOTO?: boolean
	HAS_PHOTO2?: boolean
	AVATAR_URL?: string
}

const props = defineProps<{
	latLng: { lat: number, lng: number }
}>()

const emit = defineEmits<{
	'contact-placed': [data: { contact: AddressBookContact, latLng: { lat: number, lng: number }, address: Record<string, string>, addressType: string }]
	close: []
}>()

const mapInstance = useMap()

const contactData = ref<AddressBookContact[]>([])
const contactsLoaded = ref(false)
const contactsLoading = ref(false)
const addressLoading = ref(true)
const addressEdited = ref(false)
const searchingEditedAddress = ref(false)
const addressType = ref<'home' | 'work'>('home')
const selectedContact = ref<AddressBookContact | null>(null)
const formattedAddress = ref('')
let address: Record<string, string> | null = null

/**
 * @param contact the option to derive a unique key for
 */
function getContactKey(contact: AddressBookContact): string {
	return contact.URI
}

/**
 * @param contact the contact whose avatar photo URL to build
 */
function getContactAvatar(contact: AddressBookContact): string | undefined {
	if (contact.HAS_PHOTO && contact.HAS_PHOTO2) {
		return generateUrl(
			'/remote.php/dav/addressbooks/users/' + getCurrentUser()?.uid
			+ '/' + encodeURIComponent(contact.BOOKURI)
			+ '/' + encodeURIComponent(contact.URI) + '?photo').replace(/index\.php\//, '')
	}
	return undefined
}

async function loadContacts(): Promise<void> {
	if (contactsLoaded.value || contactsLoading.value) {
		return
	}
	contactsLoading.value = true
	try {
		const response = await searchContacts()
		contactData.value = (response.data as AddressBookContact[]).filter((c) => !c.READONLY).map((c) => ({
			...c,
			AVATAR_URL: getContactAvatar(c),
		}))
		contactsLoaded.value = true
	} catch (error) {
		console.error(error)
	} finally {
		contactsLoading.value = false
	}
}

/**
 * @param contact the currently selected contact, if any
 */
function markerIconUrl(contact: AddressBookContact | null): string {
	if (!contact) {
		return accountIconUrl
	}
	return getContactAvatar(contact) || generateUrl('/apps/maps/contacts-avatar?name=' + encodeURIComponent(contact.FN))
}

function getAddress(): void {
	addressLoading.value = true
	geocode(props.latLng.lat, props.latLng.lng).then((response: { data: { address: Record<string, string> } }) => {
		address = response.data.address
		formattedAddress.value = formatAddress(address)
	}).catch((error: unknown) => {
		console.error(error)
	}).then(() => {
		addressLoading.value = false
	})
}

function onValidate(): void {
	const contact = selectedContact.value
	if (!contact) {
		return
	}
	if (!addressEdited.value) {
		emit('contact-placed', {
			contact,
			latLng: props.latLng,
			address: address ?? {},
			addressType: addressType.value,
		})
		emit('close')
		return
	}
	searchingEditedAddress.value = true
	searchAddress(formattedAddress.value, 1).then((response: { data: Array<{ address: Record<string, string>, lat: string, lon: string }> }) => {
		const res = response.data
		const found = res.length > 0 && res[0].address && res[0].lat && res[0].lon
		emit('contact-placed', {
			contact,
			latLng: found ? { lat: parseFloat(res[0].lat), lng: parseFloat(res[0].lon) } : props.latLng,
			address: (found ? res[0].address : address) ?? {},
			addressType: addressType.value,
		})
		emit('close')
	}).catch((error: unknown) => {
		console.error(error)
	}).then(() => {
		searchingEditedAddress.value = false
	})
}

const container = ref<HTMLDivElement | null>(null)
let marker: Marker | null = null
let popup: Popup | null = null
let markerEl: HTMLDivElement | null = null

function teardown(): void {
	popup?.remove()
	marker?.remove()
	popup = null
	marker = null
	markerEl = null
	container.value = null
}

watch(selectedContact, (contact) => {
	if (markerEl) {
		markerEl.style.backgroundImage = `url(${markerIconUrl(contact)})`
	}
})

watch(() => [mapInstance.map, props.latLng] as const, ([map, latLng]) => {
	teardown()
	if (!map || !latLng) {
		return
	}

	addressEdited.value = false
	address = null
	formattedAddress.value = ''
	getAddress()

	const el = document.createElement('div')
	container.value = el

	markerEl = document.createElement('div')
	markerEl.className = 'place-contact-marker-icon'
	markerEl.style.backgroundImage = `url(${markerIconUrl(selectedContact.value)})`

	// on() returns a subscription, not the popup, so it cannot be chained
	popup = new Popup({ closeButton: true, className: 'popovermenu open popupMarker placeContactPopup' })
		.setDOMContent(el)
	popup.on('close', () => emit('close'))
	marker = new Marker({ element: markerEl })
		.setLngLat([latLng.lng, latLng.lat])
		.setPopup(popup)
		.addTo(map as MaplibreMap)
	marker.togglePopup()
}, { immediate: true })

loadContacts()

onBeforeUnmount(() => {
	teardown()
})
</script>

<style lang="scss">
.place-contact-marker-icon {
	width: 40px;
	height: 40px;
	background-color: var(--color-main-background);
	background-size: cover;
	background-position: center;
	border: 2px solid #fff;
	border-radius: 50%;
	box-shadow: 0 0 4px #888;
}

.place-contact-popup-content {
	width: 220px;

	.submit-place-contact {
		height: 40px !important;
		width: 100%;
	}

	.contact-select,
	.address-type {
		display: flex;

		label {
			margin: auto 0;
			text-align: right;
		}
	}

	.address-type select {
		flex-grow: 1;
		margin: 3px 0 0;
	}

	span.icon {
		display: inline-block;
		height: 34px;
		width: 34px;

		&.icon-loading-small {
			display: block;
			margin-left: auto;
			margin-right: auto;
		}
	}

	.contact-avatar {
		margin-right: 10px;
	}

	#place-popup-title {
		text-align: center;
		font-weight: bold;
	}

	#placeContactPopupAddress {
		border: none;
		width: 100%;
		height: 75px;
		resize: vertical;
	}
}
</style>
