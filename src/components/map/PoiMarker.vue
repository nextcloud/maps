<template>
	<LMarker
		:lat-lng="[poi.lat, poi.lon]">
		<LTooltip>
			{{ tooltipContent }}
		</LTooltip>
		<LPopup>
			<div v-if="poi.icon" class="inline-wrapper">
				<img class="location-icon" :src="poi.icon">
				<h2 class="location-header">
					{{ header }}
				</h2>
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
			<div v-if="myOpeningHours"
				class="opening-hours-header inline-wrapper"
				@click="ohOpen = !ohOpen">
				<img class="popup-icon" :src="recentImagePath">

				<span v-if="ohChangeDt && ohIsCurrentlyOpen"
					class="poi-open">
					{{ t('maps', 'Open') }}
					&nbsp;
				</span>
				<span v-if="ohChangeDt && ohIsCurrentlyOpen && ohDtDiff <= 60"
					class="poi-closes">
					&nbsp;
					{{ t('maps', 'closes in {nb} minutes', { nb: parseInt(ohDtDiff) }) }}
				</span>
				<span v-if="ohChangeDt && ohIsCurrentlyOpen && ohDtDiff > 60">
					&nbsp;
					{{ t('maps', 'until {date}', { date: formattedOhChangeDt }) }}
				</span>

				<span v-if="ohChangeDt && !ohIsCurrentlyOpen"
					class="poi-closed">
					{{ t('maps', 'Closed') }}
					&nbsp;
				</span>
				<span v-if="ohChangeDt && !ohIsCurrentlyOpen"
					class="poi-opens">
					{{ t('maps', 'opens at {date}', { date: formattedOhChangeDt }) }}
				</span>

				<img v-if="ohOpen"
					id="opening-hours-table-toggle-collapse"
					:src="triangleSimagePath">
				<img v-if="!ohOpen"
					id="opening-hours-table-toggle-expand"
					:src="triangleEPath">
			</div>
			<table v-if="myOpeningHours && ohOpen"
				class="opening-hours-table">
				<tr v-for="(int, i) in ohIntervals"
					:key="i"
					:class="{ selected: int.selected }">
					<td class="opening-hours-day">
						{{ int.day }}
					</td>
					<td class="opening-hours-hours">
						{{ int.startTime }} - {{ int.endTime }}
					</td>
				</tr>
			</table>
			<div v-if="poi.extratags && poi.extratags.website" class="inline-wrapper extra-wrapper">
				<img class="popup-icon" :src="linkImagePath">
				<a :href="poi.extratags.website" target="_blank">
					{{ poi.extratags.website.replace(/^(?:\w+:|)\/\/(?:www\.|)(.*[^\/])\/*$/, '$1') }}
				</a>
			</div>
			<div v-if="poi.extratags && poi.extratags.phone" class="inline-wrapper extra-wrapper">
				<img class="popup-icon" :src="linkImagePath">
				<a :href="'tel:' + poi.extratags.phone" target="_blank">
					{{ poi.extratags.phone }}
				</a>
			</div>
			<div v-if="poi.extratags && poi.extratags.email" class="inline-wrapper extra-wrapper">
				<img class="popup-icon" :src="mailImagePath">
				<a :href="'mailto:' + poi.extratags.email" target="_blank">
					{{ poi.extratags.email }}
				</a>
			</div>
		</LPopup>
	</LMarker>
</template>

<script>
import { imagePath } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import { formatAddress } from '../../utils.js'

import { LMarker, LPopup, LTooltip } from 'vue2-leaflet'
import OpeningHours from 'opening_hours'

export default {
	name: 'PoiMarker',

	components: {
		LMarker,
		LPopup,
		LTooltip,
	},

	props: {
		poi: {
			type: Object,
			required: true,
		},
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

	methods: {
		onAddFavorite() {
			this.$emit('add-favorite',
				{
					...this.poi,
					latLng: {
						lat: this.poi.lat,
						lng: this.poi.lon,
					},
					name: this.header,
					formattedAddress: formatAddress(this.poi.address),
				})
		},
		onAddContact() {
			this.$emit('place-contact',
				{
					...this.poi,
					latLng: {
						lat: this.poi.lat,
						lng: this.poi.lon,
					},
					name: this.header,
					formattedAddress: formatAddress(this.poi.address),
				})
		},
	},

}
</script>

<style lang="scss" scoped>
.opening-hours-header {
	height: 30px;
	img {
		height: 100%;
		padding: 5px 0 5px 0;
	}
	* {
		cursor: pointer;
	}
	span {
		line-height: 30px;
	}
}

.opening-hours-table {
	width: 100%;
	td {
		padding: 0 5px 0 5px;
	}
}

.extra-wrapper {
	a {
		line-height: 20px;
	}
}
</style>
