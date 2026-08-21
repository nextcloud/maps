<template>
	<div id="routing-steps">
		<RoutingStep v-for="(s, i) in steps"
			:key="i + s.name"
			:ref="'step' + i"
			:step="s"
			:search-data="searchData"
			:placeholder="getPlaceholder(i)"
			:can-delete="canDelete(s, i)"
			:variant="getVariant(i)"
			@selected="onStepSelected(i, $event)"
			@delete="$emit('delete-step', i)" />
		<div class="steps-buttons">
			<NcButton id="add-step"
				variant="tertiary"
				:aria-label="t('maps', 'Add step')"
				@click="$emit('add-step')">
				<template #icon>
					<Plus :size="20" />
				</template>
			</NcButton>
			<NcButton v-if="planReady"
				variant="tertiary"
				:aria-label="t('maps', 'Export current route')"
				@click="$emit('export-route')">
				<template #icon>
					<ContentSave :size="20" />
				</template>
			</NcButton>
			<NcButton v-if="planReady"
				variant="tertiary"
				:aria-label="t('maps', 'Zoom on current route')"
				@click="$emit('zoom-route')">
				<template #icon>
					<Magnify :size="20" />
				</template>
			</NcButton>
			<NcButton id="reverse-steps"
				variant="tertiary"
				:aria-label="t('maps', 'Reverse steps order')"
				@click="$emit('reverse-steps')">
				<template #icon>
					<SwapVertical :size="20" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import ContentSave from 'vue-material-design-icons/ContentSave.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import SwapVertical from 'vue-material-design-icons/SwapVertical.vue'
import RoutingStep from './RoutingStep.vue'

export default {
	name: 'RoutingSteps',

	components: {
		NcButton,
		Plus,
		ContentSave,
		Magnify,
		SwapVertical,
		RoutingStep,
	},

	props: {
		steps: {
			type: Array,
			required: true,
		},
		searchData: {
			type: Array,
			required: true,
		},
		planReady: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
		}
	},

	watch: {
	},

	created() {
	},

	methods: {
		onStepSelected(i, e) {
			this.$emit('step-selected', i, e)
			const nextStepIndex = i + 1
			if (nextStepIndex < this.steps.length) {
				this.$refs['step' + nextStepIndex][0].focus()
			}
		},
		getPlaceholder(i) {
			return i === 0
				? t('maps', 'Start')
				: i === this.steps.length - 1
					? t('maps', 'Destination')
					: t('maps', 'Via {i}', { i })
		},
		getVariant(i) {
			return i === 0 ? 'start' : i === this.steps.length - 1 ? 'end' : 'via'
		},
		canDelete(step, i) {
			// impossible to delete first or last steps if it's empty
			return !((i === 0 || i === this.steps.length - 1) && !step.latLng)
		},
	},
}
</script>

<style lang="scss" scoped>

#routing-steps {
	width: 100%;
	display: flex;
	flex-direction: column;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	overflow: hidden;

	.steps-buttons {
		display: flex;
		flex-direction: row;
		justify-content: center;
		border-top: 1px solid var(--color-border);
	}
}
</style>
