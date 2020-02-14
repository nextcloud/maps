const path = require('path');
const { VueLoaderPlugin } = require('vue-loader');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');

module.exports = {
	entry: {
		adminSettings: path.join(__dirname, 'src', 'adminSettings.js'),
		script: path.join(__dirname, 'src', 'script.js'),
	},
	output: {
		path: path.join(__dirname, 'js'),
		publicPath: "/js/",
	},
	module: {
		rules: [
			{
				test: /\.css$/,
				use: ['vue-style-loader', 'css-loader'],
			},
			{
				test: /\.scss$/,
				use: ['vue-style-loader', 'css-loader', 'sass-loader'],
			},
			{
				test: /\.vue$/,
				loader: 'vue-loader',
			},
			{
				test: /\.js$/,
				loader: 'babel-loader',
				exclude: /node_modules/,
			},
			{
				test: /\.(png|jpg|gif|svg|woff|woff2|eot|ttf)$/,
				loader: 'url-loader',
			},
		],
	},
	plugins: [
		new VueLoaderPlugin(),
		new CleanWebpackPlugin(),
	],
	resolve: {
		extensions: ['*', '.js', '.vue'],
	},
}
