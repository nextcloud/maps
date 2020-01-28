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
	<AppNavigation>
		<ul v-if="favorites.length">
			<AppNavigationNew
				v-if="allowFavoriteEdits"
				:text="newFavoriteButtonLabel"
				@click="handleAddFavoriteClick" />
			<AppNavigationItem
				v-for="favorite in favorites"
				:key="favorite.id"
				:title="favorite.name || t('maps', '(No name)')"
				icon="icon-star-dark"
				@click="handleFavoriteClick(favorite.id)" />
			<AppNavigationSpacer />
		</ul>

		<div v-else class="no-favorites">
			{{ t("maps", "No favorites to display") }}
		</div>
	</AppNavigation>
</template>

<script>
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigationNew from '@nextcloud/vue/dist/Components/AppNavigationNew'
import AppNavigationSpacer from '@nextcloud/vue/dist/Components/AppNavigationSpacer'
import { mapMutations, mapState, mapActions } from 'vuex'
import { PUBLIC_FAVORITES_NAMESPACE } from '../store/modules/publicFavorites'
import MapMode from '../data/enum/MapMode'
import { MAP_NAMESPACE } from '../store/modules/map'

export default {
	name: 'PublicFavoriteShareSideBar',

	components: {
		AppNavigation,
		AppNavigationItem,
		AppNavigationNew,
		AppNavigationSpacer,
	},

	computed: {
		...mapState({
			favorites: state => state[PUBLIC_FAVORITES_NAMESPACE].favorites,
			mapMode: state => state[MAP_NAMESPACE].mode,
			shareInfo: state => state[PUBLIC_FAVORITES_NAMESPACE].shareInfo,
		}),

		allowFavoriteEdits() {
			return this.shareInfo ? this.shareInfo.allowEdits : false
		},

		newFavoriteButtonLabel() {
			return t(
				'maps',
				this.mapMode === MapMode.ADDING_FAVORITES
					? 'Cancel adding favorites'
					: 'Add favorites'
			)
		},
	},

	methods: {
		...mapActions({
			selectFavorite: `${PUBLIC_FAVORITES_NAMESPACE}/selectFavorite`,
		}),
		...mapMutations({
			setMapMode: `${MAP_NAMESPACE}/setMode`,
		}),

		handleAddFavoriteClick() {
			if (this.mapMode === MapMode.ADDING_FAVORITES) {
				this.setMapMode(MapMode.DEFAULT)
			} else {
				this.setMapMode(MapMode.ADDING_FAVORITES)
			}
		},

		handleFavoriteClick(id) {
			this.selectFavorite(id)
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
