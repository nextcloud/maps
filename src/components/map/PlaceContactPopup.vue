<template>
	<LMarker :lat-lng="latLng"
		@ready="onMarkerReady">
		<LIcon
			class-name="placement-marker-icon"
			:icon-size="[40, 40]"
			:icon-url="markerIconUrl" />
		<LPopup :options="popupOptions"
			@ready="onPopupReady">
			<h3 id="place-popup-title">
				{{ t('maps', 'New contact address') }}
			</h3>
			<span v-if="addressLoading"
				class="icon icon-loading-small" />
			<textarea v-else
				id="placeContactPopupAddress"
				v-model="formattedAddress"
				@input="addressEdited = true" />
			<br>
			<div class="contact-select">
				<label for="userMultiselect">
					<span class="icon icon-user" />
				</label>
				<NcSelect
					id="userMultiselect"
					ref="userMultiselect"
					v-model="selectedContact"
					class="contact-input"
					track-by="URI"
					label="FN"
					:placeholder="t('maps', 'Choose a contact')"
					:options="contactData"
					:internal-search="true"
					@search="asyncSearchContacts">
					<template #option="option">
						<Avatar
							class="contact-avatar"
							:is-no-user="true"
							:url="option.AVATAR_URL"
							:user="option.FN" />
						{{ option.FN }}
					</template>
				</NcSelect>
			</div>
			<div class="address-type"
				:name="t('maps', 'Address type')">
				<label for="addressTypeSelect">
					<span class="icon icon-address" />
				</label>
				<select id="addressTypeSelect"
					v-model="addressType"
					:disabled="!selectedContact">
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
				@click="onValidate">
				<span class="icon-add" />
				{{ t('maps', 'Add address to contact') }}
			</button>
		</LPopup>
	</LMarker>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'

import { LMarker, LPopup, LIcon } from 'vue2-leaflet'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'

import { formatAddress } from '../../utils.js'
import { searchContacts, geocode, searchAddress } from '../../network.js'

export default {
	name: 'PlaceContactPopup',
	components: {
		LMarker,
		LPopup,
		LIcon,
		NcSelect,
		NcAvatar,
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
		this.getAddress()
	},

	methods: {
		asyncSearchContacts(query) {
			if (query === '') {
				this.contactData = []
				return
			}
			searchContacts(query).then((response) => {
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
		onPopupReady(p) {
			// i don't know why but it is placed too high when it's created
			p.setLatLng(this.latLng)
		},
		getContactAvatar(contact) {
			if (contact.HAS_PHOTO && contact.HAS_PHOTO2) {
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
				this.$emit('contact-placed', {
					contact: this.selectedContact,
					latLng: this.latLng,
					address: this.address,
					addressType: this.addressType,
				})
			} else {
				this.searchingEditedAddress = true
				searchAddress(this.formattedAddress, 1).then((response) => {
					const res = response.data
					const addressFound = (res.length > 0 && res[0].address && res[0].lat && res[0].lon)
					const address = addressFound ? res[0].address : this.address
					const lat = addressFound ? res[0].lat : this.latLng.lat
					const lng = addressFound ? res[0].lon : this.latLng.lng

					this.$emit('contact-placed', {
						contact: this.selectedContact,
						latLng: { lat, lng },
						address,
						addressType: this.addressType,
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
.submit-place-contact {
	height: 40px !important;
	width: 100%;
}

.contact-select,
.address-type {
	display: flex;
	label {
		margin: auto 0 auto 0;
		text-align: right;
	}
}

.contact-select {
	.multiselect {
		flex-grow: 1;
	}
}

.address-type {
	select {
		flex-grow: 1;
		margin: 3px 0 0 0;
	}
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
	resize: block;
}
</style>
