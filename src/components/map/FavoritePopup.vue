<template>
  <Popup :title="favorite.name || '(No name)'">
    <form
      class="favorite"
      @submit.prevent="handleFavoriteSubmit"
      v-if="allowEdits"
    >
      <PopupFormItem
        v-model="favoriteCopy.name"
        icon="icon-add"
        type="text"
        :placeholder="t('maps', 'Name')"
        :allow-edits="allowEdits"
      />

      <PopupFormItem
        v-if="allowCategoryCustomization"
        v-model="favoriteCopy.category"
        icon="icon-category-organization"
        type="text"
        :placeholder="t('maps', 'Category')"
        :allow-edits="allowEdits"
      />

      <PopupFormItem
        v-model="favoriteCopy.comment"
        icon="icon-comment"
        :placeholder="t('maps', 'Comment')"
        :allow-edits="allowEdits"
      />

      <div v-if="allowEdits" class="buttons">
        <button class="primary">
          {{ t("maps", "Update") }}
        </button>
        <button class="danger" @click.prevent="handleDeleteClick">
          {{ t("maps", "Delete") }}
        </button>
      </div>
    </form>
    <div class="no-edits">
      <p>
        {{
          favorite.comment.length
            ? favorite.comment
            : t("maps", "No comment")
        }}
      </p>
    </div>
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
    allowEdits: VueTypes.bool.isRequired,
    allowCategoryCustomization: VueTypes.bool.def(true)
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
        this.updateFavoriteCopy();
      }
    }
  },

  mounted() {
    this.updateFavoriteCopy();
  },

  methods: {
    updateFavoriteCopy() {
      if (this.allowEdits) {
        this.favoriteCopy.name = this.favorite.name;
        this.favoriteCopy.category = this.favorite.category;
        this.favoriteCopy.comment = this.favorite.comment;
      }
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
