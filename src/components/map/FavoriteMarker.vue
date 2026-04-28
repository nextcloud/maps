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
				<NcActionButton v-if="favorite.isDeletable" @click="emit('delete', favorite.id)">
					<template #icon>
						<TrashCanIcon :size="20" />
					</template>
					{{ t('maps', 'Delete favorite') }}
				</NcActionButton>
				<NcActionButton v-if="!isPublicVal"
					@click="emit('add-to-map-favorite', favorite)">
					<template #icon>
						<ShareVariantIcon :size="20" />
					</template>
					{{ t('maps', 'Copy to map') }}
				</NcActionButton>
			</MglPopup>
		</template>
	</MglMarker>
</template>

<script setup>
import { ref, computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import { MglMarker, MglPopup } from '@indoorequal/vue-maplibre-gl'
import { isPublic } from '../../utils/common'
import ShareVariantIcon from 'vue-material-design-icons/ShareVariant.vue'
import TrashCanIcon from 'vue-material-design-icons/TrashCan.vue'

const props = defineProps({
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
})

const emit = defineEmits(['click', 'edit', 'delete', 'add-to-map-favorite'])

const showPopup = ref(false)

const isPublicVal = computed(() => isPublic())

const markerStyle = computed(() =>
	`background-color: #${props.color}; width: 36px; height: 36px; border-radius: 50%; border: 2px solid rgba(0,0,0,0.3); cursor: pointer;`,
)

function onMoved(lngLat) {
	emit('edit', { ...props.favorite, lat: lngLat.lat, lng: lngLat.lng })
}

function onLeftClick() {
	emit('click', props.favorite)
}

function onRightClick() {
	showPopup.value = true
}
</script>

<style lang="scss" scoped>
.favorite-marker-icon {
	box-shadow: 0px 0px 10px #888;
}
</style>
