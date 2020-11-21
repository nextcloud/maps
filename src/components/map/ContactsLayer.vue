<template>
	<Vue2LeafletMarkerCluster :options="clusterOptions"
		@clusterclick="onClusterClick">
		<LMarker v-for="(c, i) in displayedContacts"
			:key="c.URI + i + c.ADR"
			:options="{ data: c }"
			:icon="getContactMarkerIcon(c)"
			:lat-lng="geoToLatLng(c.GEO)">
			<LTooltip
				class="tooltip-contact-wrapper"
				:options="tooltipOptions">
				<img class="tooltip-contact-avatar"
					:src="getContactAvatar(c)"
					alt="">
				<div class="tooltip-contact-content">
					<h3 class="tooltip-contact-name">
						{{ c.FN }}
					</h3>
					<p v-if=" c.ADRTYPE.toLowerCase() === 'home'"
						class="tooltip-contact-address-type">
						{{ t('maps', 'Home') }}
					</p>
					<p v-else-if=" c.ADRTYPE.toLowerCase() === 'work'"
						class="tooltip-contact-address-type">
						{{ t('maps', 'Work') }}
					</p>
					<p v-for="l in getFormattedAddressLines(c)"
						:key="l"
						class="tooltip-contact-address">
						{{ l }}
					</p>
				</div>
			</LTooltip>
			<LPopup
				class="popup-contact-wrapper"
				:options="popupOptions">
				<div class="left-contact-popup">
					<img class="tooltip-contact-avatar"
						:src="getContactAvatar(c)"
						alt="">
					<button
						v-tooltip="{ content: t('maps', 'Delete this address') }"
						class="icon icon-delete"
						@click="onDeleteAddressClick(c)" />
				</div>
				<div class="tooltip-contact-content">
					<h3 class="tooltip-contact-name">
						{{ c.FN }}
					</h3>
					<p v-if=" c.ADRTYPE.toLowerCase() === 'home'"
						class="tooltip-contact-address-type">
						{{ t('maps', 'Home') }}
					</p>
					<p v-else-if=" c.ADRTYPE.toLowerCase() === 'work'"
						class="tooltip-contact-address-type">
						{{ t('maps', 'Work') }}
					</p>
					<p v-for="l in getFormattedAddressLines(c)"
						:key="l"
						class="tooltip-contact-address">
						{{ l }}
					</p>
					<a target="_blank"
						:href="getContactUrl(c)">
						{{ t('maps', 'Open in Contacts') }}
					</a>
				</div>
			</LPopup>
		</LMarker>
	</Vue2LeafletMarkerCluster>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'

import L from 'leaflet'
import { LMarker, LTooltip, LPopup } from 'vue2-leaflet'
import Vue2LeafletMarkerCluster from 'vue2-leaflet-markercluster'

import optionsController from '../../optionsController'
import { geoToLatLng } from '../../utils/mapUtils'

import { deleteContactAddress } from '../../network'

const CONTACT_MARKER_VIEW_SIZE = 40

export default {
	name: 'ContactsLayer',
	components: {
		Vue2LeafletMarkerCluster,
		LMarker,
		LTooltip,
		LPopup,
	},

	props: {
		contacts: {
			type: Array,
			required: true,
		},
		groups: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			optionValues: optionsController.optionValues,
			clusterOptions: {
				iconCreateFunction: this.getClusterMarkerIcon,
				spiderfyOnMaxZoom: false,
				showCoverageOnHover: false,
				zoomToBoundsOnClick: false,
				maxClusterRadius: CONTACT_MARKER_VIEW_SIZE + 10,
				icon: {
					iconSize: [CONTACT_MARKER_VIEW_SIZE, CONTACT_MARKER_VIEW_SIZE],
				},
			},
			tooltipOptions: {
				className: 'leaflet-marker-contact-tooltip',
				direction: 'top',
				offset: L.point(0, 0),
			},
			popupOptions: {
				closeOnClick: true,
				className: 'popovermenu open popupMarker contactPopup',
				offset: L.point(-5, 10),
			},
		}
	},

	computed: {
		displayedContacts() {
			return this.contacts.filter((c) => {
				if (c.GROUPS) {
					try {
						const cGroups = c.GROUPS.split(/[^\\],/).map((name) => {
							return name.replace('\\,', ',')
						})
						for (let i = 0; i < cGroups.length; i++) {
							// if at least in one enabled group
							if (this.groups[cGroups[i]].enabled) {
								return true
							}
						}
					} catch (error) {
						console.error(error)
					}
				} else if (this.groups['0'].enabled) {
					// or not grouped and this is enabled
					return true
				}
				return false
			})
		},
	},

	beforeMount() {
	},

	methods: {
		geoToLatLng(geo) {
			return geoToLatLng(geo)
		},
		onClusterClick(a) {
			if (a.layer.getChildCount() > 10 && a.layer._map.getZoom() !== a.layer._map.getMaxZoom()) {
				a.layer.zoomToBounds()
			} else {
				a.layer.spiderfy()
			}
		},
		getClusterMarkerIcon(cluster) {
			const contact = cluster.getAllChildMarkers()[0].options.data
			const iconUrl = this.getContactAvatar(contact)
			const label = cluster.getChildCount()
			return new L.DivIcon(L.extend({
				className: 'leaflet-marker-contact cluster-marker',
				html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');"></div>​<span class="label">' + label + '</span>',
			}, this.icon))
		},
		getContactMarkerIcon(contact) {
			const iconUrl = this.getContactAvatar(contact)
			return L.divIcon(
				L.extend({
					className: 'leaflet-marker-contact contact-marker',
					html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');"></div>​',
				},
				contact,
				{
					iconSize: [CONTACT_MARKER_VIEW_SIZE, CONTACT_MARKER_VIEW_SIZE],
					iconAnchor: [CONTACT_MARKER_VIEW_SIZE / 2, CONTACT_MARKER_VIEW_SIZE],
				})
			)
		},
		getContactAvatar(contact) {
			if (contact.HAS_PHOTO) {
				return generateUrl(
					'/remote.php/dav/addressbooks/users/' + getCurrentUser().uid
					+ '/' + encodeURIComponent(contact.BOOKURI)
					+ '/' + encodeURIComponent(contact.URI) + '?photo').replace(/index\.php\//, '')
			} else {
				return generateUrl('/apps/maps/contacts-avatar?name=' + encodeURIComponent(contact.FN))
			}
		},
		getFormattedAddressLines(contact) {
			const adrTab = contact.ADR.split(';')
			const formattedAddressLines = []
			if (adrTab.length > 6) {
				// check if street name is set
				if (adrTab[2] !== '') {
					formattedAddressLines.push(adrTab[2])
				}
				formattedAddressLines.push(adrTab[5] + ' ' + adrTab[3])
				formattedAddressLines.push(adrTab[4] + ' ' + adrTab[6])
			}
			return formattedAddressLines
		},
		getContactUrl(contact) {
			return generateUrl('/apps/contacts/' + t('contacts', 'All contacts') + '/' + encodeURIComponent(contact.UID + '~contacts'))
		},
		onDeleteAddressClick(contact) {
			deleteContactAddress(contact.BOOKID, contact.URI, contact.UID, contact.ADR).then((response) => {
				this.$emit('address-deleted')
			}).catch((error) => {
				console.error(error)
			})
		},
	},
}
</script>

<style lang="scss" scoped>
@import '~leaflet.markercluster/dist/MarkerCluster.css';
@import '~leaflet.markercluster/dist/MarkerCluster.Default.css';

.popup-contact-wrapper,
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
</style>
