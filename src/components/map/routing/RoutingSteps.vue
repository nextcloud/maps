<template>
	<div class="routing-steps">
		<div class="routing-steps__plan">
			<ol class="routing-steps__list">
				<RoutingStep v-for="(s, i) in steps"
					:key="i + s.name"
					:ref="(el) => setStepRef(i, el)"
					:step="s"
					:search-data="searchData"
					:placeholder="placeholder(i)"
					:position="position(i)"
					:can-delete="canDelete(s, i)"
					@selected="onStepSelected(i, $event)"
					@cleared="emit('step-selected', i, { latLng: null, name: '' })"
					@delete="emit('delete-step', i)" />
			</ol>
			<NcButton variant="tertiary"
				class="routing-steps__reverse"
				:title="t('maps', 'Reverse')"
				:aria-label="t('maps', 'Reverse')"
				@click="emit('reverse-steps')">
				<template #icon>
					<IconSwapVertical :size="20" />
				</template>
			</NcButton>
		</div>
		<NcButton variant="tertiary" @click="emit('add-step')">
			<template #icon>
				<IconPlus :size="20" />
			</template>
			{{ t('maps', 'Add stop') }}
		</NcButton>
	</div>
</template>

<script setup lang="ts">
import type { Step } from './RoutingStep.vue'

import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import IconSwapVertical from 'vue-material-design-icons/SwapVertical.vue'
import RoutingStep from './RoutingStep.vue'

const props = defineProps<{
	steps: Step[]
	searchData: unknown[]
}>()

const emit = defineEmits<{
	'add-step': []
	'step-selected': [index: number, step: Step]
	'delete-step': [index: number]
	'reverse-steps': []
}>()

const stepRefs: Array<{ focus: () => void } | null> = []

/**
 * @param i index of the step
 * @param el the rendered step component
 */
function setStepRef(i: number, el: unknown): void {
	stepRefs[i] = el as { focus: () => void } | null
}

/**
 * @param i index of the step
 */
function position(i: number): 'start' | 'via' | 'destination' {
	return i === 0 ? 'start' : i === props.steps.length - 1 ? 'destination' : 'via'
}

/**
 * @param i index of the step
 */
function placeholder(i: number): string {
	switch (position(i)) {
	case 'start':
		return t('maps', 'Start')
	case 'destination':
		return t('maps', 'Destination')
	default:
		return t('maps', 'Via {i}', { i })
	}
}

/**
 * The first and last stop can only be removed once they are set, so a route always has two stops.
 *
 * @param step the step
 * @param i index of the step
 */
function canDelete(step: Step, i: number): boolean {
	return position(i) === 'via' || step.latLng !== null
}

/**
 * Set a stop and move on to the next one.
 *
 * @param i index of the step
 * @param step the chosen location
 */
function onStepSelected(i: number, step: Step): void {
	emit('step-selected', i, step)
	stepRefs[i + 1]?.focus()
}
</script>

<style lang="scss" scoped>
.routing-steps {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: var(--default-grid-baseline);

	&__plan {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
		width: 100%;
	}

	&__list {
		display: flex;
		flex: 1 1 auto;
		flex-direction: column;
		gap: calc(2 * var(--default-grid-baseline));
		min-width: 0;
	}

	&__reverse {
		flex-shrink: 0;
	}
}
</style>
