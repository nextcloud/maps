module.exports = {
	globals: {
		appVersion: true
	},
	parserOptions: {
		requireConfigFile: false
	},
	extends: [
		'@nextcloud'
	],
	rules: {
		'no-restricted-properties': [
			'error',
			{ property: 'substr', message: 'Use .slice instead of .substr.' },
		],
		'jsdoc/require-jsdoc': 'off',
		'jsdoc/tag-lines': 'off',
		'vue/first-attribute-linebreak': 'off'
	}
}
