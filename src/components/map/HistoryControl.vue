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

<script setup>
import { computed, ref, onMounted } from 'vue'
import { useControl } from '@indoorequal/vue-maplibre-gl'
import { t } from '@nextcloud/l10n'

const props = defineProps({
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
})

defineEmits(['cancel', 'redo'])

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

function getActionLabel(action) {
	if (action.type === 'photoMove') return t('maps', 'Move photo')
	if (action.type === 'favoriteAdd') return t('maps', 'Add favorite')
	if (action.type === 'favoriteEdit') return t('maps', 'Edit favorite')
	if (action.type === 'favoriteDelete') return t('maps', 'Delete favorite')
	if (action.type === 'favoriteRenameCategory') return t('maps', 'Rename favorite category')
	if (action.type === 'contactPlace') return t('maps', 'Place contact')
	if (action.type === 'contactDelete') return t('maps', 'Delete contact address')
}

const lastActionLabel = computed(() => getActionLabel(props.lastActions[props.lastActions.length - 1]))
const lastCanceledActionLabel = computed(() => getActionLabel(props.lastCanceledActions[props.lastCanceledActions.length - 1]))
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
