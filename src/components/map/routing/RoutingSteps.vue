<template>
	<div id="routing-steps">
		<RoutingStep v-for="(s, i) in steps"
			:key="i + s.name"
			:step="s"
			:search-data="searchData"
			:placeholder="getPlaceholder(i)"
			:can-delete="canDelete(s, i)"
			@selected="$emit('step-selected', i, $event)"
			@delete="$emit('delete-step', i)" />
		<div class="steps-buttons">
			<button id="add-step" @click="$emit('add-step')">
				<span class="icon-add" />
				{{ t('maps', 'Add step') }}
			</button>
			<button id="reverse-steps" @click="$emit('reverse-steps')">
				<span class="icon-reverse" />
				{{ t('maps', 'Reverse steps order') }}
			</button>
		</div>
	</div>
</template>

<script>
import RoutingStep from './RoutingStep'

export default {
	name: 'RoutingSteps',

	components: {
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
		getPlaceholder(i) {
			return i === 0
				? t('maps', 'Start')
				: i === this.steps.length - 1
					? t('maps', 'Destination')
					: t('maps', 'Via {i}', { i })
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
	// position: absolute;
	// z-index: 999999999;
	// margin: 0 0 0 370px;
	width: 350px;
	display: flex;
	flex-direction: column;
	.steps-buttons {
		display: flex;
		flex-direction: row;
		* {
			flex-grow: 1;
		}
	}
}
</style>
