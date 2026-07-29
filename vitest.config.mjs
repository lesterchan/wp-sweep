/**
 * Vitest configuration.
 *
 * jsdom everywhere, so a test file never has to declare its own environment.
 * Excluded from the SVN deploy by the vitest.config.* rule, so this never
 * ships to users.
 */
import { defineConfig } from 'vitest/config';

export default defineConfig( {
	test: {
		environment: 'jsdom',
		include: [ 'tests/js/**/*.test.js' ],
		restoreMocks: true,
	},
} );
