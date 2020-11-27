<template>
	<LControl class="maps-routing-control" position="topleft">
		<div class="routing-header">
			<span class="icon icon-play-next" />
			<span class="title">
				{{ t('maps', 'Find directions') }}
			</span>
			<button @click="$emit('close')">
				<span class="icon icon-close" />
			</button>
		</div>
		<RoutingSteps
			:steps="steps"
			@add-step="addRoutePoint"
			@step-selected="setRoutePoint"
			@delete-step="deleteRoutePoint"
			@reverse-steps="reverseWaypoints" />
		<RoutingMachine
			ref="machine"
			:map="map"
			:visible="visible"
			@plan-changed="onPlanChanged" />
	</LControl>
</template>

<script>
import { LControl } from 'vue2-leaflet'

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
		LControl,
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
		setRouteFrom(step) {
			this.$refs.machine.setRouteFrom(step)
		},
		setRouteTo(step) {
			this.$refs.machine.setRouteTo(step)
		},
		setRoutePoint(i, step) {
			this.$refs.machine.setRoutePoint(i, step)
		},
		addRoutePoint(step = emptyStep()) {
			this.$refs.machine.addRoutePoint(step)
		},
		deleteRoutePoint(i) {
			this.$refs.machine.deleteRoutePoint(i)
		},
		reverseWaypoints() {
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
.maps-routing-control {
	z-index: 99999999 !important;

	.routing-header {
		display: flex;
		margin-bottom: 5px;

		.title {
			flex-grow: 1;
			margin: auto 0 auto 5px;
			font-size: 20px;
		}
		button {
			padding: 0;
			margin: 0;
			width: 34px;
		}
	}
}
</style>
