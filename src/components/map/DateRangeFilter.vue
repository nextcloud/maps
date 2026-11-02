<template>
	<div id="dateRangeFilter">
		<NcDateTimePicker
			class="date-range-filter-picker"
			type="date"
			:model-value="startValue"
			:min="minDate"
			:max="startMaxDate"
			:clearable="false"
			:append-to-body="true"
			:placeholder="t('maps', 'From')"
			:aria-label="t('maps', 'Time filter start date')"
			@update:model-value="onStartChange" />
		<span class="date-range-filter-separator">–</span>
		<NcDateTimePicker
			class="date-range-filter-picker"
			type="date"
			:model-value="endValue"
			:min="endMinDate"
			:max="maxDate"
			:clearable="false"
			:append-to-body="true"
			:placeholder="t('maps', 'To')"
			:aria-label="t('maps', 'Time filter end date')"
			@update:model-value="onEndChange" />
		<NcButton
			:aria-label="t('maps', 'Show all dates')"
			:title="t('maps', 'Show all dates')"
			@click="onReset">
			{{ t('maps', 'Reset') }}
		</NcButton>
	</div>
</template>

<script>
import { NcButton, NcDateTimePicker } from '@nextcloud/vue'
import optionsController from '../../optionsController.js'

export default {
	name: 'DateRangeFilter',

	components: {
		NcButton,
		NcDateTimePicker,
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

	emits: ['range-change'],

	computed: {
		// the filter can be wider than the data (e.g. 0 when "everything" is selected), keep display inside the data range
		displayStart() {
			return Math.min(Math.max(this.start, this.rangeMin), this.rangeMax)
		},
		displayEnd() {
			return Math.max(Math.min(this.end, this.rangeMax), this.displayStart)
		},
		startValue() {
			return new Date(this.displayStart * 1000)
		},
		endValue() {
			return new Date(this.displayEnd * 1000)
		},
		// first selectable day (dates before are grayed out)
		minDate() {
			return startOfDay(this.rangeMin)
		},
		// last selectable day (dates after are grayed out)
		maxDate() {
			return endOfDay(this.rangeMax)
		},
		// start can not be after the selected end day
		startMaxDate() {
			return endOfDay(this.displayEnd)
		},
		// end can not be before the selected start day
		endMinDate() {
			return startOfDay(this.displayStart)
		},
	},

	methods: {
		// whole days, in local time: start day 00:00:00, end day 23:59:59
		onStartChange(date) {
			if (!date) {
				return
			}
			this.changeRange(Math.floor(startOfDay(date.getTime() / 1000).getTime() / 1000), this.end)
		},
		onEndChange(date) {
			if (!date) {
				return
			}
			this.changeRange(this.start, Math.floor(endOfDay(date.getTime() / 1000).getTime() / 1000))
		},
		onReset() {
			this.changeRange(this.rangeMin, this.rangeMax)
		},
		changeRange(start, end) {
			// remember the selection, a bound at the edge of the data is saved as open ('')
			// so data added later (e.g. new device points) is still shown after a page reload
			optionsController.saveOptionValues({
				dateFilterStart: start <= this.rangeMin ? '' : String(start),
				dateFilterEnd: end >= this.rangeMax ? '' : String(end),
			})
			this.$emit('range-change', { start, end })
		},
	},
}

function startOfDay(timestamp) {
	const d = new Date(timestamp * 1000)
	d.setHours(0, 0, 0, 0)
	return d
}

function endOfDay(timestamp) {
	const d = new Date(timestamp * 1000)
	d.setHours(23, 59, 59, 999)
	return d
}
</script>

<style lang="scss" scoped>
#dateRangeFilter {
	position: absolute;
	left: 50%;
	bottom: 30px;
	transform: translateX(-50%);
	z-index: 10000;
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 6px;
	border-radius: var(--border-radius-large);
	background-color: var(--color-main-background);
	box-shadow: 0 0 10px var(--color-box-shadow);
}

.date-range-filter-picker {
	width: 160px;
}

.date-range-filter-separator {
	color: var(--color-text-maxcontrast);
}
</style>
