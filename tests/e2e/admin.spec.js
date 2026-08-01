/**
 * The Sweep screen itself: where it lives, who may open it, and the parts of it
 * that are not a sweep -- the totals table, the group filters, the sortable
 * columns, the bulk action, the Details row action, and the guard that stops a
 * sweep being abandoned half way.
 *
 * Every row action on this screen is a real, nonced link that works with the
 * script turned off; the script intercepts them so the page does not reload
 * nineteen times. Both paths are driven here, because they are two different
 * pieces of code that have to agree.
 *
 * There is no confirm() anywhere in this plugin -- the warning notice above the
 * table and the beforeunload guard are what stand in for one -- so the "both
 * answers" rule is applied to the guard: it must be armed while a sweep is
 * running and disarmed the rest of the time.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	GROUPS,
	GROUP_LABELS,
	INTACT,
	SWEEPS,
	SWEEP_URL,
	createFixtures,
	createJunk,
	ensureUser,
	installMuPlugin,
	loginAs,
	openSweepScreen,
	removeMuPlugin,
	row,
	sweepCount,
	sweepDirectly,
	survivors,
} = require( './helpers.js' );

/** A password for the throwaway accounts the capability tests log in as. */
const PASSWORD = 'correct-horse-battery-staple';

/** The mu-plugin the group-action test hangs its markers off. */
const GROUP_HOOKS = 'wp-sweep-e2e-group-hooks.php';

