<template>
  <Popup :title="favorite.name || '...'">
    <form @submit.prevent="handleFavoriteSubmit" class="new-favorite-form">
      <PopupFormItem icon="icon-add">
        <input
          class="input"
          type="text"
          :placeholder="t('maps', 'Name')"
          v-model="favoriteCopy.name"
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
          v-model="favoriteCopy.category"
        />
      </PopupFormItem>

      <PopupFormItem icon="icon-comment">
        <textarea
          class="textarea"
          v-model="favoriteCopy.comment"
          :placeholder="t('maps', 'Comment')"
          rows="4"
        ></textarea>
      </PopupFormItem>

      <div class="buttons">
        <button class="primary">
          {{ t("maps", "Update") }}
        </button>
        <button class="danger" @click.prevent="handleDeleteClick">
          {{ t("maps", "Delete") }}
        </button>
      </div>
    </form>
  </Popup>
</template>

<script>
import VueTypes from "vue-types";
import Popup from "./Popup";
import PopupFormItem from "./PopupFormItem";
import Types from "../../data/types";

export default {
  name: "FavoritePopup",

  props: {
    favorite: Types.Favorite.isRequired,
    isVisible: VueTypes.bool.isRequired,
    allowCategoryCustomization: VueTypes.bool.def(true)
  },

  mounted() {
    this.updateFavorite();
  },

  data() {
    return {
      favoriteCopy: {
        name: "",
        category: "",
        comment: ""
      }
    };
  },

  watch: {
    favorite: {
      deep: true,
      handler() {
        this.updateFavorite();
      }
    }
  },

  methods: {
    updateFavorite() {
      this.favoriteCopy.name = this.favorite.name;
      this.favoriteCopy.category = this.favorite.category;
      this.favoriteCopy.comment = this.favorite.comment;
    },
    handleDeleteClick() {
      const { id } = this.favorite;

      this.$emit("deleteFavorite", { id });
    },
    handleFavoriteSubmit() {
      const { id, lat, lng } = this.favorite;
      const { name, category, comment } = this.favoriteCopy;

      this.$emit("updateFavorite", {
        id,
        name,
        category,
        comment,
        lat,
        lng
      });
    }
  },

  components: {
    Popup,
    PopupFormItem
  }
};
</script>

<style scoped></style>
