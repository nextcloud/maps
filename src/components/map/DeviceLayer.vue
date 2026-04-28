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

<script>
import {
	MglMarker,
	MglPopup,
	MglGeoJsonSource,
	MglLineLayer,
} from '@indoorequal/vue-maplibre-gl'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'

import { isComputer } from '../../utils.js'
import { binSearch, isPublic } from '../../utils/common.js'
import optionsController from '../../optionsController.js'
import moment from '@nextcloud/moment'

export default {
	name: 'DeviceLayer',
	components: {
		MglMarker,
		MglPopup,
		MglGeoJsonSource,
		MglLineLayer,
		NcActionButton,
	},

	props: {
		device: {
			type: Object,
			required: true,
		},
		start: {
			type: Number,
			required: false,
			default: 0,
		},
		end: {
			type: Number,
			required: false,
			default: moment.unix(),
		},
	},

	data() {
		return {
			optionValues: optionsController.optionValues,
			showPopup: false,
		}
	},

	computed: {
		isPublicVal() {
			return isPublic()
		},
		filteredPoints() {
			const lastNullIndex = binSearch(this.device.points, (p) => !p.timestamp)
			const firstShownIndex = binSearch(this.device.points, (p) => (p.timestamp || 0) < this.start) + 1
			const lastShownIndex = binSearch(this.device.points, (p) => (p.timestamp || 0) < this.end)
			return [
				...this.device.points.slice(0, lastNullIndex + 1),
				...this.device.points.slice(firstShownIndex, lastShownIndex + 1),
			]
		},
		lineGeoJson() {
			return {
				type: 'Feature',
				geometry: {
					type: 'LineString',
					coordinates: this.filteredPoints.map(p => [p.lng, p.lat]),
				},
			}
		},
		color() {
			return this.device.color || '#0082c9'
		},
		thumbnailClass() {
			return isComputer(this.device.user_agent)
				? 'desktop'
				: 'phone'
		},
		lastPoint() {
			return this.filteredPoints.length > 0
				? this.filteredPoints[this.filteredPoints.length - 1]
				: null
		},
		mapIsUpdatable() {
			return optionsController.optionValues?.isUpdateable
		},
	},

	methods: {
		onLineClick() {
			this.$emit('click', this.device)
		},
		onLineRightClick() {
			this.showPopup = !this.showPopup
		},
		deviceLineMouseover(e) {
			const lngLat = e.lngLat
			const closestPoint = this.device.points.reduce((target, p) => {
				if (
					(!p.timestamp || (p.timestamp >= this.start && p.timestamp <= this.end))
				) {
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
				const hoverPoint = {
					...closestPoint.hoverPoint,
					color: this.color,
					user_agent: this.device.user_agent,
				}
				this.$emit('point-hover', hoverPoint)
			}
		},
		deviceLastPointMouseover() {
			const hoverPoint = {
				...this.device.points[this.device.points.length - 1],
				color: this.color,
				user_agent: this.device.user_agent,
			}
			this.$emit('point-hover', hoverPoint)
		},
	},
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
