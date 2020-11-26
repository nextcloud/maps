<template>
	<div class="routing-step">
		<SearchField
			:data="[]" />
		<button class="deleteButton" @click="$emit('delete')">
			X
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
// nothing
</style>
