<template>
  <Popup
    :title="
      addingFavorite ? t('maps', 'New Favorite') : t('maps', 'This Place')
    "
  >
    <template v-if="addingFavorite">
      <form class="new-favorite-form"
@submit.prevent="handleNewFavoriteSubmit">
        <span>Dumb</span>
        <PopupFormItem
          v-model="newFavorite.name"
          icon="icon-add"
          :placeholder="t('maps', 'Name')"
        />

        <PopupFormItem
          v-if="allowCategoryCustomization"
          v-model="newFavorite.category"
          icon="icon-category-organization"
          type="text"
          :placeholder="t('maps', 'Category')"
        />

        <PopupFormItem
          v-model="newFavorite.comment"
          icon="icon-comment"
          :placeholder="t('maps', 'Comment')"
        />

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

      <div v-if="allowEdits"
class="buttons">
        <button class="primary"
@click="handleAddToFavorites">
          {{ t("maps", "Add to Favorites") }}
        </button>
      </div>
    </template>
  </Popup>
</template>

<script>
import { MAP_NAMESPACE } from "../../store/modules/map";
import { mapState } from "vuex";
import MapMode from "../../data/enum/MapMode";
import { geocode } from "../../utils/mapUtils";
import SimpleOSMAddress from "./SimpleOSMAddress";
import VueTypes from "vue-types";
import Popup from "./Popup";
import PopupFormItem from "./PopupFormItem";
import Types from "../../data/types";

export default {
  name: "ClickPopup",

  props: {
    isVisible: VueTypes.bool.isRequired,
    latLng: Types.LatLng,
    allowCategoryCustomization: VueTypes.bool.isRequired,
    allowEdits: VueTypes.bool.isRequired
  },

  data() {
    return {
      geocodeObject: null,
      newFavorite: {
        name: "New Favorite",
        category: this.allowCategoryCustomization
          ? "Personal" // TODO: get default category name
          : null,
        comment: ""
      },
      addingFavorite: false
    };
  },

  watch: {
    isVisible(val) {
      if (val) {
        this.reset();
      }
    },
    latLng: {
      deep: true,
      handler() {
        this.reset();
        this.updateAddress();
      }
    }
  },

  computed: {
    ...mapState({
      mapMode: state => state[MAP_NAMESPACE].mode
    })
  },

  methods: {
    reset() {
      this.geocodeObject = null;
      this.addingFavorite = this.mapMode === MapMode.ADDING_FAVORITES;
    },

    handleAddToFavorites() {
      this.addingFavorite = true;
    },

    handleCancelAddingFavorite() {
      if (this.mapMode === MapMode.ADDING_FAVORITES) {
        this.$emit("close");
      } else {
        this.addingFavorite = false;
      }
    },

    handleNewFavoriteSubmit() {
      const { lat, lng } = this.latLng;
      const { name, category, comment } = this.newFavorite;

      this.$emit("addFavorite", {
        lat,
        lng,
        name,
        category,
        comment
      });
    },

    updateAddress() {
      const { lat, lng } = this.latLng;

      geocode(`${lat},${lng}`).then(res => {
        this.geocodeObject = res;
      });
    }
  },

  components: {
    Popup,
    PopupFormItem,
    SimpleOSMAddress
  }
};
</script>

<style scoped lang="scss">
.new-favorite-form {
  width: 100%;
  margin: 0;
}
</style>
