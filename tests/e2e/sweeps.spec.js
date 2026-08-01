/**
 * Every sweep, one test each: its junk created, swept from the screen, and the
 * real content proved to have survived.
 *
 * There are nineteen sweeps and nineteen tests here rather than one loop over a
 * list of names, because each one is a different pair of queries against
 * different tables, and the thing worth knowing is not "sweeping works" but
 * "this sweep removed exactly what it says it removes". A shared loop that only
 * asserted the count went to zero would pass for a sweep that emptied the whole
 * table.
 *
 * The shape is the same in each: build the junk, check the plugin can see it,
 * press the button on the screen, check the count is zero and the survivors are
 * untouched. Every test is self-contained, because a sweep is site-wide -- a
 * fixture made in beforeAll would be gone by the second test.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	INTACT,
	createFixtures,
	createJunk,
	openSweepScreen,
	row,
	sweepCount,
	sweepRow,
	survivors,
	totalCount,
	wpEvalJson,
} = require( './helpers.js' );

test.describe( 'Sweeping', () => {
	let ids;

	test.beforeEach( async () => {
		// The survivors are rebuilt for every test, because the test before may
		// legitimately have swept something they leaned on -- an unused term, an
		// orphaned relationship -- and a fixture rebuilt is cheaper to reason
		// about than a fixture that has to survive eighteen deletions.
		ids = createFixtures();
	} );

	test( 'the fixture really is content every sweep must leave alone', () => {
		// Every test below ends with the same assertion, so if the fixture were
		// ever built wrong they would all pass while checking nothing. This is
		// the test that reads it once, before any sweeping has happened.
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'revisions go and the post they belong to stays', async ( { page } ) => {
		expect( createJunk( 'revisions' ) ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'revisions' );

		expect( sweepCount( 'revisions' ) ).toBe( 0 );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'auto drafts go and published posts stay', async ( { page } ) => {
		expect( createJunk( 'auto_drafts' ) ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'auto_drafts' );

		expect( sweepCount( 'auto_drafts' ) ).toBe( 0 );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'trashed posts go and published posts stay', async ( { page } ) => {
		expect( createJunk( 'deleted_posts' ) ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'deleted_posts' );

		expect( sweepCount( 'deleted_posts' ) ).toBe( 0 );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'unapproved comments go and the approved one stays', async ( { page } ) => {
		expect( createJunk( 'unapproved_comments' ) ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'unapproved_comments' );

		expect( sweepCount( 'unapproved_comments' ) ).toBe( 0 );
		// The survivor comment is approved, so this is the assertion that the
		// sweep read comment_approved rather than emptying the table.
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'spam comments go and the approved one stays', async ( { page } ) => {
		expect( createJunk( 'spam_comments' ) ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'spam_comments' );

		expect( sweepCount( 'spam_comments' ) ).toBe( 0 );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'trashed comments go and the approved one stays', async ( { page } ) => {
		expect( createJunk( 'deleted_comments' ) ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'deleted_comments' );

		expect( sweepCount( 'deleted_comments' ) ).toBe( 0 );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'transients go and ordinary options stay', async ( { page } ) => {
		expect( createJunk( 'transient_options' ) ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'transient_options' );

		// The transient this test made, by name, rather than the sweep's count:
		// WordPress writes its own site transients back on the very next
		// request -- update checks, theme roots, the cron lock -- so this is
		// the one sweep that cannot be asked to end at zero.
		expect( wpEvalJson( "false === get_transient( 'wp_sweep_e2e_transient' )" ) ).toBe( true );

		// Options are not in survivors(), so the guard here is a row nothing
		// could run without: the sweep matches on a name pattern, and a pattern
		// that matched too much would take the whole install with it.
		expect( wpEvalJson( "'' !== (string) get_option( 'siteurl' )" ) ).toBe( true );
		expect( totalCount( 'options' ) ).toBeGreaterThan( 20 );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'orphaned post meta goes and meta on a real post stays', async ( { page } ) => {
		expect( createJunk( 'orphan_postmeta' ) ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'orphan_postmeta' );

		expect( sweepCount( 'orphan_postmeta' ) ).toBe( 0 );
		// postMeta in the survivors is exactly the "meta whose post still
		// exists" case, which is what tells this sweep from one that empties
		// the meta table.
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'orphaned comment meta goes and meta on a real comment stays', async ( { page } ) => {
		expect( createJunk( 'orphan_commentmeta' ) ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'orphan_commentmeta' );

		expect( sweepCount( 'orphan_commentmeta' ) ).toBe( 0 );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'orphaned user meta goes and meta on a real user stays', async ( { page } ) => {
		expect( createJunk( 'orphan_usermeta' ) ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'orphan_usermeta' );

		expect( sweepCount( 'orphan_usermeta' ) ).toBe( 0 );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'orphaned term meta goes and meta on a real term stays', async ( { page } ) => {
		expect( createJunk( 'orphan_termmeta' ) ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'orphan_termmeta' );

		expect( sweepCount( 'orphan_termmeta' ) ).toBe( 0 );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'orphaned term relationships go and a real one stays', async ( { page } ) => {
		expect( createJunk( 'orphan_term_relationships' ) ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'orphan_term_relationships' );

		expect( sweepCount( 'orphan_term_relationships' ) ).toBe( 0 );
		// The survivor post carries the survivor term, so the relationship
		// count in there is the other half: the sweep removed the row pointing
		// at a post that is gone and left the one pointing at a post that is
		// not.
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'unused terms go and a term attached to a post stays', async ( { page } ) => {
		expect( createJunk( 'unused_terms' ) ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'unused_terms' );

		expect( sweepCount( 'unused_terms' ) ).toBe( 0 );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'duplicated post meta loses its copies and keeps one', async ( { page } ) => {
		expect( createJunk( 'duplicated_postmeta' ) ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'duplicated_postmeta' );

		expect( sweepCount( 'duplicated_postmeta' ) ).toBe( 0 );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'duplicated comment meta loses its copies and keeps one', async ( { page } ) => {
		expect( createJunk( 'duplicated_commentmeta' ) ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'duplicated_commentmeta' );

		expect( sweepCount( 'duplicated_commentmeta' ) ).toBe( 0 );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'duplicated user meta loses its copies and keeps one', async ( { page } ) => {
		expect( createJunk( 'duplicated_usermeta' ) ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'duplicated_usermeta' );

		expect( sweepCount( 'duplicated_usermeta' ) ).toBe( 0 );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'duplicated term meta loses its copies and keeps one', async ( { page } ) => {
		expect( createJunk( 'duplicated_termmeta' ) ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'duplicated_termmeta' );

		expect( sweepCount( 'duplicated_termmeta' ) ).toBe( 0 );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'oEmbed caches go and other post meta stays', async ( { page } ) => {
		expect( createJunk( 'oembed_postmeta' ) ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'oembed_postmeta' );

		expect( sweepCount( 'oembed_postmeta' ) ).toBe( 0 );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'optimizing the tables removes nothing at all', async ( { page } ) => {
		// The one sweep that is not a delete. Its count is the number of tables
		// and stays exactly where it was afterwards, which is the difference
		// between reclaiming space and losing data -- and the reason the row
		// keeps its button rather than turning into a dash.
		const tablesBefore = sweepCount( 'optimize_database' );
		const postsBefore = totalCount( 'posts' );
		const commentsBefore = totalCount( 'comments' );

		expect( tablesBefore ).toBeGreaterThan( 0 );

		await openSweepScreen( page );
		await sweepRow( page, 'optimize_database' );

		expect( sweepCount( 'optimize_database' ) ).toBe( tablesBefore );
		expect( totalCount( 'posts' ) ).toBe( postsBefore );
		expect( totalCount( 'comments' ) ).toBe( commentsBefore );
		expect( survivors( ids ) ).toEqual( INTACT );

		// The row is still sweepable, unlike every other row once it is empty.
		await expect( row( page, 'optimize_database' ).locator( 'a.btn-sweep' ) ).toBeVisible();
	} );

	test( 'a swept row loses its buttons and says there is nothing left', async ( { page } ) => {
		// Spam rather than transients: WordPress writes its own site transients
		// back on the next request, so that row would have something to sweep
		// again by the time the screen was reloaded.
		createJunk( 'spam_comments' );

		await openSweepScreen( page );

		const target = row( page, 'spam_comments' );
		await expect( target.locator( 'a.btn-sweep' ) ).toBeVisible();

		await sweepRow( page, 'spam_comments' );

		// The count in the row is rewritten from the response rather than the
		// screen being reloaded, and the actions cell becomes the same dash a
		// fresh page load would have drawn -- so a swept row and a reloaded one
		// agree.
		await expect( target.locator( '.sweep-count' ) ).toHaveText( '0' );
		await expect( target.locator( 'a.btn-sweep' ) ).toHaveCount( 0 );
		await expect( target.locator( '.sweep-nothing' ) ).toBeVisible();
		// The checkbox stays: every row has one, empty or not, or the column
		// gains holes and select-all starts claiming rows it does not select.
		await expect( target.locator( 'input[type="checkbox"]' ) ).toBeAttached();

		await page.reload();
		await expect( row( page, 'spam_comments' ).locator( '.sweep-nothing' ) ).toBeVisible();
	} );

	test( 'the running totals above the table are rewritten after a sweep', async ( { page } ) => {
		createJunk( 'revisions' );

		await openSweepScreen( page );

		/**
		 * One running total, as a number rather than as formatted text.
		 *
		 * @param {string} type Sweep type.
		 * @return {Promise<number>} What the screen currently says.
		 */
		const shown = async ( type ) =>
			parseInt(
				( await page.locator( `.sweep-totals .sweep-count-type-${ type }` ).textContent() )
					.replace( /\D/g, '' ),
				10,
			);

		const postsBefore = await shown( 'posts' );

		await sweepRow( page, 'revisions' );

		// The screen agrees with the database without having been reloaded.
		expect( await shown( 'posts' ) ).toBe( totalCount( 'posts' ) );
		expect( await shown( 'posts' ) ).toBeLessThan( postsBefore );

		// Deleting a post takes its meta with it, so the postmeta total is
		// stale the moment a post sweep finishes even though nothing swept
		// postmeta -- which is why the response carries every related total
		// rather than only the one that was swept.
		expect( await shown( 'postmeta' ) ).toBe( totalCount( 'postmeta' ) );
	} );
} );
