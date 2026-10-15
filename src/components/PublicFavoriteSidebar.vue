<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppSidebar :name="favorite.name || t('maps', 'Favorite')"
		:subname="favorite.category"
		:compact="true"
		@close="emit('close')">
		<template #header>
			<span class="public-favorite-sidebar__avatar" :style="{ backgroundColor: color }">
				<IconStar :size="32" fill-color="#ffffff" />
			</span>
		</template>
		<div class="public-favorite-sidebar__content">
			<FavoriteEditionForm :favorite="editableFavorite"
				:categories="{}"
				:restricted="true"
				@edit="emit('edit', $event)"
				@delete="emit('delete', $event)" />
		</div>
	</NcAppSidebar>
</template>

<script setup lang="ts">
import type { Favorite } from './map/FavoritesLayer.vue'

import { translate as t } from '@nextcloud/l10n'
import { NcAppSidebar } from '@nextcloud/vue'
import { computed } from 'vue'
import IconStar from 'vue-material-design-icons/Star.vue'
import FavoriteEditionForm from './FavoriteEditionForm.vue'
import { getThemingColorFromCategoryKey } from '../utils/favoritesUtils.js'

const props = defineProps<{
	favorite: Omit<Favorite, 'isUpdateable' | 'isDeletable'>
	/** Whether the share allows changing and deleting its favorites */
	allowEdits: boolean
}>()

const emit = defineEmits<{
	close: []
	edit: [favorite: Favorite]
	delete: [id: number]
}>()

// Public favorites carry no permissions of their own, the share decides
const editableFavorite = computed<Favorite>(() => ({
	...props.favorite,
	isUpdateable: props.allowEdits,
	isDeletable: props.allowEdits,
}))

const color = computed(() => '#' + getThemingColorFromCategoryKey(props.favorite.category).replace('#', ''))
</script>

<style lang="scss" scoped>
.public-favorite-sidebar__avatar {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 100%;
	height: 100%;
	border-radius: 50%;
}

.public-favorite-sidebar__content {
	padding-inline: calc(2 * var(--default-grid-baseline));
}
</style>
