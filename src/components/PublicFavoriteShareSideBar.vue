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

<script>
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcAppNavigationSpacer from '@nextcloud/vue/components/NcAppNavigationSpacer'
import { usePublicFavoritesStore } from '../store/publicFavoritesStore.pinia.js'
import { useMapStore } from '../store/mapStore.pinia.js'
import { computed } from 'vue'
import MapMode from '../data/enum/MapMode.js'

export default {
	name: 'PublicFavoriteShareSideBar',

	components: {
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppNavigationNew,
		NcAppNavigationSpacer,
	},

	setup() {
		const favStore = usePublicFavoritesStore()
		const mapStore = useMapStore()
		return {
			favorites: computed(() => favStore.favorites),
			mapMode: computed(() => mapStore.mode),
			shareInfo: computed(() => favStore.shareInfo),
			selectFavorite: (id) => favStore.selectFavorite(id),
			setMapMode: (mode) => mapStore.setMode(mode),
		}
	},

	computed: {
		allowFavoriteEdits() {
			return this.shareInfo ? this.shareInfo.allowEdits : false
		},

		newFavoriteButtonLabel() {
			return t(
				'maps',
				this.mapMode === MapMode.ADDING_FAVORITES
					? 'Cancel adding favorites'
					: 'Add favorites',
			)
		},
	},

	methods: {
		toggleMapMode() {
			if (this.mapMode === MapMode.ADDING_FAVORITES) {
				this.setMapMode(MapMode.DEFAULT)
			} else {
				this.setMapMode(MapMode.ADDING_FAVORITES)
			}
		},
	},
}
</script>

<style scoped lang="scss">
.no-favorites {
	padding: 2em;
	text-align: center;
	color: var(--color-text-light);
}
</style>
