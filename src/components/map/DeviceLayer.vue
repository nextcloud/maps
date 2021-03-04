<template>
	<LFeatureGroup
		ref="featgroup"
		@ready="onFGReady"
		@click="$emit('click', device)"
		@contextmenu="onFGRightClick">
		<LPopup :options="popupOptions"
			class="popup-device-wrapper">
			<ActionButton
				icon="icon-category-monitoring"
				@click="$emit('toggle-history', device)">
				{{ t('maps', 'Toggle history') }}
			</ActionButton>
			<ActionButton
				icon="icon-colorpicker"
				@click="$emit('change-color', device)">
				<!--template #icon>
					<div class="icon-colorpicker" />
				</template-->
				{{ t('maps', 'Change color') }}
			</ActionButton>
			<ActionButton
				icon="icon-search"
				@click="$emit('zoom', device)">
				{{ t('maps', 'Zoom on area') }}
			</ActionButton>
			<ActionButton
				icon="icon-file"
				@click="$emit('export', device)">
				{{ t('maps', 'Export') }}
			</ActionButton>
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
			:lat-lng="firstPoint" />
		<LPolyline v-if="device.historyEnabled"
			color="black"
			:opacity="1"
			:weight="4 * 1.6"
			:lat-lngs="points" />
		<LPolyline v-if="device.historyEnabled"
			:color="color"
			:opacity="1"
			:weight="4"
			:lat-lngs="points" />
	</LFeatureGroup>
</template>

<script>
import L from 'leaflet'
import { LMarker, LTooltip, LPopup, LFeatureGroup, LPolyline } from 'vue2-leaflet'

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import { generateUrl } from '@nextcloud/router'

import { isComputer } from '../../utils'
import optionsController from '../../optionsController'

const DEVICE_MARKER_VIEW_SIZE = 40

export default {
	name: 'DeviceLayer',
	components: {
		LMarker,
		LTooltip,
		LPopup,
		LFeatureGroup,
		LPolyline,
		ActionButton,
	},

	props: {
		device: {
			type: Object,
			required: true,
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
			return this.device.points.map(p => [p.lat, p.lng])
		},
		color() {
			return this.device.color || '#0082c9'
		},
		iconUrl() {
			const color = this.device.color || '#0082c9'
			return isComputer(this.device.user_agent)
				? generateUrl('/svg/core/clients/desktop?color=' + color.replace('#', ''))
				: generateUrl('/svg/core/clients/phone?color=' + color.replace('#', ''))
		},
		markerIcon() {
			return L.divIcon(L.extend({
				html: '<div class="thumbnail-wrapper" style="--custom-color: ' + this.color + '; border-color: ' + this.color + ';">'
					+ '<div class="thumbnail" style="background-image: url(\'' + this.iconUrl + '\');"></div></div>​',
				className: 'leaflet-marker-device device-marker',
			}, null, {
				iconSize: [DEVICE_MARKER_VIEW_SIZE, DEVICE_MARKER_VIEW_SIZE],
				iconAnchor: [DEVICE_MARKER_VIEW_SIZE / 2, DEVICE_MARKER_VIEW_SIZE],
			}))
		},
		firstPoint() {
			return this.points.length > 0
				? this.points[0]
				: null
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
