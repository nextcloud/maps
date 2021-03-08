const path = require('path')
const webpack = require('webpack')
const webpackConfig = require('@nextcloud/webpack-vue-config')
const { VueLoaderPlugin } = require('vue-loader')
const StyleLintPlugin = require('stylelint-webpack-plugin')
const { CleanWebpackPlugin } = require('clean-webpack-plugin')

webpackConfig.stats = {
	colors: true,
	modules: false,
}

module.entry = {
	adminSettings: path.join(__dirname, 'src', 'adminSettings.js'),
	main: { import: path.join(__dirname, 'src', 'main.js'), filename: 'maps-main.js' },
	'public-favorite-share': { import: path.join(__dirname, 'src', 'publicFavoriteShare.js'), filename: 'maps-publicFavoriteShare.js' },
}

module.exports = webpackConfig
