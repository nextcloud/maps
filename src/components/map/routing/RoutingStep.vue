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

<script>
import SearchField from '../SearchField.vue'

export default {
	name: 'RoutingStep',

	components: {
		SearchField,
	},

	props: {
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
	},

	data() {
		return {
		}
	},

	computed: {
		selectedOption() {
			return this.step && this.step.latLng
				? {
					type: 'initial',
					id: this.step.name,
					label: this.step.name,
				}
				: null
		},
	},

	watch: {
	},

	created() {
	},

	methods: {
		focus() {
			this.$refs.field.focus()
		},
		onValidate(option) {
			if (option.type === 'mylocation') {
				navigator.geolocation.getCurrentPosition((position) => {
					const lat = position.coords.latitude
					const lng = position.coords.longitude
					const step = {
						latLng: { lat, lng },
						name: option.label,
					}
					this.$emit('selected', step)
				})
			} else {
				const step = {
					latLng: option.latLng,
					name: option.label,
				}
				this.$emit('selected', step)
			}
		},
	},
}
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
