/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: CC0-1.0
 */

import { recommended } from '@nextcloud/eslint-config'

export default [
	...recommended,
	{
		rules: {
			'no-restricted-properties': [
				'error',
				{ property: 'substr', message: 'Use .slice instead of .substr.' },
			],
			'jsdoc/require-jsdoc': 'off',
			'jsdoc/tag-lines': 'off',
			'vue/first-attribute-linebreak': 'off',
		},
	},
]
