<template>
	<LMarker :lat-lng="latLng"
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
			<button class="search-add-favorite" @click="$emit('add-favorite', { latLng, address, formattedAddress })">
				<span class="icon-favorite" />
				{{ t('maps', 'Add to favorites') }}
			</button>
			<button class="search-place-contact" @click="$emit('place-contact', { latLng, address })">
				<span class="icon-user" />
				{{ t('maps', 'Add contact address') }}
			</button>
		</LPopup>
	</LMarker>
</template>

<script>
import { LMarker, LPopup } from 'vue2-leaflet'

import { formatAddress } from '../../utils'
import { geocode } from '../../network'

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
	},

	data() {
		return {
			popupOptions: {
				closeButton: false,
			},
			addressLoading: false,
			address: null,
			formattedAddress: '',
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
