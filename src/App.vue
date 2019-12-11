<template>
  <Content app-name="maps">
    <PublicFavoriteShareSideBar />
    <AppContent class="content-wrapper">
      <MapContainer
        :favorite-categories="favoritesMappedByCategory"
        :is-public-share="true"
        :allow-favorite-edits="allowFavoriteEdits"
        @addFavorite="addFavorite"
        @updateFavorite="updateFavorite"
        @deleteFavorite="deleteFavorite"
      />
    </AppContent>
  </Content>
</template>

<script>
import Content from "@nextcloud/vue/dist/Components/Content";
import AppContent from "@nextcloud/vue/dist/Components/AppContent";
import MapContainer from "./components/MapContainer";
import PublicFavoriteShareSideBar from "./components/PublicFavoriteShareSideBar";
import { mapActions, mapGetters, mapState } from "vuex";
import { PUBLIC_FAVORITES_NAMESPACE } from "./store/modules/publicFavorites";

export default {
  name: "App",

  components: {
    AppContent,
    Content,
    MapContainer,
    PublicFavoriteShareSideBar
  },

  data() {
    return {
      mode: "default"
    };
  },

  computed: {
    ...mapGetters({
      favoritesMappedByCategory: `${PUBLIC_FAVORITES_NAMESPACE}/mappedByCategory`
    }),
    ...mapState({
      allowFavoriteEdits: state =>
        state[PUBLIC_FAVORITES_NAMESPACE].shareInfo
          ? state[PUBLIC_FAVORITES_NAMESPACE].shareInfo.allowEdits
          : false
    })
  },

  mounted() {
    this.getFavorites();
  },

  methods: {
    ...mapActions({
      getFavorites: `${PUBLIC_FAVORITES_NAMESPACE}/getFavorites`,
      addFavorite: `${PUBLIC_FAVORITES_NAMESPACE}/addFavorite`,
      updateFavorite: `${PUBLIC_FAVORITES_NAMESPACE}/updateFavorite`,
      deleteFavorite: `${PUBLIC_FAVORITES_NAMESPACE}/deleteFavorite`
    })
  }
};
</script>

<style lang="scss">
/* Override header style */
#header {
  .header-shared-by {
    color: var(--color-primary-text);
  }
}
</style>
