<template>
	<template>
		<MglGeoJsonSource v-if="device.historyEnabled && filteredPoints.length <= 2500"
			:source-id="'device-outline-' + device.id"
			:data="lineGeoJson">
			<MglLineLayer
				:layer-id="'device-outline-layer-' + device.id"
				:layout="{ 'line-join': 'round', 'line-cap': 'round' }"
				:paint="{ 'line-color': '#000000', 'line-width': 6.4, 'line-opacity': 1 }"
				@click.stop="onLineClick"
				@contextmenu.stop="onLineRightClick"
				@mousemove="deviceLineMouseover" />
		</MglGeoJsonSource>
		<MglGeoJsonSource v-if="device.historyEnabled && filteredPoints.length <= 2500"
			:source-id="'device-line-' + device.id"
			:data="lineGeoJson">
			<MglLineLayer
				:layer-id="'device-line-layer-' + device.id"
				:layout="{ 'line-join': 'round', 'line-cap': 'round' }"
				:paint="{ 'line-color': color, 'line-width': 4, 'line-opacity': 1 }" />
		</MglGeoJsonSource>
		<MglMarker v-if="lastPoint"
			:coordinates="[lastPoint.lng, lastPoint.lat]">
			<template #default>
				<div class="device-last-point-marker"
					:class="thumbnailClass"
					:style="'background-color: ' + color + '; border-color: ' + color"
					@click.stop="onLineClick"
					@contextmenu.stop="onLineRightClick"
					@mouseover="deviceLastPointMouseover" />
				<MglPopup v-if="showPopup" :close-button="false" anchor="bottom" @close="showPopup = false">
					<NcActionButton icon="icon-category-monitoring" @click="$emit('toggle-history', device)">
						{{ device.historyEnabled ? t('maps', 'Hide history') : t('maps', 'Show history') }}
					</NcActionButton>
					<NcActionButton v-if="mapIsUpdatable" icon="icon-colorpicker" @click="$emit('change-color', device)">
						{{ t('maps', 'Change color') }}
					</NcActionButton>
					<NcActionButton icon="icon-file" @click="$emit('export', device)">
						{{ t('maps', 'Export') }}
					</NcActionButton>
					<NcActionButton v-if="!isPublicVal" icon="icon-share" @click="$emit('add-to-map-device', device)">
						{{ t('maps', 'Link to map') }}
					</NcActionButton>
				</MglPopup>
			</template>
		</MglMarker>
	</template>
</template>

<script setup>
import { ref, computed } from 'vue'
import { t } from '@nextcloud/l10n'
import { MglMarker, MglPopup, MglGeoJsonSource, MglLineLayer } from '@indoorequal/vue-maplibre-gl'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import { isComputer } from '../../utils.js'
import { binSearch, isPublic } from '../../utils/common.js'
import optionsController from '../../optionsController.js'
import moment from '@nextcloud/moment'

const props = defineProps({
	device: {
		type: Object,
		required: true,
	},
	start: {
		type: Number,
		default: 0,
	},
	end: {
		type: Number,
		default: () => moment.unix(),
	},
})

const emit = defineEmits(['click', 'toggle-history', 'change-color', 'export', 'add-to-map-device', 'point-hover'])

const showPopup = ref(false)

const isPublicVal = computed(() => isPublic())
const mapIsUpdatable = computed(() => optionsController.optionValues?.isUpdateable)
const color = computed(() => props.device.color || '#0082c9')
const thumbnailClass = computed(() => isComputer(props.device.user_agent) ? 'desktop' : 'phone')

const filteredPoints = computed(() => {
	const lastNullIndex = binSearch(props.device.points, (p) => !p.timestamp)
	const firstShownIndex = binSearch(props.device.points, (p) => (p.timestamp || 0) < props.start) + 1
	const lastShownIndex = binSearch(props.device.points, (p) => (p.timestamp || 0) < props.end)
	return [
		...props.device.points.slice(0, lastNullIndex + 1),
		...props.device.points.slice(firstShownIndex, lastShownIndex + 1),
	]
})

const lineGeoJson = computed(() => ({
	type: 'Feature',
	geometry: {
		type: 'LineString',
		coordinates: filteredPoints.value.map(p => [p.lng, p.lat]),
	},
}))

const lastPoint = computed(() =>
	filteredPoints.value.length > 0
		? filteredPoints.value[filteredPoints.value.length - 1]
		: null,
)

function onLineClick() {
	emit('click', props.device)
}

function onLineRightClick() {
	showPopup.value = !showPopup.value
}

function deviceLineMouseover(e) {
	const lngLat = e.lngLat
	const closestPoint = props.device.points.reduce((target, p) => {
		if (!p.timestamp || (p.timestamp >= props.start && p.timestamp <= props.end)) {
			const dx = lngLat.lng - p.lng
			const dy = lngLat.lat - p.lat
			const dist = Math.sqrt(dx * dx + dy * dy)
			if (dist < target.minDist) {
				target.minDist = dist
				target.hoverPoint = p
			}
		}
		return target
	}, { minDist: 40000000, hoverPoint: null })
	if (closestPoint.hoverPoint) {
		emit('point-hover', {
			...closestPoint.hoverPoint,
			color: color.value,
			user_agent: props.device.user_agent,
		})
	}
}

function deviceLastPointMouseover() {
	emit('point-hover', {
		...props.device.points[props.device.points.length - 1],
		color: color.value,
		user_agent: props.device.user_agent,
	})
}
</script>

<style lang="scss" scoped>
.device-last-point-marker {
	width: 40px;
	height: 40px;
	border-radius: 50%;
	border: 2px solid;
	cursor: pointer;
}

::v-deep .icon-colorpicker {
	opacity: 1;
	mask: url('../../../img/color_picker.svg') no-repeat;
	-webkit-mask: url('../../../img/color_picker.svg') no-repeat;
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
	width: 44px;
	height: 44px;
}
</style>
