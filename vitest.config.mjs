/**
 * Vitest configuration for WP-Sweep.
 *
 * The admin script is an IIFE that attaches delegated listeners to `document`
 * and is loaded into a jsdom page, so the tests drive it the same way an
 * administrator does: build the markup the PHP side emits, dispatch a real
 * click, then assert on the DOM and on what was sent to admin-ajax.php.
 *
 * Excluded from the SVN deploy, so this never ships to users.
 */
export default {
	test: {
		environment: 'jsdom',
		include: [ 'tests/js/**/*.test.js' ],
		restoreMocks: true,
	},
};
