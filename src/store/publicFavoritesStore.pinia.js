import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import {
	publicApiRequest,
	getPublicShareCategory,
} from '../utils/common.js'
import { getCategoryKey } from '../utils/favoritesUtils.js'
import { showError } from '@nextcloud/dialogs'

export const usePublicFavoritesStore = defineStore('publicFavorites', () => {
	const favorites = ref([])
	const selectedFavoriteId = ref(null)
	const shareInfo = ref(null)

	const mappedByCategory = computed(() => {
		if (favorites.value.length === 0) {
			return {}
		}
		return {
			[getCategoryKey(favorites.value[0].category)]: favorites.value,
		}
	})

	function selectFavorite(favoriteId) {
		selectedFavoriteId.value = favoriteId
	}

	function getFavorites() {
		publicApiRequest('favorites', 'GET')
			.then(response => {
				const data = response.data
				shareInfo.value = data.share
				favorites.value = data.favorites
			})
			.catch(() => {
				showError(t('maps', 'Failed to get favorites'))
			})
	}

	function addFavorite({ lat, lng, name, comment }) {
		return publicApiRequest('favorites', 'POST', {
			lat,
			lng,
			name,
			category: getPublicShareCategory(),
			comment,
			extensions: '',
		})
			.then(response => {
				favorites.value = [...favorites.value, response.data]
			})
			.catch(() => showError(t('maps', 'Failed to create favorite')))
	}

	function updateFavorite({ id, name, comment }) {
		return publicApiRequest(`favorites/${id}`, 'PUT', {
			name,
			category: getPublicShareCategory(),
			comment,
			extensions: '',
		})
			.then(response => {
				favorites.value = favorites.value.map(el =>
					el.id === response.data.id ? response.data : el,
				)
			})
			.catch(() => showError(t('maps', 'Failed to update favorite')))
	}

	function deleteFavorite({ id }) {
		return publicApiRequest(`favorites/${id}`, 'DELETE')
			.then(() => {
				favorites.value = favorites.value.filter(el => el.id !== id)
			})
			.catch(() => showError(t('maps', 'Failed to delete favorite')))
	}

	return {
		favorites,
		selectedFavoriteId,
		shareInfo,
		mappedByCategory,
		selectFavorite,
		getFavorites,
		addFavorite,
		updateFavorite,
		deleteFavorite,
	}
})
