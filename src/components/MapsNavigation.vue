<template>
	<NcAppNavigation :aria-label="t('maps', 'Maps navigation')">
		<template #list>
			<slot name="header" />

			<NcAppNavigationCaption :name="t('maps', 'Layers')"
				:is-heading="true"
				heading-id="maps-navigation-layers">
				<template v-if="$slots['add-actions']" #actions>
					<slot name="add-actions" />
				</template>
				<template #actionsTriggerIcon>
					<IconPlus :size="20" />
				</template>
			</NcAppNavigationCaption>

			<div v-if="loading" class="maps-navigation__loading">
				<NcLoadingIcon :size="20" />
			</div>
			<slot name="items" />
		</template>

		<template v-if="showSettings" #footer>
			<ul class="maps-navigation__footer">
				<NcAppNavigationItem :name="t('maps', 'Settings')" @click="settingsOpen = true">
					<template #icon>
						<IconCog :size="20" />
					</template>
				</NcAppNavigationItem>
			</ul>
			<NcAppSettingsDialog :open="settingsOpen"
				:name="t('maps', 'Maps settings')"
				@update:open="settingsOpen = $event">
				<NcAppSettingsSection id="maps-settings-general" :name="t('maps', 'General')">
					<NcCheckboxRadioSwitch type="switch"
						:model-value="optionValues.trackMe === 'true'"
						@update:model-value="onTrackMeChange">
						{{ t('maps', 'Track my position') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch type="switch"
						:model-value="optionValues.displaySlider === 'true'"
						@update:model-value="onDisplaySliderChange">
						{{ t('maps', 'Display time filter slider') }}
					</NcCheckboxRadioSwitch>
					<NcButton class="maps-navigation__geo-link" @click="emit('register-geo-link')">
						{{ t('maps', 'Register Maps to open geo links') }}
					</NcButton>
				</NcAppSettingsSection>
				<NcAppSettingsSection id="maps-settings-projection" :name="t('maps', 'Map projection')">
					<p class="maps-navigation__hint">
						{{ t('maps', 'Keep in mind that map projections always distort sizes of countries. The standard Mercator projection is particularly biased. Read more at:') }}
						<a href="http://kai.sub.blue/en/africa.html"
							target="_blank"
							rel="noreferrer noopener">
							{{ t('maps', 'The True Size of Africa') }} ↗
						</a>
					</p>
				</NcAppSettingsSection>
			</NcAppSettingsDialog>
		</template>
	</NcAppNavigation>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import {
	NcAppNavigation,
	NcAppNavigationCaption,
	NcAppNavigationItem,
	NcAppSettingsDialog,
	NcAppSettingsSection,
	NcButton,
	NcCheckboxRadioSwitch,
	NcLoadingIcon,
} from '@nextcloud/vue'
import { reactive, ref } from 'vue'
import IconCog from 'vue-material-design-icons/CogOutline.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import optionsController from '../optionsController.js'

withDefaults(defineProps<{
	loading?: boolean
	/** The settings are the viewer's own, so they are hidden on public map pages */
	showSettings?: boolean
}>(), {
	loading: false,
	showSettings: true,
})

const emit = defineEmits<{
	'toggle-trackme': [enabled: boolean]
	'register-geo-link': []
	'toggle-slider': [enabled: boolean]
}>()

const optionValues = reactive(optionsController.optionValues)
const settingsOpen = ref(false)

/**
 * @param checked whether the position should be tracked
 */
function onTrackMeChange(checked: boolean): void {
	optionValues.trackMe = checked ? 'true' : 'false'
	optionsController.saveOptionValues({ trackMe: optionValues.trackMe })
	emit('toggle-trackme', checked)
}

/**
 * @param checked whether the time filter slider should be shown
 */
function onDisplaySliderChange(checked: boolean): void {
	optionValues.displaySlider = checked ? 'true' : 'false'
	optionsController.saveOptionValues({ displaySlider: optionValues.displaySlider })
	emit('toggle-slider', checked)
}
</script>

<style scoped lang="scss">
.maps-navigation__loading {
	display: flex;
	justify-content: center;
	padding: calc(2 * var(--default-grid-baseline));
}

.maps-navigation__footer {
	padding: var(--default-grid-baseline);
	border-top: 1px solid var(--color-border);
}

.maps-navigation__geo-link {
	margin-top: calc(2 * var(--default-grid-baseline));
}

.maps-navigation__hint {
	color: var(--color-text-maxcontrast);
}

:deep(.app-navigation-toggle) {
	background-color: var(--color-background-darker);
	z-index: 100000;
	border-radius: var(--border-radius-pill);
}
</style>
