<template>
	<div ref="controlContent" class="maps-history-control leaflet-control">
		<div id="history">
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
import L from 'leaflet'

export default {
	name: 'HistoryControl',

	props: {
		map: {
			type: Object,
			required: true,
		},
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
			default: 'topright',
		},
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

	created() {
		this.control = null;
	},

	mounted() {
		const el = this.$refs.controlContent;
		if (!el) return;

		// Create native Leaflet Control
		const CustomControl = L.Control.extend({
			onAdd: () => el
		});

		this.control = new CustomControl({ position: this.position });
		this.control.addTo(this.map);

		// Prevent clicks/scrolls from falling through to the map
		L.DomEvent.disableClickPropagation(el);
		L.DomEvent.disableScrollPropagation(el);
	},

	beforeDestroy() {
		if (this.control && this.map) {
			this.map.removeControl(this.control);
		}
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