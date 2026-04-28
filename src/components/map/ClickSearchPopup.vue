<template>
	<MglMarker :coordinates="[latLng.lng, latLng.lat]">
		<template #marker>
			<div style="display:none" />
		</template>
		<MglPopup :close-button="false" anchor="bottom" :offset="[0, 0]" :showed="true">
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
			</MglPopup>
	</MglMarker>
</template>

<script setup>
import { ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import { MglMarker, MglPopup } from '@indoorequal/vue-maplibre-gl'
import { formatAddress } from '../../utils.js'
import { geocode } from '../../network.js'

const props = defineProps({
	latLng: {
		type: Object,
		required: true,
	},
	favoriteIsCreatable: {
		type: Boolean,
		default: true,
	},
	contactIsCreatable: {
		type: Boolean,
		default: true,
	},
})

defineEmits(['add-favorite', 'place-contact'])

const addressLoading = ref(false)
const address = ref(null)
const formattedAddress = ref('')
const mapActions = window.OCA?.Maps?.mapActions ?? []

function getAddress() {
	addressLoading.value = true
	geocode(props.latLng.lat, props.latLng.lng).then((response) => {
		address.value = response.data.address
		formattedAddress.value = formatAddress(address.value)
	}).catch((error) => {
		console.error(error)
	}).then(() => {
		addressLoading.value = false
	})
}

watch(() => props.latLng, () => {
	address.value = null
	formattedAddress.value = ''
	getAddress()
})

getAddress()

function actionCallback(action) {
	const object = {
		id: 'geo:' + props.latLng.lat + ',' + props.latLng.lng,
		name: formattedAddress.value,
		latitude: props.latLng.lat.toString(),
		longitude: props.latLng.lng.toString(),
	}
	action.callback(object)
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
