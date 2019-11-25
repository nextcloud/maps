<template>
  <div class="map-container">
    <LMap
      ref="map"
      :center="mapOptions.center"
      :max-bounds="mapOptions.maxBounds"
      :min-zoom="mapOptions.minZoom"
      :max-zoom="mapOptions.maxZoom"
      :zoom="mapOptions.zoom"
      @ready="onMapReady"
    >
      <LTileLayer
v-for="layer in layers" :key="layer.name"
:url="layer.url"
/>

      <LMarkerCluster
        v-for="categoryKey in Object.keys(favoriteCategories)"
        :key="categoryKey"
        :options="{
          iconCreateFunction: getClusterIconCreateFunction(categoryKey),
          animate: mapOptions.animateClusters,
          showCoverageOnHover: mapOptions.showClusterBounds
        }"
      >
        <LMarker
          v-for="favorite in favoriteCategories[categoryKey]"
          :key="favorite.id"
          :lat-lng="[favorite.lat, favorite.lng]"
          :icon="createNewDivIcon(categoryKey)"
          @popupopen="handleMarkerPopupOpened(favorite.id)"
          @popupclose="handleMarkerPopupClosed(favorite.id)"
          @ready="marker => handleMarkerReady(favorite.id, marker)"
        >
          <LPopup>
            <FavoritePopup
              :favorite="favorite"
              :is-visible="openMarkerPopupId === favorite.id"
              :allow-category-customization="!isPublicShare"
              :allow-edits="allowFavoriteEdits"
              @deleteFavorite="handleDeleteFavorite"
              @updateFavorite="handleUpdateFavorite"
            />
          </LPopup>
        </LMarker>
      </LMarkerCluster>

      <LFeatureGroup @ready="onFeatureGroupReady">
        <LPopup :lat-lng="popup.latLng">
          <ClickPopup
            :is-visible="popup.visible"
            :lat-lng="popup.latLng"
            :allow-category-customization="!isPublicShare"
            :allow-edits="allowFavoriteEdits"
            @close="handlePopupCloseRequest"
            @addFavorite="handleAddFavorite"
          />
        </LPopup>
      </LFeatureGroup>
    </LMap>
  </div>
</template>

<script>
import L from "leaflet";
import VueTypes from "vue-types";
import "leaflet.markercluster";
import "leaflet.featuregroup.subgroup";

import { LMap, LTileLayer, LMarker, LPopup, LFeatureGroup } from "vue2-leaflet";
import LMarkerCluster from "vue2-leaflet-markercluster";
import { latLngBounds, latLng } from "leaflet";
import { mapActions, mapMutations, mapState } from "vuex";
import ClickPopup from "./map/ClickPopup";
import FavoritePopup from "./map/FavoritePopup";
import { isPublicShare } from "../utils/common";
import { PUBLIC_FAVORITES_NAMESPACE } from "../store/modules/publicFavorites";

const CLUSTER_MARKER_VIEW_SIZE = 27;

