<template>
	<li class="routing-step" :class="`routing-step--${position}`">
		<span class="routing-step__marker" aria-hidden="true">
			<IconMapMarker v-if="position === 'destination'" :size="20" />
			<IconCircleOutline v-else-if="position === 'start'" :size="16" />
			<IconCircleMedium v-else :size="16" />
		</span>
		<SearchField ref="field"
			class="routing-step__field"
			:data="searchData"
			:placeholder="placeholder"
			:selected-option="selectedOption"
			@validate="onValidate"
			@clear="emit('cleared')" />
		<NcButton v-if="canDelete"
			variant="tertiary"
			:title="t('maps', 'Remove stop')"
			:aria-label="t('maps', 'Remove stop')"
			@click="emit('delete')">
			<template #icon>
				<IconClose :size="20" />
			</template>
		</NcButton>
		<span v-else class="routing-step__spacer" />
	</li>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import { computed, ref } from 'vue'
import IconCircleMedium from 'vue-material-design-icons/CircleMedium.vue'
import IconCircleOutline from 'vue-material-design-icons/CircleOutline.vue'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconMapMarker from 'vue-material-design-icons/MapMarker.vue'
import SearchField from '../SearchField.vue'

export interface Step {
	latLng: { lat: number, lng: number } | null
	name: string
}

const props = withDefaults(defineProps<{
	step: Step
	searchData: unknown[]
	placeholder: string
	position: 'start' | 'via' | 'destination'
	canDelete?: boolean
}>(), {
	canDelete: true,
})

const emit = defineEmits<{
	selected: [step: Step]
	cleared: []
	delete: []
}>()

const field = ref<InstanceType<typeof SearchField> | null>(null)

const selectedOption = computed(() => props.step.latLng
	? { type: 'initial', id: props.step.name, label: props.step.name }
	: null)

/**
 * @param option the chosen search result
 */
function onValidate(option: { type: string, label: string, latLng?: { lat: number, lng: number } }): void {
	if (option.type === 'mylocation') {
		navigator.geolocation.getCurrentPosition((position) => {
			emit('selected', {
				latLng: { lat: position.coords.latitude, lng: position.coords.longitude },
				name: option.label,
			})
		})
	} else {
		emit('selected', { latLng: option.latLng ?? null, name: option.label })
	}
}

defineExpose({
	focus: () => field.value?.focus(),
})
</script>

<style lang="scss" scoped>
.routing-step {
	position: relative;
	display: flex;
	align-items: center;
	gap: var(--default-grid-baseline);

	// Dotted line linking the markers of consecutive stops
	&:not(:last-child)::after {
		position: absolute;
		top: calc(50% + 10px);
		// reaches down to the next stop's marker, across the gap between the rows
		bottom: calc(-50% - 2 * var(--default-grid-baseline) + 10px);
		inset-inline-start: calc(var(--default-clickable-area) / 2 - 1px);
		border-inline-start: 2px dotted var(--color-border-maxcontrast);
		content: '';
	}

	&__marker {
		display: flex;
		flex-shrink: 0;
		align-items: center;
		justify-content: center;
		width: var(--default-clickable-area);
		color: var(--color-text-maxcontrast);
	}

	&--destination &__marker {
		color: var(--color-error);
	}

	&__spacer {
		flex-shrink: 0;
		width: var(--default-clickable-area);
	}

	&__field {
		flex-grow: 1;
		min-width: 0;
	}

	// more specific than NcSelect's own .nc-select.v-select.select margin
	&__field.nc-select {
		margin: 0;
	}
}
</style>
