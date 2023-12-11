<template>
	<LMarker :lat-lng="latLng"
		:icon="icon"
		@ready="onMarkerReady">
		<LPopup :options="popupOptions"
			@ready="onPopupReady">
			<h3 id="click-search-popup-title">
				{{ t('maps', 'This place') }}
			</h3>
			<span v-if="addressLoading"
				class="icon icon-loading-small" />
			<textarea v-else
				id="clickSearchAddress"
				v-model="formattedAddress" />
			<button v-if="favoriteIsCreatable" class="search-add-favorite" @click="$emit('add-favorite', { latLng, address, formattedAddress })">
				<span class="icon-favorite" />
				{{ t('maps', 'Add to favorites') }}
			</button>
			<button v-if="contactIsCreatable" class="search-place-contact" @click="$emit('place-contact', { latLng, address })">
				<span class="icon-user" />
				{{ t('maps', 'Add contact address') }}
			</button>
			<button v-for="action in mapActions"
				:key="action.label"
				:icon="action.icon"
				@click="actionCallback(action)">
				<span :class="{ [action.icon]: true }" />
				<span>{{ action.label }}</span>
			</button>
		</LPopup>
	</LMarker>
</template>

<script>
import L from 'leaflet'
import { LMarker, LPopup } from 'vue2-leaflet'

import { formatAddress } from '../../utils.js'
import { geocode } from '../../network.js'

export default {
	name: 'ClickSearchPopup',
	components: {
		LMarker,
		LPopup,
	},

	props: {
		latLng: {
			type: Object,
			required: true,
		},
		favoriteIsCreatable: {
			type: Boolean,
			required: false,
			default: true,
		},
		contactIsCreatable: {
			type: Boolean,
			required: false,
			default: true,
		},
	},

	data() {
		return {
			popupOptions: {
				closeButton: false,
				offset: L.point(-1, 42),
			},
			addressLoading: false,
			address: null,
			formattedAddress: '',
			mapActions: window.OCA.Maps.mapActions,
			icon: L.icon({
				iconUrl: 'noIcon',
			}),
		}
	},

	computed: {
	},

	watch: {
		latLng() {
			this.address = null
			this.formattedAddress = ''
			this.getAddress()
		},
	},

	beforeMount() {
		this.getAddress()
	},

	methods: {
		onMarkerReady(m) {
			m.openPopup()
		},
		onPopupReady(p) {
			// i don't know why but it is placed too high when it's created
			p.setLatLng(this.latLng)
		},
		getAddress() {
			this.addressLoading = true
			geocode(this.latLng.lat, this.latLng.lng).then((response) => {
				this.address = response.data.address
				this.formattedAddress = formatAddress(this.address)
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				this.addressLoading = false
			})
		},
		actionCallback(action) {
			const object = {
				id: 'geo:' + this.latLng.lat + ',' + this.latLng.lng,
				name: this.formattedAddress,
				latitude: this.latLng.lat,
				longitude: this.latLng.lng,
			}
			action.callback(object)
		},
	},
}
</script>

<style lang="scss" scoped>
span.icon {
	display: inline-block;
	height: 34px;
	width: 34px;

	&.icon-loading-small {
		display: block;
		margin-left: auto;
		margin-right: auto;
	}
}

#clickSearchAddress,
#click-search-popup-title {
	text-align: center;
}

#click-search-add-favorite,
#click-search-place-contact {
	margin-left: auto;
	margin-right: auto;
	display: block;
}

#clickSearchAddress {
	border: none;
	width: 100%;
	height: 75px;
}
</style>
