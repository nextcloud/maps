<template>
	<LMarker :lat-lng="latLng"
		@ready="onMarkerReady">
		<LIcon
			class-name="placement-marker-icon"
			:icon-size="[40, 40]"
			:icon-url="markerIconUrl" />
		<LPopup :options="popupOptions">
			<h3>{{ t('maps', 'New contact address') }}</h3>
			<span v-if="addressLoading"
				class="icon icon-loading-small" />
			<textarea v-else
				id="placeContactPopupAddress"
				v-model="formattedAddress"
				@input="addressEdited = true" />
			<br>
			<span class="icon icon-user" />
			<Multiselect
				ref="userMultiselect"
				v-model="selectedContact"
				class="contact-input"
				track-by="URI"
				label="FN"
				:placeholder="t('maps', 'Choose a contact')"
				:options="contactData"
				:internal-search="true">
				<template #option="{option}">
					<Avatar
						class="contact-avatar"
						:is-no-user="true"
						:url="option.AVATAR_URL"
						:user="option.FN" />
					{{ option.FN }}
				</template>
			</Multiselect>
			<br>
			<label for="addressTypeSelect">{{ t('maps', 'Address type') }}</label>
			<select id="addressTypeSelect"
				v-model="addressType"
				:disabled="!selectedContact">
				<option value="home">
					{{ t('maps', 'Home') }}
				</option>
				<option value="work">
					{{ t('maps', 'Work') }}
				</option>
			</select>
			<br>
			<button id="submitPlaceContactButton"
				:disabled="!selectedContact"
				:class="{ loading: searchingEditedAddress }"
				@click="onValidate">
				{{ t('maps', 'Add address to contact') }}
			</button>
		</LPopup>
	</LMarker>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'

import { LMarker, LPopup, LIcon } from 'vue2-leaflet'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'

import { formatAddress } from '../../utils'
import { getAllContacts, geocode, searchAddress, placeContact } from '../../network'

export default {
	name: 'PlaceContactPopup',
	components: {
		LMarker,
		LPopup,
		LIcon,
		Multiselect,
		Avatar,
	},

	props: {
		latLng: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			popupOptions: {
				closeButton: false,
				offset: [-2, 40],
			},
			contactData: [],
			addressLoading: false,
			addressEdited: false,
			searchingEditedAddress: false,
			addressType: 'home',
			address: null,
			formattedAddress: '',
			selectedContact: null,
		}
	},

	computed: {
		markerIconUrl() {
			return this.selectedContact
				? this.getContactAvatar(this.selectedContact)
					? this.getContactAvatar(this.selectedContact)
					: generateUrl('/apps/maps/contacts-avatar?name=' + encodeURIComponent(this.selectedContact.FN))
				: generateUrl('/svg/core/actions/user?color=000000')
		},
	},

	watch: {
		latLng() {
			this.address = null
			this.formattedAddress = ''
			this.addressEdited = false
			this.getAddress()
		},
	},

	beforeMount() {
		this.getContactData()
		this.getAddress()
	},

	methods: {
		getContactData() {
			getAllContacts().then((response) => {
				this.contactData = response.data.filter((c) => { return !c.READONLY }).map((c) => {
					return {
						...c,
						AVATAR_URL: this.getContactAvatar(c),
					}
				})
			}).catch((error) => {
				console.error(error)
			})
		},
		onMarkerReady(m) {
			m.openPopup()
		},
		getContactAvatar(contact) {
			if (contact.HAS_PHOTO && !contact.HAS_PHOTO2) {
				return generateUrl(
					'/remote.php/dav/addressbooks/users/' + getCurrentUser().uid
					+ '/' + encodeURIComponent(contact.BOOKURI)
					+ '/' + encodeURIComponent(contact.URI) + '?photo').replace(/index\.php\//, '')
			}
			return undefined
		},
		getAddress() {
			this.addressLoading = true
			geocode(this.latLng.lat, this.latLng.lng).then((response) => {
				this.address = response.data.address
				this.formattedAddress = formatAddress(this.address)
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				this.addressLoading = false
			})
		},
		onValidate() {
			if (!this.addressEdited) {
				placeContact(this.selectedContact.BOOKID, this.selectedContact.URI,
					this.selectedContact.UID, this.latLng.lat, this.latLng.lng,
					this.address, this.addressType
				).then((response) => {
					this.$emit('contact-placed')
				})
			} else {
				this.searchingEditedAddress = true
				searchAddress(this.formattedAddress, 1).then((response) => {
					const res = response.data
					const addressFound = (res.length > 0 && res[0].address && res[0].lat && res[0].lon)
					const address = addressFound ? res[0].address : this.address
					const lat = addressFound ? res[0].lat : this.latLng.lat
					const lng = addressFound ? res[0].lon : this.latLng.lng
					placeContact(this.selectedContact.BOOKID, this.selectedContact.URI,
						this.selectedContact.UID, lat, lng, address, this.addressType
					).then((response) => {
						this.$emit('contact-placed')
					})
				}).catch((error) => {
					console.error(error)
				}).then(() => {
					this.searchingEditedAddress = true
				})
			}
		},
	},
}
</script>

<style lang="scss" scoped>
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
</style>
