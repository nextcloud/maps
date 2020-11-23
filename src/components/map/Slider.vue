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
</style>
