/**
 * Playwright configuration for the WP-Sweep end-to-end suite.
 *
 * The suite drives a real browser against this plugin's own wp-env, on its own
 * ports, so a clean checkout of this repository alone is enough to run it. Every
 * plugin in the collection has its own pair of ports for the same reason, and
 * E2E uses the tests environment rather than the development one because it
 * creates posts and changes settings.
 *
 * These tests exist because a PHPUnit suite cannot see the things that only go
 * wrong in a browser: a script that was never enqueued, a nonce printed twice,
 * a control that renders correctly and does nothing when clicked. Each of those
 * shipped past a green suite in this collection, every time because the test
 * called a function directly and the function was right on its own terms.
 */

const path = require( 'path' );
const { defineConfig, devices } = require( '@playwright/test' );

// From .wp-env.json, so the port lives in one place. Overridable, for CI or a
// second checkout on another pair.
const { testsPort } = require( './.wp-env.json' );

const baseURL = process.env.WP_BASE_URL || `http://localhost:${ testsPort }`;

const artifactsPath = process.env.WP_ARTIFACTS_PATH || path.join( __dirname, 'artifacts' );

// Where the logged-in admin session is saved by global-setup.js, so the tests
// do not each log in through the form.
process.env.WP_ARTIFACTS_PATH = artifactsPath;
process.env.STORAGE_STATE_PATH =
	process.env.STORAGE_STATE_PATH || path.join( artifactsPath, 'storage-states/admin.json' );

module.exports = defineConfig( {
	testDir: path.join( __dirname, 'tests/e2e' ),
	globalSetup: require.resolve( './tests/e2e/global-setup.js' ),
	outputDir: path.join( artifactsPath, 'test-results' ),

	// A failing E2E test is far more often a real bug than a flake, so a retry
	// locally would hide the thing the suite exists to catch. CI retries once,
	// because a container that has just started is a different kind of flaky.
	retries: process.env.CI ? 1 : 0,
	forbidOnly: !! process.env.CI,

	// One worker. The tests share one WordPress install and change its state;
	// running two at once means one test's settings decide another's assertions.
	workers: 1,
	fullyParallel: false,

	timeout: 60_000,
	expect: { timeout: 10_000 },

	reporter: process.env.CI ? [ [ 'github' ], [ 'list' ] ] : [ [ 'list' ] ],

	use: {
		baseURL,
		storageState: process.env.STORAGE_STATE_PATH,
		// Kept only for a failure: a passing run leaves nothing behind.
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'off',
	},

	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
