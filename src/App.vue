<template>
  <div id="maps-app">
    <PublicFavoriteShareSideBar />
    <div class="content-wrapper">
      <MapContainer
        :favorite-categories="favoritesMappedByCategory"
        :propagate-left-click="mapPropagateLeftClick"
        @addFavorite="addFavorite"
        @updateFavorite="updateFavorite"
        @deleteFavorite="deleteFavorite"
        :is-public-share="true"
      />
    </div>
  </div>
</template>

<script>
import MapContainer from "./components/MapContainer";
import PublicFavoriteShareSideBar from "./components/PublicFavoriteShareSideBar";
import { mapActions, mapGetters, mapState } from "vuex";
import { PUBLIC_FAVORITES_NAMESPACE } from "./store/modules/publicFavorites";
import AppMode from "./data/enum/MapMode";

export default {
  name: "App",

  data() {
    return {
      mode: "default"
    };
  },

  mounted() {
    this.getFavorites();
  },

  computed: {
    ...mapGetters({
      favoritesMappedByCategory: `${PUBLIC_FAVORITES_NAMESPACE}/mappedByCategory`
    }),
    mapPropagateLeftClick() {
      return this.appMode === AppMode.ADDING_FAVORITES;
    }
  },

  methods: {
    ...mapActions({
      getFavorites: `${PUBLIC_FAVORITES_NAMESPACE}/getFavorites`,
      addFavorite: `${PUBLIC_FAVORITES_NAMESPACE}/addFavorite`,
      updateFavorite: `${PUBLIC_FAVORITES_NAMESPACE}/updateFavorite`,
      deleteFavorite: `${PUBLIC_FAVORITES_NAMESPACE}/deleteFavorite`
    })
  },

  components: {
    MapContainer,
    PublicFavoriteShareSideBar
  }
};
</script>

<style lang="scss">
#maps-app {
  width: 100%;

  .content-wrapper {
    margin-left: 300px;
    height: 100%;
  }
}
</style>
