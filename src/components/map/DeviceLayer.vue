<template>
	<LFeatureGroup
		ref="featgroup"
		@ready="onFGReady"
		@click="$emit('click', device)"
		@contextmenu="onFGRightClick">
		<LPopup :options="popupOptions"
			class="popup-device-wrapper">
			<NcActionButton
				icon="icon-category-monitoring"
				@click="$emit('toggle-history', device)">
				{{ device.historyEnabled ? t('maps', 'Hide history') : t('maps', 'Show history') }}
			</NcActionButton>
			<NcActionButton v-if="mapIsUpdatable"
				icon="icon-colorpicker"
				@click="$emit('change-color', device)">
				<!--template #icon>
					<div class="icon-colorpicker" />
				</template-->
				{{ t('maps', 'Change color') }}
			</NcActionButton>
			<NcActionButton
				icon="icon-file"
				@click="$emit('export', device)">
				{{ t('maps', 'Export') }}
			</NcActionButton>
			<NcActionButton v-if="!isPublic()"
				icon="icon-share"
				@click="$emit('add-to-map-device', device)">
				{{ t('maps', 'Link to map') }}
			</NcActionButton>
		</LPopup>
		<LTooltip :options="tooltipOptions">
			<div class="tooltip-device-wrapper"
				:style="'border: 2px solid ' + color">
				<b>{{ t('maps', 'Name') }}:</b>
				<span>{{ device.user_agent }}</span>
			</div>
		</LTooltip>
		<LMarker
			:icon="markerIcon"
			:lat-lng="lastPoint"
			@mouseover="deviceLastPointMouseover" />
		<LFeatureGroup
			@mouseover="deviceLineMouseover">
			<LPolyline v-if="device.historyEnabled && points.length <= 2500"
				color="black"
				:opacity="1"
				:weight="4 * 1.6"
				:lat-lngs="points" />
			<LPolyline v-if="device.historyEnabled && points.length <= 2500"
				:color="color"
				:opacity="1"
				:weight="4"
				:lat-lngs="points" />
		</LFeatureGroup>
	</LFeatureGroup>
</template>

<script>
import L from 'leaflet'
import { LMarker, LTooltip, LPopup, LFeatureGroup, LPolyline } from 'vue2-leaflet'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'

import { isComputer } from '../../utils.js'
import { binSearch, isPublic } from '../../utils/common.js'
import optionsController from '../../optionsController.js'
import moment from '@nextcloud/moment'

const DEVICE_MARKER_VIEW_SIZE = 40

export default {
	name: 'DeviceLayer',
	components: {
		LMarker,
		LTooltip,
		LPopup,
		LFeatureGroup,
		LPolyline,
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
			lineOptions: {
				weight: 4,
				opacity: 1,
			},
			tooltipOptions: {
				sticky: true,
				className: 'leaflet-marker-device-tooltip',
				direction: 'top',
				offset: L.point(0, 0),
			},
			popupOptions: {
				closeButton: false,
				closeOnClick: false,
				className: 'popovermenu open popupMarker devicePopup',
				offset: L.point(-5, -20),
			},
		}
	},

	computed: {
		points() {
			const lastNullIndex = binSearch(this.device.points, (p) => !p.timestamp)
			const firstShownIndex = binSearch(this.device.points, (p) => (p.timestamp || 0) < this.start) + 1
			const lastShownIndex = binSearch(this.device.points, (p) => (p.timestamp || 0) < this.end)
			const filteredDevicePoints = [
				...this.device.points.slice(0, lastNullIndex + 1),
				...this.device.points.slice(firstShownIndex, lastShownIndex + 1),
			]
			return filteredDevicePoints.map((p) => [p.lat, p.lng])
		},
		color() {
			return this.device.color || '#0082c9'
		},
		thumbnailClass() {
			return isComputer(this.device.user_agent)
				? 'desktop'
				: 'phone'
		},
		markerIcon() {
			return L.divIcon(L.extend({
				html: '<div class="thumbnail-wrapper" style="--custom-color: ' + this.color + '; border-color: ' + this.color + ';">'
					+ '<div class="thumbnail ' + this.thumbnailClass + '" style="background-color: ' + this.color + ';"></div></div>​',
				className: 'leaflet-marker-device device-marker',
			}, null, {
				iconSize: [DEVICE_MARKER_VIEW_SIZE, DEVICE_MARKER_VIEW_SIZE],
				iconAnchor: [DEVICE_MARKER_VIEW_SIZE / 2, DEVICE_MARKER_VIEW_SIZE],
			}))
		},
		lastPoint() {
			return this.points.length > 0
				? this.points[this.points.length - 1]
				: null
		},
		mapIsUpdatable() {
			return optionsController.optionValues?.isUpdateable
		},
	},

	created() {
	},

	methods: {
		onFGReady(f) {
			// avoid left click popup
			L.DomEvent.on(f, 'click', (ev) => {
				f.closePopup()
			})
		},
		onFGRightClick(e) {
			this.$refs.featgroup.mapObject.openPopup()
		},
		deviceLineMouseover(e) {
			const overLatLng = e.layer._map.layerPointToLatLng(e.layerPoint)
			const closestPoint = this.device.points.reduce((target, p) => {
				if (
					(!p.timestamp || (p.timestamp >= this.start && p.timestamp <= this.end))
						&& (e.layer._map.distance(overLatLng, [p.lat, p.lng]) < target.minDist)
				) {
					target.minDist = e.layer._map.distance(overLatLng, [p.lat, p.lng])
					target.hoverPoint = p
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
		isPublic() {
			return isPublic()
		},
	},
}
</script>

<style lang="scss" scoped>
.tooltip-device-wrapper {
	padding: 6px;
	border-radius: 3px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
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
