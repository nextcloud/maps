/**
 * @copyright Copyright (c) 2019, Paul Schwörer <hello@paulschwoerer.de>
 *
 * @author Paul Schwörer <hello@paulschwoerer.de>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import {
	publicApiRequest,
	getPublicShareCategory,
} from '../../utils/common.js'
import { getCategoryKey } from '../../utils/favoritesUtils.js'
import { showError } from '@nextcloud/dialogs'

export const PUBLIC_FAVORITES_NAMESPACE = 'publicFavorites'

const state = {
	favorites: [],
	selectedFavoriteId: null,
	shareInfo: null,
}

const getters = {
	mappedByCategory(state) {
		if (state.favorites.length === 0) {
			return {}
		}

		return {
			[getCategoryKey(state.favorites[0].category)]: state.favorites,
		}
	},
}

const actions = {
	selectFavorite({ commit }, favoriteId) {
		commit('setSelectedFavoriteId', favoriteId)
	},
	getFavorites({ commit }) {
		publicApiRequest('favorites', 'GET')
			.then(response => {
				const data = response.data
				commit('setShareInfo', data.share)
				commit('setFavorites', data.favorites)
			})
			.catch(() => {
				showError(t('maps', 'Failed to get favorites'))
			})
	},
	addFavorite({ commit }, { lat, lng, name, comment }) {
		return publicApiRequest('favorites', 'POST', {
			lat,
			lng,
			name,
			category: getPublicShareCategory(),
			comment,
			extensions: '', // TODO:
		})
			.then(response => {
				const data = response.data
				commit('addFavorite', data)
			})
			.catch(() => showError(t('maps', 'Failed to create favorite')))
	},
	updateFavorite({ commit }, { id, name, comment }) {
		return publicApiRequest(`favorites/${id}`, 'PUT', {
			name,
			category: getPublicShareCategory(),
			comment,
			extensions: '', // TODO:
		})
			.then(response => {
				const data = response.data
				commit('editFavorite', data)
			})
			.catch(() => showError(t('maps', 'Failed to update favorite')))
	},
	deleteFavorite({ commit }, { id }) {
		return publicApiRequest(`favorites/${id}`, 'DELETE')
			.then(() => {
				commit('deleteFavorite', id)
			})
			.catch(() => showError(t('maps', 'Failed to delete favorite')))
	},
}

const mutations = {
	setFavorites(state, favorites) {
		state.favorites = favorites
	},
	setShareInfo(state, info) {
		state.shareInfo = info
	},
	addFavorite(state, favorite) {
		state.favorites = [...state.favorites, favorite]
	},
	editFavorite(state, favorite) {
		state.favorites = state.favorites.map(el =>
			el.id === favorite.id ? favorite : el,
		)
	},
	deleteFavorite(state, id) {
		state.favorites = state.favorites.filter(el => el.id !== id)
	},
	setSelectedFavoriteId(state, favoriteId) {
		state.selectedFavoriteId = favoriteId
	},
}

export default {
	namespaced: true,
	state,
	getters,
	actions,
	mutations,
}
