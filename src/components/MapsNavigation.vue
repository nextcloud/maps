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
				:legacy="false"
				:name="t('maps', 'Maps settings')"
				:show-navigation="true"
				@update:open="settingsOpen = $event">
				<NcAppSettingsSection id="maps-settings-general" :name="t('maps', 'General')">
					<NcFormBox>
						<NcFormBoxSwitch :model-value="optionValues.trackMe === 'true'"
							:label="t('maps', 'Track my position')"
							:description="t('maps', 'Regularly save the position of this device to your devices')"
							@update:model-value="onTrackMeChange" />
						<NcFormBoxSwitch :model-value="optionValues.displaySlider === 'true'"
							:label="t('maps', 'Display time filter slider')"
							:description="t('maps', 'Only show what was recorded in a chosen time range')"
							@update:model-value="onDisplaySliderChange" />
						<NcFormBoxButton :label="t('maps', 'Register Maps to open geo links')"
							:description="t('maps', 'Open geo: links from other apps and websites in Maps')"
							@click="emit('register-geo-link')">
							<template #icon>
								<IconOpenInApp :size="20" />
							</template>
						</NcFormBoxButton>
					</NcFormBox>
					<NcRadioGroup v-if="optionValues.displaySlider === 'true'"
						class="maps-navigation__time-filter-mode"
						:label="t('maps', 'Time filter')"
						:description="t('maps', 'Filter with a slider, or pick the start and end dates in a calendar')"
						:model-value="timeFilterMode"
						@update:model-value="onTimeFilterModeChange">
						<NcRadioGroupButton value="slider" :label="t('maps', 'Slider')" />
						<NcRadioGroupButton value="calendar" :label="t('maps', 'Calendar')" />
					</NcRadioGroup>
				</NcAppSettingsSection>
				<NcAppSettingsSection id="maps-settings-projection" :name="t('maps', 'Map projection')">
					<NcFormBox>
						<NcFormBoxButton :label="t('maps', 'The True Size of Africa')"
							:description="t('maps', 'Map projections always distort the sizes of countries, the standard Mercator projection particularly so.')"
							href="http://kai.sub.blue/en/africa.html"
							target="_blank" />
					</NcFormBox>
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
	NcFormBox,
	NcFormBoxButton,
	NcFormBoxSwitch,
	NcLoadingIcon,
	NcRadioGroup,
	NcRadioGroupButton,
} from '@nextcloud/vue'
import { computed, reactive, ref } from 'vue'
import IconCog from 'vue-material-design-icons/CogOutline.vue'
import IconOpenInApp from 'vue-material-design-icons/OpenInApp.vue'
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
	'change-time-filter-mode': [mode: string]
}>()

const optionValues = reactive(optionsController.optionValues)
const settingsOpen = ref(false)
const timeFilterMode = computed(() => optionValues.timeFilterMode === 'calendar' ? 'calendar' : 'slider')

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

/**
 * @param mode 'slider' or 'calendar'
 */
function onTimeFilterModeChange(mode: string): void {
	optionValues.timeFilterMode = mode
	optionsController.saveOptionValues({ timeFilterMode: mode })
	emit('change-time-filter-mode', mode)
}
</script>

<style scoped lang="scss">
.maps-navigation__loading {
	display: flex;
	justify-content: center;
	padding: calc(2 * var(--default-grid-baseline));
}

.maps-navigation__time-filter-mode {
	margin-top: calc(2 * var(--default-grid-baseline));
}

.maps-navigation__footer {
	padding: var(--default-grid-baseline);
	border-top: 1px solid var(--color-border);
}

:deep(.app-navigation-toggle) {
	background-color: var(--color-background-darker);
	z-index: 100000;
	border-radius: var(--border-radius-pill);
}
</style>
