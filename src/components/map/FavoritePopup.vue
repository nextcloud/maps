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
				:value="favoriteCopy.name"
				icon="icon-add"
				type="text"
				:placeholder="t('maps', 'Name')"
				:allow-edits="allowEdits"
				@input="favoriteCopy.name = $event" />

			<PopupFormItem
				v-if="allowCategoryCustomization"
				:value="favoriteCopy.category"
				icon="icon-category-organization"
				type="text"
				:placeholder="t('maps', 'Category')"
				:allow-edits="allowEdits"
				@input="favoriteCopy.category = $event" />

			<PopupFormItem
				:value="favoriteCopy.comment"
				icon="icon-comment"
				:placeholder="t('maps', 'Comment')"
				:allow-edits="allowEdits"
				@input="favoriteCopy.comment = $event" />

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

<script setup>
import { reactive, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import Popup from './Popup.vue'
import PopupFormItem from './PopupFormItem.vue'

const props = defineProps({
	favorite: {
		type: Object,
		required: true,
	},
	isVisible: {
		type: Boolean,
		default: false,
	},
	allowEdits: {
		type: Boolean,
		default: false,
	},
	allowCategoryCustomization: {
		type: Boolean,
		default: true,
	},
})

const emit = defineEmits(['delete-favorite', 'update-favorite'])

const favoriteCopy = reactive({ name: '', category: '', comment: '' })

watch(() => props.favorite, () => {
	if (props.allowEdits) {
		favoriteCopy.name = props.favorite.name
		favoriteCopy.category = props.favorite.category
		favoriteCopy.comment = props.favorite.comment
	}
}, { immediate: true, deep: true })

function handleDeleteClick() {
	emit('delete-favorite', { id: props.favorite.id })
}

function handleFavoriteSubmit() {
	const { id, lat, lng } = props.favorite
	const { name, category, comment } = favoriteCopy
	emit('update-favorite', { id, name, category, comment, lat, lng })
}
</script>
