<template>
  <LFeatureGroup @ready="handleFeatureGroupReady">
    <LPopup :lat-lng="latLng">
      <MapPopup
        :is-visible="showPopup"
        :lat-lng="latLng"
        v-bind="$props"
        @close="handleCloseEvent"
      />
    </LPopup>
  </LFeatureGroup>
</template>

<script>
import { LFeatureGroup, LPopup } from "vue2-leaflet";
import MapPopup from "./ClickPopup";
import { mapMutations, mapState } from "vuex";
import { MAP_NAMESPACE } from "../../store/modules/map";

const Origin = {
  Store: "store",
  None: "none"
};

export default {
  name: "MapPopupLayer",

  watch: {
    showPopup(show) {
      if (show) {
        this.openOrigin = Origin.Store;
        this.openPopup();
      } else {
        this.closeOrigin = Origin.Store;
        this.closePopup();
      }
    }
  },

  created() {
    this.closeOrigin = Origin.None;
    this.openOrigin = Origin.None;

    this.featureGroupObject = null;
  },

  computed: mapState({
    showPopup: state => state[MAP_NAMESPACE].popup.show,
    latLng: state => state[MAP_NAMESPACE].popup.latLng
  }),

  methods: {
    ...mapMutations({
      storeOpenPopup: `${MAP_NAMESPACE}/openPopup`,
      storeClosePopup: `${MAP_NAMESPACE}/closePopup`
    }),

    handleMapClick(e) {
      const { lat, lng } = e.latlng;

      if (!this.showPopup) {
        this.storeOpenPopup({ latLng: [lat, lng] });
      }
    },

    handleFeatureGroupReady(mapObject) {
      mapObject.on("popupopen", this.handlePopupOpenEvent);
      mapObject.on("popupclose", this.handlePopupCloseEvent);

      this.featureGroupObject = mapObject;
    },

    openPopup() {
      const [lat, lng] = this.latLng;
      this.featureGroupObject.openPopup([lat, lng]);
    },

    closePopup() {
      this.featureGroupObject.closePopup();
    },

    handlePopupOpenEvent() {
      if (this.openOrigin !== Origin.Store) {
        throw new Error(
          "Popup was not opened through vuex store. This could lead to issues."
        );
      }

      this.openOrigin = Origin.None;
    },

    handlePopupCloseEvent() {
      if (this.closeOrigin === Origin.Store) {
        this.closeOrigin = Origin.None;
        return;
      }

      this.storeClosePopup();
    },

    handleCloseEvent() {
      this.closePopup();
    }
  },

  components: {
    LFeatureGroup,
    MapPopup,
    LPopup
  }
};
</script>
