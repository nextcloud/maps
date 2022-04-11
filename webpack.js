const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

const buildMode = process.env.NODE_ENV
const isDev = buildMode === 'development'
webpackConfig.devtool = isDev ? 'cheap-source-map' : 'source-map'

webpackConfig.stats = {
	colors: true,
	modules: false,
}

webpackConfig.entry = {
	adminSettings: { import: path.join(__dirname, 'src', 'adminSettings.js'), filename: 'maps-adminSettings.js' },
	// script: { import: path.join(__dirname, 'src', 'script.js'), filename: 'maps-script.js' },
	main: { import: path.join(__dirname, 'src', 'main.js'), filename: 'maps-main.js' },
	'public-favorite-share': { import: path.join(__dirname, 'src', 'publicFavoriteShare.js'), filename: 'maps-publicFavoriteShare.js' },
}

module.exports = webpackConfig
