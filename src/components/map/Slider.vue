<template>
	<div id="timeRangeSlider" ref="slider" />
</template>

<script>
import noUiSlider from 'nouislider'
export default {
	name: 'Slider',

	components: {
	},

	props: {
		min: {
			type: Number,
			required: true,
		},
		max: {
			type: Number,
			required: true,
		},
	},

	mounted() {
		noUiSlider.create(this.$refs.slider, {
			start: [20, 80],
			connect: true,
			behaviour: 'drag',
			tooltips: [{
				to: (x) => {
					return new Date(x * 1000).toIsoString()
				},
			}, {
				to: (x) => {
					return new Date(x * 1000).toIsoString()
				},
			}],
			range: {
				min: this.min,
				max: this.max,
			},
		})
	},
}
</script>

<style lang="scss" scoped>
@import 'nouislider/distribute/nouislider.css';

#timeRangeSlider {
	position: absolute;
	margin: 10px 0 0 10px;
	left: 20%;
	top: 90%;
	z-index: 10000;
	width: 60%;
	height: 10px;
	background-color: var(--color-main-background) !important;
	box-shadow: none;
}

.timeRangeSlider-active {
	background-color: var(--color-warning, #b10610) !important;
}

::v-deep .noUi-handle::after,
::v-deep .noUi-handle::before {
	height: 8px !important;
}

::v-deep .noUi-handle {
	height: 20px !important;
	border-radius: 10px !important;
	background-color: var(--color-main-background) !important;
	box-shadow: inset 0 0 1px var(--color-background-dark), inset 0 1px 7px var(--color-background-dark), 0 3px 6px -3px var(--color-background-darker) !important;
}

::v-deep .noUi-tooltip {
	display: none;
	z-index: 10001;
	color: var(--color-main-text) !important;
	background-color: var(--color-main-background) !important;
}

::v-deep .noUi-handle:hover .noUi-tooltip,
::v-deep .noUi-active .noUi-tooltip {
	display: block;
	z-index: 10001;
}

::v-deep .noUi-connect {
	background-color: var(--color-primary);
}
</style>
