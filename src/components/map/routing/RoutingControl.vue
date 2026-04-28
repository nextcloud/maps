<template>
	<div ref="el" style="display:none">
		<div class="maps-routing-control maplibregl-ctrl maplibregl-ctrl-group" :class="{'mobile': isMobile, 'desktop':!isMobile}">
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
				@route-selected="onRouteSelected"
				@track-added="$emit('track-added', $event)" />
		</div>
	</div>
</template>

<script>
import { useControl } from '@indoorequal/vue-maplibre-gl'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { computed, ref, onMounted } from 'vue'

import RoutingSteps from './RoutingSteps.vue'
import RoutingMachine from './RoutingMachine.vue'

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

	setup() {
		const isMobile = useIsMobile()
		const el = ref(null)

		onMounted(() => {
			useControl(() => ({
				onAdd() {
					this._container = el.value?.children[0]
					return this._container
				},
				onRemove() {},
			}), { position: 'top-left' })
		})

		return { isMobile: computed(() => isMobile.value), el }
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
.desktop {
	width: 350px;
	margin-left: 56px !important;
	background-color: var(--color-main-background);
	padding: 5px;
	border: 2px solid var(--color-border-dark);
	border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
	border-bottom: 0;
}
.mobile {
	width: 100vw;
	background: var(--color-main-background);
	padding-left: 56px;
	padding-right: 56px;
	padding-top: 10px;
	padding-bottom: 7px;
	margin-top: 0px !important;
}

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
