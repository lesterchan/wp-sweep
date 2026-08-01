/**
 * Logs in once, before any test runs.
 *
 * RequestUtils authenticates over the REST API and writes the session to
 * storageState, which every test then starts from. Without this each test would
 * drive wp-login.php itself, which is a slow way to assert nothing about the
 * plugin.
 */

const { request } = require( '@playwright/test' );
const { RequestUtils } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * @param {import('@playwright/test').FullConfig} config Resolved Playwright config.
 * @return {Promise<void>} Resolves once the admin session is stored.
 */
module.exports = async function globalSetup( config ) {
	const { storageState, baseURL } = config.projects[ 0 ].use;

	const context = await request.newContext( { baseURL } );

	const requestUtils = new RequestUtils( context, {
		storageStatePath: storageState,
	} );

	await requestUtils.setupRest();

	await context.dispose();
};
