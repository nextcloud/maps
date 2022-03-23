<template>
	<AppSidebar v-show="show"
		:title="sidebarTitle"
		:compact="!hasPreview() || isFullScreen"
		:background="backgroundImageUrl"
		:subtitle="sidebarSubtitle"
		:active="activeTab"
		:class="{
			'app-sidebar--has-preview': hasPreview(),
			'app-sidebar--full': isFullScreen,}"
		@update:active="onActiveChanged"
		@opened="$emit('opened')"
		@close="$emit('close')">
		<FavoriteSidebarTab v-if="activeTab === 'favorite'"
			:favorite="favorite"
			:categories="favoriteCategories"
			@edit="$emit('edit-favorite', $event)"
			@delete="$emit('delete-favorite', $event)" />
		<TrackSidebarTab v-if="activeTab === 'track'"
			:track="track" />
		<PhotoSidebarTab v-if="activeTab === 'photo'"
			:photo="photo" />
		<PhotoSuggestionsSidebarTab v-if="activeTab === 'photo-suggestion'"
			:photo-suggestions="photoSuggestions"
			:photo-suggestions-selected-indices="photoSuggestionsSelectedIndices"
			:loading="photosLoading"
			@select-all="$emit('select-all-photo-suggestions')"
			@clear-selection="$emit('clear-photo-suggestions-selection',$event)"
			@cancel="$emit('cancel-photo-suggestions')"
			@save="$emit('save-photo-suggestions-selection',$event)"
			@zoom="$emit('zoom-photo-suggestion', $event)" />
	</AppSidebar>
</template>

<script>
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import { generateUrl } from '@nextcloud/router'

import FavoriteSidebarTab from '../components/FavoriteSidebarTab'
import TrackSidebarTab from '../components/TrackSidebarTab'
import PhotoSidebarTab from '../components/PhotoSidebarTab'
import PhotoSuggestionsSidebarTab from './PhotoSuggestionsSidebarTab'

export default {
	name: 'Sidebar',

	components: {
		// ActionButton,
		AppSidebar,
		FavoriteSidebarTab,
		TrackSidebarTab,
		PhotoSidebarTab,
		PhotoSuggestionsSidebarTab,
	},

	props: {
		show: {
			type: Boolean,
			required: true,
		},
		activeTab: {
			type: String,
			required: true,
		},
		photo: {
			validator: prop => typeof prop === 'object' || prop === null,
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
		photoSuggestionsSelectedIndices: {
			required: true,
			type: Array,
		},
		favorite: {
			validator: prop => typeof prop === 'object' || prop === null,
			required: true,
		},
		favoriteCategories: {
			type: Object,
			required: true,
		},
		track: {
			validator: prop => typeof prop === 'object' || prop === null,
			required: true,
		},
		isFullScreen: {
			type: Boolean,
			required: false,
			default: false,
		},
	},

	data() {
		return {}
	},

	computed: {
		sidebarTitle() {
			if (this.activeTab === 'track') {
				return t('maps', 'Track')
			} else if (this.activeTab === 'favorite') {
				return t('maps', 'Favorite')
			} else if (this.activeTab === 'photo') {
				return this.photo.basename
			} else if (this.activeTab === 'photo-suggestion') {
				return t('maps', 'Photo location suggestions')
			}
			return t('maps', 'Sidebar')
		},
		sidebarSubtitle() {
			if (this.activeTab === 'track') {
				return ''
			} else if (this.activeTab === 'favorite') {
				return ''
			} else if (this.activeTab === 'photo') {
				return this.photo.filename
			} else if (this.activeTab === 'photo-suggestion') {
				return ''
			}
			return t('maps', 'shows cool information')
		},
		backgroundImageUrl() {
			const iconColor = OCA.Accessibility?.theme === 'dark' ? 'ffffff' : '000000'
			if (this.activeTab === 'track') {
				return generateUrl('/svg/maps/road?color=' + iconColor)
			} else if (this.activeTab === 'favorite') {
				return generateUrl('/svg/core/actions/star?color=' + iconColor)
			} else if (this.activeTab === 'photo') {
				return this.previewUrl()
			} else if (this.activeTab === 'photo-suggestion') {
				return generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2'
			}
			return ''
		},
	},

	methods: {
		onActiveChanged(newActive) {
			this.$emit('active-changed', newActive)
		},
		previewUrl() {
			return this.photo.hasPreview
				? generateUrl('core') + '/preview?fileId=' + this.photo.fileId + '&x=500&y=300&a=1'
				: generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2'
		},
		hasPreview() {
			return this.activeTab === 'photo' && this.photo.hasPreview
		},
	},
}
</script>

<style lang="scss" scoped>
::v-deep .icon-tab-track {
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask: url('../../img/road.svg') no-repeat;
	mask-size: 18px 18px;
	mask-position: center 7px;
	-webkit-mask: url('../../img/road.svg') no-repeat;
	-webkit-mask-size: 18px 18px;
	-webkit-mask-position: center top;
	min-width: 44px !important;
	min-height: 44px !important;
}

.app-sidebar {
	&--full {
		position: fixed !important;
		z-index: 2025 !important;
		top: 0 !important;
		height: 100% !important;
	}
	&--has-preview::v-deep {
		.app-sidebar-header__figure {
			background-size: cover;
		}

		&[data-mimetype="text/plain"],
		&[data-mimetype="text/markdown"] {
			.app-sidebar-header__figure {
				background-size: contain;
			}
		}
	}
}
</style>
