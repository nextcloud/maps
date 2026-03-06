<template>
	<div class="click-search-popup-content">
		<h3 id="click-search-popup-title">
			{{ t('maps', 'This place') }}
		</h3>
		<span v-if="addressLoading"
			class="icon icon-loading-small" />
		<textarea v-else
			id="clickSearchAddress"
			v-model="formattedAddress" />
		<button v-if="favoriteIsCreatable" class="search-add-favorite" @click="onAddFavorite">
			<span class="icon-favorite" />
			{{ t('maps', 'Add to favorites') }}
		</button>
		<button v-if="contactIsCreatable" class="search-place-contact" @click="onPlaceContact">
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
	</div>
</template>

<script>
import L from 'leaflet'
import { formatAddress } from '../../utils.js'
import { geocode } from '../../network.js'

export default {
	name: 'ClickSearchPopup',

	props: {
		map: {
			type: Object,
			required: true,
		},
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
			addressLoading: false,
			address: null,
			formattedAddress: '',
			mapActions: window.OCA && window.OCA.Maps ? window.OCA.Maps.mapActions : [],
			icon: L.icon({
				iconUrl: 'noIcon', // transparent/invisible icon if that was the original intent
			}),
		}
	},

	watch: {
		latLng() {
			this.address = null
			this.formattedAddress = ''
			this.getAddress()
			if (this.marker) {
				this.marker.setLatLng(this.latLng)
				this.marker.openPopup()
			}
		},
	},

	created() {
		// Store leaflet objects non-reactively
		this.marker = null
	},

	beforeMount() {
		this.getAddress()
	},

	mounted() {
		// 1. Create the marker natively
		this.marker = L.marker(this.latLng, { icon: this.icon }).addTo(this.map)
		
		// 2. Bind THIS component's HTML to the popup natively
		this.marker.bindPopup(this.$el, { 
			closeButton: false, 
			offset: L.point(-1, 42) 
		})
		
		this.marker.openPopup()
	},

	beforeDestroy() {
		// 3. Clean up the native Leaflet marker
		if (this.marker && this.map) {
			this.map.removeLayer(this.marker)
		}
	},

	methods: {
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
				latitude: this.latLng.lat.toString(),
				longitude: this.latLng.lng.toString(),
			}
			action.callback(object)
			if (this.map) this.map.closePopup()
		},
		onAddFavorite() {
			this.$emit('add-favorite', { 
				latLng: this.latLng, 
				address: this.address, 
				formattedAddress: this.formattedAddress 
			})
			if (this.map) this.map.closePopup()
		},
		onPlaceContact() {
			this.$emit('place-contact', { 
				latLng: this.latLng, 
				address: this.address 
			})
			if (this.map) this.map.closePopup()
		}
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