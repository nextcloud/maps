<template>
	<Multiselect
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
		:loading="searching"
		:options="formattedOptions"
		:user-select="false"
		@input="onOptionSelected"
		@update:value="onUpdateValue"
		@change="onChange"
		@search-change="onSearchChange">
		<template #option="{option}">
			<span :class="'option-icon ' + option.icon" />
			<span class="option-label" :title="option.label">
				{{ option.label }}
			</span>
		</template>
		<template #singleLabel="{option}">
			<div class="plop">
				{{ option.value || option.label }}
			</div>
		</template>
		<template #noOptions>
			{{ t('maps', 'No suggestions') }}
		</template>
	</Multiselect>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'

import L from 'leaflet'

import * as network from '../../network'

export default {
	name: 'SearchField',

	components: {
		Multiselect,
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
	},

	data() {
		return {
			mySelectedOption: this.selectedOption,
			searching: false,
			currentOsmResults: null,
			currentSearchQueryOption: null,
		}
	},

	computed: {
		formattedOptions() {
			return this.options.map((o) => {
				return {
					...o,
					multiselectKey: o.id + o.type,
				}
			})
		},
		options() {
			return this.currentSearchQueryOption
				? [this.currentSearchQueryOption, ...this.allData]
				: this.allData
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
			this.updateSearchOption(query)
		},
		updateSearchOption(searchQuery) {
			// add one
			if (searchQuery !== null && searchQuery !== '') {
				this.currentSearchQueryOption = {
					type: 'query',
					icon: 'icon-search',
					id: searchQuery,
					value: searchQuery,
					label: t('cospend', 'Search for {q}', { q: searchQuery }),
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