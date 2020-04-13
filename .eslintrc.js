module.exports = {
  root: true,
  env: {
    node: true
  },
  extends: [
    "nextcloud"
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
    ecmaVersion: 6,
    sourceType: "module",
    parser: "babel-eslint"
  }
};
