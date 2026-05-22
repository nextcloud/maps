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

<script setup>
import { ref, computed, onMounted } from 'vue'
import { t } from '@nextcloud/l10n'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import * as network from '../../network.js'
import { accented } from '../../utils.js'

const props = defineProps({
	data: {
		type: Array,
		required: true,
	},
	placeholder: {
		type: String,
		default: () => t('maps', 'Search'),
	},
	selectedOption: {
		type: Object,
		default: null,
	},
	loading: {
		type: Boolean,
		default: false,
	},
})

const emit = defineEmits(['validate'])

const select = ref(null)
const mySelectedOption = ref(props.selectedOption)
const searching = ref(false)
const query = ref('')
const currentOsmResults = ref(null)
const currentSearchQueryOption = ref(null)
const currentCoordinateOption = ref(null)

const allData = computed(() =>
	currentOsmResults.value ? [...currentOsmResults.value, ...props.data] : props.data,
)

const options = computed(() => {
	if (currentCoordinateOption.value && currentSearchQueryOption.value) {
		return [currentCoordinateOption.value, currentSearchQueryOption.value, ...allData.value]
	}
	if (currentCoordinateOption.value) {
		return [currentCoordinateOption.value, ...allData.value]
	}
	if (currentSearchQueryOption.value) {
		return [currentSearchQueryOption.value, ...allData.value]
	}
	return allData.value
})

const formattedOptions = computed(() =>
	options.value.map((o) => ({ ...o, multiselectKey: o.id + o.type })),
)

const filteredOptions = computed(() => {
	const queryParts = query.value.split(/\s+/).map((part) => {
		return part.replace(/\S/g, (char) => accented[char.toUpperCase()] || char)
	})
	const regex = new RegExp(queryParts.join('|'), 'i')
	return formattedOptions.value.filter((option) => regex.test(option.label || option.value))
})

onMounted(() => {
	const input = select.value.$el.querySelector('input')
	input.addEventListener('focus', () => {
		if (mySelectedOption.value) {
			input.value = mySelectedOption.value.value || mySelectedOption.value.label
		}
	})
})

function focus() {
	select.value.$el.querySelector('input').focus()
}

function onOptionSelected(option) {
	if (option?.type === 'query') {
		searchOsm(option.value)
	} else if (option) {
		emit('validate', option)
		mySelectedOption.value = option
	}
}

function onSearchChange(q) {
	query.value = q
	updateCoordinateOption(q)
	updateSearchOption(q)
}

function updateCoordinateOption(searchQuery) {
	const coordinateRegExp = /(geo:)?("?lat"?:)?"?(?<lat>-?\d{1,2}.\d+)"?,"?("?lon"?:)?"?(?<lng>-?\d{1,3}.\d+)"?(;.*)?/gmi
	const regResult = coordinateRegExp.exec(searchQuery.replace(/\s*/g, ''))
	if (regResult) {
		const lat = parseFloat(regResult.groups.lat)
		const lng = parseFloat(regResult.groups.lng)
		currentCoordinateOption.value = {
			type: 'coordinate',
			latLng: { lat, lng },
			lat,
			lng,
			id: 'coordinateSearch_' + searchQuery,
			value: searchQuery,
			label: t('maps', 'Point at {coords}', { coords: searchQuery }),
		}
	} else {
		currentCoordinateOption.value = null
	}
}

function updateSearchOption(searchQuery) {
	if (searchQuery !== null && searchQuery !== '') {
		currentSearchQueryOption.value = {
			type: 'query',
			icon: 'icon-search',
			id: 'osmSearch_' + searchQuery,
			value: searchQuery,
			label: t('maps', 'Search for {q}', { q: searchQuery }),
		}
	} else {
		currentSearchQueryOption.value = null
	}
}

function searchOsm(q) {
	mySelectedOption.value = currentSearchQueryOption.value
	currentSearchQueryOption.value = null
	searching.value = true
	network.searchAddress(q, 5).then((response) => {
		currentOsmResults.value = response.data.map((r) => ({
			type: 'result',
			id: r.osm_id,
			icon: 'icon-link',
			value: r.display_name,
			label: r.display_name,
			latLng: { lat: parseFloat(r.lat), lng: parseFloat(r.lon) },
			rawResult: r,
		}))
		select.value.$el.querySelector('input').focus()
	}).catch((error) => {
		console.error(error)
	}).then(() => {
		searching.value = false
	})
}

defineExpose({ focus })
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
