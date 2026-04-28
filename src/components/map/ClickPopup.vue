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
		:name="
			addingFavorite ? t('maps', 'New Favorite') : t('maps', 'This Place')
		">
		<template v-if="addingFavorite">
			<form class="new-favorite-form" @submit.prevent="handleNewFavoriteSubmit">
				<span>Dumb</span>
				<PopupFormItem
					:value="newFavorite.name"
					icon="icon-add"
					:placeholder="t('maps', 'Name')"
					@input="newFavorite.name = $event" />

				<PopupFormItem
					v-if="allowCategoryCustomization"
					:value="newFavorite.category"
					icon="icon-category-organization"
					type="text"
					:placeholder="t('maps', 'Category')"
					@input="newFavorite.category = $event" />

				<PopupFormItem
					:value="newFavorite.comment"
					icon="icon-comment"
					:placeholder="t('maps', 'Comment')"
					@input="newFavorite.comment = $event" />

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
					{{ t("maps", "Add to favorites") }}
				</button>
			</div>
		</template>
	</Popup>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import { useMapStore } from '../../store/mapStore.pinia.js'
import MapMode from '../../data/enum/MapMode.js'
import { geocode } from '../../utils/mapUtils.js'
import { getDefaultCategoryName } from '../../utils/favoritesUtils.js'
import Popup from './Popup.vue'
import PopupFormItem from './PopupFormItem.vue'
import SimpleOSMAddress from './SimpleOSMAddress.vue'

const props = defineProps({
	isVisible: {
		type: Boolean,
		default: false,
	},
	latLng: {
		type: Object,
		default: () => ({ lat: 0, lng: 0 }),
	},
	allowCategoryCustomization: {
		type: Boolean,
		default: false,
	},
	allowEdits: {
		type: Boolean,
		default: false,
	},
})

const emit = defineEmits(['add-favorite', 'close'])

const mapStore = useMapStore()
const mapMode = computed(() => mapStore.mode)

const geocodeObject = ref(null)
const addingFavorite = ref(false)
const newFavorite = reactive({
	name: 'New Favorite',
	category: props.allowCategoryCustomization ? getDefaultCategoryName() : null,
	comment: '',
})

function reset() {
	geocodeObject.value = null
	addingFavorite.value = mapMode.value === MapMode.ADDING_FAVORITES
}

function updateAddress() {
	const { lat, lng } = props.latLng
	geocode(`${lat},${lng}`).then(res => {
		geocodeObject.value = res
	})
}

watch(() => props.isVisible, (val) => {
	if (val) reset()
})

watch(() => props.latLng, () => {
	reset()
	updateAddress()
}, { deep: true })

function handleAddToFavorites() {
	addingFavorite.value = true
}

function handleCancelAddingFavorite() {
	if (mapMode.value === MapMode.ADDING_FAVORITES) {
		emit('close')
	} else {
		addingFavorite.value = false
	}
}

function handleNewFavoriteSubmit() {
	const { lat, lng } = props.latLng
	const { name, category, comment } = newFavorite
	emit('add-favorite', { lat, lng, name, category, comment })
}
</script>

<style scoped lang="scss">
.new-favorite-form {
	width: 100%;
	margin: 0;
}
</style>
