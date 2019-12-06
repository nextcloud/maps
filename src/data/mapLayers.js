// const attributionESRI =
//   "Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN...";

export const LayerTypes = {
  Base: "base",
  Overlay: "overlay"
};

export const LayerIds = {
  OSM: "osm"
  // RoadsOverlay: "roads-overlay",
  // ESRI: "esri",
  // ESRITopo: "esri-topo",
  // OpenTopo: "open-topo",
  // Watercolor: "watercolor",
};

export const Layers = [
  /*{
    id: LayerIds.RoadsOverlay,
    name: "Roads Overlay",
    type: LayerTypes.Overlay,
    url:
      "https://{s}.tile.openstreetmap.se/hydda/roads_and_labels/{z}/{x}/{y}.png",
    attribution:
      "Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN...",
    options: {
      noWrap: false,
      detectRetina: false,
      maxZoom: 18
    },
    opacity: 0.7
  },*/
  {
    id: LayerIds.OSM,
    name: "Open Street Map",
    type: LayerTypes.Base,
    url: "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
    attribution:
      '&copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>',
    options: {
      noWrap: false,
      detectRetina: false,
      maxZoom: 19
    }
  }
  /*{
    id: LayerIds.ESRI,
    name: "ESRI",
    url:
      "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
    attribution: attributionESRI,
    options: {
      noWrap: false,
      detectRetina: false,
      maxZoom: 19
    }
  },
  {
    id: LayerIds.ESRITopo,
    name: "ESRI Topology",
    url:
      "https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}",
    attribution: attributionESRI,
    options: {
      noWrap: false,
      detectRetina: false,
      maxZoom: 19
    }
  },
  {
    id: LayerIds.OpenTopo,
    name: "Open Topo",
    url:
      "https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}",
    attribution:
      'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, <a href="http://viewfinderpanoramass.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)',
    options: {
      noWrap: false,
      detectRetina: false,
      maxZoom: 17
    }
  },
  {
    id: LayerIds.Watercolor,
    name: "Watercolor",
    url:
      "https://stamen-tiles-{s}.a.ssl.fastly.net/watercolor/{z}/{x}/{y}.{ext}",
    attribution:
      '<a href="https://leafletjs.com" title="A JS library for interactive maps">Leaflet</a> | © Map tiles by <a href="https://stamen.com">Stamen Design</a>, under <a href="https://creativecommons.org/licenses/by/3.0">CC BY 3.0</a>, Data by <a href="https://openstreetmap.org">OpenStreetMap</a>, under <a href="https://creativecommons.org/licenses/by-sa/3.0">CC BY SA</a>.',
    options: {
      noWrap: false,
      detectRetina: false,
      maxZoom: 18,
      ext: "jpg",
      subdomains: "abcd"
    }
  }*/
];
