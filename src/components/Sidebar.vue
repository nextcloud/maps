<template>
	<NcAppSidebar v-if="show"
		:name="name"
		:subname="subname"
		:compact="true"
		@close="$emit('close')">
		<template #header>
			<span v-if="activeTab === 'favorite' && favorite"
				class="favorite-header-icon"
				:style="{ backgroundColor: favoriteColor }">
				<IconStar :size="32" fill-color="#ffffff" />
			</span>
			<span v-else-if="icon" :class="['header-icon icon', icon]" />
		</template>
		<FavoriteSidebarTab v-if="activeTab === 'favorite'"
			:favorite="favorite"
			:categories="favoriteCategories"
			@edit="$emit('edit-favorite', $event)"
			@delete="$emit('delete-favorite', $event)" />
		<PhotoSuggestionsSidebarTab v-if="activeTab === 'photo-suggestion'"
			:photo-suggestions="photoSuggestions"
			:photo-suggestions-tracks-and-devices="photoSuggestionsTracksAndDevices"
			:photo-suggestions-selected-indices="photoSuggestionsSelectedIndices"
			:photo-suggestions-hide-photos="photoSuggestionsHidePhotos"
			:photo-suggestions-timezone="photoSuggestionsTimezone"
			:loading="photosLoading"
			@load-more="$emit('load-more-photo-suggestions')"
			@change-timezone="$emit('change-photo-suggestions-timezone',$event)"
			@select-all="$emit('select-all-photo-suggestions')"
			@clear-selection="$emit('clear-photo-suggestions-selection',$event)"
			@cancel="$emit('cancel-photo-suggestions')"
			@toggle-hide-photos="$emit('toggle-photo-suggestions-hide-photo')"
			@save="$emit('save-photo-suggestions-selection',$event)"
			@zoom="$emit('zoom-photo-suggestion', $event)"
			@toggle-track-or-device="$emit('photo-suggestion-toggle-track-or-device', $event)" />
		<TrackMetadataTab v-if="activeTab === 'maps-track-metadata'"
			:track="track" />
	</NcAppSidebar>
</template>

<script>
import { NcAppSidebar } from '@nextcloud/vue'
import IconStar from 'vue-material-design-icons/Star.vue'

import FavoriteSidebarTab from '../components/FavoriteSidebarTab.vue'
import PhotoSuggestionsSidebarTab from './Sidebar/PhotoSuggestionsSidebarTab.vue'
import TrackMetadataTab from './TrackMetadataTab.vue'

/**
 * Sidebar for the maps' own entities (favorites, photo suggestions, tracks).
 * Files and folders are shown in the files sidebar instead, see utils/filesSidebar.ts.
 */
export default {
	name: 'Sidebar',

	components: {
		NcAppSidebar,
		IconStar,
		FavoriteSidebarTab,
		PhotoSuggestionsSidebarTab,
		TrackMetadataTab,
	},

	props: {
		show: {
			type: Boolean,
			required: true,
		},
		activeTab: {
			type: String,
			default: null,
		},
		favorite: {
			validator: prop => typeof prop === 'object' || prop === null,
			required: true,
		},
		favoriteCategories: {
			type: Object,
			required: true,
		},
		photosLoading: {
			required: true,
			type: Boolean,
		},
		photoSuggestions: {
			required: true,
			type: Array,
		},
		photoSuggestionsTracksAndDevices: {
			required: true,
			type: Object,
		},
		photoSuggestionsSelectedIndices: {
			required: true,
			type: Array,
		},
		photoSuggestionsHidePhotos: {
			type: Boolean,
			default: false,
		},
		photoSuggestionsTimezone: {
			required: true,
			type: String,
		},
		track: {
			required: false,
			validator: prop => typeof prop === 'object' || prop === null,
			default: null,
		},
	},

	emits: [
		'close',
		'edit-favorite',
		'delete-favorite',
		'load-more-photo-suggestions',
		'change-photo-suggestions-timezone',
		'select-all-photo-suggestions',
		'clear-photo-suggestions-selection',
		'cancel-photo-suggestions',
		'toggle-photo-suggestions-hide-photo',
		'save-photo-suggestions-selection',
		'zoom-photo-suggestion',
		'photo-suggestion-toggle-track-or-device',
	],

	computed: {
		name() {
			switch (this.activeTab) {
			case 'favorite':
				return this.favorite?.name || t('maps', 'Favorite')
			case 'photo-suggestion':
				return t('maps', 'Photo suggestions')
			case 'maps-track-metadata':
				return t('maps', 'Track metadata')
			default:
				return ''
			}
		},

		subname() {
			return this.activeTab === 'favorite' ? (this.favorite?.category ?? '') : ''
		},

		favoriteColor() {
			const color = this.favoriteCategories[this.favorite?.category]?.color ?? '0082c9'
			return '#' + color.replace('#', '')
		},

		icon() {
			switch (this.activeTab) {
			case 'photo-suggestion':
				return 'icon-picture'
			default:
				return null
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.favorite-header-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 100%;
	height: 100%;
	border-radius: 50%;
}

.header-icon {
	display: block;
	width: 70px;
	height: 60px;
	background-size: 40px 40px;
}
</style>
