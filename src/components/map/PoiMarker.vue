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
		map: { type: Object, required: true },
	},
	data() {
		return { 
			recentImagePath: imagePath('maps', 'recent.svg'),
			triangleSimagePath: imagePath('maps', 'triangle-s.svg'),
			triangleEPath: imagePath('maps', 'triangle-e.svg'),
			mailImagePath: imagePath('maps', 'mail.svg'),
			linkImagePath: imagePath('maps', 'link.svg'),
			ohOpen: false,
		}
	},
	computed: { 
		tooltipContent() {
			return this.poi.namedetails?.name || this.poi.display_name
		},
		// OPENING HOURS
		myOpeningHours() {
			return this.poi.extratags?.opening_hours
				? new OpeningHours(this.poi.extratags.opening_hours, this.poi)
				: null
		},
		ohChangeDt() {
			return this.myOpeningHours?.getNextChange()
		},
		formattedOhChangeDt() {
			return moment(this.ohChangeDt).format('HH:mm')
		},
		ohDtDiff() {
			const currentDt = new Date()
			return (this.ohChangeDt.getTime() - currentDt) / 60000
		},
		ohIsCurrentlyOpen() {
			return this.myOpeningHours.getState()
		},
		ohIntervals() {
			const todayStart = new Date()
			todayStart.setHours(0)
			todayStart.setMinutes(0)
			todayStart.setSeconds(0)
			const sevDaysEnd = new Date(todayStart)
			const sevDaysMs = 7 * 24 * 60 * 60 * 1000
			sevDaysEnd.setTime(sevDaysEnd.getTime() + sevDaysMs)
			const intervals = this.myOpeningHours.getOpenIntervals(todayStart, sevDaysEnd)
			// intervals should be 7, if 8, then first entry is interval after 00:00:00 from last day
			if (intervals.length === 8) {
				// set end time of last element to end time of first element and remove it
				intervals[7][1] = intervals[0][1]
				intervals.splice(0, 1)
			}
			const resultIntervals = intervals.map((interval) => {
				return {
					startTime: moment(interval[0]).format('HH:mm'),
					endTime: moment(interval[1]).format('HH:mm'),
					day: moment(interval[1]).format('dddd'),
					selected: false,
				}
			})
			resultIntervals[0].selected = true
			return resultIntervals
		},
		// ADDRESS
		city() {
			const poi = this.poi
			return (poi.address.city || poi.address.town || poi.address.village)
				? poi.address.city
					? poi.address.city
					: poi.address.town
						? poi.address.town
						: poi.address.village
							? poi.address.village
							: ''
				: ''
		},
		road() {
			let road = this.poi.address?.road || ''
			if (road && this.poi.address?.house_number) {
				road += ' ' + this.poi.address.house_number
			}
			return road
		},
		header() {
			return this.poi.namedetails?.name || this.road || this.city
		},
		desc() {
			const poi = this.poi
			let desc = ''
			let needSeparator = false
			// add road to desc if it is not heading and exists (isn't heading, if 'name' is set)
			if (poi.namedetails?.name && this.road) {
				desc = this.road
				needSeparator = true
			}
			if (poi.address.postcode) {
				if (needSeparator) {
					desc += ', '
					needSeparator = false
				}
				desc += poi.address.postcode
			}
			if (this.city) {
				if (needSeparator) {
					desc += ', '
					needSeparator = false
				} else if (desc.length > 0) {
					desc += ' '
				}
				desc += this.city
			}
			// assume that state is only important for us addresses
			if (poi.address?.state && poi.address?.country_code === 'us') {
				if (desc.length > 0) {
					desc += ' '
				}
				desc += '(' + poi.address.state + ')'
			}
			return desc
		},
	 },
	created() {
		this.marker = null; // Non-reactive
	},
	mounted() {
		this.marker = L.marker([this.poi.lat, this.poi.lon]).addTo(this.map);
		
		this.marker.bindTooltip(this.tooltipContent);
		
		this.marker.bindPopup(this.$el);
	},
	beforeUnmount() {
		if (this.marker && this.map) {
			this.map.removeLayer(this.marker);
		}
	},
	methods: { 

	 }
}
</script>