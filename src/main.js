import Vue from "vue";
import App from "./App";
import { Icon } from "leaflet";
import "leaflet/dist/leaflet.css";

import store from "./store";

Vue.prototype.t = window.t;
Vue.prototype.n = window.n;
Vue.prototype.OC = window.OC;
Vue.prototype.OCA = window.OCA;

if (process && process.env.NODE_ENV === "development") {
  Vue.config.devtools = true;
}

// this part resolve an issue where the markers would not appear
delete Icon.Default.prototype._getIconUrl;

Icon.Default.mergeOptions({
  iconRetinaUrl: require("leaflet/dist/images/marker-icon-2x.png"),
  iconUrl: require("leaflet/dist/images/marker-icon.png"),
  shadowUrl: require("leaflet/dist/images/marker-shadow.png")
});

new Vue({
  render: h => h(App),
  store
}).$mount("#content");
