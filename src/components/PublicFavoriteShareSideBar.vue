<template>
  <AppNavigation>
    <ul id="app-vueexample-navigation">
      <AppNavigationNew
        :text="newFavoriteButtonLabel"
        @click="handleAddFavoriteClick"
      />
      <AppNavigationItem
        v-for="favorite in favorites"
        :key="favorite.id"
        :title="favorite.name"
        @click="handleFavoriteClick(favorite.id)"
      />
      <AppNavigationSpacer />
    </ul>
    <AppNavigationSettings>
      Example settings
    </AppNavigationSettings>
  </AppNavigation>
</template>

<script>
import AppNavigation from "@nextcloud/vue/dist/Components/AppNavigation";
import AppNavigationCaption from "@nextcloud/vue/dist/Components/AppNavigationCaption";
import AppNavigationSettings from "@nextcloud/vue/dist/Components/AppNavigationSettings";
import AppNavigationItem from "@nextcloud/vue/dist/Components/AppNavigationItem";
import AppNavigationNew from "@nextcloud/vue/dist/Components/AppNavigationNew";
import AppNavigationSpacer from "@nextcloud/vue/dist/Components/AppNavigationSpacer";
import { mapMutations, mapState, mapActions } from "vuex";
import { PUBLIC_FAVORITES_NAMESPACE } from "../store/modules/publicFavorites";
import MapMode from "../data/enum/MapMode";
import { MAP_NAMESPACE } from "../store/modules/map";

export default {
  name: "PublicFavoriteShareSideBar",

  computed: {
    ...mapState({
      favorites: state => state[PUBLIC_FAVORITES_NAMESPACE].favorites,
      mapMode: state => state[MAP_NAMESPACE].mode
    }),

    newFavoriteButtonLabel() {
      return t(
        "maps",
        this.mapMode === MapMode.ADDING_FAVORITES
          ? "Cancel adding favorites"
          : "Add favorites"
      );
    }
  },

  methods: {
    ...mapActions({
      selectFavorite: `${PUBLIC_FAVORITES_NAMESPACE}/selectFavorite`
    }),
    ...mapMutations({
      setMapMode: `${MAP_NAMESPACE}/setMode`
    }),

    handleAddFavoriteClick() {
      if (this.mapMode === MapMode.ADDING_FAVORITES) {
        this.setMapMode(MapMode.DEFAULT);
      } else {
        this.setMapMode(MapMode.ADDING_FAVORITES);
      }
    },

    handleFavoriteClick(id) {
      this.selectFavorite(id);
    }
  },

  components: {
    AppNavigation,
    AppNavigationCaption,
    AppNavigationSettings,
    AppNavigationItem,
    AppNavigationNew,
    AppNavigationSpacer
  }
};
</script>

<style scoped></style>
