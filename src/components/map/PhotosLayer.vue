<template>
	<template>
		<MglMarker v-for="photo in displayedPhotos"
			:key="photo.fileId"
			:coordinates="[photo.lng, photo.lat]"
			:draggable="draggable && photo.isUpdateable"
			@update:coordinates="(ll) => $emit('photo-moved', photo, ll)">
			<template #default>
				<div class="leaflet-marker-photo photo-marker"
					:style="'background-image: url(' + getPreviewUrl(photo) + ')'"
					@click.stop="onPhotoClick(photo)"
					@contextmenu.stop="onPhotoRightClick(photo)"
					@mouseover="currentPhoto = photo"
					@mouseleave="currentPhoto = null" />
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
			</template>
		</MglMarker>
	</template>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import { basename } from '@nextcloud/paths'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'

import { MglMarker, MglPopup } from '@indoorequal/vue-maplibre-gl'

import optionsController from '../../optionsController.js'
import { binSearch, getToken, isPublic } from '../../utils/common.js'

export default {
	name: 'PhotosLayer',
	components: {
		MglMarker,
		MglPopup,
		NcActionButton,
	},

	props: {
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
	},

	data() {
		return {
			optionValues: optionsController.optionValues,
			currentPhoto: null,
			currentPopupPhoto: null,
		}
	},

	computed: {
		isPublicVal() {
			return isPublic()
		},
		displayedPhotos() {
			if (!this.dateFilterEnabled) {
				return this.photos
			}
			const lastNullIndex = binSearch(this.photos, (p) => !p.dateTaken)
			const firstShownIndex = binSearch(this.photos, (p) => (p.dateTaken || 0) < this.dateFilterStart) + 1
			const lastShownIndex = binSearch(this.photos, (p) => (p.dateTaken || 0) < this.dateFilterEnd)
			return [
				...this.photos.slice(0, lastNullIndex + 1),
				...this.photos.slice(firstShownIndex, lastShownIndex + 1),
			]
		},
	},

	methods: {
		basename(path) {
			return basename(path)
		},
		getPreviewUrl(photo) {
			if (photo && photo.hasPreview) {
				const token = getToken()
				return token
					? generateUrl('apps/files_sharing/publicpreview/') + token + '?file=' + encodeURIComponent(photo.path) + '&x=341&y=256&a=1'
					: generateUrl('core') + '/preview?fileId=' + photo.fileId + '&x=341&y=256&a=1'
			} else {
				return generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2'
			}
		},
		getPhotoFormattedDate(photo) {
			if (photo) {
				const d = new Date(photo.dateTaken * 1000)
				const mom = moment.unix(photo.dateTaken + d.getTimezoneOffset() * 60)
				return mom.format('LL') + ' ' + mom.format('HH:mm:ss')
			}
			return ''
		},
		onPhotoClick(photo) {
			this.viewPhoto(photo)
		},
		onPhotoRightClick(photo) {
			this.currentPopupPhoto = photo
		},
		viewPhoto(photo) {
			if (OCA.Viewer && OCA.Viewer.open) {
				OCA.Viewer.open({ path: photo.path, list: [photo] })
			}
		},
		isPublic() {
			return isPublic()
		},
	},
}
</script>

<style lang="scss" scoped>
// nothing
</style>
