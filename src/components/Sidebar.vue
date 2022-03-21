<template>
	<AppSidebar v-show="show"
		:title="sidebarTitle"
		:compact="true"
		:background="backgroundImageUrl"
		:subtitle="''"
		:active="activeTab"
		:class="{'app-sidebar--full': isFullScreen}"
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
	</AppSidebar>
</template>

<script>
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import { generateUrl } from '@nextcloud/router'

import FavoriteSidebarTab from '../components/FavoriteSidebarTab'
import TrackSidebarTab from '../components/TrackSidebarTab'
import PhotoSidebarTab from '../components/PhotoSidebarTab'

export default {
	name: 'Sidebar',

	components: {
		// ActionButton,
		AppSidebar,
		FavoriteSidebarTab,
		TrackSidebarTab,
		PhotoSidebarTab,
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
		backgroundImageUrl() {
			const iconColor = OCA.Accessibility?.theme === 'dark' ? 'ffffff' : '000000'
			if (this.activeTab === 'track') {
				return generateUrl('/svg/maps/road?color=' + iconColor)
			} else if (this.activeTab === 'favorite') {
				return generateUrl('/svg/core/actions/star?color=' + iconColor)
			}
			return ''
		},
		sidebarTitle() {
			if (this.activeTab === 'track') {
				return t('maps', 'Track')
			} else if (this.activeTab === 'favorite') {
				return t('maps', 'Favorite')
			}
			return ''
		},
	},

	methods: {
		onActiveChanged(newActive) {
			this.$emit('active-changed', newActive)
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
}
</style>
