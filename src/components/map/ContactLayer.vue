<template>
	<LMarker
		:options="{ data: contact }"
		:icon="contactMarkerIcon"
		:lat-lng="latLng"
		@click="onMarkerClick"
		@contextmenu="onMarkerContextmenu">
		<LTooltip
			class="tooltip-contact-wrapper"
			:options="tooltipOptions">
			<img class="tooltip-contact-avatar"
				:src="contactAvatar"
				alt="">
			<div class="tooltip-contact-content">
				<h3 class="tooltip-contact-name">
					{{ contact.FN }}
				</h3>
				<p class="tooltip-contact-address-type">
					<template v-for="adrtype in contact.ADRTYPE">
						<span v-if=" adrtype.toLowerCase() === 'home'"
							class="tooltip-contact-address-type">
							{{ t('maps', 'Home') }}
						</span>
						<span v-else-if=" adrtype.toLowerCase() === 'work'"
							class="tooltip-contact-address-type">
							{{ t('maps', 'Work') }}
						</span>
						<span v-else
							class="tooltip-contact-address-type">
							{{ t('maps', adrtype.toLowerCase()) }}
						</span>
					</template>
				</p>
				<p v-for="l in formattedAddressLines"
					:key="l"
					class="tooltip-contact-address">
					{{ l }}
				</p>
			</div>
		</LTooltip>
		<LPopup
			class="popup-contact-wrapper"
			:options="popupOptions">
			<div v-if="click === 'left'">
				<div class="left-contact-popup">
					<img class="tooltip-contact-avatar"
						:src="contactAvatar"
						alt="">
					<button v-if="contact.isDeletable"
						v-tooltip="{ content: contact.ADR?t('maps', 'Delete this address'):t('maps', 'Delete this location') }"
						class="icon icon-delete"
						@click="onDeleteAddressClick()" />
					<button v-if="!isPublic()"
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
							<span v-if=" adrtype.toLowerCase() === 'home'"
								class="tooltip-contact-address-type">
								{{ t('maps', 'Home') }}
							</span>
							<span v-else-if=" adrtype.toLowerCase() === 'work'"
								class="tooltip-contact-address-type">
								{{ t('maps', 'Work') }}
							</span>
							<span v-else
								class="tooltip-contact-address-type">
								{{ t('maps', adrtype.toLowerCase()) }}
							</span>
						</template>
					</p>
					<p v-for="l in formattedAddressLines"
						:key="l"
						class="tooltip-contact-address">
						{{ l }}
					</p>
					<a v-if="contact.UID && contact.URI"
						target="_blank"
						:href="contactUrl">
						{{ t('maps', 'Open in Contacts') }}
					</a>
				</div>
			</div>
			<div v-if="click === 'right'" class="right-contact-popup">
				<div>
					<NcActionButton v-if="contact.isUpdateable"
						icon="icon-delete"
						@click="onDeleteAddressClick()">
						{{ contact.ADR?t('maps', 'Delete this address'):t('maps', 'Delete this location') }}
					</NcActionButton>
					<NcActionButton v-if="!isPublic()"
						icon="icon-share"
						@click="$emit('add-to-map-contact', contact)">
						{{ t('maps', 'Copy to map') }}
					</NcActionButton>
				</div>
			</div>
		</LPopup>
	</LMarker>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'

import L from 'leaflet'
import { LMarker, LTooltip, LPopup } from 'vue2-leaflet'

import optionsController from '../../optionsController.js'
import { geoToLatLng } from '../../utils/mapUtils.js'
import { getToken, isPublic } from '../../utils/common.js'

const CONTACT_MARKER_VIEW_SIZE = 40

export default {
	name: 'ContactLayer',
	components: {
		LMarker,
		LTooltip,
		LPopup,
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
			optionValues: optionsController.optionValues,
			tooltipOptions: {
				className: 'leaflet-marker-contact-tooltip',
				direction: 'top',
				offset: L.point(0, 0),
			},
			popupOptions: {
				closeOnClick: false,
				closeButton: false,
				className: 'popovermenu open popupMarker contactPopup',
				offset: L.point(-5, 10),
			},
		}
	},

	computed: {
		latLng() {
			return geoToLatLng(this.contact.GEO)
		},
		contactMarkerIcon() {
			const iconUrl = this.contactAvatar
			return L.divIcon(
				L.extend({
					className: 'leaflet-marker-contact contact-marker',
					html: '<div class="thumbnail" style="background-image: url(\'' + iconUrl + '\');"></div>​',
				},
				this.contact,
				{
					iconSize: [CONTACT_MARKER_VIEW_SIZE, CONTACT_MARKER_VIEW_SIZE],
					iconAnchor: [CONTACT_MARKER_VIEW_SIZE / 2, CONTACT_MARKER_VIEW_SIZE],
				}),
			)
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
				// check if street name is set
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
		isPublic() {
			return isPublic()
		},
		onMarkerClick(e) {
			this.click = 'left'
			this.popupOptions.offset = L.point(-5, 10)
			this.$nextTick(() => {
				e.target.closeTooltip()
			})
		},
		onMarkerContextmenu(e) {
			this.click = 'right'
			this.popupOptions.offset = L.point(-5, -25)
			this.$nextTick(() => {
				e.target.openPopup()
				e.target.closeTooltip()
				this.popupOptions.offset = L.point(-5, 10)
			})
		},
		onDeleteAddressClick() {
			const c = this.contact
			// We only want to delete the ADR not the GEO
			if (c.ADR && c.GEO) {
				delete c.GEO
			}
			this.$emit('address-deleted', this.contact)
		},
	},
}
</script>

<style lang="scss" scoped>
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
