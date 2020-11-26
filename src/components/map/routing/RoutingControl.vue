<template>
	<div>
		<RoutingSteps
			:steps="steps"
			@add-step="onAddStep"
			@delete-step="onDeleteStep"
			@reverse-steps="onReverseSteps" />
		<RoutingMachine
			ref="machine"
			:map="map"
			:visible="visible"
			@plan-changed="onPlanChanged" />
	</div>
</template>

<script>
import RoutingSteps from './RoutingSteps'
import RoutingMachine from './RoutingMachine'

const emptyStep = () => {
	return {
		latLng: null,
		name: '',
	}
}

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
			steps: [emptyStep(), emptyStep()],
		}
	},

	watch: {
	},

	created() {
	},

	methods: {
		// ============ custom plan list events ============
		onAddStep() {
			this.$refs.machine.addRoutePoint(emptyStep())
		},
		onDeleteStep(i) {
			this.$refs.machine.deleteRoutePoint(i)
		},
		onReverseSteps() {
			this.$refs.machine.reverseWaypoints()
		},
		// ============ routing machine events ============
		onPlanChanged(waypoints) {
			this.steps = waypoints
		},
	},
}
</script>

<style lang="scss" scoped>
// nothing
</style>
