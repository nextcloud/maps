import { createAppConfig } from '@nextcloud/vite-config'
import { join } from 'path'

const isProduction = process.env.NODE_ENV === 'production'

export default createAppConfig({
	adminSettings: join(import.meta.dirname, 'src', 'adminSettings.js'),
	main: join(import.meta.dirname, 'src', 'main.js'),
	'init-files': join(import.meta.dirname, 'src', 'init-files.ts'),
	'track-metadata-tab': join(import.meta.dirname, 'src', 'track-metadata-tab.js'),
	'copy-map-link': join(import.meta.dirname, 'src', 'copy-map-link.js'),
	'report-error-map-action': join(import.meta.dirname, 'src', 'report-error-map-action.js'),
	publicFavoriteShare: join(import.meta.dirname, 'src', 'publicFavoriteShare.js'),
}, {
	minify: isProduction,
	thirdPartyLicense: false,
	extractLicenseInformation: true,
	createEmptyCSSEntryPoints: true,
	emptyOutputDirectory: {
		additionalDirectories: ['css'],
	},
	config: {
		resolve: {
			alias: {
				'vue-demi': join(import.meta.dirname, 'node_modules/@vueuse/shared/node_modules/vue-demi/lib/v3/index.mjs'),
			},
		},
	},
})
