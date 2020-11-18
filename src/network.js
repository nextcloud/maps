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
