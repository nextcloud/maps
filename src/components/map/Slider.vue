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

	data() {
		return {
			sliderConnect: null,
			onUpdateCallbackBlock: false,
			onChangeCallbackBlock: false,
			myMin: this.min,
			myMax: this.max,
			myStart: this.min,
			myEnd: this.max,
		}
	},

	watch: {
		min() {
			this.myMin = this.min
			this.myStart = this.min
			this.updateSliderRange()
			this.setSlider()
		},
		max() {
			this.myMax = this.max
			this.myEnd = this.max
			this.updateSliderRange()
			this.setSlider()
		},
	},

	mounted() {
		noUiSlider.create(this.$refs.slider, {
			start: [this.myMin, this.myMax],
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
				min: this.myMin,
				max: this.myMax,
			},
		})
		this.sliderConnect = this.$refs.slider.getElementsByClassName('noUi-connect')[0]
		this.updateSliderRange()
		this.setSlider()
		this.$refs.slider.noUiSlider.on('update', this.onUpdateSlider)
		this.$refs.slider.noUiSlider.on('change', this.onChangeSlider)
	},

	methods: {
		updateSliderRange() {
			const range = this.myMax - this.myMin
			this.$refs.slider.noUiSlider.updateOptions({
				range: {
					min: this.myMin - range / 10,
					max: this.myMax + range / 10,
				},
			})
		},
		setSlider() {
			this.$refs.slider.noUiSlider.set([this.myStart, this.myEnd])
		},
		// slider handle moving
		onUpdateSlider(values, handle, unencoded, tap, positions) {
			if (!this.onUpdateCallbackBlock) {
				this.onUpdateCallbackBlock = true
				/* if (handle === 0) {
					this.myStart = unencoded[0]
				} else {
					this.myEnd = unencoded[1]
				} */

				this.onUpdateCallbackBlock = false
				if (Math.round(unencoded[0]) < Math.round(this.myMin)
					|| Math.round(unencoded[1]) > Math.round(this.myMax)
					|| positions[1] - positions[0] < 10) {
					this.sliderConnect.classList.add('timeRangeSlider-active')
				} else {
					this.sliderConnect.classList.remove('timeRangeSlider-active')
				}
			}
		},
		// slider handle released
		onChangeSlider(values, handle, unencoded, tap, positions) {
			if (!this.onChangeCallbackBlock) {
				this.onChangeCallbackBlock = true

				this.myStart = unencoded[0]
				this.myEnd = unencoded[1]
				this.$emit('range-change', { start: this.myStart, end: this.myEnd })

				const r = this.myMax - this.myMin
				if (unencoded[0] < this.myMin) {
					const deltaMin = this.myMin - unencoded[0]
					this.myMin = this.myMin - 25 * deltaMin * deltaMin / r
					this.updateSliderRange()
				}
				if (unencoded[1] > this.myMax) {
					const deltaMax = -this.myMax + unencoded[1]
					this.myMax = this.myMax + 25 * deltaMax * deltaMax / r
					this.updateSliderRange()
				}
				if (positions[1] - positions[0] < 10) {
					const m = (unencoded[0] + unencoded[1]) / 2
					const d = Math.max((unencoded[1] - unencoded[0]) / 2, 1)
					this.myMin = m - 2.5 * d
					this.myMax = m + 2.5 * d
					this.updateSliderRange()
					this.setSlider()
				}
				this.sliderConnect.classList.remove('timeRangeSlider-active')
				this.onChangeCallbackBlock = false
			}
		},
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

::v-deep .timeRangeSlider-active {
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
