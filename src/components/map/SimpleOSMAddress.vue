<template>
  <div class="osm-address">
    <div class="osm-address-text">
      <p v-html="textContents" />
    </div>
    <div class="loading" :class="{ visible: loading }" />
  </div>
</template>

<script>
import Types from "../../data/types";

export default {
  name: "SimpleOSMAddress",

  props: {
    geocodeObject: Types.OSMGeoCodeResult
  },

  computed: {
    loading() {
      return !this.geocodeObject;
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
