<template>
	<Multiselect
		ref="select"
		v-model="selectedOption"
		class="affectUserInput"
		label="label"
		track-by="multiselectKey"
		:placeholder="placeholder"
		:loading="searching"
		:options="formattedOptions"
		:user-select="false"
		@input="onOptionSelected"
		@update:value="onUpdateValue"
		@change="onChange" />
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'

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
	},

	data() {
		return {
			selectedOption: null,
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
			// return [{ type: 'result', label: 'PLPLPL' }]
			return this.currentOsmResults || this.data
		},
	},

	watch: {
	},

	mounted() {
		const input = this.$refs.select.$el.querySelector('input')
		this.$refs.select.$el.addEventListener('click', e => {
			console.debug('multiselect CLICK')
			// e.preventDefault()
			// e.stopPropagation()
		})
		input.addEventListener('click', e => {
			console.debug('input CLICK')
		})
		input.addEventListener('focus', e => {
			console.debug('input FOCUS')
			if (this.selectedOption) {
				input.value = this.selectedOption.value
			}
		})
		input.addEventListener('keyup', e => {
			if (e.key === 'Enter') {
				// trick to add member when pressing enter on NC user multiselect
				// this.onMultiselectEnterPressed(e.target)
			} else {
				// add a simple user entry in multiselect when typing
				this.updateSearchOption(e.target.value)
			}
		})
		// remove search option when loosing focus
		input.addEventListener('blur', e => {
			// console.debug('BLUR')
			// console.debug(e)
			this.updateSearchOption(null)
		})
	},

	methods: {
		onOptionSelected() {
			console.debug('option selected in search field')
			console.debug(this.selectedOption)
			if (this.selectedOption?.type === 'query') {
				this.searchOsm(this.selectedOption.value)
			}
			// this.$refs.select.$el.querySelector('input').focus()
		},
		onUpdateValue(e) {
			console.debug('on update value')
			console.debug(e)
		},
		onChange(e) {
			console.debug('on change')
			console.debug(e)
		},
		updateSearchOption(searchQuery) {
			// delete existing search option
			this.currentSearchQueryOption = null
			// without this, it works once every two tries
			// this.selectedOption = null
			console.debug('updateSearchOption ' + searchQuery)
			// add one
			if (searchQuery !== null && searchQuery !== '') {
				this.currentSearchQueryOption = {
					type: 'query',
					id: '',
					value: searchQuery,
					label: t('cospend', 'Search for {q}', { q: searchQuery }),
				}
			}
		},
		searchOsm(query) {
			this.searching = true
			network.searchAddress(query, 5).then((response) => {
				console.debug(response.data)
				this.currentOsmResults = response.data.map((r) => {
					return {
						type: 'result',
						value: r.display_name,
						label: r.display_name,
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
// nothing
</style>
