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
	overrides: [
		{
			// Allow `<script setup lang="ts">` in Vue SFCs being ported to TypeScript
			files: ['*.vue'],
			parserOptions: {
				parser: '@typescript-eslint/parser',
				sourceType: 'module',
				extraFileExtensions: ['.vue'],
			},
		},
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
