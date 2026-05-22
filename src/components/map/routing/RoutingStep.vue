<template>
	<div class="routing-step">
		<SearchField
			ref="field"
			:data="searchData"
			:placeholder="placeholder"
			:selected-option="selectedOption"
			@validate="onValidate" />
		<button v-if="canDelete"
			class="deleteButton"
			@click="$emit('delete')">
			<span class="icon icon-close" />
		</button>
	</div>
</template>

<script setup>
import { computed, ref } from 'vue'
import SearchField from '../SearchField.vue'

const props = defineProps({
	step: {
		type: Object,
		required: true,
	},
	searchData: {
		type: Array,
		required: true,
	},
	placeholder: {
		type: String,
		required: true,
	},
	canDelete: {
		type: Boolean,
		default: true,
	},
})

const emit = defineEmits(['selected', 'delete'])

const field = ref(null)

const selectedOption = computed(() =>
	props.step && props.step.latLng
		? { type: 'initial', id: props.step.name, label: props.step.name }
		: null,
)

function focus() {
	field.value.focus()
}

function onValidate(option) {
	if (option.type === 'mylocation') {
		navigator.geolocation.getCurrentPosition((position) => {
			emit('selected', {
				latLng: { lat: position.coords.latitude, lng: position.coords.longitude },
				name: option.label,
			})
		})
	} else {
		emit('selected', { latLng: option.latLng, name: option.label })
	}
}

defineExpose({ focus })
</script>

<style lang="scss" scoped>
.routing-step {
	display: flex;
	margin-bottom: 10px;
	height: 34px;

	.multiselect {
		flex-grow: 1;
		background: transparent;
	}
	button {
		margin: 0 0 0 5px;
		padding: 0;
		min-width: 34px;
	}
}
</style>
