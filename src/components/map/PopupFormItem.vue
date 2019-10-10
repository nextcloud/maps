<template>
  <div class="form-item">
    <span class="icon" :class="icon"></span>
    <div class="input-wrapper">
      <slot />
    </div>
  </div>
</template>

<script>
import VueTypes from "vue-types";

export default {
  name: "PopupFormItem",

  props: {
    icon: VueTypes.string
  }
};
</script>

<style scoped lang="scss">
$spacing: 0.5em;

.form-item {
  width: 100%;
  margin: $spacing 0;
  display: flex;
  align-items: center;

  .icon {
    height: 44px;
    margin-right: 2 * $spacing;
  }

  .input-wrapper {
    width: 100%;

    /deep/ {
      .textarea {
        resize: vertical;
      }

      .input,
      .textarea {
        display: block;
        width: 100%;
        flex: 0;
      }
    }
  }
}
</style>
