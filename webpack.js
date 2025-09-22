const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

const buildMode = process.env.NODE_ENV
const isDev = buildMode === 'development'
webpackConfig.devtool = isDev ? 'cheap-source-map' : 'source-map'

webpackConfig.stats = {
	colors: true,
	modules: false,
}

webpackConfig.module.rules.push({
	resourceQuery: /raw/,
	type: 'asset/source',
})

webpackConfig.entry = {
	adminSettings: { import: path.join(__dirname, 'src', 'adminSettings.js'), filename: 'maps-adminSettings.js' },
	// script: { import: path.join(__dirname, 'src', 'script.js'), filename: 'maps-script.js' },
	main: { import: path.join(__dirname, 'src', 'main.js'), filename: 'maps-main.js' },
	'init-files': { import: path.join(__dirname, 'src', 'init-files.ts'), filename: 'maps-init-files.js' },
	'track-metadata-tab': { import: path.join(__dirname, 'src', 'track-metadata-tab.js'), filename: 'maps-track-metadata-tab.js' },
	'copy-map-link': { import: path.join(__dirname, 'src', 'copy-map-link.js'), filename: 'maps-copy-map-link.js' },
	'report-error-map-action': { import: path.join(__dirname, 'src', 'report-error-map-action.js'), filename: 'maps-report-error-map-action.js' },
	'public-favorite-share': { import: path.join(__dirname, 'src', 'publicFavoriteShare.js'), filename: 'maps-publicFavoriteShare.js' },
}

module.exports = webpackConfig
