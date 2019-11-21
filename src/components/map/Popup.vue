<template>
  <div class="popup">
    <h2 v-if="title"
class="popup-title">
      {{ title }}
    </h2>

    <div class="popup-content">
      <slot />
    </div>

    <div class="buttons">
      <slot name="buttons" />
    </div>
  </div>
</template>

<script>
import VueTypes from "vue-types";

export default {
  name: "Popup",

  props: {
    title: VueTypes.string
  }
};
</script>

<style scoped lang="scss">
.popup {
  width: 180px;

  .popup-title {
    display: inline-block;
    line-height: 1em;
    color: #333;
    text-transform: uppercase;
    font-size: 1em;
    margin: 0;
    padding: 0;
    white-space: nowrap;
  }

  .popup-content {
    position: relative;
    width: 100%;
  }

  /deep/ .buttons {
    display: flex;
  }
}
</style>
