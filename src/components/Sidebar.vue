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
	</AppSidebar>
</template>

<script>
// import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
// import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'

import { generateUrl } from '@nextcloud/router'

export default {
	name: 'Sidebar',

	components: {
		// ActionButton,
		// ActionLink,
		AppSidebar,
		AppSidebarTab,
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