test.describe( 'The Sweep screen', () => {
	let ids;

	test.beforeEach( async () => {
		ids = createFixtures();
	} );

	test.afterEach( async () => {
		removeMuPlugin( GROUP_HOOKS );
	} );

	test( 'the fixture really is nineteen sweeps on one page under Tools', async ( { page } ) => {
		// Everything below addresses rows by name and leans on all of them
		// being on one page: there are nineteen sweeps and the table shows
		// twenty, so nothing pages and a row that is missing is missing rather
		// than overleaf.
		await page.goto( '/wp-admin/tools.php' );

		await page.locator( '#menu-tools' ).hover();
		await page.locator( '#menu-tools' ).getByRole( 'link', { name: 'WP-Sweep' } ).click();

		await expect( page.getByRole( 'heading', { name: 'Sweep', exact: true } ) ).toBeVisible();
		expect( page.url() ).toContain( 'tools.php?page=wp-sweep' );
		await expect( page.locator( '#the-list tr' ) ).toHaveCount( SWEEPS.length );
		// Nineteen sweeps and twenty rows to a page, so there is exactly one
		// page and nothing below is ever overleaf.
		await expect( page.locator( '.tablenav-pages .displaying-num' ).first() ).toContainText(
			String( SWEEPS.length ),
		);

		for ( const name of SWEEPS ) {
			await expect( row( page, name ) ).toHaveCount( 1 );
		}
	} );

	test( 'the screen tells a site owner to back up before doing anything', async ( { page } ) => {
		await openSweepScreen( page );

		// Every row on this screen deletes data that does not come back, so the
		// warning is part of the screen rather than decoration -- and so is the
		// sentence saying how long a details list can get.
		const notice = page.locator( '.notice-warning' );
		await expect( notice ).toBeVisible();
		await expect( notice ).toContainText( 'backup your database' );
		await expect( notice.locator( 'a[href*="wp-dbmanager"]' ) ).toBeAttached();

		await expect( page.locator( 'p.description' ).first() ).toContainText(
			'Details lists a sample of up to 500 items',
		);
	} );

	test( 'every sweep says what it removes', async ( { page } ) => {
		await openSweepScreen( page );

		// "Orphaned Term Relationships" tells a site owner nothing about
		// whether it is safe to tick, which is why each row carries a sentence
		// of its own.
		for ( const name of SWEEPS ) {
			await expect( row( page, name ).locator( 'p.description' ).first() ).not.toBeEmpty();
		}

		// And the one that needs a second warning has it: unused terms include
		// the ones belonging to drafts nobody has published yet.
		await expect( row( page, 'unused_terms' ) ).toContainText( 'have no draft posts' );
	} );

	test( 'the totals table counts what is really in the database', async ( { page } ) => {
		await openSweepScreen( page );

		const totals = page.locator( '.sweep-totals' );

		await expect( totals ).toBeVisible();
		// Six groups of counts, and they are counts of the tables rather than
		// of anything a sweep would remove -- a row saying "Posts 3" beside a
		// sweep button reads as three posts about to be deleted otherwise.
		await expect( totals.locator( 'tbody tr' ) ).toHaveCount( 6 );

		for ( const type of [ 'posts', 'postmeta', 'comments', 'users', 'terms', 'options', 'tables' ] ) {
			await expect( totals.locator( `.sweep-count-type-${ type }` ) ).toHaveCount( 1 );
		}
	} );

	test( 'the group filters show only their own sweeps', async ( { page } ) => {
		await openSweepScreen( page );

		const expected = {};
		for ( const group of Object.values( GROUPS ) ) {
			expected[ group ] = ( expected[ group ] || 0 ) + 1;
		}

		await expect( page.locator( '.subsubsub li' ) ).toHaveCount( 7 );

		for ( const [ group, count ] of Object.entries( expected ) ) {
			await page.locator( `.subsubsub a[href*="group=${ group }"]` ).click();

			await expect( page.locator( '#the-list tr' ) ).toHaveCount( count );
			await expect( page.locator( `.subsubsub a[href*="group=${ group }"]` ) ).toHaveClass(
				/current/,
			);

			// Every row shown really belongs to the group rather than the count
			// happening to match -- read once out of the Group column, not
			// nineteen times out of nineteen locators.
			const labels = await page.locator( '#the-list .column-group' ).allTextContents();
			expect( [ ...new Set( labels ) ] ).toEqual( [ GROUP_LABELS[ group ] ] );
		}

		await page.locator( '.subsubsub a[href*="group=all"]' ).click();
		await expect( page.locator( '#the-list tr' ) ).toHaveCount( SWEEPS.length );
	} );

	test( 'the sortable columns reorder the table both ways', async ( { page } ) => {
		await openSweepScreen( page );

		// The default is the order the sweeps have to run in -- posts before
		// the sweeps that hunt for the meta deleting them just orphaned -- so
		// the first row is Revisions until something is clicked.
		await expect( page.locator( '#the-list tr' ).first() ).toContainText( 'Revisions' );

		await page.locator( 'thead #name a' ).click();
		const ascending = await page.locator( '#the-list .column-name strong' ).allTextContents();
		expect( ascending ).toEqual( [ ...ascending ].sort() );

		await page.locator( 'thead #name a' ).click();
		const descending = await page.locator( '#the-list .column-name strong' ).allTextContents();
		expect( descending ).toEqual( [ ...ascending ].reverse() );
	} );

	test( 'the Details row action lists a sample without the script', async ( { page } ) => {
		createJunk( 'transient_options' );

		await openSweepScreen( page );

		// With JavaScript disabled the link is followed as a nonced request and
		// the list is rendered by PHP into the same container -- which is the
		// half of this screen that has to keep working when the script does
		// not load at all.
		// The administrator's own session, copied out of the running context
		// rather than read back off disk, so this does not depend on where the
		// config happened to put the storage state.
		const context = await page.context().browser().newContext( {
			javaScriptEnabled: false,
			storageState: await page.context().storageState(),
		} );
		const plain = await context.newPage();

		try {
			await plain.goto( SWEEP_URL );

			const href = await row( plain, 'transient_options' )
				.locator( 'a.btn-sweep-details' )
				.getAttribute( 'href' );

			await plain.goto( href );

			// What a person sees: the sample is on screen and readable.
			const list = row( plain, 'transient_options' ).locator( 'ol' );
			await expect( list ).toBeVisible();
			await expect( list.locator( 'li' ) ).not.toHaveCount( 0 );
			await expect( list ).toContainText( '_transient_' );

			// And it is inside the container the script fills on the other
			// path, so the two agree about where a details list lives. They do
			// not: the list is written as <p class="sweep-details"><ol>, and an
			// <ol> may not sit inside a <p> -- the parser closes the paragraph
			// first, which leaves .sweep-details empty and the list beside it
			// rather than in it.
			await expect(
				row( plain, 'transient_options' ).locator( '.sweep-details ol' ),
			).toHaveCount( 1 );
		} finally {
			await context.close();
		}
	} );

	test( 'the Details row action toggles a list in place with the script', async ( { page } ) => {
		createJunk( 'transient_options' );

		await openSweepScreen( page );

		const trigger = row( page, 'transient_options' ).locator( 'a.btn-sweep-details' );
		const details = row( page, 'transient_options' ).locator( '.sweep-details' );

		await expect( trigger ).toHaveAttribute( 'aria-expanded', 'false' );

		await trigger.click();
		await expect( details.locator( 'li' ) ).not.toHaveCount( 0 );
		await expect( trigger ).toHaveAttribute( 'aria-expanded', 'true' );

		// A toggle, not a one-way door: the list can be long, and the only way
		// to put it away used to be reloading the screen.
		await trigger.click();
		await expect( details ).toBeHidden();
		await expect( trigger ).toHaveAttribute( 'aria-expanded', 'false' );

		// And nothing was deleted by looking at it.
		expect( sweepCount( 'transient_options' ) ).toBeGreaterThan( 0 );
	} );

	test( 'the bulk action sweeps everything that was ticked and nothing else', async ( { page } ) => {
		// Spam and auto drafts rather than transients: WordPress writes its own
		// site transients back on the very next request -- update checks, theme
		// roots, the cron lock -- so "this sweep is now empty" is not something
		// that sweep can ever be held to.
		createJunk( 'spam_comments' );
		createJunk( 'auto_drafts' );
		createJunk( 'revisions' );

		await openSweepScreen( page );

		await page.locator( '#sweep_spam_comments' ).check();
		await page.locator( '#sweep_auto_drafts' ).check();
		await page.locator( '#bulk-action-selector-top' ).selectOption( 'sweep' );
		await page.locator( '#doaction' ).click();

		// By its own id: settings_errors() gives each message the id of the code
		// it was registered under, and the screen carries a static "back up
		// first" warning that a class selector would find as well.
		await expect( page.locator( '#setting-error-wp_sweep_swept' ) ).toBeVisible();

		expect( sweepCount( 'spam_comments' ) ).toBe( 0 );
		expect( sweepCount( 'auto_drafts' ) ).toBe( 0 );
		// The one that was not ticked is untouched, which is what makes this
		// about the checkboxes rather than about a bulk action that sweeps
		// everything.
		expect( sweepCount( 'revisions' ) ).toBeGreaterThan( 0 );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'a bulk sweep with nothing ticked says so and deletes nothing', async ( { page } ) => {
		createJunk( 'spam_comments' );
		const before = sweepCount( 'spam_comments' );

		await openSweepScreen( page );

		// With the script running, core never lets the form go: WP_List_Table's
		// own handler stops a bulk action with no rows ticked and says so.
		await page.locator( '#bulk-action-selector-top' ).selectOption( 'sweep' );
		await page.locator( '#doaction' ).click();

		await expect( page.locator( '.notice' ).filter( { hasText: 'select at least one item' } ) )
			.toBeVisible();
		expect( sweepCount( 'spam_comments' ) ).toBe( before );

		// Without it the form really does post, and the plugin's own answer is
		// the one that has to be right. Every path on this screen works with the
		// script turned off, and this is the branch that only exists there.
		const context = await page.context().browser().newContext( {
			javaScriptEnabled: false,
			storageState: await page.context().storageState(),
		} );
		const plain = await context.newPage();

		try {
			await plain.goto( SWEEP_URL );
			await plain.locator( '#bulk-action-selector-top' ).selectOption( 'sweep' );
			await plain.locator( '#doaction' ).click();

			// Two different outcomes that used to share one message. This one is
			// "you ticked nothing".
			await expect( plain.locator( '#setting-error-wp_sweep_nothing' ) ).toContainText(
				'Nothing was selected',
			);
		} finally {
			await context.close();
		}

		expect( sweepCount( 'spam_comments' ) ).toBe( before );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'a bulk sweep of an empty sweep says there was nothing left', async ( { page } ) => {
		sweepDirectly( 'spam_comments' );
		expect( sweepCount( 'spam_comments' ) ).toBe( 0 );

		await openSweepScreen( page );

		await page.locator( '#sweep_spam_comments' ).check();
		await page.locator( '#bulk-action-selector-top' ).selectOption( 'sweep' );
		await page.locator( '#doaction' ).click();

		// The other outcome, and it is not the same sentence: ticking a sweep
		// that had nothing in it used to report "Nothing was selected", which
		// is simply false.
		await expect( page.locator( '#setting-error-wp_sweep_nothing' ) ).toContainText(
			'There was nothing left to sweep',
		);
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'a single sweep through its plain link reports what it did', async ( { page } ) => {
		createJunk( 'spam_comments' );

		await openSweepScreen( page );

		// The row action without the script, which is a real nonced GET. Before
		// 2.0.0 this path deleted the data and then discarded the message, so
		// it told the user nothing at all.
		const href = await row( page, 'spam_comments' ).locator( 'a.btn-sweep' ).getAttribute( 'href' );

		await page.goto( href );

		await expect( page.locator( '#setting-error-wp_sweep_swept' ) ).toContainText(
			'Spam Comments Processed',
		);
		expect( sweepCount( 'spam_comments' ) ).toBe( 0 );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'a sweep link with a bad nonce is refused and deletes nothing', async ( { page } ) => {
		createJunk( 'spam_comments' );
		const before = sweepCount( 'spam_comments' );

		await page.goto( `${ SWEEP_URL }&sweep=spam_comments&_wpnonce=not-a-nonce` );

		await expect( page.locator( 'body' ) ).toContainText( /link you followed has expired/i );
		expect( sweepCount( 'spam_comments' ) ).toBe( before );
		expect( survivors( ids ) ).toEqual( INTACT );
	} );

	test( 'the page-closing guard is armed only while a sweep is running', async ( { page } ) => {
		createJunk( 'transient_options' );

		await openSweepScreen( page );

		/**
		 * Whether the beforeunload listener would put a prompt up.
		 *
		 * A synthetic, cancelable event rather than a real navigation: the
		 * handler answers by calling preventDefault(), so defaultPrevented is
		 * the honest reading of "would this have warned", and it can be taken
		 * at a moment of the test's choosing rather than whenever Chrome
		 * decides a user gesture counts.
		 *
		 * @return {Promise<boolean>} True when the guard would warn.
		 */
		const guardArmed = () =>
			page.evaluate( () => {
				const event = new Event( 'beforeunload', { cancelable: true } );
				window.dispatchEvent( event );

				return event.defaultPrevented;
			} );

		// Before anything is swept: leaving is nobody's business.
		expect( await guardArmed() ).toBe( false );

		// The sweep is held open for a few seconds, so there is a window in
		// which it is genuinely in flight rather than a race with a request
		// that has already come back.
		await page.route( '**/admin-ajax.php**', async ( route ) => {
			await new Promise( ( resolve ) => setTimeout( resolve, 4000 ) );
			await route.continue();
		} );

		await row( page, 'transient_options' ).locator( 'a.btn-sweep' ).click();

		await expect( page.locator( 'body' ) ).toHaveClass( /sweep-active/ );
		expect( await guardArmed() ).toBe( true );

		await expect( page.locator( '.sweep-message .updated' ) ).toBeVisible( {
			timeout: 30_000,
		} );
		await page.unroute( '**/admin-ajax.php**' );

		// And disarmed again once it is over, so an ordinary click away from a
		// finished screen does not put a dialog up.
		await expect( page.locator( 'body' ) ).not.toHaveClass( /sweep-active/ );
		expect( await guardArmed() ).toBe( false );
	} );

	test( 'the six extension points fire below the table', async ( { page } ) => {
		installMuPlugin(
			GROUP_HOOKS,
			`<?php
/**
 * Plugin Name: WP-Sweep E2E group hooks
 * Description: Marks each of the six actions fired below the sweep table.
 */
foreach ( array( 'post', 'comment', 'user', 'term', 'option', 'database' ) as $group ) {
	add_action(
		'wp_sweep_admin_' . $group . '_sweep',
		function () use ( $group ) {
			echo '<p class="e2e-group-' . esc_attr( $group ) . '">' . esc_html( $group ) . '</p>';
		}
	);
}
`,
		);

		await openSweepScreen( page );

		// These are where a sibling plugin hangs rows of its own, and they have
		// been public API since 1.0.4 -- so all six firing is a contract rather
		// than a detail.
		for ( const group of [ 'post', 'comment', 'user', 'term', 'option', 'database' ] ) {
			await expect( page.locator( `.e2e-group-${ group }` ) ).toBeVisible();
		}
	} );

	test( 'a subscriber gets no menu and no screen, and an administrator gets both', async ( { page } ) => {
		// Both directions in one test on purpose. "The subscriber sees nothing"
		// passes just as well with the plugin deactivated; the administrator
		// half is what proves the gate is the capability rather than a missing
		// page.
		await page.goto( '/wp-admin/tools.php' );
		await expect( page.locator( '#adminmenu' ) ).toContainText( 'WP-Sweep' );

		await page.goto( SWEEP_URL );
		await expect( page.getByRole( 'heading', { name: 'Sweep', exact: true } ) ).toBeVisible();

		ensureUser( 'sweep_subscriber', 'subscriber', PASSWORD );
		const other = await loginAs( page, 'sweep_subscriber', PASSWORD );

		try {
			await other.goto( '/wp-admin/index.php' );
			await expect( other.locator( '#adminmenu' ).getByText( 'WP-Sweep' ) ).toHaveCount( 0 );

			await other.goto( SWEEP_URL );
			await expect( other.locator( 'body' ) ).toContainText( /not allowed to access this page/ );
		} finally {
			await other.context().close();
		}
	} );

	test( 'an editor gets the screen only once the capability filter says so', async ( { page } ) => {
		ensureUser( 'sweep_editor', 'editor', PASSWORD );

		// Without the filter: activate_plugins is the shipped capability, and
		// an editor does not have it.
		let other = await loginAs( page, 'sweep_editor', PASSWORD );
		try {
			await other.goto( SWEEP_URL );
			await expect( other.locator( 'body' ) ).toContainText( /not allowed to access this page/ );
		} finally {
			await other.context().close();
		}

		installMuPlugin(
			GROUP_HOOKS,
			`<?php
/**
 * Plugin Name: WP-Sweep E2E capability filter
 * Description: Hands the sweep screen to editors for one test.
 */
add_filter( 'wp_sweep_capability', function () { return 'edit_pages'; } );
`,
		);

		other = await loginAs( page, 'sweep_editor', PASSWORD );
		try {
			await other.goto( SWEEP_URL );
			await expect( other.getByRole( 'heading', { name: 'Sweep', exact: true } ) ).toBeVisible();
			await expect( other.locator( '#the-list tr' ) ).toHaveCount( SWEEPS.length );
		} finally {
			await other.context().close();
		}
	} );
} );
