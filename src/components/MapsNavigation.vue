<template>
	<NcAppNavigation aria-label="maps-app-navigation">
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

<script>
import { NcAppNavigation, NcAppNavigationSettings, NcActionCheckbox, NcActionText, NcActionLink } from '@nextcloud/vue'

import optionsController from '../optionsController.js'

const clickOutside = {
	beforeMount(el, binding) {
		el.clickOutsideEvent = (event) => {
			if (!(el === event.target || el.contains(event.target))) {
				binding.value(event);
			}
		};
		document.body.addEventListener('click', el.clickOutsideEvent);
	},
	unmounted(el) {
		document.body.removeEventListener('click', el.clickOutsideEvent);
	},
};

export default {
	name: 'MapsNavigation',
	components: {
		NcAppNavigation,
		NcAppNavigationSettings,
		NcActionCheckbox,
		NcActionText,
		NcActionLink,
	},
	directives: {
		clickOutside,
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
		onGeoLinkClick(e) {
			this.$emit('toggle-geo-link', e.target.checked)
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
