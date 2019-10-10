<template>
  <div class="osm-address">
    <div class="osm-address-text">
      <p v-html="textContents"></p>
    </div>
    <!--<textarea
      :value="textContents"
      @input="$emit('input', $event.target.value)"
      class="osm-address-text"
      rows="6"
    ></textarea>-->
    <div class="loading" :class="{ visible: loading }"></div>
  </div>
</template>

<script>
import VueTypes from "vue-types";

export default {
  name: "SimpleOSMAddress",

  props: {
    geocodeObject: VueTypes.shape({
      address: VueTypes.shape({
        country: VueTypes.string,
        county: VueTypes.string,
        country_code: VueTypes.string,
        postcode: VueTypes.string,
        village: VueTypes.string,
        state: VueTypes.string,
        city: VueTypes.string,
        pedestrian: VueTypes.string,
        house_number: VueTypes.string,
        road: VueTypes.string
      }).loose,
      display_name: VueTypes.string,
      lat: VueTypes.string,
      lon: VueTypes.string,
      osm_id: VueTypes.number,
      osm_type: VueTypes.string,
      place_id: VueTypes.number,

      error: VueTypes.string
    }).loose
  },

  computed: {
    loading() {
      return this.geocodeObject === null;
    },

    textContents() {
      if (!this.geocodeObject) {
        return "";
      }

      if (typeof this.geocodeObject.error !== "undefined") {
        return t("maps", "Unknown Place");
      }

      const {
        address: {
          country,
          postcode,
          village,
          pedestrian,
          county,
          state,
          city,
          house_number,
          road
        }
      } = this.geocodeObject;

      const lineFeed = "<br />";
      let address = "";

      if (road) {
        address += `${road} ${house_number || ""}${lineFeed}`;
      } else if (pedestrian) {
        address += `${pedestrian} ${house_number || ""}${lineFeed}`;
      }

      if (city) {
        address += `${postcode ? postcode + " " : ""}${city}${lineFeed}`;
      } else if (village) {
        address += `${postcode ? postcode + " " : ""}${village}${lineFeed}`;
      }

      if (county) {
        address += `${county}${lineFeed}`;
      }

      if (state) {
        address += `${state}${lineFeed}`;
      }

      if (country) {
        address += country;
      }

      if (address.length === 0) {
        return t("maps", "Unknown Place");
      }

      return address;
    }
  }
};
</script>

<style scoped lang="scss">
$transitionDuration: 0.3s;

.osm-address {
  position: relative;
  width: 100%;

  .osm-address-text {
    width: 100%;
    min-height: 8em;
    /*border: 1px solid rgba(#000, 0.05);*/
    /*resize: vertical;*/
  }

  .loading {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    opacity: 0;
    visibility: hidden;
    transition: opacity $transitionDuration, visibility 0s $transitionDuration;
    background: #fff;

    &.visible {
      opacity: 1;
      visibility: visible;
      transition: none;
    }
  }
}
</style>
