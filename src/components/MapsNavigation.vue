<template>
	<NcAppNavigation aria-label="maps-app-navigation">
		<template #list>
			<h2 v-if="loading"
				class="icon-loading-small loading-icon" />
			<slot name="items" />
		</template>
		<template #footer>
			<NcAppNavigationSettings>
				<div class="maps-settings">
					<NcCheckboxRadioSwitch
						:checked="optionValues.trackMe === 'true'"
						@update:checked="onTrackMeChange">
						{{ window.t('maps', 'Track my position') }}
					</NcCheckboxRadioSwitch>
					
					<NcCheckboxRadioSwitch
						:checked="false"
						@update:checked="onGeoLinkChange">
						{{ window.t('maps', 'Open geo links') }}
					</NcCheckboxRadioSwitch>
					
					<NcCheckboxRadioSwitch
						:checked="optionValues.displaySlider === 'true'"
						@update:checked="onDisplaySliderChange">
						{{ window.t('maps', 'Display time filter slider') }}
					</NcCheckboxRadioSwitch>
					
					<p class="maps-settings-text">
						{{ trueSizeText }}
					</p>
					
					<a href="http://kai.sub.blue/en/africa.html"
						target="_blank"
						rel="noreferrer noopener"
						class="maps-settings-link">
						<span class="icon-external"></span>
						{{ window.t('maps', 'The True Size of Africa') }}
					</a>
				</div>
			</NcAppNavigationSettings>
		</template>
	</NcAppNavigation>
</template>

<script>
import { NcAppNavigation, NcAppNavigationSettings, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import optionsController from '../optionsController.js'

// Native Vue 3 Click-Outside directive
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
		NcCheckboxRadioSwitch,
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
			// Make window available to template
			window: window,
		}
	},
	
	computed: {
		trueSizeText() {
			return window.t('maps', 'Keep in mind that map projections always distort sizes of countries. The standard Mercator projection is particularly biased. Read more at:')
		}
	},
	
	methods: {
		onTrackMeChange(checked) {
			this.optionValues.trackMe = checked ? 'true' : 'false'
			optionsController.saveOptionValues({ trackMe: checked ? 'true' : 'false' })
			this.$emit('toggle-trackme', checked)
		},
		onGeoLinkChange(checked) {
			this.$emit('toggle-geo-link', checked)
		},
		onDisplaySliderChange(checked) {
			this.optionValues.displaySlider = checked ? 'true' : 'false'
			optionsController.saveOptionValues({ displaySlider: checked ? 'true' : 'false' })
			this.$emit('toggle-slider', checked)
		},
	},
}
</script>

<style scoped lang="scss">
.loading-icon {
	margin-top: 16px;
}

.maps-settings {
	padding: 12px;
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.maps-settings-text {
	color: var(--color-text-maxcontrast);
	line-height: 1.4;
	margin-top: 8px;
}

.maps-settings-link {
	display: flex;
	align-items: center;
	gap: 8px;
	color: var(--color-text-maxcontrast);
	text-decoration: none;

	&:hover, &:focus {
		color: var(--color-primary-element);
		text-decoration: underline;
	}

	.icon-external {
		opacity: 0.7;
	}
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