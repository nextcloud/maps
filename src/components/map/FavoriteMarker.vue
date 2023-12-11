<template>
	<LMarker
		ref="marker"
		:options="{ data: favorite }"
		:icon="icon"
		:lat-lng="[favorite.lat, favorite.lng]"
		:draggable="draggable && favorite.isUpdateable"
		@ready="onMarkerReady"
		@contextmenu="onRightClick"
		@click="$emit('click', favorite)"
		@moveend="onMoved">
		<LTooltip
			:options="{ ...tooltipOptions, opacity: draggable && favorite.isUpdateable ? 0 : 1 }">
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
		<LPopup
			class="popup-favorite-wrapper"
			:options="popupOptions">
			<NcActionButton v-if="favorite.isDeletable" icon="icon-delete" @click="$emit('delete', favorite.id)">
				{{ t('maps', 'Delete favorite') }}
			</NcActionButton>
			<NcActionButton v-if="!isPublic()"
				icon="icon-share"
				@click="$emit('add-to-map-favorite', favorite)">
				{{ t('maps', 'Copy to map') }}
			</NcActionButton>
		</LPopup>
	</LMarker>
</template>

<script>
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'

import L from 'leaflet'
import { LMarker, LTooltip, LPopup } from 'vue2-leaflet'
import { isPublic } from '../../utils/common'

export default {
	name: 'FavoriteMarker',

	components: {
		LMarker,
		LTooltip,
		LPopup,
		NcActionButton,
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
				closeButton: false,
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
		onMarkerReady(m) {
			// avoid left click popup
			L.DomEvent.on(m, 'click', (ev) => {
				m.closePopup()
			})
		},
		onRightClick(e) {
			this.$refs.marker.mapObject.openPopup()
		},
		isPublic() {
			return isPublic()
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
