<template>
	<Vue2LeafletMarkerCluster>
		<LMarker v-for="c in displayedContacts"
			:key="c.URI"
			:lat-lng="geoToLatLng(c.GEO)" />
	</Vue2LeafletMarkerCluster>
</template>

<script>
// import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
// import AppNavigationSettings from '@nextcloud/vue/dist/Components/AppNavigationSettings'
// import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'

import { LMarker } from 'vue2-leaflet'
import Vue2LeafletMarkerCluster from 'vue2-leaflet-markercluster'

import optionsController from '../../optionsController'

export default {
	name: 'ContactsLayer',
	components: {
		// AppNavigation,
		// AppNavigationSettings,
		// ActionCheckbox,
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
	},
}
</script>

<style lang="scss" scoped>
@import '~leaflet.markercluster/dist/MarkerCluster.css';
@import '~leaflet.markercluster/dist/MarkerCluster.Default.css';
</style>
