/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
	adminSettings: 'src/adminSettings.js',
	main: 'src/main.js',
	'init-files': 'src/init-files.ts',
	'track-metadata-tab': 'src/track-metadata-tab.js',
	'copy-map-link': 'src/copy-map-link.js',
	'report-error-map-action': 'src/report-error-map-action.js',
	publicFavoriteShare: 'src/publicFavoriteShare.js',
}, {
	// Setup REUSE information extraction
	extractLicenseInformation: {
		// Also create .license files for source maps
		includeSourceMaps: true,
	},
	thirdPartyLicense: false,
	// Make sure we have one cache-able CSS entry point per JS entry
	createEmptyCSSEntryPoints: true,
	// Enable CSS code splitting to create correct CSS files per JS entry
	config: {
		build: {
			cssCodeSplit: true,
		},
	},
})
