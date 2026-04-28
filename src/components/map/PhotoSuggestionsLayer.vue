<template>
	<template>
		<MglMarker v-for="(photo, i) in displayedSuggestions"
			:key="i"
			:coordinates="[photo.lng, photo.lat]"
			:draggable="draggable"
			@update:coordinates="(ll) => $emit('photo-suggestion-moved', photo.originalIndex, ll)">
			<template #default>
				<div :class="['leaflet-marker-photo-suggestion', 'photo-suggestion-marker', photoSuggestionsSelectedIndices.includes(photo.originalIndex) ? 'photo-suggestion-marker-selected' : '']"
					:style="'background-image: url(' + getPreviewUrl(photo) + ')'"
					@click.stop="$emit('photo-suggestion-selected', photo.originalIndex)"
					@contextmenu.stop="currentPopupSuggestion = photo"
					@mouseover="currentSuggestion = photo"
					@mouseleave="currentSuggestion = null" />
				<MglPopup v-if="currentPopupSuggestion === photo" :close-button="false" anchor="bottom" @close="currentPopupSuggestion = null">
					<NcActionButton icon="icon-toggle" @click="viewPhoto(photo)">
						{{ t('maps', 'Display picture') }}
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
import { binSearch, getToken } from '../../utils/common.js'

export default {
	name: 'PhotoSuggestionsLayer',
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
		photoSuggestions: {
			type: Array,
			required: true,
		},
		photoSuggestionsTracksAndDevices: {
			type: Object,
			required: true,
		},
		photoSuggestionsSelectedIndices: {
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
			currentSuggestion: null,
			currentPopupSuggestion: null,
		}
	},

	computed: {
		displayedSuggestions() {
			const indexed = this.photoSuggestions
				.map((p, i) => p ? { ...p, originalIndex: i } : null)
				.filter((p) => p && this.photoSuggestionsTracksAndDevices[p.trackOrDeviceId]?.enabled)

			if (!this.dateFilterEnabled) {
				return indexed
			}
			const lastNullIndex = binSearch(indexed, (p) => !p.dateTaken)
			const firstShownIndex = binSearch(indexed, (p) => (p.dateTaken || 0) < this.dateFilterStart) + 1
			const lastShownIndex = binSearch(indexed, (p) => (p.dateTaken || 0) < this.dateFilterEnd)
			return [
				...indexed.slice(0, lastNullIndex + 1),
				...indexed.slice(firstShownIndex, lastShownIndex + 1),
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
		viewPhoto(photo) {
			if (OCA.Viewer && OCA.Viewer.open) {
				OCA.Viewer.open({ path: photo.path, list: [photo] })
			}
		},
	},
}
</script>

<style lang="scss" scoped>
// nothing
</style>
