/**
 * WordPress JS coding standards for WP-Sweep.
 *
 * "recommended-with-formatting" uses native ESLint formatting rules rather
 * than delegating to Prettier, so no Prettier install is needed.
 *
 * Excluded from the SVN deploy, so this never ships to users.
 */
import wordpress from '@wordpress/eslint-plugin';
import globals from 'globals';

export default [
	{
		ignores: [ '**/node_modules/**', '**/vendor/**', '**/*.min.js' ],
	},
	...wordpress.configs[ 'recommended-with-formatting' ],
	{
		languageOptions: {
			globals: {
				...globals.browser,
				// Localised into the page by wp_localize_script().
				wpSweepL10n: 'readonly',
			},
		},
		settings: {
			react: { version: '18.0' },
		},
	},
	{
		files: [ 'tests/js/**/*.test.js' ],
		languageOptions: {
			globals: {
				...globals.node,
			},
		},
	},
	{
		// The Playwright suite is CommonJS and runs under Node, not in a page:
		// it requires its helpers and exports nothing to a browser.
		files: [ 'tests/e2e/**/*.js', 'playwright.config.js' ],
		languageOptions: {
			sourceType: 'commonjs',
			globals: {
				...globals.node,
			},
		},
	},
];
