/**
 * The REST routes.
 *
 * Three routes -- count, details and sweep -- under `sweep/v1`, the bare noun
 * rather than the plugin slug. A `wp-` prefix is a wordpress.org directory
 * convention for keeping one plugin's download page apart from another's, and
 * says nothing about what a plugin should call what it registers. Another
 * plugin can claim the same bare noun and WordPress will not detect it; that is
 * the accepted trade, and it is the namespace the released 1.2.0 already
 * shipped.
 *
 * They are the same three calls the screen makes, so what is worth testing here
 * is what only the REST layer decides: that a name it does not implement is
 * refused by the route rather than reaching the engine, that the delete really
 * is a DELETE, and that an unauthenticated caller cannot sweep a site by
 * curling one URL.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	INTACT,
	createFixtures,
	createJunk,
	sweepCount,
	survivors,
} = require( './helpers.js' );

/** Every route lives under this namespace. */
const NAMESPACE = '/sweep/v1';

test.describe( 'The REST routes', () => {
	let ids;

	test.beforeEach( async () => {
		ids = createFixtures();
	} );

	test( 'the fixture really is the namespace this plugin registered', async ( {
		requestUtils,
	} ) => {
		// Everything below calls three paths under one namespace. If the
		// namespace were ever renamed, every one of those calls would 404 and
		// the "an unknown name is refused" tests would pass for the wrong
		// reason.
		const index = await requestUtils.rest( { path: '/' } );

		expect( index.namespaces ).toContain( 'sweep/v1' );
	} );

	test( 'count answers with the same number the engine reports', async ( { requestUtils } ) => {
		// Spam comments rather than transients: WordPress writes its own site
		// transients back on the very next request, so a count read twice a
		// second apart would not be the same number twice.
		const expected = createJunk( 'spam_comments' );
		expect( expected ).toBeGreaterThan( 0 );

		const response = await requestUtils.rest( { path: `${ NAMESPACE }/count/spam_comments` } );

		expect( response.name ).toBe( 'spam_comments' );
		expect( response.count ).toBe( expected );
		// Reading a count deletes nothing, which is worth saying out loud on a
		// plugin where two of the three routes look very similar.
		expect( sweepCount( 'spam_comments' ) ).toBe( expected );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'details answers with a sample and its size', async ( { requestUtils } ) => {
		createJunk( 'spam_comments' );

		const response = await requestUtils.rest( {
			path: `${ NAMESPACE }/details/spam_comments`,
		} );

		expect( response.name ).toBe( 'spam_comments' );
		expect( response.data.length ).toBe( response.count );
		expect( response.data.join( ' ' ) ).toContain( 'Sweep junk commenter' );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'sweep removes the junk and says what it did', async ( { requestUtils } ) => {
		createJunk( 'spam_comments' );

		const response = await requestUtils.rest( {
			method: 'DELETE',
			path: `${ NAMESPACE }/sweep/spam_comments`,
		} );

		expect( response.success ).toBe( true );
		expect( response.message ).toContain( 'Spam Comments Processed' );
		expect( sweepCount( 'spam_comments' ) ).toBe( 0 );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'sweeping an empty sweep succeeds and says there was nothing to do', async ( {
		requestUtils,
	} ) => {
		await requestUtils.rest( {
			method: 'DELETE',
			path: `${ NAMESPACE }/sweep/spam_comments`,
		} );

		const response = await requestUtils.rest( {
			method: 'DELETE',
			path: `${ NAMESPACE }/sweep/spam_comments`,
		} );

		// Not an error: there was nothing to remove, which is a perfectly
		// ordinary answer and the one a scheduled job gets most of the time.
		expect( response.success ).toBe( false );
		expect( response.message ).toBe( 'No items left to sweep.' );
	} );

	test( 'the sweep route only answers DELETE', async ( { requestUtils } ) => {
		createJunk( 'spam_comments' );
		const before = sweepCount( 'spam_comments' );

		// rejects.toMatchObject, not rejects.toThrow. requestUtils.rest() does
		// `throw json` on a non-2xx -- it rejects with the parsed error body,
		// a plain object and not an Error -- and toThrow() only recognises an
		// Error, so it reported "did not throw" for a call that had rejected.
		// Asserting the body is the stronger check anyway: it pins *why* the
		// call was refused, so a route that started 500ing would no longer
		// satisfy this test.
		await expect(
			requestUtils.rest( { path: `${ NAMESPACE }/sweep/spam_comments` } ),
		).rejects.toMatchObject( { code: 'rest_no_route', data: { status: 404 } } );

		// A destructive route that answered GET would be one a link, a
		// prefetcher or a crawler could fire.
		expect( sweepCount( 'spam_comments' ) ).toBe( before );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'a name this plugin does not implement is refused by every route', async ( {
		requestUtils,
	} ) => {
		for ( const route of [ 'count', 'details', 'sweep' ] ) {
			// See the note above on toMatchObject. rest_invalid_param is the
			// answer from the route's own validate_callback, which is the
			// point of the test: 400 rather than 404 is what says the name was
			// rejected by is_sweep_name_valid() rather than by there being no
			// such route.
			await expect(
				requestUtils.rest( {
					method: 'sweep' === route ? 'DELETE' : 'GET',
					path: `${ NAMESPACE }/${ route }/not_a_sweep`,
				} ),
			).rejects.toMatchObject( { code: 'rest_invalid_param', data: { status: 400 } } );
		}

		// The validation is on the route rather than inside the engine, so the
		// name never reaches the switch statements at all.
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'a logged out caller cannot count, inspect or sweep', async ( { page } ) => {
		createJunk( 'spam_comments' );
		const before = sweepCount( 'spam_comments' );

		// A context with no session at all, which is what a curl from outside
		// looks like.
		const context = await page.context().browser().newContext( { storageState: undefined } );
		const guest = await context.newPage();

		try {
			for ( const [ method, route ] of [
				[ 'GET', 'count' ],
				[ 'GET', 'details' ],
				[ 'DELETE', 'sweep' ],
			] ) {
				const response = await guest.request.fetch(
					`/index.php?rest_route=${ NAMESPACE }/${ route }/spam_comments`,
					{ method },
				);

				expect( response.status() ).toBe( 401 );
			}
		} finally {
			await context.close();
		}

		// Nothing was swept by any of those, which is the assertion that
		// matters on a route that deletes.
		expect( sweepCount( 'spam_comments' ) ).toBe( before );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );
} );
