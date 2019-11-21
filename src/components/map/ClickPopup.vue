<template>
  <Popup
    :title="
      addingFavorite ? t('maps', 'New Favorite') : t('maps', 'This Place')
    "
  >
    <template v-if="addingFavorite">
      <form @submit.prevent="handleNewFavoriteSubmit" class="new-favorite-form">
        <PopupFormItem icon="icon-add">
          <input
            class="input"
            type="text"
            :placeholder="t('maps', 'Name')"
            v-model="newFavorite.name"
          />
        </PopupFormItem>

        <PopupFormItem
          icon="icon-category-organization"
          v-if="allowCategoryCustomization"
        >
          <input
            class="input"
            type="text"
            :placeholder="t('maps', 'Category')"
            v-model="newFavorite.category"
          />
        </PopupFormItem>

        <PopupFormItem icon="icon-comment">
          <textarea
            class="textarea"
            v-model="newFavorite.comment"
            :placeholder="t('maps', 'Comment')"
            rows="4"
          ></textarea>
        </PopupFormItem>

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
      <SimpleOSMAddress :geocodeObject="geocodeObject" />

      <div class="buttons">
        <button class="primary" @click="handleAddToFavorites">
          {{ t("maps", "Add to Favorites") }}
        </button>
      </div>
    </template>
  </Popup>
</template>

<script>
import Actions from "@nextcloud/vue/dist/Components/Actions";
import ActionButton from "@nextcloud/vue/dist/Components/ActionButton";
import ActionInput from "@nextcloud/vue/dist/Components/ActionInput";
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
    allowCategoryCustomization: VueTypes.bool.def(true)
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
