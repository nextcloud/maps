<template>
	<AppSidebar v-show="show"
		:title="t('maps', 'Nextcloud Maps')"
		:compact="true"
		:background="backgroundImageUrl"
		:subtitle="''"
		:active="activeTab"
		@update:active="onActiveChanged"
		@close="$emit('close')">
		<!--template slot="primary-actions" /-->
		<AppSidebarTab
			id="sharing"
			icon="icon-shared"
			:name="t('maps', 'Sharing')"
			:order="1">
			PLOP
		</AppSidebarTab>
		<AppSidebarTab
			id="settings"
			icon="icon-settings-dark"
			:name="t('maps', 'Settings')"
			:order="2">
			LALA
		</AppSidebarTab>
		<AppSidebarTab
			id="favorite"
			icon="icon-settings-dark"
			:name="t('maps', 'Favorite')"
			:order="3">
			<FavoriteSidebarTab
				:favorite="favorite"
				:categories="favoriteCategories"
				@edit="$emit('edit-favorite', $event)"
				@delete="$emit('delete-favorite', $event)" />
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
// import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
// import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import { generateUrl } from '@nextcloud/router'

import FavoriteSidebarTab from '../components/FavoriteSidebarTab'

export default {
	name: 'Sidebar',

	components: {
		// ActionButton,
		AppSidebar,
		AppSidebarTab,
		FavoriteSidebarTab,
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
		favorite: {
			validator: prop => typeof prop === 'object' || prop === null,
			required: true,
		},
		favoriteCategories: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			backgroundImageUrl: generateUrl('/apps/theming/img/core/actions/address.svg?v=' + (window.OCA?.Theming?.cacheBuster || 0)),
		}
	},

	computed: {
	},

	methods: {
		onActiveChanged(newActive) {
			this.$emit('active-changed', newActive)
		},
	},
}
</script>

<style lang="scss" scoped>
// nothing
</style>
