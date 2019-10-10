import Vuex from "vuex";
import Vue from "vue";
import publicFavorites from "./modules/publicFavorites";
import map from "./modules/map";

Vuex.install(Vue);

export default new Vuex.Store({
  modules: {
    publicFavorites,
    map
  },
  strict: process.env.NODE_ENV !== "production"
});
