<template>
	<MglMarker
		:coordinates="[favorite.lng, favorite.lat]"
		:draggable="draggable && favorite.isUpdateable"
		@update:coordinates="onMoved">
		<template #default>
			<div
				class="favorite-marker-icon"
				:style="markerStyle"
				@click.stop="onLeftClick"
				@contextmenu.stop="onRightClick" />
			<MglPopup
				v-if="showPopup"
				:close-button="false"
				anchor="bottom"
				@close="showPopup = false">
				<NcActionButton v-if="favorite.isDeletable" icon="icon-delete" @click="$emit('delete', favorite.id)">
					{{ t('maps', 'Delete favorite') }}
				</NcActionButton>
				<NcActionButton v-if="!isPublicVal"
					icon="icon-share"
					@click="$emit('add-to-map-favorite', favorite)">
					{{ t('maps', 'Copy to map') }}
				</NcActionButton>
			</MglPopup>
		</template>
	</MglMarker>
</template>

<script>
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import { MglMarker, MglPopup } from '@indoorequal/vue-maplibre-gl'
import { isPublic } from '../../utils/common'

export default {
	name: 'FavoriteMarker',

	components: {
		MglMarker,
		MglPopup,
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
			required: false,
			default: null,
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
			showPopup: false,
		}
	},

	computed: {
		isPublicVal() {
			return isPublic()
		},
		markerStyle() {
			return `background-color: #${this.color}; width: 36px; height: 36px; border-radius: 50%; border: 2px solid rgba(0,0,0,0.3); cursor: pointer;`
		},
	},

	methods: {
		onMoved(lngLat) {
			const editedFav = {
				...this.favorite,
				lat: lngLat.lat,
				lng: lngLat.lng,
			}
			this.$emit('edit', editedFav)
		},
		onLeftClick() {
			this.$emit('click', this.favorite)
		},
		onRightClick() {
			this.showPopup = true
		},
	},
}
</script>

<style lang="scss" scoped>
.favorite-marker-icon {
	box-shadow: 0px 0px 10px #888;
}
</style>
