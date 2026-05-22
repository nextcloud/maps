<template>
	<NcAppNavigation>
		<template #list>
			<h2 v-if="loading"
				class="icon-loading-small loading-icon" />
			<slot name="items" />
		</template>
		<template #footer>
			<NcAppNavigationSettings>
				<NcActionCheckbox
					:checked="optionValues.trackMe === 'true'"
					@change="onTrackMeClick">
					{{ t('maps', 'Track my position') }}
				</NcActionCheckbox>
				<NcActionCheckbox
					:checked="false"
					@change="onGeoLinkClick">
					{{ t('maps', 'Open geo links') }}
				</NcActionCheckbox>
				<NcActionCheckbox
					:checked="optionValues.displaySlider === 'true'"
					@change="onDisplaySliderClick">
					{{ t('maps', 'Display time filter slider') }}
				</NcActionCheckbox>
				<NcActionText>
					{{ trueSizeText }}
				</NcActionText>
				<NcActionLink href="http://kai.sub.blue/en/africa.html"
					target="_blank"
					icon="icon-external">
					{{ t('maps', 'The True Size of Africa') }}
				</NcActionLink>
			</NcAppNavigationSettings>
		</template>
	</NcAppNavigation>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationSettings from '@nextcloud/vue/components/NcAppNavigationSettings'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionText from '@nextcloud/vue/components/NcActionText'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import optionsController from '../optionsController.js'

defineProps({
	loading: {
		type: Boolean,
		default: false,
	},
})

const emit = defineEmits(['toggle-trackme', 'toggle-geo-link', 'toggle-slider'])

const optionValues = optionsController.optionValues
const trueSizeText = t('maps', 'Keep in mind that map projections always distort sizes of countries. The standard Mercator projection is particularly biased. Read more at:')

function onTrackMeClick(e) {
	optionValues.trackMe = e.target.checked
	optionsController.saveOptionValues({ trackMe: e.target.checked ? 'true' : 'false' })
	emit('toggle-trackme', e.target.checked)
}

function onGeoLinkClick(e) {
	emit('toggle-geo-link', e.target.checked)
}

function onDisplaySliderClick(e) {
	optionValues.displaySlider = e.target.checked
	optionsController.saveOptionValues({ displaySlider: e.target.checked ? 'true' : 'false' })
	emit('toggle-slider', e.target.checked)
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
