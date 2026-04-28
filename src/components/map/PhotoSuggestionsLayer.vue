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

<script setup>
import { ref, computed } from 'vue'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import { MglMarker, MglPopup } from '@indoorequal/vue-maplibre-gl'
import { binSearch, getToken } from '../../utils/common.js'

const props = defineProps({
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
})

defineEmits(['photo-suggestion-moved', 'photo-suggestion-selected'])

const currentSuggestion = ref(null)
const currentPopupSuggestion = ref(null)

const displayedSuggestions = computed(() => {
	const indexed = props.photoSuggestions
		.map((p, i) => p ? { ...p, originalIndex: i } : null)
		.filter((p) => p && props.photoSuggestionsTracksAndDevices[p.trackOrDeviceId]?.enabled)

	if (!props.dateFilterEnabled) {
		return indexed
	}
	const lastNullIndex = binSearch(indexed, (p) => !p.dateTaken)
	const firstShownIndex = binSearch(indexed, (p) => (p.dateTaken || 0) < props.dateFilterStart) + 1
	const lastShownIndex = binSearch(indexed, (p) => (p.dateTaken || 0) < props.dateFilterEnd)
	return [
		...indexed.slice(0, lastNullIndex + 1),
		...indexed.slice(firstShownIndex, lastShownIndex + 1),
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

function viewPhoto(photo) {
	if (OCA.Viewer && OCA.Viewer.open) {
		OCA.Viewer.open({ path: photo.path, list: [photo] })
	}
}
</script>

<style lang="scss" scoped>
// nothing
</style>
