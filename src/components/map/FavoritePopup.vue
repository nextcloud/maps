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
	<Popup :name="favorite.name || '(No name)'">
		<form
			v-if="allowEdits"
			class="favorite"
			@submit.prevent="handleFavoriteSubmit">
			<PopupFormItem
				v-model="favoriteCopy.name"
				icon="icon-add"
				type="text"
				:placeholder="t('maps', 'Name')"
				:allow-edits="allowEdits" />

			<PopupFormItem
				v-if="allowCategoryCustomization"
				v-model="favoriteCopy.category"
				icon="icon-category-organization"
				type="text"
				:placeholder="t('maps', 'Category')"
				:allow-edits="allowEdits" />

			<PopupFormItem
				v-model="favoriteCopy.comment"
				icon="icon-comment"
				:placeholder="t('maps', 'Comment')"
				:allow-edits="allowEdits" />

			<div v-if="allowEdits" class="buttons">
				<button class="primary">
					{{ t("maps", "Update") }}
				</button>
				<button class="danger" @click.prevent="handleDeleteClick">
					{{ t("maps", "Delete") }}
				</button>
			</div>
		</form>
		<div class="no-edits">
			<p v-if="favorite.comment">
				{{ favorite.comment }}
			</p>
		</div>
	</Popup>
</template>

<script>
import VueTypes from 'vue-types'
import Popup from './Popup.vue'
import PopupFormItem from './PopupFormItem.vue'
import Types from '../../data/types'

export default {
	name: 'FavoritePopup',

	components: {
		Popup,
		PopupFormItem,
	},

	props: {
		favorite: Types.Favorite.isRequired,
		isVisible: VueTypes.bool.isRequired.def(false),
		allowEdits: VueTypes.bool.isRequired.def(false),
		allowCategoryCustomization: VueTypes.bool.def(true),
	},

	data() {
		return {
			favoriteCopy: {
				name: '',
				category: '',
				comment: '',
			},
		}
	},

	watch: {
		favorite: {
			deep: true,
			handler() {
				this.updateFavoriteCopy()
			},
		},
	},

	mounted() {
		this.updateFavoriteCopy()
	},

	methods: {
		updateFavoriteCopy() {
			if (this.allowEdits) {
				this.favoriteCopy.name = this.favorite.name
				this.favoriteCopy.category = this.favorite.category
				this.favoriteCopy.comment = this.favorite.comment
			}
		},
		handleDeleteClick() {
			const { id } = this.favorite

			this.$emit('delete-favorite', { id })
		},
		handleFavoriteSubmit() {
			const { id, lat, lng } = this.favorite
			const { name, category, comment } = this.favoriteCopy

			this.$emit('update-favorite', {
				id,
				name,
				category,
				comment,
				lat,
				lng,
			})
		},
	},
}
</script>
