/* jshint esversion: 6 */

import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import {
	showError,
} from '@nextcloud/dialogs'

export function getOptionValues(successCB) {
	const url = generateUrl('/apps/maps/getOptionsValues')
	const req = {}
	axios.post(url, req)
		.then((response) => {
			successCB(response.data)
		})
		.catch((error) => {
			showError(
				t('maps', 'Failed to restore options values.')
			)
			console.debug(error)
		})
}

export function saveOptionValue(optionValues) {
	const req = {
		options: optionValues,
	}
	const url = generateUrl('/apps/cospend/saveOptionValue')
	axios.post(url, req)
		.then((response) => {
		})
		.catch((error) => {
			showError(
				t('maps', 'Failed to save option values')
				+ ': ' + error.response.request.responseText
			)
		})
}
