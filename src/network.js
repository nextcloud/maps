import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import {
	// showSuccess,
	showError,
} from '@nextcloud/dialogs'

export function saveOptionValues(optionValues) {
	const req = {
		options: optionValues,
	}
	const url = generateUrl('/apps/maps/saveOptionValue')
	axios.post(url, req)
		.then((response) => {
		})
		.catch((error) => {
			showError(
				t('maps', 'Failed to save option values')
				+ ': ' + error.response?.request?.responseText
			)
		})
}

export function getOptionValues() {
	const url = generateUrl('/apps/maps/getOptionsValues')
	return axios.get(url)
}

export function getContacts() {
	const url = generateUrl('/apps/maps/contacts')
	return axios.get(url)
}

export function getAllContacts() {
	const url = generateUrl('/apps/maps/contacts-all')
	return axios.get(url)
}

export function geocode(lat, lng) {
	const url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng + '&addressdetails=1'
	return axios.get(url)
}

export function searchAddress(address, limit = 8) {
	const query = encodeURIComponent(address)
	const url = 'https://nominatim.openstreetmap.org/search/' + query + '?format=json&addressdetails=1&extratags=1&namedetails=1&limit=' + limit
	return axios.get(url)
}

export function exportRoute(type, coords, name, totDist, totTime) {
	const req = {
		type,
		coords,
		name,
		totDist,
		totTime,
	}
	const url = generateUrl('/apps/maps/exportRoute')
	return axios.post(url, req)
}

export function deleteContactAddress(bookid, uri, uid, vcardAddress) {
	const req = {
		params: {
			uid,
			adr: vcardAddress,
		},
	}
	const url = generateUrl('/apps/maps/contacts/' + bookid + '/' + uri)
	return axios.delete(url, req)
}

export function placeContact(bookid, uri, uid, lat, lng, address, type = 'home') {
	let road = (address.road || '') + ' ' + (address.pedestrian || '')
		+ ' ' + (address.suburb || '') + ' ' + (address.city_district || '')
	road = road.replace(/\s+/g, ' ').trim()
	let city = address.village || address.town || address.city || ''
	city = city.replace(/\s+/g, ' ').trim()
	const req = {
		lat,
		lng,
		uid,
		attraction: address.attraction,
		house_number: address.house_number,
		road,
		postcode: address.postcode,
		city,
		state: address.state,
		country: address.country,
		type,
	}
	const url = generateUrl('/apps/maps/contacts/' + bookid + '/' + uri)
	return axios.put(url, req)
}

export function getFavorites() {
	const url = generateUrl('/apps/maps/favorites')
	return axios.get(url)
}

export function addFavorite(lat, lng, name, category = null, comment = null, extensions = null) {
	const req = {
		name,
		lat,
		lng,
		category,
		comment,
		extensions,
	}
	const url = generateUrl('/apps/maps/favorites')
	return axios.post(url, req)
}

export function deleteFavorite(favid) {
	const url = generateUrl('/apps/maps/favorites/' + favid)
	return axios.delete(url)
}

export function deleteFavorites(ids) {
	const req = {
		params: {
			ids,
		},
	}
	const url = generateUrl('/apps/maps/favorites')
	return axios.delete(url, req)
}

export function editFavorite(id, name, category = null, comment = null, lat = null, lng = null) {
	const req = {
		name,
		extensions: null,
	}
	if (comment !== null) {
		req.comment = comment
	}
	if (category !== null) {
		req.category = category
	}
	if (lat !== null) {
		req.lat = lat
	}
	if (lng !== null) {
		req.lng = lng
	}
	const url = generateUrl('/apps/maps/favorites/' + id)
	return axios.put(url, req)
}

export function getPhotos() {
	const url = generateUrl('/apps/maps/photos')
	return axios.get(url)
}

export function placePhotos(paths, lats, lngs, directory = false) {
	const req = {
		paths,
		lats,
		lngs,
		directory,
	}
	const url = generateUrl('/apps/maps/photos')
	return axios.post(url, req)
}

export function resetPhotosCoords(paths) {
	const req = {
		params: {
			paths,
		},
	}
	const url = generateUrl('/apps/maps/photos')
	return axios.delete(url, req)
}
