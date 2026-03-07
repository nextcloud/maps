<template>
	<div class="poi-popup-content">
		<div v-if="poi.icon" class="inline-wrapper">
			<img class="location-icon" :src="poi.icon">
			<h2 class="location-header">{{ header }}</h2>
		</div>
		<span class="location-city">{{ desc }}</span>
		
		<button class="search-add-favorite" @click="onAddFavorite">
			<span class="icon-favorite" />
			{{ t('maps', 'Add to favorites') }}
		</button>
		<button class="search-place-contact" @click="onAddContact">
			<span class="icon-user" />
			{{ t('maps', 'Add contact address') }}
		</button>
		
		</div>
</template>

<script>
import L from 'leaflet'
import { imagePath } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import OpeningHours from 'opening_hours'
import { formatAddress } from '../../utils.js'

export default {
	name: 'PoiMarker',
	props: {
		poi: { type: Object, required: true },
		map: { type: Object, required: true }, // Pass map instance
	},
	data() {
		return { /* keep existing data */ }
	},
	computed: { /* keep existing computeds */ },
	created() {
		this.marker = null; // Non-reactive
	},
	mounted() {
		// 1. Create the marker
		this.marker = L.marker([this.poi.lat, this.poi.lon]).addTo(this.map);
		
		// 2. Bind the tooltip
		this.marker.bindTooltip(this.tooltipContent);
		
		// 3. Bind THIS Vue component's HTML element as the popup content!
		this.marker.bindPopup(this.$el);
	},
	beforeUnmount() {
		if (this.marker && this.map) {
			this.map.removeLayer(this.marker);
		}
	},
	methods: { /* keep existing methods */ }
}
</script>