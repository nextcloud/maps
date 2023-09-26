import axios from '@nextcloud/axios'
import { default as realAxios } from 'axios'
import { generateUrl } from '@nextcloud/router'
import {
	showError,
} from '@nextcloud/dialogs'

export function saveOptionValues(optionValues, myMapId = null, token = null) {
	const req = {
		options: optionValues,
		myMapId,
	}
	const url = generateUrl('/apps/maps' + (token ? '/s/' + token : '') + '/saveOptionValue')
	axios.post(url, req)
		.then((response) => {
		})
		.catch((error) => {
			console.log('Failed to save option values' + ': ' + error.response?.request?.responseText)
		})
}

export function getOptionValues(myMapId = null, token = null) {
	const url = generateUrl('/apps/maps' + (token ? '/s/' + token : '') + '/getOptionsValues')
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

export function getContacts(myMapId = null, token = null) {
	const conf = {
		params: {
			myMapId,
		},
	}
	const url = generateUrl('/apps/maps' + (token ? '/s/' + token : '') + '/contacts')
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
	const url = 'https://nominatim.openstreetmap.org/search?q=' + query + '&format=json&addressdetails=1&extratags=1&namedetails=1&limit=' + limit
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

export function deleteContactAddress(bookid, uri, uid, vcardAddress = '', vcardGEO = '', fileId = null, myMapId = null) {
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

export function placeContact(bookid, uri, uid, lat, lng, address = null, type = 'home', fileId = null, myMapId = null) {
	let req = {
		lat,
		lng,
		uid,
		fileId,
		myMapId,
	}
	if (address && (typeof address === 'string' || address instanceof String)) {
		req = Object.assign(req, {
			address_string: address,
		})
	} else if (address) {
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

export function addContactToMap(bookid, uri, uid, myMapId, fileId = null) {
	const req = {
		uid,
		fileId,
		myMapId,
	}
	const url = generateUrl('apps/maps/contacts/' + bookid + '/' + uri + '/add-to-map/')
	return axios.put(url, req)
}

export function getFavorites(myMapId = null, token = null) {
	const conf = {
	    params: {
			myMapId,
		},
	}
	const url = generateUrl('/apps/maps' + (token ? '/s/' + token : '') + '/favorites')
	return axios.get(url, conf)
}

export function getFavoritesByToken(token) {
	const conf = {
	}
	const url = generateUrl('/apps/maps/api/1.0/public/' + token + '/favorites')
	return axios.get(url, conf)
}

export function getSharedFavoriteCategories(myMapId = null, token = null) {
	const conf = {
		params: {
			myMapId,
		},
	}
	const url = generateUrl('/apps/maps' + (token ? '/s/' + token : '') + '/favorites-category/shared')
	return axios.get(url, conf)
}

export function addFavorite(
	lat,
	lng,
	name,
	category = null,
	comment = null,
	extensions = null,
	myMapId = null,
	token = null) {
	const req = {
		name,
		lat,
		lng,
		category,
		comment,
		extensions,
		myMapId,
	}
	const url = generateUrl('/apps/maps' + (token ? '/s/' + token : '') + '/favorite')
	return axios.post(url, req)
}

export function addFavorites(favorites, myMapId, token = null) {
	const req = {
		favorites,
		myMapId,
	}
	const url = generateUrl('/apps/maps' + (token ? '/s/' + token : '') + '/favorites')
	return axios.post(url, req)
}

export function deleteFavorite(favid, myMapId = null, token = null) {
	const req = {
		params: {
			myMapId,
		},
	}
	const url = generateUrl('/apps/maps' + (token ? '/s/' + token : '') + '/favorites/' + favid)
	return axios.delete(url, req)
}

export function renameFavoriteCategory(catIds, newCatName, myMapId = null, token = null) {
	const req = {
		categories: catIds,
		newName: newCatName,
		myMapId,
	}
	const url = generateUrl('/apps/maps' + (token ? '/s/' + token : '') + '/favorites-category')
	return axios.put(url, req)
}

export function deleteFavorites(ids, myMapId = null, token = null) {
	const req = {
		params: {
			ids,
			myMapId,
		},
	}
	const url = generateUrl('/apps/maps' + (token ? '/s/' + token : '') + '/favorites')
	return axios.delete(url, req)
}

export function editFavorite(
	id,
	name,
	category = null,
	comment = null,
	lat = null,
	lng = null,
	myMapId = null,
	token = null) {
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
	const url = generateUrl('/apps/maps' + (token ? '/s/' + token : '') + '/favorites/' + id)
	return axios.put(url, req)
}

export function exportFavorites(
	catIdList,
	begin = null,
	end = null,
	myMapId = null) {
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

export async function getPhotos(myMapId = null, token = null) {
	const url = generateUrl('/apps/maps' + (token ? '/s/' + token : '') + '/photos')
	const conf = {
		params: {
			myMapId,
		},
	}
	return axios.get(url, conf)
}

export async function getBackgroundJobStatus() {
	const url = generateUrl('/apps/maps/photos/backgroundJobStatus')
	return axios.get(url)
}

export async function clearPhotoCache(token = null) {
	const url = generateUrl('/apps/maps' + (token ? '/s/' + token : '') + '/photos/clearCache')
	return axios.get(url)
}

export async function getPhotoSuggestions(myMapId = null, token = null, timezone = null, limit = null, offset = null) {
	const url = generateUrl('apps/maps' + (token ? '/s/' + token : '') + '/photos/nonlocalized')
	const conf = {
		params: {
			myMapId,
			timezone,
			limit,
			offset,
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
			myMapId,
		},
	}
	const url = generateUrl('/apps/maps/photos')
	return axios.delete(url, req)
}

export function getTracks(myMapId = null, token = null) {
	const conf = {
		params: {
			myMapId,
		},
	}
	const url = generateUrl('/apps/maps' + (token ? '/s/' + token : '') + '/tracks')
	return axios.get(url, conf)
}

export function getTrack(id, myMapId = null, isFileId = false, token = null) {
	const conf = {
		params: {
			myMapId,
		},
	}
	const url = generateUrl('/apps/maps' + (token ? '/s/' + token : '') + '/tracks/' + (isFileId ? 'file/' : '') + id)
	// return axios.get(url, { responseType: 'json' })
	return axios.get(url, conf)
}

export function editTrack(id, color, myMapId = null, token = null) {
	const req = {
		color,
		myMapId,
	}
	const url = generateUrl('/apps/maps' + (token ? '/s/' + token : '') + '/tracks/' + id)
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

export async function getDevice(id, myMapId = null, limit = null, offset = null, tokens = null) {
	const conf = {
		params: {
			myMapId,
			limit,
			offset,
			tokens,
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

export function shareDevice(id, timestampFrom, timestampTo) {
	const url = generateUrl('/apps/maps/devices/' + id + '/share')
	const req = {
		timestampFrom,
		timestampTo,
	}
	return axios.post(url, req)
}

export function addSharedDeviceToMap(token, targetMapId) {
	const url = generateUrl('/apps/maps/devices/s/' + token + '/map-link/' + targetMapId)
	return axios.post(url)
}

export function removeDeviceShare(token) {
	const url = generateUrl('/apps/maps/devices/s/' + token)
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

export function copyByPath(from, to) {
	const fileClient = OC.Files.getClient()
	return fileClient.copy(from, to, false)
}

export function addSharedFavoriteCategoryToMap(sharedCategory, targetMapId, myMapId = null) {
	const req = {
		myMapId,
	}
	const url = generateUrl('apps/maps/favorites-category/' + sharedCategory + '/add-to-map/' + targetMapId)
	return axios.put(url, req)
}

export function deleteSharedFavoriteCategoryFromMap(catId, myMapId) {
	const req = {
		params: {
			myMapId,
		},
	}
	const url = generateUrl('apps/maps/favorites-category/' + catId + '/')
	return axios.delete(url, req)
}
