<template>
	<div class="routing-step">
		<SearchField
			:data="[]"
			:placeholder="placeholder" />
		<button class="deleteButton" @click="$emit('delete')">
			<span class="icon icon-close" />
		</button>
	</div>
</template>

<script>
import SearchField from '../SearchField'

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
		placeholder: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	watch: {
	},

	created() {
	},

	methods: {
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
		margin: 0;
		padding: 0;
		width: 34px;
	}
}
</style>
