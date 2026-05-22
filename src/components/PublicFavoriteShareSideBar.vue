<!--
  - @copyright Copyright (c) 2019 Paul Schwörer <hello@paulschwoerer.de>
  -
  - @author Paul Schwörer <hello@paulschwoerer.de>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<NcAppNavigation>
		<ul v-if="favorites.length">
			<NcAppNavigationNew
				v-if="allowFavoriteEdits"
				:text="newFavoriteButtonLabel"
				@click="toggleMapMode" />
			<NcAppNavigationItem
				v-for="favorite in favorites"
				:key="favorite.id"
				:name="favorite.name || t('maps', '(No name)')"
				icon="icon-star-dark"
				@click="selectFavorite(favorite.id)" />
			<NcAppNavigationSpacer />
		</ul>

		<div v-else class="no-favorites">
			{{ t("maps", "No favorites to display") }}
		</div>
	</NcAppNavigation>
</template>

<script setup>
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcAppNavigationSpacer from '@nextcloud/vue/components/NcAppNavigationSpacer'
import { usePublicFavoritesStore } from '../store/publicFavoritesStore.pinia.js'
import { useMapStore } from '../store/mapStore.pinia.js'
import MapMode from '../data/enum/MapMode.js'

const favStore = usePublicFavoritesStore()
const mapStore = useMapStore()

const favorites = computed(() => favStore.favorites)
const mapMode = computed(() => mapStore.mode)
const shareInfo = computed(() => favStore.shareInfo)

const allowFavoriteEdits = computed(() => shareInfo.value ? shareInfo.value.allowEdits : false)
const newFavoriteButtonLabel = computed(() =>
	t('maps', mapMode.value === MapMode.ADDING_FAVORITES ? 'Cancel adding favorites' : 'Add favorites'),
)

function selectFavorite(id) {
	favStore.selectFavorite(id)
}

function toggleMapMode() {
	mapStore.setMode(mapMode.value === MapMode.ADDING_FAVORITES ? MapMode.DEFAULT : MapMode.ADDING_FAVORITES)
}
</script>

<style scoped lang="scss">
.no-favorites {
	padding: 2em;
	text-align: center;
	color: var(--color-text-light);
}
</style>
