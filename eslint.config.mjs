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
				// Defined by wp-admin on every admin screen.
				ajaxurl: 'readonly',
			},
		},
		rules: {
			// Properties stay exempt: the wp_localize_script() keys and the
			// fields posted to admin-ajax.php are named on the PHP side, so
			// their keys are snake_case by necessity.
			camelcase: [ 'error', { properties: 'never' } ],
		},
		settings: {
			react: { version: '18.0' },
		},
	},
];
