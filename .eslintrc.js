module.exports = {
  root: true,
  env: {
    node: true
  },
  extends: [
    "plugin:prettier/recommended",
    "plugin:vue/recommended"
  ],
  rules: {
    "vue/component-name-in-template-casing": ["error", "PascalCase"],
    "no-console": process.env.NODE_ENV === "production" ? "error" : "off",
    "no-debugger": process.env.NODE_ENV === "production" ? "error" : "off"
  },
  globals: {
    OC: false,
    OCA: false,
    t: false,
    n: false,
    $: false // TODO: remove once jQuery has been removed
  },
  parserOptions: {
    sourceType: "module",
    parser: "babel-eslint"
  },
};
