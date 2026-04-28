<template>
	<div id="timeRangeSlider" ref="slider" />
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import noUiSlider from 'nouislider'

const props = defineProps({
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
})

const emit = defineEmits(['range-change'])

const slider = ref(null)
const sliderConnect = ref(null)
const onUpdateCallbackBlock = ref(false)
const onChangeCallbackBlock = ref(false)
const myRangeMin = ref(props.rangeMin)
const myRangeMax = ref(props.rangeMax)
const myStart = ref(props.start)
const myEnd = ref(props.end)

watch(() => props.start, () => updateStartEnd())
watch(() => props.end, () => updateStartEnd())
watch(() => props.rangeMin, () => updateRangeMinMax())
watch(() => props.rangeMax, () => updateRangeMinMax())

onMounted(() => {
	updateRangeMinMax(false)
	noUiSlider.create(slider.value, {
		start: [myStart.value, myEnd.value],
		connect: true,
		behaviour: 'drag',
		tooltips: [{
			to: (x) => new Date(x * 1000).toIsoString(),
		}, {
			to: (x) => new Date(x * 1000).toIsoString(),
		}],
		range: {
			min: myRangeMin.value,
			max: myRangeMax.value,
		},
	})
	sliderConnect.value = slider.value.getElementsByClassName('noUi-connect')[0]
	updateSliderRange()
	setSlider()
	slider.value.noUiSlider.on('update', onUpdateSlider)
	slider.value.noUiSlider.on('change', onChangeSlider)
	slider.value.ondblclick = () => setSliderToMaxInterval()
})

function updateStartEnd(updateRange = true) {
	const start = Math.min(Math.max(props.start, props.rangeMin), props.end - 1, props.rangeMax - 1)
	const end = Math.max(Math.min(props.end, props.rangeMax), myStart.value + 1, props.rangeMin + 1)
	updateRange = checkUpdateSliderRange(start, end) && updateRange
	myEnd.value = end
	myStart.value = start
	if (updateRange) {
		myRangeMin.value = start
		myRangeMax.value = end
		updateSliderRange()
	}
	setSlider(false)
}

function updateRangeMinMax(updateRange = true) {
	const min = props.start <= myRangeMin.value || props.end <= myRangeMin.value + 1
		? Math.min(props.rangeMin, props.rangeMax - 1)
		: myRangeMin.value
	const max = props.end >= myRangeMax.value || props.start >= myRangeMax.value - 1
		? Math.max(props.rangeMax, props.rangeMin + 1)
		: myRangeMax.value
	myRangeMin.value = min
	myRangeMax.value = max
	if (updateRange) {
		updateSliderRange()
		updateStartEnd(updateRange)
	}
}

function checkUpdateSliderRange(min, max) {
	const range = (myRangeMax.value - myRangeMin.value) * 1.2
	return min < max && (min < myRangeMin.value || max > myRangeMax.value || 100 * (max - min) < 10 * range)
}

function updateSliderRange() {
	const range = myRangeMax.value - myRangeMin.value
	slider.value.noUiSlider.updateOptions({
		range: {
			min: myRangeMin.value - range / 10,
			max: myRangeMax.value + range / 10,
		},
	})
}

function setSlider(emitEvent = true) {
	slider.value.noUiSlider.set([myStart.value, myEnd.value])
	if (emitEvent) {
		emit('range-change', { start: myStart.value, end: myEnd.value })
	}
}

function setSliderToMaxInterval() {
	myRangeMin.value = props.rangeMin
	myRangeMax.value = props.rangeMax
	updateSliderRange()
	myStart.value = props.rangeMin
	myEnd.value = props.rangeMax
	setSlider()
}

function onUpdateSlider(values, handle, unencoded, tap, positions) {
	if (!onUpdateCallbackBlock.value) {
		onUpdateCallbackBlock.value = true
		onUpdateCallbackBlock.value = false
		if (Math.round(unencoded[0]) < Math.round(myRangeMin.value)
			|| Math.round(unencoded[1]) > Math.round(myRangeMax.value)
			|| positions[1] - positions[0] < 10) {
			sliderConnect.value.classList.add('timeRangeSlider-active')
		} else {
			sliderConnect.value.classList.remove('timeRangeSlider-active')
		}
	}
}

function onChangeSlider(values, handle, unencoded, tap, positions) {
	if (!onChangeCallbackBlock.value) {
		onChangeCallbackBlock.value = true

		myStart.value = unencoded[0]
		myEnd.value = unencoded[1]
		emit('range-change', { start: myStart.value, end: myEnd.value })

		const r = myRangeMax.value - myRangeMin.value
		if (unencoded[0] < myRangeMin.value) {
			const deltaMin = myRangeMin.value - unencoded[0]
			myRangeMin.value = Math.max(myRangeMin.value - 25 * deltaMin * deltaMin / r, props.rangeMin)
			updateSliderRange()
		}
		if (unencoded[1] > myRangeMax.value) {
			const deltaMax = -myRangeMax.value + unencoded[1]
			myRangeMax.value = Math.min(myRangeMax.value + 25 * deltaMax * deltaMax / r, props.rangeMax)
			updateSliderRange()
		}
		if (positions[1] - positions[0] < 10) {
			const m = (unencoded[0] + unencoded[1]) / 2
			const d = Math.max((unencoded[1] - unencoded[0]) / 2, 1)
			myRangeMin.value = Math.max(m - 2.5 * d, props.rangeMin)
			myRangeMax.value = Math.min(m + 2.5 * d, props.rangeMax)
			updateSliderRange()
			setSlider()
		}
		sliderConnect.value.classList.remove('timeRangeSlider-active')
		onChangeCallbackBlock.value = false
	}
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
