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
		start: {
			type: Number,
			required: true,
		},
		end: {
			type: Number,
			required: true,
		},
		rangeMin: {
			type: Number,
			required: true,
		},
		rangeMax: {
			type: Number,
			required: true,
		},
	},

	data() {
		return {
			sliderConnect: null,
			onUpdateCallbackBlock: false,
			onChangeCallbackBlock: false,
			myRangeMin: this.rangeMin,
			myRangeMax: this.rangeMax,
			myStart: this.start,
			myEnd: this.end,
		}
	},

	watch: {
		start() {
			this.updateStartEnd()
		},
		end() {
			this.updateStartEnd()
		},
		rangeMin() {
			this.updateRangeMinMax()
		},
		rangeMax() {
			this.updateRangeMinMax()
		},
	},
	mounted() {
		this.updateRangeMinMax(false)
		noUiSlider.create(this.$refs.slider, {
			start: [this.myStart, this.myEnd],
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
				min: this.myRangeMin,
				max: this.myRangeMax,
			},
		})
		this.sliderConnect = this.$refs.slider.getElementsByClassName('noUi-connect')[0]
		this.updateSliderRange()
		this.setSlider()
		this.$refs.slider.noUiSlider.on('update', this.onUpdateSlider)
		this.$refs.slider.noUiSlider.on('change', this.onChangeSlider)
		this.$refs.slider.ondblclick = () => {
			this.setSliderToMaxInterval()
		}
	},

	methods: {
		updateStartEnd(updateRange = true) {
			const start = Math.min(Math.max(this.start, this.rangeMin), this.end - 1, this.rangeMax - 1)
			const end = Math.max(Math.min(this.end, this.rangeMax), this.myStart + 1, this.rangeMin + 1)
			updateRange = this.checkUpdateSliderRange(start, end) && updateRange
			this.myEnd = end
			this.myStart = start
			if (updateRange) {
				this.myRangeMin = start
				this.myRangeMax = end
				this.updateSliderRange()
			}
			this.setSlider(false)
		},
		updateRangeMinMax(updateRange = true) {
			const min = this.start <= this.myRangeMin || this.end <= this.myRangeMin + 1
				? Math.min(this.rangeMin, this.rangeMax - 1)
				: this.myRangeMin
			const max = this.end >= this.myRangeMax || this.start >= this.myRangeMax - 1
				? Math.max(this.rangeMax, this.rangeMin + 1)
				: this.myRangeMax
			this.myRangeMin = min
			this.myRangeMax = max
			if (updateRange) {
				this.updateSliderRange()
				this.updateStartEnd(updateRange)
			}
		},
		checkUpdateSliderRange(min, max) {
			const range = (this.myRangeMax - this.myRangeMin) * 1.2
			return min < max && (min < this.myRangeMin || max > this.myRangeMax || 100 * (max - min) < 10 * range)
		},
		updateSliderRange() {
			const range = this.myRangeMax - this.myRangeMin
			this.$refs.slider.noUiSlider.updateOptions({
				range: {
					min: this.myRangeMin - range / 10,
					max: this.myRangeMax + range / 10,
				},
			})
		},
		setSlider(emit = true) {
			this.$refs.slider.noUiSlider.set([this.myStart, this.myEnd])
			if (emit) {
				this.$emit('range-change', { start: this.myStart, end: this.myEnd })
			}
		},
		setSliderToMaxInterval() {
			this.myRangeMin = this.rangeMin
			this.myRangeMax = this.rangeMax
			this.updateSliderRange()
			this.myStart = this.rangeMin
			this.myEnd = this.rangeMax
			this.setSlider()
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
				if (Math.round(unencoded[0]) < Math.round(this.myRangeMin)
					|| Math.round(unencoded[1]) > Math.round(this.myRangeMax)
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

				const r = this.myRangeMax - this.myRangeMin
				if (unencoded[0] < this.myRangeMin) {
					const deltaMin = this.myRangeMin - unencoded[0]
					this.myRangeMin = Math.max(this.myRangeMin - 25 * deltaMin * deltaMin / r, this.rangeMin)
					this.updateSliderRange()
				}
				if (unencoded[1] > this.myRangeMax) {
					const deltaMax = -this.myRangeMax + unencoded[1]
					this.myRangeMax = Math.min(this.myRangeMax + 25 * deltaMax * deltaMax / r, this.rangeMax)
					this.updateSliderRange()
				}
				if (positions[1] - positions[0] < 10) {
					const m = (unencoded[0] + unencoded[1]) / 2
					const d = Math.max((unencoded[1] - unencoded[0]) / 2, 1)
					this.myRangeMin = Math.max(m - 2.5 * d, this.rangeMin)
					this.myRangeMax = Math.min(m + 2.5 * d, this.rangeMax)
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
@import 'nouislider/dist/nouislider.css';

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
	background-color: var(--color-primary-element);
}
</style>
