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
	<Popup
		:title="
			addingFavorite ? t('maps', 'New Favorite') : t('maps', 'This Place')
		">
		<template v-if="addingFavorite">
			<form class="new-favorite-form" @submit.prevent="handleNewFavoriteSubmit">
				<span>Dumb</span>
				<PopupFormItem
					v-model="newFavorite.name"
					icon="icon-add"
					:placeholder="t('maps', 'Name')" />

				<PopupFormItem
					v-if="allowCategoryCustomization"
					v-model="newFavorite.category"
					icon="icon-category-organization"
					type="text"
					:placeholder="t('maps', 'Category')" />

				<PopupFormItem
					v-model="newFavorite.comment"
					icon="icon-comment"
					:placeholder="t('maps', 'Comment')" />

				<div class="buttons">
					<button class="primary">
						{{ t("maps", "Add") }}
					</button>
					<button @click.prevent="handleCancelAddingFavorite">
						{{ t("maps", "Cancel") }}
					</button>
				</div>
			</form>
		</template>
		<template v-else>
			<SimpleOSMAddress :geocode-object="geocodeObject" />

			<div v-if="allowEdits" class="buttons">
				<button class="primary" @click="handleAddToFavorites">
					{{ t("maps", "Add to Favorites") }}
				</button>
			</div>
		</template>
	</Popup>
</template>

<script>
import { MAP_NAMESPACE } from '../../store/modules/map'
import { mapState } from 'vuex'
import MapMode from '../../data/enum/MapMode'
import { geocode } from '../../utils/mapUtils'
import SimpleOSMAddress from './SimpleOSMAddress'
import VueTypes from 'vue-types'
import Popup from './Popup'
import PopupFormItem from './PopupFormItem'
import Types from '../../data/types'
import { getDefaultCategoryName } from '../../utils/favoritesUtils'

export default {
	name: 'ClickPopup',

	components: {
		Popup,
		PopupFormItem,
		SimpleOSMAddress,
	},

	props: {
		isVisible: VueTypes.bool.isRequired.def(false),
		latLng: Types.LatLng.def(null),
		allowCategoryCustomization: VueTypes.bool.isRequired.def(false),
		allowEdits: VueTypes.bool.isRequired.def(false),
	},

	data() {
		return {
			geocodeObject: null,
			newFavorite: {
				name: 'New Favorite',
				category: this.allowCategoryCustomization
					? getDefaultCategoryName()
					: null,
				comment: '',
			},
			addingFavorite: false,
		}
	},

	computed: {
		...mapState({
			mapMode: state => state[MAP_NAMESPACE].mode,
		}),
	},

	watch: {
		isVisible(val) {
			if (val) {
				this.reset()
			}
		},
		latLng: {
			deep: true,
			handler() {
				this.reset()
				this.updateAddress()
			},
		},
	},

	methods: {
		reset() {
			this.geocodeObject = null
			this.addingFavorite = this.mapMode === MapMode.ADDING_FAVORITES
		},

		handleAddToFavorites() {
			this.addingFavorite = true
		},

		handleCancelAddingFavorite() {
			if (this.mapMode === MapMode.ADDING_FAVORITES) {
				this.$emit('close')
			} else {
				this.addingFavorite = false
			}
		},

		handleNewFavoriteSubmit() {
			const { lat, lng } = this.latLng
			const { name, category, comment } = this.newFavorite

			this.$emit('addFavorite', {
				lat,
				lng,
				name,
				category,
				comment,
			})
		},

		updateAddress() {
			const { lat, lng } = this.latLng

			geocode(`${lat},${lng}`).then(res => {
				this.geocodeObject = res
			})
		},
	},
}
</script>

<style scoped lang="scss">
.new-favorite-form {
    width: 100%;
    margin: 0;
}
</style>
