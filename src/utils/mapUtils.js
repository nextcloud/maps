/**
 * @copyright Copyright (c) 2019, Paul Schwörer <hello@paulschwoerer.de>
 *
 * @author Paul Schwörer <hello@paulschwoerer.de>
 * @author Nextcloud Maps contributors
 *
 * @license AGPL-3.0-or-later
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

import { request } from './common.js'

export const isGeocodeable = str => {
	const pattern = /^\s*-?\d+\.?\d*,\s*-?\d+\.?\d*\s*$/

	return pattern.test(str)
}

export const constructGeoCodeUrl = (lat, lng) =>
	`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`

export const geocode = latLngStr => {
	if (!isGeocodeable(latLngStr)) {
		return Promise.reject(new Error(`${latLngStr} is not geocodable`))
	}

	const latLng = latLngStr.split(',')

	const lat = latLng[0].trim()
	const lng = latLng[1].trim()

	return request(constructGeoCodeUrl(lat, lng), 'GET')
}

export const getShouldMapUseImperial = () => {
	const locale = OC.getLocale()

	return (
		locale === 'en_US'
    || locale === 'en_GB'
    || locale === 'en_AU'
    || locale === 'en_IE'
    || locale === 'en_NZ'
    || locale === 'en_CA'
	)
}

export const geoToLatLng = (geo) => {
	let ll
	const fourFirsts = geo.slice(0, 4)
	if (fourFirsts === 'geo:') {
		ll = geo.slice(4).split(',')
	} else {
		ll = geo.split(';')
	}
	return ll
}

export const getFormattedADR = (adr) => {
	const adrTab = adr.split(';')
	let formattedAddress = ''
	if (adrTab.length > 6) {
		// check if street name is set
		if (adrTab[2] !== '') {
			formattedAddress += adrTab[2] + ' '
		}
		formattedAddress += adrTab[5] + ' ' + adrTab[3] + ' ' + adrTab[4] + ' ' + adrTab[6]
	}
	return formattedAddress.trim()
}
