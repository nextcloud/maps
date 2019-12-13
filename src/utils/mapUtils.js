import { request } from "./common";

export const isGeocodeable = str => {
  const pattern = /^\s*-?\d+\.?\d*,\s*-?\d+\.?\d*\s*$/;

  return pattern.test(str);
};

export const constructGeoCodeUrl = (lat, lng) =>
  `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`;

export const geocode = latLngStr => {
  if (!isGeocodeable(latLngStr)) {
    return Promise.reject(`${latLngStr} is not geocodable`);
  }

  const latLng = latLngStr.split(",");

  const lat = latLng[0].trim();
  const lng = latLng[1].trim();

  return request(constructGeoCodeUrl(lat, lng), "GET");
};

export const getShouldMapUseImperial = () => {
  const locale = OC.getLocale();

  return (
    locale === "en_US" ||
    locale === "en_GB" ||
    locale === "en_AU" ||
    locale === "en_IE" ||
    locale === "en_NZ" ||
    locale === "en_CA"
  );
};
