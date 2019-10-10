import { publicApiRequest, showNotification } from "../../utils/common";
import { getCategoryRawName } from "../../utils/mapUtils";
import { getPublicShareCategory } from "../../utils/publicShareUtils";

export const PUBLIC_FAVORITES_NAMESPACE = "publicFavorites";

const state = {
  favorites: [],
  selectedFavoriteId: null
};

const getters = {
  mappedByCategory(state) {
    if (state.favorites.length === 0) {
      return {};
    }

    return {
      [getCategoryRawName(state.favorites[0].category)]: state.favorites
    };
  }
};

const actions = {
  selectFavorite({ commit }, favoriteId) {
    commit("setSelectedFavoriteId", favoriteId);
  },
  getFavorites({ commit }) {
    return publicApiRequest("favorites", "GET")
      .then(data => {
        commit("setFavorites", data);
      })
      .catch(() => showNotification(t("maps", "Failed to get favorites")));
  },
  addFavorite({ commit }, { lat, lng, name, comment }) {
    return publicApiRequest("favorites", "POST", {
      lat,
      lng,
      name,
      category: getPublicShareCategory(),
      comment,
      extensions: "" // TODO:
    })
      .then(data => {
        commit("addFavorite", data);
      })
      .catch(() => showNotification(t("maps", "Failed to create favorite")));
  },
  updateFavorite({ commit }, { id, name, comment }) {
    return publicApiRequest(`favorites/${id}`, "PUT", {
      name,
      category: getPublicShareCategory(),
      comment,
      extensions: "" // TODO:
    })
      .then(data => {
        commit("editFavorite", data);
      })
      .catch(() => showNotification(t("maps", "Failed to update favorite")));
  },
  deleteFavorite({ commit }, { id }) {
    return publicApiRequest(`favorites/${id}`, "DELETE")
      .then(() => {
        commit("deleteFavorite", id);
      })
      .catch(() => showNotification(t("maps", "Failed to delete favorite")));
  }
};

const mutations = {
  setFavorites(state, favorites) {
    state.favorites = favorites;
  },
  addFavorite(state, favorite) {
    state.favorites = [...state.favorites, favorite];
  },
  editFavorite(state, favorite) {
    state.favorites = state.favorites.map(el =>
      el.id === favorite.id ? favorite : el
    );
  },
  deleteFavorite(state, id) {
    state.favorites = state.favorites.filter(el => el.id !== id);
  },
  setSelectedFavoriteId(state, favoriteId) {
    state.selectedFavoriteId = favoriteId;
  }
};

export default {
  namespaced: true,
  state,
  getters,
  actions,
  mutations
};
