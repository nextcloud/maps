<template>
	<MglMarker :coordinates="[poi.lon, poi.lat]">
		<template #default>
			<div class="poi-marker-icon" />
			<MglPopup :close-button="true" anchor="bottom">
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

					<span v-if="ohChangeDt && ohIsCurrentlyOpen" class="poi-open">
						{{ t('maps', 'Open') }}
						&nbsp;
					</span>
					<span v-if="ohChangeDt && ohIsCurrentlyOpen && ohDtDiff <= 60" class="poi-closes">
						&nbsp;
						{{ t('maps', 'closes in {nb} minutes', { nb: parseInt(ohDtDiff) }) }}
					</span>
					<span v-if="ohChangeDt && ohIsCurrentlyOpen && ohDtDiff > 60">
						&nbsp;
						{{ t('maps', 'until {date}', { date: formattedOhChangeDt }) }}
					</span>

					<span v-if="ohChangeDt && !ohIsCurrentlyOpen" class="poi-closed">
						{{ t('maps', 'Closed') }}
						&nbsp;
					</span>
					<span v-if="ohChangeDt && !ohIsCurrentlyOpen" class="poi-opens">
						{{ t('maps', 'opens at {date}', { date: formattedOhChangeDt }) }}
					</span>

					<img v-if="ohOpen" id="opening-hours-table-toggle-collapse" :src="triangleSimagePath">
					<img v-if="!ohOpen" id="opening-hours-table-toggle-expand" :src="triangleEPath">
				</div>
				<table v-if="myOpeningHours && ohOpen" class="opening-hours-table">
					<tr v-for="(int, i) in ohIntervals" :key="i" :class="{ selected: int.selected }">
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
			</MglPopup>
		</template>
	</MglMarker>
</template>

<script setup>
import { ref, computed } from 'vue'
import { t } from '@nextcloud/l10n'
import { imagePath } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import { MglMarker, MglPopup } from '@indoorequal/vue-maplibre-gl'
import OpeningHours from 'opening_hours'
import { formatAddress } from '../../utils.js'

const props = defineProps({
	poi: {
		type: Object,
		required: true,
	},
})

const emit = defineEmits(['add-favorite', 'place-contact'])

const recentImagePath = imagePath('maps', 'recent.svg')
const triangleSimagePath = imagePath('maps', 'triangle-s.svg')
const triangleEPath = imagePath('maps', 'triangle-e.svg')
const mailImagePath = imagePath('maps', 'mail.svg')
const linkImagePath = imagePath('maps', 'link.svg')
const ohOpen = ref(false)

const myOpeningHours = computed(() =>
	props.poi.extratags?.opening_hours
		? new OpeningHours(props.poi.extratags.opening_hours, props.poi)
		: null,
)

const ohChangeDt = computed(() => myOpeningHours.value?.getNextChange())
const formattedOhChangeDt = computed(() => moment(ohChangeDt.value).format('HH:mm'))
const ohDtDiff = computed(() => (ohChangeDt.value.getTime() - new Date()) / 60000)
const ohIsCurrentlyOpen = computed(() => myOpeningHours.value?.getState())

const ohIntervals = computed(() => {
	const todayStart = new Date()
	todayStart.setHours(0, 0, 0)
	const sevDaysEnd = new Date(todayStart.getTime() + 7 * 24 * 60 * 60 * 1000)
	const intervals = myOpeningHours.value.getOpenIntervals(todayStart, sevDaysEnd)
	if (intervals.length === 8) {
		intervals[7][1] = intervals[0][1]
		intervals.splice(0, 1)
	}
	const result = intervals.map((interval) => ({
		startTime: moment(interval[0]).format('HH:mm'),
		endTime: moment(interval[1]).format('HH:mm'),
		day: moment(interval[1]).format('dddd'),
		selected: false,
	}))
	result[0].selected = true
	return result
})

const city = computed(() => {
	const { address } = props.poi
	return address.city || address.town || address.village || ''
})

const road = computed(() => {
	let r = props.poi.address?.road || ''
	if (r && props.poi.address?.house_number) {
		r += ' ' + props.poi.address.house_number
	}
	return r
})

const header = computed(() => props.poi.namedetails?.name || road.value || city.value)

const desc = computed(() => {
	const poi = props.poi
	let d = ''
	let needSeparator = false
	if (poi.namedetails?.name && road.value) {
		d = road.value
		needSeparator = true
	}
	if (poi.address.postcode) {
		if (needSeparator) { d += ', '; needSeparator = false }
		d += poi.address.postcode
	}
	if (city.value) {
		if (needSeparator) { d += ', '; needSeparator = false }
		else if (d.length > 0) d += ' '
		d += city.value
	}
	if (poi.address?.state && poi.address?.country_code === 'us') {
		if (d.length > 0) d += ' '
		d += '(' + poi.address.state + ')'
	}
	return d
})

function onAddFavorite() {
	emit('add-favorite', {
		...props.poi,
		latLng: { lat: props.poi.lat, lng: props.poi.lon },
		name: header.value,
		formattedAddress: formatAddress(props.poi.address),
	})
}

function onAddContact() {
	emit('place-contact', {
		...props.poi,
		latLng: { lat: props.poi.lat, lng: props.poi.lon },
		name: header.value,
		formattedAddress: formatAddress(props.poi.address),
	})
}
</script>

<style lang="scss" scoped>
.poi-marker-icon {
	width: 12px;
	height: 12px;
	border-radius: 50%;
	background-color: #e74c3c;
	border: 2px solid #c0392b;
	cursor: pointer;
}

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
