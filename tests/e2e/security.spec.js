/**
 * The stored XSS regression, in a real browser.
 *
 * A details list is a sample of rows straight out of the database: post titles,
 * option names, meta keys and -- the one that matters -- comment author names,
 * which are supplied by whoever left the comment. Spam comments are exactly the
 * rows this plugin exists to remove, and exactly the rows most likely to be
 * signed with a script tag.
 *
 * Before 2.0.0 the list was assembled by string concatenation and injected with
 * .html(), so clicking Details on Spammed Comments ran the spammer's script in
 * the administrator's browser. That is the bug this file exists for, and it is
 * only visible here: the markup never exists on the server, so no PHPUnit test
 * can see it.
 *
 * Both paths are checked, because they are two different pieces of code. The
 * script writes each entry as a text node; the no-JavaScript path escapes it in
 * PHP. Either one getting it wrong is the same hole.
 *
 * The message a sweep reports is checked too. It comes back through the public
 * wp_sweep_sweep filter, which can return anything, and settings_errors() prints
 * what it is given without escaping it.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	SWEEP_URL,
	createJunk,
	installFilter,
	openSweepScreen,
	removeFilter,
	row,
	sweepCount,
	sweepDetails,
	sweepDirectly,
	wpEval,
} = require( './helpers.js' );

const SCRIPT_PAYLOAD = '<script>window.__pwned = 1;</script>';
const IMG_PAYLOAD = '<img src=x onerror="window.__pwned = 1">';

/**
 * Whether any payload managed to run.
 *
 * @param {import('@playwright/test').Page} target Page to ask.
 * @return {Promise<boolean>} True if the sentinel was set.
 */
function pwned( target ) {
	return target.evaluate( () => window.__pwned === 1 );
}

/**
 * Put a spam comment signed with a payload into the table.
 *
 * Through $wpdb rather than wp_insert_comment(), because the author name has to
 * be the payload byte for byte -- the row a site with a spam problem already
 * has, written by something that was never going near a sanitiser.
 *
 * @param {string} author The author name to store.
 * @return {void}
 */
function insertHostileSpam( author ) {
	wpEval(
		`global $wpdb;
		$post_id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' LIMIT 1" );
		if ( ! $post_id ) {
			$post_id = wp_insert_post( array( 'post_title' => 'Sweep security post', 'post_content' => 'Body.', 'post_status' => 'publish' ) );
		}
		$wpdb->insert(
			$wpdb->comments,
			array(
				'comment_post_ID'  => $post_id,
				'comment_author'   => base64_decode( '${ Buffer.from( author, 'utf8' ).toString(
		'base64',
	) }' ),
				'comment_content'  => 'Buy things.',
				'comment_approved' => 'spam',
				'comment_type'     => 'comment',
				'comment_date'     => current_time( 'mysql' ),
				'comment_date_gmt' => current_time( 'mysql', 1 ),
			)
		);
		echo '<<<done>>>';`,
	);
}

