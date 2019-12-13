/**
 * @copyright Copyright (c) 2019, Paul Schwörer <hello@paulschwoerer.de>
 *
 * @author Paul Schwörer <hello@paulschwoerer.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

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
