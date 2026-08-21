<template>
	<div class="routing-step" :class="`variant-${variant}`">
		<SearchField
			ref="field"
			:data="searchData"
			:placeholder="placeholder"
			:selected-option="selectedOption"
			@validate="onValidate" />
		<NcButton v-if="canDelete"
			variant="tertiary"
			:aria-label="t('maps', 'Delete step')"
			@click="$emit('delete')">
			<template #icon>
				<Close :size="20" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import Close from 'vue-material-design-icons/Close.vue'
import SearchField from '../SearchField.vue'

export default {
	name: 'RoutingStep',

	components: {
		NcButton,
		Close,
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
		variant: {
			type: String,
			default: 'via',
			validator: (v) => ['start', 'via', 'end'].includes(v),
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
	align-items: center;
	height: 44px;
	padding: 0 4px 0 8px;
	border-left: 4px solid var(--color-border-dark);
	border-bottom: 1px solid var(--color-border);

	&:last-child {
		border-bottom: 0;
	}

	&.variant-start {
		border-left-color: var(--color-success);
	}

	&.variant-end {
		border-left-color: var(--color-error);
	}

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
