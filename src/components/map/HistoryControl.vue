<!--
 - SPDX-FileCopyrightText: 2020 Julien Veyssier <eneiluj@posteo.net>
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	Ported from Leaflet to vue-maplibre-gl: a plain MglCustomControl
	instead of an L.Control, NcButton instead of plain CSS-mask icon
	buttons.
-->
<template>
	<MglCustomControl position="top-right">
		<div class="maps-history-control">
			<NcButton v-if="lastActions.length"
				variant="tertiary"
				:aria-label="t('maps', 'Undo {action} (Ctrl+Z)', { action: lastActionLabel })"
				@click="emit('cancel')">
				<template #icon>
					<Undo :size="20" />
				</template>
			</NcButton>
			<NcButton v-if="lastCanceledActions.length"
				variant="tertiary"
				:aria-label="t('maps', 'Redo {action} (Ctrl+Shift+Z)', { action: lastCanceledActionLabel })"
				@click="emit('redo')">
				<template #icon>
					<Redo :size="20" />
				</template>
			</NcButton>
		</div>
	</MglCustomControl>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import { computed } from 'vue'
import { MglCustomControl } from 'vue-maplibre-gl'
import Redo from 'vue-material-design-icons/Redo.vue'
import Undo from 'vue-material-design-icons/Undo.vue'

export interface HistoryAction {
	type: string
}

const props = defineProps<{
	lastActions: HistoryAction[]
	lastCanceledActions: HistoryAction[]
}>()

const emit = defineEmits<{
	cancel: []
	redo: []
}>()

const ACTION_LABELS: Record<string, () => string> = {
	photoMove: () => t('maps', 'Move photo'),
	favoriteAdd: () => t('maps', 'Add favorite'),
	favoriteEdit: () => t('maps', 'Edit favorite'),
	favoriteDelete: () => t('maps', 'Delete favorite'),
	favoriteRenameCategory: () => t('maps', 'Rename favorite category'),
	contactPlace: () => t('maps', 'Place contact'),
	contactDelete: () => t('maps', 'Delete contact address'),
}

/**
 * @param action the history action to describe
 */
function actionLabel(action?: HistoryAction): string {
	return (action && ACTION_LABELS[action.type]?.()) ?? ''
}

const lastActionLabel = computed(() => actionLabel(props.lastActions[props.lastActions.length - 1]))
const lastCanceledActionLabel = computed(() => actionLabel(props.lastCanceledActions[props.lastCanceledActions.length - 1]))
</script>

<style lang="scss" scoped>
.maps-history-control {
	display: flex;
	background-color: var(--color-main-background);
}
</style>
