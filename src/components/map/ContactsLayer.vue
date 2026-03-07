<template>
	<div style="display: none;"></div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import L from 'leaflet'
import 'leaflet.markercluster'

import { getToken, isPublic } from '../../utils/common.js'
import { splitByNonEscapedComma } from '../../utils.js'
import { geoToLatLng } from '../../utils/mapUtils.js'

const CONTACT_MARKER_VIEW_SIZE = 40

export default {
	name: 'ContactsLayer',

	props: {
		map: { type: Object, required: true },
		contacts: { type: Array, required: true },
		groups: { type: Object, required: true },
	},

	data() {
		return {
			spiderfied: false,
		}
	},

	created() {
		this.clusterGroup = null;
		this.contactMarkers = [];
	},

	watch: {
		contacts: 'renderMarkers',
		groups: { handler: 'renderMarkers', deep: true },
	},

	mounted() {
		this.clusterGroup = L.markerClusterGroup({
			iconCreateFunction: this.getClusterMarkerIcon.bind(this),
			spiderfyOnMaxZoom: false,
			showCoverageOnHover: false,
			zoomToBoundsOnClick: false,
			maxClusterRadius: CONTACT_MARKER_VIEW_SIZE + 10,
		});

		this.clusterGroup.on('clusterclick', this.onClusterClick.bind(this));
		this.clusterGroup.on('spiderfied', () => this.spiderfied = true);

		this.map.addLayer(this.clusterGroup);
		this.renderMarkers();
	},

	beforeUnmount() {
		if (this.clusterGroup && this.map) {
			this.map.removeLayer(this.clusterGroup);
		}
	},

	methods: {
		isPublic() { return isPublic() },

		getDisplayedContacts() {
			return this.contacts.filter((c) => {
				if (c.GROUPS) {
					try {
						const cGroups = splitByNonEscapedComma(c.GROUPS);
						for (let i = 0; i < cGroups.length; i++) {
							if (this.groups[cGroups[i]] && this.groups[cGroups[i]].enabled) return true;
						}
					} catch (error) {
						console.error(error);
					}
				} else if (this.groups['0'] && this.groups['0'].enabled) {
					return true; // Not grouped
				}
				return false;
			});
		},

		renderMarkers() {
			this.clusterGroup.clearLayers();
			const activeContacts = this.getDisplayedContacts();

			this.contactMarkers = activeContacts.map((contact) => {
				const latLng = geoToLatLng(contact.GEO);
				const iconUrl = this.getContactAvatar(contact);
				
				// Fix: Renamed variable 't' to 'adrType' to avoid shadowing window.t()
				const adrTypesHtml = (contact.ADRTYPE || []).map(adrType => 
					`<span class="tooltip-contact-address-type">${adrType.toLowerCase() === 'home' ? window.t('maps', 'Home') : adrType.toLowerCase() === 'work' ? window.t('maps', 'Work') : window.t('maps', adrType.toLowerCase())}</span>`
				).join(' ');
				
				const adrTab = (contact.ADR || '').split(';').map(s => s.trim());
				const formattedLines = [];
				if (adrTab.length > 6) {
					if (adrTab[2]) formattedLines.push(adrTab[2]);
					formattedLines.push(adrTab[5] + ' ' + adrTab[3]);
					formattedLines.push(adrTab[4] + ' ' + adrTab[6]);
				}
				const addressHtml = formattedLines.filter(s => s).map(l => `<p class="tooltip-contact-address">${l}</p>`).join('');

				const marker = L.marker(latLng, {
					icon: L.divIcon({
						className: 'leaflet-marker-contact contact-marker',
						html: `<div class="thumbnail" style="background-image: url('${iconUrl}');"></div>​`,
						iconSize: [CONTACT_MARKER_VIEW_SIZE, CONTACT_MARKER_VIEW_SIZE],
						iconAnchor: [CONTACT_MARKER_VIEW_SIZE / 2, CONTACT_MARKER_VIEW_SIZE],
					})
				});

				marker.data = contact;

				marker.bindTooltip(`
					<div class="tooltip-contact-wrapper">
						<img class="tooltip-contact-avatar" src="${iconUrl}" />
						<div class="tooltip-contact-content">
							<h3 class="tooltip-contact-name">${contact.FN || ''}</h3>
							<p class="tooltip-contact-address-type">${adrTypesHtml}</p>
							${addressHtml}
						</div>
					</div>
				`, { className: 'leaflet-marker-contact-tooltip', direction: 'top', offset: L.point(0, 0) });

				marker.on('click', (e) => this.onMarkerClick(e, contact, iconUrl, adrTypesHtml, addressHtml));
				marker.on('contextmenu', (e) => this.onMarkerRightClick(e, contact));

				return marker;
			});

			this.clusterGroup.addLayers(this.contactMarkers);
		},

		onMarkerClick(e, contact, iconUrl, adrTypesHtml, addressHtml) {
			const contactUrl = contact.URL || generateUrl('/apps/contacts/direct/contact/' + encodeURIComponent(contact.UID + '~contacts'));
			
			const popupContent = L.DomUtil.create('div', 'popup-contact-wrapper');
			popupContent.innerHTML = `
				<div class="left-contact-popup">
					<img class="tooltip-contact-avatar" src="${iconUrl}" />
					${contact.isDeletable ? `<button class="icon icon-delete" data-action="delete" title="${contact.ADR ? window.t('maps', 'Delete this address') : window.t('maps', 'Delete this location')}"></button>` : ''}
					${!this.isPublic() ? `<button class="icon icon-share" data-action="copy" title="${window.t('maps', 'Copy to map')}"></button>` : ''}
				</div>
				<div class="tooltip-contact-content">
					<h3 class="tooltip-contact-name">${contact.FN}</h3>
					<p class="tooltip-contact-address-type">${adrTypesHtml}</p>
					${addressHtml}
					${contact.UID && contact.URI ? `<a target="_blank" href="${contactUrl}">${window.t('maps', 'Open in Contacts')}</a>` : ''}
				</div>
			`;

			popupContent.addEventListener('click', (event) => {
				if (event.target.getAttribute('data-action') === 'delete') this.onDeleteAddressClick(contact);
				if (event.target.getAttribute('data-action') === 'copy') this.$emit('add-to-map-contact', contact);
			});

			e.target.closeTooltip();
			L.popup({ closeButton: false, className: 'popovermenu open popupMarker contactPopup', offset: L.point(-5, 10) })
				.setLatLng(e.target.getLatLng())
				.setContent(popupContent)
				.openOn(this.map);
		},

		onMarkerRightClick(e, contact) {
			const popupContent = L.DomUtil.create('div', 'right-contact-popup popup-contact-wrapper');
			popupContent.innerHTML = `
				${contact.isUpdateable ? `<button class="action-btn" data-action="delete">${contact.ADR ? window.t('maps', 'Delete this address') : window.t('maps', 'Delete this location')}</button>` : ''}
				${!this.isPublic() ? `<button class="action-btn" data-action="copy">${window.t('maps', 'Copy to map')}</button>` : ''}
			`;

			popupContent.addEventListener('click', (event) => {
				if (event.target.getAttribute('data-action') === 'delete') this.onDeleteAddressClick(contact);
				if (event.target.getAttribute('data-action') === 'copy') this.$emit('add-to-map-contact', contact);
			});

			e.target.closeTooltip();
			L.popup({ closeButton: false, className: 'popovermenu open popupMarker contactPopup', offset: L.point(-5, -25) })
				.setLatLng(e.target.getLatLng())
				.setContent(popupContent)
				.openOn(this.map);
		},

		onDeleteAddressClick(contact) {
			if (contact.ADR && contact.GEO) delete contact.GEO;
			this.$emit('address-deleted', contact);
			this.map.closePopup();
		},

		onClusterClick(a) {
			if (a.layer.getChildCount() > 10 && this.map.getZoom() !== this.map.getMaxZoom()) {
				a.layer.zoomToBounds();
			} else {
				a.layer.spiderfy();
			}
		},

		getClusterMarkerIcon(cluster) {
			const contact = cluster.getAllChildMarkers()[0].data;
			const iconUrl = this.getContactAvatar(contact);
			const label = cluster.getChildCount();
			return new L.DivIcon({
				className: 'leaflet-marker-contact cluster-marker',
				html: `<div class="thumbnail" style="background-image: url('${iconUrl}');"></div>​<span class="label">${label}</span>`,
				iconSize: [CONTACT_MARKER_VIEW_SIZE, CONTACT_MARKER_VIEW_SIZE],
			});
		},

		getContactAvatar(contact) {
			if (contact.HAS_PHOTO) {
				return generateUrl('/remote.php/dav/addressbooks/users/' + getCurrentUser().uid + '/' + encodeURIComponent(contact.BOOKURI) + '/' + encodeURIComponent(contact.URI) + '?photo').replace(/index\.php\//, '');
			} else {
				return generateUrl('/apps/maps' + (this.isPublic() ? '/s/' + getToken() : '') + '/contacts-avatar?name=' + encodeURIComponent(contact.FN));
			}
		},
	},
}
</script>

<style lang="scss">
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
		&:hover { background-color: var(--color-background-hover); }
	}
}
.right-contact-popup {
	display: flex;
	flex-direction: column;
	align-items: center;
}
.right-contact-popup .action-btn {
	display: block;
	width: 100%;
	padding: 8px;
	border: none;
	background: transparent;
	text-align: left;
	cursor: pointer;
}
.right-contact-popup .action-btn:hover {
	background: var(--color-background-hover);
}
</style>