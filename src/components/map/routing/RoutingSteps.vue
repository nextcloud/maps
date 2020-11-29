<template>
	<div id="routing-steps">
		<RoutingStep v-for="(s, i) in steps"
			:key="i + s.name"
			:step="s"
			:search-data="searchData"
			:placeholder="getPlaceholder(i)"
			:can-delete="canDelete(s, i)"
			@selected="$emit('step-selected', i, $event)"
			@delete="$emit('delete-step', i)" />
		<div class="steps-buttons">
			<button id="add-step"
				v-tooltip="{ content: t('maps', 'Add step') }"
				@click="$emit('add-step')">
				<span class="icon-add" />
			</button>
			<button v-if="canExport"
				id="export-route"
				v-tooltip="{ content: t('maps', 'Export current route') }"
				@click="$emit('export-route')">
				<span class="icon-save" />
			</button>
			<button id="reverse-steps"
				v-tooltip="{ content: t('maps', 'Reverse steps order') }"
				@click="$emit('reverse-steps')">
				<span class="icon-reverse" />
			</button>
		</div>
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
		searchData: {
			type: Array,
			required: true,
		},
		canExport: {
			type: Boolean,
			default: false,
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
		getPlaceholder(i) {
			return i === 0
				? t('maps', 'Start')
				: i === this.steps.length - 1
					? t('maps', 'Destination')
					: t('maps', 'Via {i}', { i })
		},
		canDelete(step, i) {
			// impossible to delete first or last steps if it's empty
			return !((i === 0 || i === this.steps.length - 1) && !step.latLng)
		},
	},
}
</script>

<style lang="scss" scoped>
#routing-steps {
	// position: absolute;
	// z-index: 999999999;
	// margin: 0 0 0 370px;
	width: 100%;
	display: flex;
	flex-direction: column;
	.steps-buttons {
		display: flex;
		flex-direction: row;
		* {
			// flex-grow: 1;
			width: 44px;
			height: 44px;
			margin: 0 auto 0 auto;
			padding: 0;
		}
		.icon-save {
			background-color: var(--color-main-text);
			mask: url('../../../../img/save.svg') no-repeat;
			mask-size: 16px auto;
			mask-position: center;
			-webkit-mask: url('../../../../img/save.svg') no-repeat;
			-webkit-mask-size: 16px auto;
			-webkit-mask-position: center;
		}
		.icon-reverse {
			background-color: var(--color-main-text);
			mask: url('../../../../img/reverse.svg') no-repeat;
			mask-size: 16px auto;
			mask-position: center;
			-webkit-mask: url('../../../../img/reverse.svg') no-repeat;
			-webkit-mask-size: 16px auto;
			-webkit-mask-position: center;
		}
	}
}
</style>
