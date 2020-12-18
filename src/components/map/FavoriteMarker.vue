<template>
	<LMarker
		:options="{ data: favorite }"
		:icon="icon"
		:lat-lng="[favorite.lat, favorite.lng]"
		:draggable="draggable"
		@click="$emit('click', favorite)"
		@moveend="onMoved">
		<LTooltip
			:options="{ ...tooltipOptions, opacity: draggable ? 0 : 1 }">
			<div class="tooltip-favorite-wrapper"
				:style="'border: 2px solid #' + color">
				<b>{{ t('maps', 'Name') }}:</b>
				<span>{{ favorite.name || t('maps', 'No name') }}</span>
				<br>
				<b>{{ t('maps', 'Category') }}:</b>
				<span>{{ favorite.category }}</span>
				<br v-if="favorite.comment">
				<b v-if="favorite.comment">{{ t('maps', 'Comment') }}:</b>
				<span v-if="favorite.comment">{{ favorite.comment }}</span>
			</div>
		</LTooltip>
	</LMarker>
</template>

<script>
import L from 'leaflet'
import { LMarker, LTooltip } from 'vue2-leaflet'

export default {
	name: 'FavoriteMarker',

	components: {
		LMarker,
		LTooltip,
	},

	props: {
		favorite: {
			type: Object,
			required: true,
		},
		categories: {
			type: Object,
			required: true,
		},
		icon: {
			type: Object,
			required: true,
		},
		color: {
			type: String,
			required: true,
		},
		draggable: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			tooltipOptions: {
				className: 'leaflet-marker-favorite-tooltip',
				direction: 'top',
				offset: L.point(0, 0),
			},
			popupOptions: {
				closeOnClick: false,
				className: 'popovermenu open popupMarker favoritePopup',
				offset: L.point(-5, 10),
			},
		}
	},

	computed: {
	},

	beforeMount() {
	},

	methods: {
		onMoved(e) {
			const editedFav = {
				...this.favorite,
				lat: e.target.getLatLng().lat,
				lng: e.target.getLatLng().lng,
			}
			this.$emit('edit', editedFav)
		},
	},
}
</script>

<style lang="scss" scoped>
.tooltip-favorite-wrapper {
	padding: 6px;
	border-radius: 3px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
}
</style>
