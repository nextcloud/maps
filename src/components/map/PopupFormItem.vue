<template>
  <div class="form-item">
    <span class="icon"
:class="icon" />
    <div class="input-wrapper">
      <template v-if="allowEdits">
        <textarea
          v-if="type === 'textarea'"
          :placeholder="placeholder"
          :value="value"
          rows="4"
          @input="$emit('input', $event.target.value)"
        />

        <input
          v-else
          :type="type"
          :placeholder="placeholder"
          :value="value"
          @input="$emit('input', $event.target.value)"
        >
      </template>
      <template v-else>
        <span>{{ value }}</span>
      </template>
    </div>
  </div>
</template>

<script>
import VueTypes from "vue-types";

export default {
  name: "PopupFormItem",

  props: {
    icon: VueTypes.string,
    value: VueTypes.any,
    placeholder: VueTypes.string.def(""),
    type: VueTypes.oneOf(["textarea", "text"]),
    allowEdits: VueTypes.bool.def(true)
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
