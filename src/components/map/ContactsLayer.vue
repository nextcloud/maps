<template>
	<Vue2LeafletMarkerCluster :options="clusterOptions">
		<LMarker v-for="c in displayedContacts"
			:key="c.URI"
			:options="{ data: c }"
			:icon="getContactMarkerIcon(c)"
			:lat-lng="geoToLatLng(c.GEO)" />
	</Vue2LeafletMarkerCluster>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'

import L from 'leaflet'
import { LMarker } from 'vue2-leaflet'
import Vue2LeafletMarkerCluster from 'vue2-leaflet-markercluster'

import optionsController from '../../optionsController'

const CONTACT_MARKER_VIEW_SIZE = 40

export default {
	name: 'ContactsLayer',
	components: {
		Vue2LeafletMarkerCluster,
		LMarker,
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
			let ll
			const fourFirsts = geo.substr(0, 4)
			if (fourFirsts === 'geo:') {
				ll = geo.substr(4).split(',')
			} else {
				ll = geo.split(';')
			}
			return ll
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
				return generateUrl('/apps/maps/contacts-avatar?name=' + encodeURIComponent(name))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@import '~leaflet.markercluster/dist/MarkerCluster.css';
@import '~leaflet.markercluster/dist/MarkerCluster.Default.css';
</style>