export default {
  name: "MapContainer",

  props: {
    favoriteCategories: VueTypes.object.isRequired,
    isPublicShare: VueTypes.bool.isRequired,
    allowFavoriteEdits: VueTypes.bool.def(false)
  },

  data() {
    return {
      openMarkerPopupId: null,
      popup: {
        visible: false,
        latLng: { lat: 0, lng: 0 }
      },
      mapOptions: {
        center: [0, 0],
        zoom: 2,
        minZoom: 2,
        maxZoom: 19,
        initialBounds: latLngBounds([
          [40.70081290280357, -74.26963806152345],
          [40.82991732677597, -74.08716201782228]
        ]),
        maxBounds: latLngBounds([
          [-90, 720],
          [90, -720]
        ]),
        animateClusters: true, // TODO: use setting?
        showClusterBounds: false
      },
      layers: [
        {
          name: "OSM",
          url: "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        }
      ]
    };
  },

  watch: {
    selectedFavoriteId(val) {
      if (val !== null) {
        const marker = this.markerMap[val];

        if (marker) {
          this.setMapView(marker.getLatLng(), 10);
          marker.openPopup();
        } else {
          console.warn(
            "[MapContainer] Cannot find marker for favorite id: ",
            val
          );
        }
      }
    }
  },

  created() {
    this.featureGroup = null;
    this.popupWasJustClosed = false;
    this.markerMap = [];
  },

  computed: {
    // TODO: clean
    ...mapState({
      selectedFavoriteId: state =>
        isPublicShare()
          ? state[PUBLIC_FAVORITES_NAMESPACE].selectedFavoriteId
          : null,
      selectedFavorite: state =>
        isPublicShare()
          ? state[PUBLIC_FAVORITES_NAMESPACE].favorites.find(
              favorite =>
                favorite.id ===
                state[PUBLIC_FAVORITES_NAMESPACE].selectedFavoriteId
            )
          : null
    })
  },

  methods: {
    ...mapActions({
      selectFavorite: `${PUBLIC_FAVORITES_NAMESPACE}/selectFavorite`
    }),

    setMapView(latLng, zoom) {
      this.$refs.map.mapObject.setView(latLng, zoom);
    },

    handleMarkerReady(favoriteId, marker) {
      this.markerMap[favoriteId] = marker;
    },

    handleAddFavorite(data) {
      this.$emit("addFavorite", data);
    },

    handleUpdateFavorite(data) {
      this.$emit("updateFavorite", data);
    },

    handleDeleteFavorite(data) {
      this.$emit("deleteFavorite", data);
    },

    handleMarkerPopupOpened(id) {
      this.openMarkerPopupId = id;
    },

    handleMarkerPopupClosed(id) {
      this.openMarkerPopupId = null;

      this.selectFavorite(null);
    },

    openPopup(lat, lng) {
      this.popup.visible = true;
      this.popup.latLng = { lat, lng };
      this.featureGroup.openPopup([lat, lng]);
    },

    closePopup() {
      this.resetPopupState();
      this.featureGroup.closePopup();
    },

    resetPopupState() {
      this.popup.visible = false;
      this.popup.latLng = { lat: 0, lng: 0 };
    },

    handleMapClick(e) {
      if (!this.popup.visible && !this.popupWasJustClosed) {
        this.openPopup(e.latlng.lat, e.latlng.lng);
      }
    },

    handlePopupCloseRequest() {
      this.closePopup();
    },

    createNewDivIcon(categoryKey) {
      return new L.DivIcon({
        iconAnchor: [9, 9],
        className: "leaflet-marker-favorite",
        html:
          '<div class="favorite-marker ' +
          categoryKey +
          'CategoryMarker"></div>'
      });
    },

    getClusterIconCreateFunction(categoryKey) {
      return cluster => {
        const label = cluster.getChildCount();

        return new L.DivIcon({
          iconAnchor: [14, 14],
          className: "leaflet-marker-favorite-cluster cluster-marker",
          html:
            '<div class="favorite-cluster-marker ' +
            categoryKey +
            'CategoryMarker"></div>​<span class="label">' +
            label +
            "</span>"
        });
      };
    },

    handlePopupOpenEvent() {},

    handlePopupCloseEvent() {
      this.popupWasJustClosed = true;
      this.resetPopupState();

      this.$nextTick(() => {
        this.popupWasJustClosed = false;
      });
    },

    onMapReady(map) {
      map.on("click", this.handleMapClick);
    },

    onFeatureGroupReady(featureGroup) {
      featureGroup.on("popupopen", this.handlePopupOpenEvent);
      featureGroup.on("popupclose", this.handlePopupCloseEvent);

      this.featureGroup = featureGroup;
    }
  },

  components: {
    ClickPopup,
    LMap,
    LFeatureGroup,
    LMarker,
    LMarkerCluster,
    LTileLayer,
    LPopup,
    FavoritePopup
  }
};
</script>

<style lang="scss">
.map-container {
  position: relative;
  height: 100%;
  width: 100%;

  * {
    box-sizing: content-box;
  }

  .leaflet-tooltip {
    white-space: normal !important;
  }

  .leaflet-container {
    background: var(--color-main-background);
  }

  .leaflet-control-layers-base {
    line-height: 30px;
  }

  .leaflet-control-layers-selector {
    min-height: 0;
  }

  .leaflet-control-layers-toggle {
    background-size: 75% !important;
  }

  .leaflet-control-layers:not(.leaflet-control-layers-expanded) {
    width: 33px;
    height: 37px;
  }

  .leaflet-control-layers:not(.leaflet-control-layers-expanded) > a {
    width: 100%;
    height: 100%;
  }

  .favorite-marker,
  .favorite-cluster-marker {
    /*-webkit-mask: url("../../css/images/star-circle.svg") no-repeat 50% 50%;
    mask: url("../../css/images/star-circle.svg") no-repeat 50% 50%;
    background: url("../../css/images/star-white.svg") no-repeat 50% 50%; */ // TODO: webpack image/svg config
    background: red; // TODO: remove
    border-radius: 50%;
    box-shadow: 0 0 10px #888;
  }

  .favorite-marker {
    height: 18px !important;
    width: 18px !important;
    /*-webkit-mask-size: 18px;
    mask-size: 18px;
    background-size: 18px 18px;*/
  }

  .favorite-cluster-marker {
    height: 27px !important;
    width: 27px !important;
    /*-webkit-mask-size: 27px;
    mask-size: 27px;
    background-size: 27px 27px;*/
  }

  .leaflet-marker-favorite-cluster {
    .label {
      position: absolute;
      top: -7px;
      right: 0;
      color: #fff;
      background-color: #333;
      border-radius: 9px;
      height: 18px;
      min-width: 18px;
      line-height: 12px;
      text-align: center;
      padding: 3px;
    }
  }

  /* Adjust button styles to Nextcloud */
  .leaflet-touch {
    .leaflet-control-layers,
    .leaflet-bar {
      border: none;
      border-radius: var(--border-radius);
    }
  }

  /* Fix attribution overlapping map on mobile */
  .leaflet-control-attribution.leaflet-control {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 50vw;
  }

  /* Increase padding of popup close button */
  .leaflet-popup {
    .leaflet-popup-content-wrapper {
      border-radius: 4px;
    }

    .leaflet-popup-close-button {
      top: 9px;
      right: 9px;
    }
  }
}
</style>
