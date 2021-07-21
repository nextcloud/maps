<template>
	<AppNavigation>
		<template slot="list">
			<h2 v-if="loading"
				class="icon-loading-small loading-icon" />
			<slot name="items" />
		</template>
		<template slot="footer">
			<AppNavigationSettings>
				<ActionCheckbox
					:checked="optionValues.trackMe === 'true'"
					@change="onTrackMeClick">
					{{ t('maps', 'Track my position') }}
				</ActionCheckbox>
				<ActionCheckbox
					:checked="optionValues.displaySlider === 'true'"
					@change="onDisplaySliderClick">
					{{ t('maps', 'Display time filter slider') }}
				</ActionCheckbox>
				<ActionText>
					{{ trueSizeText }}
				</ActionText>
				<ActionLink href="http://kai.sub.blue/en/africa.html"
					target="_blank"
					icon="icon-external">
					{{ t('maps', 'The True Size of Africa') }}
				</ActionLink>
			</AppNavigationSettings>
		</template>
	</AppNavigation>
</template>

<script>
import ClickOutside from 'vue-click-outside'

import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationSettings from '@nextcloud/vue/dist/Components/AppNavigationSettings'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import ActionText from '@nextcloud/vue/dist/Components/ActionText'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'

import optionsController from '../optionsController'

export default {
	name: 'MapsNavigation',
	components: {
		AppNavigation,
		AppNavigationSettings,
		ActionCheckbox,
		ActionText,
		ActionLink,
	},
	directives: {
		ClickOutside,
	},
	props: {
		loading: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			optionValues: optionsController.optionValues,
			trueSizeText: t('maps', 'Keep in mind that map projections always distort sizes of countries. The standard Mercator projection is particularly biased. Read more at:'),
		}
	},
	computed: {
	},
	beforeMount() {
	},
	methods: {
		onTrackMeClick(e) {
			this.optionValues.trackMe = e.target.checked
			optionsController.saveOptionValues({ trackMe: e.target.checked ? 'true' : 'false' })
			this.$emit('toggle-trackme', e.target.checked)
		},
		onDisplaySliderClick(e) {
			this.optionValues.displaySlider = e.target.checked
			optionsController.saveOptionValues({ displaySlider: e.target.checked ? 'true' : 'false' })
			this.$emit('toggle-slider', e.target.checked)
		},
	},
}
</script>
<style scoped lang="scss">
.loading-icon {
	margin-top: 16px;
}

::v-deep #app-settings-content {
	list-style: none;
}

::v-deep .app-navigation-toggle {
	background-color: var(--color-background-darker);
	z-index: 100000;
	border-radius: var(--border-radius-pill);
}
</style>
