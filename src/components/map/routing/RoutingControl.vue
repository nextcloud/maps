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

<script setup>
import { ref, onMounted } from 'vue'
import { t } from '@nextcloud/l10n'
import { useControl } from '@indoorequal/vue-maplibre-gl'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import RoutingSteps from './RoutingSteps.vue'
import RoutingMachine from './RoutingMachine.vue'

const emptyStep = () => ({ latLng: null, name: '' })

const props = defineProps({
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
})

defineEmits(['close', 'track-added'])

const isMobile = useIsMobile()
const el = ref(null)
const machine = ref(null)
const steps = ref([emptyStep(), emptyStep()])
const planReady = ref(false)

onMounted(() => {
	useControl(() => ({
		onAdd() {
			this._container = el.value?.children[0]
			return this._container
		},
		onRemove() {},
	}), { position: 'top-left' })
})

function setRouteFrom(step) { machine.value.setRouteFrom(step) }
function setRouteTo(step) { machine.value.setRouteTo(step) }
function setRoutePoint(i, step) { machine.value.setRoutePoint(i, step) }
function addRoutePoint(step = emptyStep()) { machine.value.addRoutePoint(step) }
function deleteRoutePoint(i) { machine.value.deleteRoutePoint(i) }
function reverseWaypoints() { machine.value.reverseWaypoints() }
function onExportRoute() { machine.value.onExportRoute() }
function onZoomRoute() { machine.value.onZoomRoute() }

function onPlanChanged(waypoints) { steps.value = waypoints }
function onPlanReadyChanged(ready) { planReady.value = ready }
function onRouteSelected() { onZoomRoute() }

defineExpose({ setRouteFrom, setRouteTo, setRoutePoint, addRoutePoint, deleteRoutePoint, reverseWaypoints, onExportRoute, onZoomRoute })
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
