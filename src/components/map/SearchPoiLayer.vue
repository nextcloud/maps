<template>
	<LFeatureGroup>
		<LMarker v-for="poi in pois"
			:key="poi.place_id"
			:lat-lng="[poi.lat, poi.lon]">
			<LTooltip>
				{{ getTooltipContent(poi) }}
			</LTooltip>
			<LPopup>
				<div v-if="poi.icon" class="inline-wrapper">
					<img class="location-icon" :src="poi.icon">
					<h2 class="location-header">
						{{ getHeader(poi) }}
					</h2>
				</div>
				<span class="location-city">{{ getDesc(poi) }}</span>
				<button class="search-add-favorite" @click="$emit('add-favorite', poi)">
					<span class="icon-favorite" />
					{{ t('maps', 'Add to favorites') }}
				</button>
				<button class="search-place-contact" @click="$emit('place-contact', poi)">
					<span class="icon-user" />
					{{ t('maps', 'Add contact address') }}
				</button>
			</LPopup>
		</LMarker>
	</LFeatureGroup>
</template>

<script>
import { LFeatureGroup, LMarker, LPopup, LTooltip } from 'vue2-leaflet'

export default {
	name: 'SearchPoiLayer',

	components: {
		LFeatureGroup,
		LMarker,
		LPopup,
		LTooltip,
	},

	props: {
		pois: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
	},

	methods: {
		getTooltipContent(poi) {
			return poi.namedetails?.name || poi.display_name
		},
		getCity(poi) {
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
		getRoad(poi) {
			let road = poi.address?.road || ''
			if (road && poi.address?.house_number) {
				road += ' ' + poi.address.house_number
			}
			return road
		},
		getHeader(poi) {
			return poi.namedetails?.name || this.getRoad(poi) || this.getCity(poi)
		},
		getDesc(poi) {
			const road = this.getRoad(poi)
			const city = this.getCity(poi)

			let desc = ''
			let needSeparator = false
			// add road to desc if it is not heading and exists (isn't heading, if 'name' is set)
			if (poi.namedetails?.name && road) {
				desc = road
				needSeparator = true
			}
			if (poi.address.postcode) {
				if (needSeparator) {
					desc += ', '
					needSeparator = false
				}
				desc += poi.address.postcode
			}
			if (city) {
				if (needSeparator) {
					desc += ', '
					needSeparator = false
				} else if (desc.length > 0) {
					desc += ' '
				}
				desc += city
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

}
</script>

<style lang="scss" scoped>
// nothing
</style>
