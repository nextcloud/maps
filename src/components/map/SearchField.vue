<template>
	<NcSelect
		ref="select"
		class="search-select"
		label="label"
		track-by="multiselectKey"
		:value="mySelectedOption"
		:auto-limit="false"
		:limit="8"
		:options-limit="8"
		:max-height="8 * 45"
		:close-on-select="false"
		:clear-on-select="false"
		:preserve-search="true"
		:placeholder="placeholder"
		:loading="searching || loading"
		:options="filteredOptions"
		:user-select="false"
		:internal-search="false"
		@input="onOptionSelected"
		@update:value="onUpdateValue"
		@change="onChange"
		@search="onSearchChange">
		<template #option="option">
			<span :class="'option-icon ' + option.icon" />
			<span class="option-label" :title="option.label">
				{{ option.label }}
			</span>
		</template>
		<template #singleLabel="option">
			<div class="single-label">
				{{ option.value || option.label }}
			</div>
		</template>
		<template #noOptions>
			{{ t('maps', 'No suggestions') }}
		</template>
	</NcSelect>
</template>

<script>
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

import L from 'leaflet'

import * as network from '../../network.js'
import { accented } from '../../utils.js'

export default {
	name: 'SearchField',

	components: {
		NcSelect,
	},

	props: {
		data: {
			type: Array,
			required: true,
		},
		placeholder: {
			type: String,
			default: t('maps', 'Search'),
		},
		selectedOption: {
			type: Object,
			default: null,
		},
		loading: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			mySelectedOption: this.selectedOption,
			searching: false,
			query: '',
			currentOsmResults: null,
			currentSearchQueryOption: null,
			currentCoordinateOption: null,
		}
	},

	computed: {
		// apply custom filter based on query (because internal search is too restrictive)
		filteredOptions() {
			const queryParts = this.query.split(/\s+/).map((part) => {
				return part.replace(/\S/g, (char) => { return accented[char.toUpperCase()] || char })
			})
			const regex = new RegExp(queryParts.join('|'), 'i')
			return this.formattedOptions.filter((option) => {
				return regex.test(option.label || option.value)
			})
		},
		formattedOptions() {
			return this.options.map((o) => {
				return {
					...o,
					multiselectKey: o.id + o.type,
				}
			})
		},
		options() {
			if (this.currentCoordinateOption && this.currentSearchQueryOption) {
				return [this.currentCoordinateOption, this.currentSearchQueryOption, ...this.allData]
			}
			if (this.currentCoordinateOption) {
				return [this.currentCoordinateOption, ...this.allData]
			}
			if (this.currentSearchQueryOption) {
				return [this.currentSearchQueryOption, ...this.allData]
			}
			return this.allData
		},
		allData() {
			return this.currentOsmResults
				? [...this.currentOsmResults, ...this.data]
				: this.data
		},
	},

	watch: {
	},

	mounted() {
		const input = this.$refs.select.$el.querySelector('input')
		input.addEventListener('focus', e => {
			if (this.mySelectedOption) {
				input.value = this.mySelectedOption.value || this.mySelectedOption.label
			}
		})
	},

	methods: {
		focus() {
			const input = this.$refs.select.$el.querySelector('input')
			input.focus()
			// this does not work...
			/*
			if (this.mySelectedOption) {
				input.value = this.mySelectedOption.value || this.mySelectedOption.label
			}
			*/
		},
		onOptionSelected(option, id) {
			if (option?.type === 'query') {
				this.searchOsm(option.value)
			} else {
				if (option) {
					this.$emit('validate', option)
					this.mySelectedOption = option
				}
			}
		},
		onUpdateValue(e) {
		},
		onChange(e) {
		},
		onSearchChange(query) {
			this.query = query
			this.updateCoordinateOption(query)
			this.updateSearchOption(query)
		},
		updateCoordinateOption(searchQuery) {
			const coordinateRegExp = /(geo:)?("?lat"?:)?"?(?<lat>-?\d{1,2}.\d+)"?,"?("?lon"?:)?"?(?<lng>-?\d{1,3}.\d+)"?(;.*)?/gmi
			const regResult = coordinateRegExp.exec(searchQuery.replace(/\s*/g, ''))
			if (regResult) {
				const lat = parseFloat(regResult.groups.lat)
				const lng = parseFloat(regResult.groups.lng)
				this.currentCoordinateOption = {
					type: 'coordinate',
					latLng: L.latLng([
						lat,
						lng,
					]),
					lat,
					lng,
					id: 'coordinateSearch_' + searchQuery,
					value: searchQuery,
					label: t('maps', 'Point at {coords}', { coords: searchQuery }),
				}
			} else {
				this.currentCoordinateOption = null
			}
		},
		updateSearchOption(searchQuery) {
			// add one
			if (searchQuery !== null && searchQuery !== '') {
				this.currentSearchQueryOption = {
					type: 'query',
					icon: 'icon-search',
					id: 'osmSearch_' + searchQuery,
					value: searchQuery,
					label: t('maps', 'Search for {q}', { q: searchQuery }),
				}
			} else {
				// remove search option when query is empty
				this.currentSearchQueryOption = null
			}
		},
		searchOsm(query) {
			this.mySelectedOption = this.currentSearchQueryOption
			this.currentSearchQueryOption = null
			this.searching = true
			network.searchAddress(query, 5).then((response) => {
				this.currentOsmResults = response.data.map((r) => {
					return {
						type: 'result',
						id: r.osm_id,
						icon: 'icon-link',
						value: r.display_name,
						label: r.display_name,
						latLng: L.latLng(r.lat, r.lon),
						rawResult: r,
					}
				})
				this.$refs.select.$el.querySelector('input').focus()
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				this.searching = false
			})
		},
	},
}
</script>

<style lang="scss" scoped>
::v-deep .multiselect__option {
	height: 44px !important;
}

.single-label {
	height: 18px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.search-select {
	.option-icon {
		margin-right: 10px;
	}

	.option-label {
		overflow: hidden;
		text-overflow: ellipsis;
	}
}
</style>
