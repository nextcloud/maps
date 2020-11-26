<template>
	<div id="routing-steps">
		<p v-for="(s, i) in steps" :key="i">
			superplop
		</p>
		<button id="add-step" @click="onAddStep">
			Add step
		</button>
		<button id="reverse-steps" @click="onReverseSteps">
			Reverse steps order
		</button>
	</div>
</template>

<script>
export default {
	name: 'RoutingSteps',

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
		onAddStep() {
			this.$emit('add-step')
		},
		onReverseSteps() {
			this.$emit('reverse-steps')
		},
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
