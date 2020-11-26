<template>
	<div id="routing-steps">
		<RoutingStep v-for="(s, i) in steps"
			:key="i"
			:step="s"
			@delete="$emit('delete-step', i)" />
		<button id="add-step" @click="$emit('add-step')">
			Add step
		</button>
		<button id="reverse-steps" @click="$emit('reverse-steps')">
			Reverse steps order
		</button>
	</div>
</template>

<script>
import RoutingStep from './RoutingStep'

export default {
	name: 'RoutingSteps',

	components: {
		RoutingStep,
	},

	props: {
		steps: {
			type: Array,
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
#routing-steps {
	position: absolute;
	z-index: 999999999;
	margin: 10px 0 0 370px;
}
</style>
