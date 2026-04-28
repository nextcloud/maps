<template>
	<MglMarker :coordinates="[contact.GEO.lng, contact.GEO.lat]">
		<template #default>
			<div class="contact-marker-icon">
				<div class="thumbnail" :style="'background-image: url(\'' + contactAvatar + '\');'" />
			</div>
			<MglPopup
				v-if="showPopup"
				:close-button="false"
				anchor="bottom"
				@close="showPopup = false">
				<div v-if="click === 'left'">
					<div class="left-contact-popup">
						<img class="tooltip-contact-avatar"
							:src="contactAvatar"
							alt="">
						<button v-if="contact.isDeletable"
							v-tooltip="{ content: contact.ADR?t('maps', 'Delete this address'):t('maps', 'Delete this location') }"
							class="icon icon-delete"
							@click="onDeleteAddressClick()" />
						<button v-if="!isPublicVal"
							v-tooltip="{ content: t('maps', 'Copy to map') }"
							class="icon icon-share"
							@click="$emit('add-to-map-contact', contact)" />
					</div>
					<div class="tooltip-contact-content">
						<h3 class="tooltip-contact-name">
							{{ contact.FN }}
						</h3>
						<p class="tooltip-contact-address-type">
							<template v-for="adrtype in contact.ADRTYPE">
								<span v-if="adrtype.toLowerCase() === 'home'" class="tooltip-contact-address-type">
									{{ t('maps', 'Home') }}
								</span>
								<span v-else-if="adrtype.toLowerCase() === 'work'" class="tooltip-contact-address-type">
									{{ t('maps', 'Work') }}
								</span>
								<span v-else class="tooltip-contact-address-type">
									{{ t('maps', adrtype.toLowerCase()) }}
								</span>
							</template>
						</p>
						<p v-for="l in formattedAddressLines" :key="l" class="tooltip-contact-address">
							{{ l }}
						</p>
						<a v-if="contact.UID && contact.URI" target="_blank" :href="contactUrl">
							{{ t('maps', 'Open in Contacts') }}
						</a>
					</div>
				</div>
				<div v-if="click === 'right'" class="right-contact-popup">
					<div>
						<NcActionButton v-if="contact.isUpdateable" icon="icon-delete" @click="onDeleteAddressClick()">
							{{ contact.ADR?t('maps', 'Delete this address'):t('maps', 'Delete this location') }}
						</NcActionButton>
						<NcActionButton v-if="!isPublicVal" icon="icon-share" @click="$emit('add-to-map-contact', contact)">
							{{ t('maps', 'Copy to map') }}
						</NcActionButton>
					</div>
				</div>
			</MglPopup>
		</template>
	</MglMarker>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'

import { MglMarker, MglPopup } from '@indoorequal/vue-maplibre-gl'

import optionsController from '../../optionsController.js'
import { getToken, isPublic } from '../../utils/common.js'

export default {
	name: 'ContactLayer',
	components: {
		MglMarker,
		MglPopup,
		NcActionButton,
	},

	props: {
		contact: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			click: 'left',
			showPopup: false,
			optionValues: optionsController.optionValues,
		}
	},

	computed: {
		isPublicVal() {
			return isPublic()
		},
		contactAvatar() {
			if (this.contact.HAS_PHOTO) {
				return generateUrl(
					'/remote.php/dav/addressbooks/users/' + getCurrentUser().uid
					+ '/' + encodeURIComponent(this.contact.BOOKURI)
					+ '/' + encodeURIComponent(this.contact.URI) + '?photo').replace(/index\.php\//, '')
			} else {
				return generateUrl('/apps/maps' + (isPublic() ? '/s/' + getToken() : '') + '/contacts-avatar?name=' + encodeURIComponent(this.contact.FN))
			}
		},
		formattedAddressLines() {
			const adrTab = this.contact.ADR.split(';').map((s) => { return s.trim() })
			const formattedAddressLines = []
			if (adrTab.length > 6) {
				if (adrTab[2] !== '') {
					formattedAddressLines.push(adrTab[2])
				}
				formattedAddressLines.push(adrTab[5] + ' ' + adrTab[3])
				formattedAddressLines.push(adrTab[4] + ' ' + adrTab[6])
			}
			return formattedAddressLines.filter((s) => { return s.trim() !== '' })
		},
		contactUrl() {
			return this.contact.URL || generateUrl('/apps/contacts/direct/contact/' + encodeURIComponent(this.contact.UID + '~contacts'))
		},
	},

	methods: {
		onMarkerClick() {
			this.click = 'left'
			this.showPopup = true
		},
		onMarkerContextmenu() {
			this.click = 'right'
			this.showPopup = true
		},
		onDeleteAddressClick() {
			const c = this.contact
			if (c.ADR && c.GEO) {
				delete c.GEO
			}
			this.$emit('address-deleted', this.contact)
		},
	},
}
</script>

<style lang="scss" scoped>
.contact-marker-icon {
	width: 40px;
	height: 40px;
	cursor: pointer;
	.thumbnail {
		width: 40px;
		height: 40px;
		border-radius: 50%;
		background-size: cover;
		background-position: center;
		border: 2px solid var(--color-border);
	}
}

.popup-contact-wrapper {
	width: 100%;
	.action {
		width: 100%;
	}
}

.popup-contact-wrapper > div,
.tooltip-contact-wrapper {
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
		&:hover {
			background-color: var(--color-background-hover);
		}
	}
}

.right-contact-popup {
	display: flex;
	flex-direction: column;
	align-items: center;
}
</style>
