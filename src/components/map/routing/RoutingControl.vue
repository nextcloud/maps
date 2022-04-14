<template>
	<LControl class="maps-routing-control" position="topleft">
		<div class="routing-header">
			<span class="icon icon-routing" />
			<span class="title">
				{{ t('maps', 'Find directions') }}
			</span>
			<button @click="$emit('close')">
				<span class="icon icon-close" />
			</button>
		</div>
		<RoutingSteps
			:steps="steps"
			:search-data="searchData"
			:plan-ready="planReady"
			@add-step="addRoutePoint"
			@step-selected="setRoutePoint"
			@delete-step="deleteRoutePoint"
			@export-route="onExportRoute"
			@zoom-route="onZoomRoute"
			@reverse-steps="reverseWaypoints" />
		<RoutingMachine
			ref="machine"
			:map="map"
			:visible="visible"
			@plan-changed="onPlanChanged"
			@plan-ready-changed="onPlanReadyChanged"
			@route-selected="onRouteSelected" />
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
		searchData: {
			type: Array,
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
			planReady: false,
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
		onExportRoute() {
			this.$refs.machine.onExportRoute()
		},
		onZoomRoute() {
			this.$refs.machine.onZoomRoute()
		},
		// ============ routing machine events ============
		onPlanChanged(waypoints) {
			this.steps = waypoints
		},
		onPlanReadyChanged(ready) {
			this.planReady = ready
		},
		onRouteSelected() {
			this.onZoomRoute()
		},
	},
}
</script>

<style lang="scss" scoped>
.maps-routing-control {
	z-index: 99999999 !important;
	background-color: var(--color-main-background);
	width: 350px;
	padding: 5px;
	border: 2px solid rgba(0, 0, 0, 0.2);
	border-bottom: 0;

	.routing-header {
		display: flex;
		margin-bottom: 5px;

		.title {
			flex-grow: 1;
			margin: auto 0 auto 5px;
			font-size: 20px;
		}
		> button {
			padding: 0;
			margin: 0;
			width: 34px;
			&:hover span {
				opacity: 1;
			}
		}
		.icon-routing {
			width: 34px;
		}
	}
}
</style>
