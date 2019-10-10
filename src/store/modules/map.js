import MapMode from "../../data/enum/MapMode";

export const MAP_NAMESPACE = "map";

const state = {
  mode: MapMode.DEFAULT
};

const getters = {};

const actions = {};

const mutations = {
  setMode(state, mode) {
    state.mode = mode;
  }
};

export default {
  namespaced: true,
  state,
  getters,
  actions,
  mutations
};
