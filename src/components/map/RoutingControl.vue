<template>
	<div>
		<RoutingSteps
			:steps="steps"
			@add-step="onAddStep"
			@reverse-steps="onReverseSteps" />
		<RoutingMachine
			:map="map"
			:visible="visible" />
	</div>
</template>

<script>
import RoutingSteps from './RoutingSteps'
import RoutingMachine from './RoutingMachine'

export default {
	name: 'RoutingControl',

	components: {
		RoutingSteps,
		RoutingMachine,
	},

	props: {
		map: {
			type: Object,
			required: true,
		},
		visible: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			steps: [null, null],
		}
	},

	watch: {
	},

	created() {
	},

	methods: {
		// ============ custom plan list ============
		onAddStep() {
			this.steps.splice(this.steps.length - 1, 0, null)
		},
		onReverseSteps() {
			this.steps.reverse()
		},
	},
}
</script>

<style lang="scss" scoped>
// nothing
</style>
