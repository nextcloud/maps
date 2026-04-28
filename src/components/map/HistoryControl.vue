<template>
	<div ref="el" style="display:none">
		<div id="history" class="maps-history-control maplibregl-ctrl maplibregl-ctrl-group">
			<button
				v-if="lastActions.length"
				v-tooltip="{ content: t('maps', 'Undo {action} (Ctrl+Z)', { action: lastActionLabel }) }"
				@click="$emit('cancel')">
				<span class="icon icon-history" />
			</button>
			<button
				v-if="lastCanceledActions.length"
				v-tooltip="{ content: t('maps', 'Redo {action} (Ctrl+Shift+Z)', { action: lastCanceledActionLabel }) }"
				@click="$emit('redo')">
				<span class="icon icon-redo" />
			</button>
		</div>
	</div>
</template>

<script>
import { useControl } from '@indoorequal/vue-maplibre-gl'
import { ref, onMounted } from 'vue'

export default {
	name: 'HistoryControl',

	props: {
		lastActions: {
			type: Array,
			required: true,
		},
		lastCanceledActions: {
			type: Array,
			required: true,
		},
		position: {
			type: String,
			default: 'top-right',
		},
	},

	setup(props) {
		const el = ref(null)

		onMounted(() => {
			useControl(() => ({
				onAdd() {
					this._container = el.value?.children[0]
					return this._container
				},
				onRemove() {},
			}), { position: props.position })
		})

		return { el }
	},

	computed: {
		lastActionLabel() {
			const action = this.lastActions[this.lastActions.length - 1]
			return this.getActionLabel(action)
		},
		lastCanceledActionLabel() {
			const action = this.lastCanceledActions[this.lastCanceledActions.length - 1]
			return this.getActionLabel(action)
		},
	},

	methods: {
		getActionLabel(action) {
			if (action.type === 'photoMove') {
				return t('maps', 'Move photo')
			} else if (action.type === 'favoriteAdd') {
				return t('maps', 'Add favorite')
			} else if (action.type === 'favoriteEdit') {
				return t('maps', 'Edit favorite')
			} else if (action.type === 'favoriteDelete') {
				return t('maps', 'Delete favorite')
			} else if (action.type === 'favoriteRenameCategory') {
				return t('maps', 'Rename favorite category')
			} else if (action.type === 'contactPlace') {
				return t('maps', 'Place contact')
			} else if (action.type === 'contactDelete') {
				return t('maps', 'Delete contact address')
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.maps-history-control {
	z-index: 99999999 !important;

	#history {
		display: flex;
		> button {
			width: 44px;
			height: 44px;
			margin: 0 5px 0 5px;
			padding: 0;

			&:hover .icon {
				opacity: 1;
			}
		}
	}

	.icon-redo {
		opacity: 0.6;
		background-color: var(--color-main-text);
		mask: url('../../../img/redo.svg') no-repeat;
		mask-size: 16px auto;
		mask-position: center;
		-webkit-mask: url('../../../img/redo.svg') no-repeat;
		-webkit-mask-size: 16px auto;
		-webkit-mask-position: center;
	}
}
</style>
