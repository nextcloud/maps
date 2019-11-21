import VueTypes from "vue-types";

const LatLng = VueTypes.shape({
  lat: VueTypes.number,
  lng: VueTypes.number
});

const Favorite = VueTypes.shape({
  id: VueTypes.number,
  name: VueTypes.string,
  comment: VueTypes.string,
  category: VueTypes.string,
  extensions: VueTypes.string,
  date_created: VueTypes.number,
  date_modified: VueTypes.number,
  lat: VueTypes.number,
  lng: VueTypes.number
});

const OSMGeoCodeResult = VueTypes.shape({
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
}).loose;

export default {
  LatLng,
  Favorite,
  OSMGeoCodeResult
};
