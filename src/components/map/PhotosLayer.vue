<template>
	<template>
		<MglMarker v-for="photo in displayedPhotos"
			:key="photo.fileId"
			:coordinates="[photo.lng, photo.lat]"
			:draggable="draggable && photo.isUpdateable"
			@update:coordinates="(ll) => $emit('photo-moved', photo, ll)">
			<template #marker>
				<div class="leaflet-marker-photo photo-marker"
					:style="'background-image: url(' + getPreviewUrl(photo) + ')'"
					@click.stop="onPhotoClick(photo)"
					@contextmenu.stop="onPhotoRightClick(photo)"
					@mouseover="currentPhoto = photo"
					@mouseleave="currentPhoto = null" />
			</template>
			<MglPopup v-if="currentPopupPhoto === photo" :close-button="false" anchor="bottom" @close="currentPopupPhoto = null">
				<NcActionButton v-if="photo.path" icon="icon-toggle" @click="$emit('open-sidebar', photo.path)">
					{{ t('maps', 'Open in Sidebar') }}
				</NcActionButton>
				<NcActionButton icon="icon-toggle" @click="viewPhoto(photo)">
					{{ t('maps', 'Display picture') }}
				</NcActionButton>
				<NcActionButton v-if="photo.isUpdateable" icon="icon-history" @click="$emit('coords-reset', [photo.path])">
					{{ t('maps', 'Remove geo data') }}
				</NcActionButton>
				<NcActionButton v-if="!isPublicVal"
					icon="icon-share"
					@click="$emit('add-to-map-photo', photo)">
					{{ t('maps', 'Copy to map') }}
				</NcActionButton>
			</MglPopup>
		</MglMarker>
	</template>
</template>

<script setup>
import { ref, computed } from 'vue'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { basename } from '@nextcloud/paths'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import { MglMarker, MglPopup } from '@indoorequal/vue-maplibre-gl'
import { binSearch, getToken, isPublic } from '../../utils/common.js'

const props = defineProps({
	map: {
		type: Object,
		required: true,
	},
	photos: {
		type: Array,
		required: true,
	},
	dateFilterEnabled: {
		type: Boolean,
		required: true,
	},
	dateFilterStart: {
		type: Number,
		required: true,
	},
	dateFilterEnd: {
		type: Number,
		required: true,
	},
	draggable: {
		type: Boolean,
		required: true,
	},
})

defineEmits(['photo-moved', 'open-sidebar', 'coords-reset', 'add-to-map-photo'])

const currentPhoto = ref(null)
const currentPopupPhoto = ref(null)

const isPublicVal = computed(() => isPublic())

const displayedPhotos = computed(() => {
	if (!props.dateFilterEnabled) {
		return props.photos
	}
	const lastNullIndex = binSearch(props.photos, (p) => !p.dateTaken)
	const firstShownIndex = binSearch(props.photos, (p) => (p.dateTaken || 0) < props.dateFilterStart) + 1
	const lastShownIndex = binSearch(props.photos, (p) => (p.dateTaken || 0) < props.dateFilterEnd)
	return [
		...props.photos.slice(0, lastNullIndex + 1),
		...props.photos.slice(firstShownIndex, lastShownIndex + 1),
	]
})

function getPreviewUrl(photo) {
	if (photo && photo.hasPreview) {
		const token = getToken()
		return token
			? generateUrl('apps/files_sharing/publicpreview/') + token + '?file=' + encodeURIComponent(photo.path) + '&x=341&y=256&a=1'
			: generateUrl('core') + '/preview?fileId=' + photo.fileId + '&x=341&y=256&a=1'
	} else {
		return generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2'
	}
}

function onPhotoClick(photo) {
	viewPhoto(photo)
}

function onPhotoRightClick(photo) {
	currentPopupPhoto.value = photo
}

function viewPhoto(photo) {
	if (OCA.Viewer && OCA.Viewer.open) {
		OCA.Viewer.open({ path: photo.path, list: [photo] })
	}
}
</script>

<style lang="scss" scoped>
// nothing
</style>
