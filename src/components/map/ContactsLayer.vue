<template>
	<Vue2LeafletMarkerCluster :options="clusterOptions"
		@clusterclick="onClusterClick"
		@spiderfied="spiderfied = true">
		<ContactLayer v-for="(c, i) in displayedContacts"
			:key="c.URI + i + c.ADR"
			:contact="c"
			@address-deleted="$emit('address-deleted', $event)"
			@add-to-map-contact="$emit('add-to-map-contact', $event)" />
	</Vue2LeafletMarkerCluster>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'

import L from 'leaflet'
import Vue2LeafletMarkerCluster from 'vue2-leaflet-markercluster'

import ContactLayer from './ContactLayer.vue'
import optionsController from '../../optionsController.js'
import { getToken, isPublic } from '../../utils/common.js'
import { splitByNonEscapedComma } from '../../utils.js'

const CONTACT_MARKER_VIEW_SIZE = 40

export default {
	name: 'ContactsLayer',
	components: {
		Vue2LeafletMarkerCluster,
		ContactLayer,
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
			spiderfied: false,
		}
	},

	computed: {
		displayedContacts() {
			return this.contacts.filter((c) => {
				if (c.GROUPS) {
					try {
						const cGroups = splitByNonEscapedComma(c.GROUPS)
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

	methods: {
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
			}))
		},
		getContactAvatar(contact) {
			if (contact.HAS_PHOTO) {
				return generateUrl(
					'/remote.php/dav/addressbooks/users/' + getCurrentUser().uid
					+ '/' + encodeURIComponent(contact.BOOKURI)
					+ '/' + encodeURIComponent(contact.URI) + '?photo').replace(/index\.php\//, '')
			} else {
				return generateUrl('/apps/maps' + (isPublic() ? '/s/' + getToken() : '') + '/contacts-avatar?name=' + encodeURIComponent(contact.FN))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
// nothing
</style>
