const path = require('path')
const webpack = require('webpack')
const { VueLoaderPlugin } = require('vue-loader')
const StyleLintPlugin = require('stylelint-webpack-plugin')
const { CleanWebpackPlugin } = require('clean-webpack-plugin')

module.exports = {
	entry: {
		adminSettings: path.join(__dirname, 'src', 'adminSettings.js'),
		script: path.join(__dirname, 'src', 'script.js'),
		'public-favorite-share': path.join(__dirname, 'src', 'publicFavoriteShare.js'),
	},
	output: {
		path: path.resolve(__dirname, './js'),
		publicPath: '/js/',
		filename: '[name].js',
	},
	module: {
		rules: [
			{
				test: /\.css$/,
				use: ['vue-style-loader', 'css-loader'],
			},
			{
				test: /\.scss$/,
				use: ['vue-style-loader', 'css-loader', {
					loader: 'sass-loader',
					options: {
						// Prefer `node-sass`
						implementation: require('node-sass'),
					},
				}],
			},
			{
				test: /src\/.*\.(js|vue)$/,
				enforce: 'pre',
				loader: 'eslint-loader',
			},
			{
				test: /\.vue$/,
				loader: 'vue-loader',
			},
			{
				test: /\.js$/,
				loader: 'babel-loader',
				exclude: /node_modules\/(?!(p-limit|p-defer|p-queue|p-try|cdav-library))/,
			},
			{
				test: /\.(png|jpg|gif|svg)$/,
				loader: 'file-loader',
				options: {
					name: '[name].[ext]',
					outputPath: '../img',
					publicPath: '/apps/maps/img/',
				},
			},
			{
				test: /\.(woff|woff2|eot|ttf)$/,
				loader: 'url-loader',
			},
		],
	},
	plugins: [
		new VueLoaderPlugin(),
		new webpack.DefinePlugin({
			$appVersion: JSON.stringify(require('./package.json').version),
		}),
		new StyleLintPlugin({
			files: ['**/*.{vue,htm,html,css,sss,less,scss,sass}'],
		}),
		new CleanWebpackPlugin(),
	],
	resolve: {
		extensions: ['*', '.js', '.vue', '.json'],
	},
}