test.describe( 'Hostile values in a details list', () => {
	test.beforeEach( async () => {
		// A clean spam queue every time, so the assertions are about the row
		// this test wrote rather than one left behind by the last.
		sweepDirectly( 'spam_comments' );
		insertHostileSpam( `Spammer ${ SCRIPT_PAYLOAD }` );
		insertHostileSpam( `Spammer ${ IMG_PAYLOAD }` );
	} );

	test.afterEach( async () => {
		removeFilter();
		sweepDirectly( 'spam_comments' );
	} );

	test( 'the fixture really is unsanitised author names in the comments table', () => {
		// Straight out of the rows rather than through the plugin, so these are
		// the byte-for-byte payloads and not something a filter tidied up on
		// the way in.
		const authors = sweepDetails( 'spam_comments' );

		expect( authors.join( ' ' ) ).toContain( '<script>window.__pwned = 1;</script>' );
		expect( authors.join( ' ' ) ).toContain( 'onerror' );
		expect( sweepCount( 'spam_comments' ) ).toBe( 2 );
	} );

	test( 'the details list the script builds is text, not markup', async ( { page } ) => {
		await openSweepScreen( page );

		const target = row( page, 'spam_comments' );

		await target.locator( 'a.btn-sweep-details' ).click();
		await expect( target.locator( '.sweep-details li' ) ).toHaveCount( 2 );

		// The whole point of the file. Each entry is written with textContent,
		// so the payload is on screen as characters and nothing about it ever
		// became an element.
		expect( await pwned( page ) ).toBe( false );
		await expect( target.locator( '.sweep-details script' ) ).toHaveCount( 0 );
		await expect( target.locator( '.sweep-details img' ) ).toHaveCount( 0 );
		await expect( target.locator( '.sweep-details' ) ).toContainText( 'window.__pwned' );

		// And the sentinel stays undefined after the page has had time to run
		// anything an injected element would have scheduled.
		await page.waitForTimeout( 250 );
		expect( await pwned( page ) ).toBe( false );
	} );

	test( 'the details list PHP builds is escaped as well', async ( { page } ) => {
		// The same list without the script, which is the path a screen with a
		// blocked or failed script still has to get right.
		const context = await page.context().browser().newContext( {
			javaScriptEnabled: false,
			storageState: await page.context().storageState(),
		} );
		const plain = await context.newPage();

		try {
			await plain.goto( SWEEP_URL );

			const href = await row( plain, 'spam_comments' )
				.locator( 'a.btn-sweep-details' )
				.getAttribute( 'href' );

			await plain.goto( href );

			const details = row( plain, 'spam_comments' ).locator( '.sweep-details' );

			await expect( details.locator( 'li' ) ).toHaveCount( 2 );
			await expect( details.locator( 'script' ) ).toHaveCount( 0 );
			await expect( details.locator( 'img' ) ).toHaveCount( 0 );
			// As text, not merely absent: an administrator deciding whether to
			// sweep has to be able to read what is in the row.
			await expect( details ).toContainText( 'window.__pwned' );
		} finally {
			await context.close();
		}
	} );

	test( 'the details list on screen never runs even when hovered', async ( { page } ) => {
		await openSweepScreen( page );

		const target = row( page, 'spam_comments' );
		await target.locator( 'a.btn-sweep-details' ).click();
		await expect( target.locator( '.sweep-details li' ) ).toHaveCount( 2 );

		// An attribute payload only fires on the event it named, so the list is
		// hovered rather than merely looked at.
		await target.locator( '.sweep-details li' ).first().hover();
		await target.locator( '.sweep-details li' ).last().hover();

		expect( await pwned( page ) ).toBe( false );
	} );

	test( 'a hostile post title in a details list is text too', async ( { page } ) => {
		// A different column of a different table, reached by a different
		// query, rendered by the same two code paths. The row a compromised
		// install has, written past every sanitiser.
		createJunk( 'deleted_posts' );
		wpEval(
			`global $wpdb;
			$id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash' ORDER BY ID DESC LIMIT 1" );
			$wpdb->update(
				$wpdb->posts,
				array( 'post_title' => base64_decode( '${ Buffer.from(
		`Trashed ${ SCRIPT_PAYLOAD }`,
		'utf8',
	).toString( 'base64' ) }' ) ),
				array( 'ID' => $id ),
				array( '%s' ),
				array( '%d' )
			);
			clean_post_cache( $id );
			echo '<<<done>>>';`,
		);

		try {
			await openSweepScreen( page );

			const target = row( page, 'deleted_posts' );
			await target.locator( 'a.btn-sweep-details' ).click();

			await expect( target.locator( '.sweep-details' ) ).toContainText( 'window.__pwned' );
			await expect( target.locator( '.sweep-details script' ) ).toHaveCount( 0 );
			expect( await pwned( page ) ).toBe( false );
		} finally {
			sweepDirectly( 'deleted_posts' );
		}
	} );

	test( 'a hostile message from the sweep filter is escaped in the notice', async ( { page } ) => {
		// settings_errors() prints what it is handed without escaping it, and
		// the message comes back through wp_sweep_sweep, which is public API and
		// can return anything at all.
		installFilter(
			'wp_sweep_sweep',
			`return 'spam_comments' === $name ? base64_decode( '${ Buffer.from(
				`Swept ${ SCRIPT_PAYLOAD }`,
				'utf8',
			).toString( 'base64' ) }' ) : $value;`,
		);

		await openSweepScreen( page );

		// The plain link, which is the path that goes through
		// add_settings_error() and settings_errors().
		const href = await row( page, 'spam_comments' ).locator( 'a.btn-sweep' ).getAttribute( 'href' );

		await page.goto( href );

		expect( await pwned( page ) ).toBe( false );
		await expect( page.locator( '#setting-error-wp_sweep_swept' ) ).toContainText( 'window.__pwned' );
		await expect( page.locator( '#setting-error-wp_sweep_swept script' ) ).toHaveCount( 0 );
	} );

	test( 'the same hostile message is text in the notice the script writes', async ( { page } ) => {
		installFilter(
			'wp_sweep_sweep',
			`return 'spam_comments' === $name ? base64_decode( '${ Buffer.from(
				`Swept ${ SCRIPT_PAYLOAD }`,
				'utf8',
			).toString( 'base64' ) }' ) : $value;`,
		);

		await openSweepScreen( page );

		await row( page, 'spam_comments' ).locator( 'a.btn-sweep' ).click();

		// The AJAX path builds its own notice, and it builds it out of a text
		// node -- which is a different piece of code from the PHP escape above
		// and has to reach the same answer.
		await expect( page.locator( '.sweep-message .updated' ) ).toContainText( 'window.__pwned' );
		await expect( page.locator( '.sweep-message script' ) ).toHaveCount( 0 );
		expect( await pwned( page ) ).toBe( false );
	} );
} );
