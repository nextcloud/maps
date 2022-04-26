import axios from '@nextcloud/axios'
import * as realAxios from 'axios'
import { generateUrl } from '@nextcloud/router'
import {
	// showSuccess,
	showError,
} from '@nextcloud/dialogs'

export function saveOptionValues(optionValues, myMapId) {
	const req = {
		options: optionValues,
		myMapId,
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

export function getOptionValues(myMapId = null) {
	const url = generateUrl('/apps/maps/getOptionsValues')
	const conf = {
		params: {
			myMapId,
		},
	}
	return axios.get(url, conf)
}

export function sendMyPosition(lat, lng, name, acc, ts, myMapId = null) {
	const req = {
		myMapId,
		lat,
		lng,
		user_agent: name,
		accuracy: acc,
		timestamp: ts,
	}
	const url = generateUrl('/apps/maps/devices')
	return axios.post(url, req)
}

export function getContacts(myMapId = null) {
	const conf = {
		params: {
			myMapId,
		},
	}
	const url = generateUrl('/apps/maps/contacts')
	return axios.get(url, conf)
}

export function searchContacts(query = '', myMapId = null) {
	const req = {
		params: {
			myMapId,
		},
		query,
	}
	const url = generateUrl('/apps/maps/contacts-search')
	return axios.get(url, req)
}

export function geocode(lat, lng) {
	const url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng + '&addressdetails=1'
	return realAxios.get(url)
}

export function searchAddress(address, limit = 8) {
	const query = encodeURIComponent(address)
	const url = 'https://nominatim.openstreetmap.org/search/' + query + '?format=json&addressdetails=1&extratags=1&namedetails=1&limit=' + limit
	return realAxios.get(url)
}

export function exportRoute(type, coords, name, totDist, totTime, myMapId = null) {
	const req = {
		type,
		coords,
		name,
		totDist,
		totTime,
		myMapId,
	}
	const url = generateUrl('/apps/maps/exportRoute')
	return axios.post(url, req)
}

export function deleteContactAddress(bookid, uri, uid, vcardAddress = '', vcardGEO = '') {
	const req = {
		params: {
			uid,
			adr: vcardAddress,
			geo: vcardGEO,
		},
	}
	const url = generateUrl('/apps/maps/contacts/' + bookid + '/' + uri)
	return axios.delete(url, req)
}

export function placeContact(bookid, uri, uid, lat, lng, address = null, type = 'home') {
	let req = {
		lat,
		lng,
		uid,
	}
	if (address) {
		let road = (address.road || '') + ' ' + (address.pedestrian || '')
		+ ' ' + (address.suburb || '') + ' ' + (address.city_district || '')
		road = road.replace(/\s+/g, ' ').trim()
		let city = address.village || address.town || address.city || ''
		city = city.replace(/\s+/g, ' ').trim()
		req = Object.assign(req, {
			attraction: address.attraction,
			house_number: address.house_number,
			road,
			postcode: address.postcode,
			city,
			state: address.state,
			country: address.country,
			type,
		})
	}
	const url = generateUrl('/apps/maps/contacts/' + bookid + '/' + uri)
	return axios.put(url, req)
}

export function getFavorites(myMapId = null) {
	const conf = {
	    params: {
			myMapId,
		},
	}
	const url = generateUrl('/apps/maps/favorites')
	return axios.get(url, conf)
}

export function getFavoritesByToken(token) {
	const conf = {
	}
	const url = generateUrl('/apps/maps/api/1.0/public/' + token + '/favorites')
	return axios.get(url, conf)
}

export function getSharedFavoriteCategories(myMapId = null) {
	const conf = {
		params: {
			myMapId,
		},
	}
	const url = generateUrl('/apps/maps/favorites-category/shared')
	return axios.get(url, conf)
}

export function addFavorite(lat, lng, name, category = null, comment = null, extensions = null, myMapId = null) {
	const req = {
		name,
		lat,
		lng,
		category,
		comment,
		extensions,
		myMapId,
	}
	const url = generateUrl('/apps/maps/favorites')
	return axios.post(url, req)
}

export function deleteFavorite(favid, myMapId = null) {
	const req = {
		myMapId,
	}
	const url = generateUrl('/apps/maps/favorites/' + favid)
	return axios.delete(url, req)
}

export function renameFavoriteCategory(catIds, newCatName, myMapId = null) {
	const req = {
		categories: catIds,
		newName: newCatName,
		myMapId,
	}
	const url = generateUrl('/apps/maps/favorites-category')
	return axios.put(url, req)
}

export function deleteFavorites(ids, myMapId = null) {
	const req = {
		params: {
			ids,
		},
		myMapId,
	}
	const url = generateUrl('/apps/maps/favorites')
	return axios.delete(url, req)
}

export function editFavorite(id, name, category = null, comment = null, lat = null, lng = null, myMapId = null) {
	const req = {
		name,
		extensions: null,
		myMapId,
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

export function exportFavorites(catIdList, begin = null, end = null, myMapId = null) {
	const req = {
		categoryList: catIdList,
		begin,
		end,
		myMapId,
	}
	const url = generateUrl('/apps/maps/export/favorites')
	return axios.post(url, req)
}

export function importFavorites(path, myMapId = null) {
	const req = {
		path,
		myMapId,
	}
	const url = generateUrl('/apps/maps/import/favorites')
	return axios.post(url, req)
}

export function shareFavoriteCategory(catid) {
	const url = generateUrl('/apps/maps/favorites-category/' + catid + '/share')
	return axios.post(url)
}

export function unshareFavoriteCategory(catid) {
	const url = generateUrl('/apps/maps/favorites-category/' + catid + '/un-share')
	return axios.post(url)
}

export function getPhotos(myMapId = null) {
	const url = generateUrl('/apps/maps/photos')
	const conf = {
		params: {
			myMapId,
		},
	}
	return axios.get(url, conf)
}

export function placePhotos(paths, lats, lngs, directory = false, myMapId = null) {
	const req = {
		paths,
		lats,
		lngs,
		directory,
		myMapId,
	}
	const url = generateUrl('/apps/maps/photos')
	return axios.post(url, req)
}

export function resetPhotosCoords(paths, myMapId = null) {
	const req = {
		params: {
			paths,
		},
		myMapId,
	}
	const url = generateUrl('/apps/maps/photos')
	return axios.delete(url, req)
}

export function getTracks(myMapId = null) {
	const conf = {
		params: {
			myMapId,
		},
	}
	const url = generateUrl('/apps/maps/tracks')
	return axios.get(url, conf)
}

export function getTrack(id, myMapId = null, isFileId = false) {
	const conf = {
		params: {
			myMapId,
		},
	}
	const url = generateUrl('/apps/maps/tracks/' + (isFileId ? 'file/' : '') + id)
	// return axios.get(url, { responseType: 'json' })
	return axios.get(url, conf)
}

export function editTrack(id, color, myMapId = null) {
	const req = {
		color,
		myMapId,
	}
	const url = generateUrl('/apps/maps/tracks/' + id)
	return axios.put(url, req)
}

export function getDevices(myMapId = null) {
	const conf = {
		params: {
			myMapId,
		},
	}
	const url = generateUrl('/apps/maps/devices')
	return axios.get(url, conf)
}

export function getDevice(id, myMapId = null) {
	const conf = {
		params: {
			myMapId,
		},
	}
	const url = generateUrl('/apps/maps/devices/' + id)
	return axios.get(url, conf)
}

export function editDevice(id, name, color, myMapId = null) {
	const req = {
		color,
		name,
		myMapId,
	}
	const url = generateUrl('/apps/maps/devices/' + id)
	return axios.put(url, req)
}

export function exportDevices(deviceIdList, all = false, begin = null, end = null) {
	const req = {
		deviceIdList,
		all,
		begin,
		end,
	}
	const url = generateUrl('/apps/maps/export/devices')
	return axios.post(url, req)
}

export function updateDevicePositions(device) {
	const pruneBefore = (device.points && device.points.length > 0)
		? device.points[device.points.length - 1].timestamp
		: null
	const req = {
		params: {
			pruneBefore,
		},
	}
	const url = generateUrl('/apps/maps/devices/' + device.id)
	return axios.get(url, req)
}

export function importDevices(path) {
	const req = {
		path,
	}
	const url = generateUrl('/apps/maps/import/devices')
	return axios.post(url, req)
}

export function deleteDevice(id) {
	const url = generateUrl('/apps/maps/devices/' + id)
	return axios.delete(url)
}

export function getMyMaps() {
	const url = generateUrl('/apps/maps/maps')
	return axios.get(url)
}

export function addMyMap(newName) {
	const req = {
		id: null,
		values: { newName },
	}
	const url = generateUrl('/apps/maps/maps')
	return axios.post(url, req)
}

export function changeMyMapColor(id, color) {
	const req = {
		values: { color },
	}
	const url = generateUrl('/apps/maps/maps/' + id)
	return axios.post(url, req)
}

export function deleteMyMap(id) {
	const req = {
		id,
	}
	const url = generateUrl('/apps/maps/maps/' + id)
	return axios.delete(url, req)
}

export function renameMyMap(id, newName) {
	const req = {
		id,
		values: { newName },
	}
	const url = generateUrl('/apps/maps/maps/' + id)
	return axios.put(url, req)
}
