/**
 * Shared steps for the WP-Sweep end-to-end suite.
 *
 * Everything here exists because this plugin deletes things, and a test suite
 * for a plugin that deletes things has two jobs that pull against each other:
 * it has to prove the rubbish went, and it has to prove nothing else did.
 *
 * **Every sweep is site-wide.** There is no "sweep this post's revisions": the
 * button removes every revision on the install. So a test cannot lean on a
 * fixture another test made -- the sweep in between would have taken it -- and
 * every test here creates its own junk immediately before sweeping it, and its
 * own survivors immediately before checking them.
 *
 * **The survivors are the point.** createFixtures() builds one real post, one
 * real approved comment, one real user and one real term, each with meta, and
 * the post carries the term. survivors() reads all of that back. A sweep that
 * removed any of it is the failure this plugin can least afford, and it is what
 * every test in sweeps.spec.js asserts after the junk has gone.
 *
 * **The junk is not reachable through any WordPress API.** Orphaned meta is
 * meta whose object no longer exists, and update_post_meta() refuses to make
 * one; a duplicated meta row is a row the meta API deduplicates. So the junk
 * goes in with SQL, which is also exactly the state a real site gets into.
 */

const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const { expect } = require( '@wordpress/e2e-test-utils-playwright' );

/** The plugin root, which is where wp-env reads .wp-env.json from. */
const PLUGIN_ROOT = path.join( __dirname, '../..' );

/** The one screen this plugin has. It lives under Tools. */
const SWEEP_URL = '/wp-admin/tools.php?page=wp-sweep';

/** Every sweep, in the order get_sweeps() declares them. */
const SWEEPS = [
	'revisions',
	'auto_drafts',
	'deleted_posts',
	'unapproved_comments',
	'spam_comments',
	'deleted_comments',
	'transient_options',
	'orphan_postmeta',
	'orphan_commentmeta',
	'orphan_usermeta',
	'orphan_termmeta',
	'orphan_term_relationships',
	'unused_terms',
	'duplicated_postmeta',
	'duplicated_commentmeta',
	'duplicated_usermeta',
	'duplicated_termmeta',
	'optimize_database',
	'oembed_postmeta',
];

/** The group each sweep is filed under, keyed by sweep name. */
const GROUPS = {
	revisions: 'posts',
	auto_drafts: 'posts',
	deleted_posts: 'posts',
	unapproved_comments: 'comments',
	spam_comments: 'comments',
	deleted_comments: 'comments',
	transient_options: 'options',
	orphan_postmeta: 'posts',
	orphan_commentmeta: 'comments',
	orphan_usermeta: 'users',
	orphan_termmeta: 'terms',
	orphan_term_relationships: 'terms',
	unused_terms: 'terms',
	duplicated_postmeta: 'posts',
	duplicated_commentmeta: 'comments',
	duplicated_usermeta: 'users',
	duplicated_termmeta: 'terms',
	optimize_database: 'database',
	oembed_postmeta: 'posts',
};

/** The heading each group is filed under on the screen. */
const GROUP_LABELS = {
	posts: 'Post Sweep',
	comments: 'Comment Sweep',
	users: 'User Sweep',
	terms: 'Term Sweep',
	options: 'Option Sweep',
	database: 'Database Sweep',
};

/**
 * Run PHP inside the tests environment and hand back what it printed.
 *
 * The code is base64'd rather than passed as itself: the security spec stores
 * quotes, angle brackets and a script tag in a comment author's name, and a
 * fixture that is not the payload byte for byte proves nothing about escaping
 * it.
 *
 * @param {string} code PHP to evaluate, without an opening tag.
 * @return {string} Whatever the code echoed between its markers.
 */
function wpEval( code ) {
	const encoded = Buffer.from( code, 'utf8' ).toString( 'base64' );

	const output = execFileSync(
		'npx',
		[
			'--yes',
			'@wordpress/env',
			'run',
			'tests-cli',
			'wp',
			'eval',
			`eval( base64_decode( '${ encoded }' ) );`,
		],
		{ cwd: PLUGIN_ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] },
	);

	// wp-env prints its own progress around the command's output, so the code
	// wraps what it wants to return in markers rather than the caller trying to
	// tell the two apart by position.
	const matched = output.match( /<<<([\s\S]*?)>>>/ );

	return matched ? matched[ 1 ] : '';
}

/**
 * Run PHP and read back a JSON value, so types survive the round trip.
 *
 * @param {string} expression PHP expression to encode and return.
 * @return {*} The decoded value.
 */
function wpEvalJson( expression ) {
	return JSON.parse( wpEval( `echo '<<<' . wp_json_encode( ${ expression } ) . '>>>';` ) );
}

/**
 * Encode a value for a PHP literal, so payloads cross the shell intact.
 *
 * @param {*} value Anything JSON can carry.
 * @return {string} A base64 string.
 */
function encode( value ) {
	return Buffer.from( JSON.stringify( value ), 'utf8' ).toString( 'base64' );
}

/**
 * How many items a sweep would remove.
 *
 * @param {string} name Sweep name.
 * @return {number} The count.
 */
function sweepCount( name ) {
	return wpEvalJson( `(int) WP_Sweep::get_instance()->count( '${ name }' )` );
}

/**
 * How many rows the table a sweep is measured against holds.
 *
 * @param {string} type Sweep type.
 * @return {number} The count.
 */
function totalCount( type ) {
	return wpEvalJson( `(int) WP_Sweep::get_instance()->total_count( '${ type }' )` );
}

/**
 * The sample of items a sweep would remove.
 *
 * @param {string} name Sweep name.
 * @return {Array} The details.
 */
function sweepDetails( name ) {
	return wpEvalJson( `array_values( (array) WP_Sweep::get_instance()->details( '${ name }' ) )` );
}

/**
 * Build the content every sweep must leave alone.
 *
 * One published post carrying real meta and a real term, one approved comment
 * with meta, one user with meta, and a term with meta. Between them they are a
 * row in every table any sweep touches, which is what makes "nothing else went"
 * a thing a test can assert rather than hope for.
 *
 * @return {Object} The ids, so a test can name what it expects to find.
 */
function createFixtures() {
	return JSON.parse(
		wpEval(
			`$post_id = wp_insert_post( array(
				'post_title'   => 'Sweep survivor post',
				'post_content' => 'Real content that must not be swept.',
				'post_status'  => 'publish',
			) );
			update_post_meta( $post_id, 'sweep_survivor_meta', 'keep me' );

			$comment_id = wp_insert_comment( array(
				'comment_post_ID'  => $post_id,
				'comment_author'   => 'Sweep survivor commenter',
				'comment_content'  => 'A real, approved comment.',
				'comment_approved' => '1',
				'comment_type'     => 'comment',
			) );
			update_comment_meta( $comment_id, 'sweep_survivor_meta', 'keep me' );

			$user = get_user_by( 'login', 'sweep_survivor' );
			$user_id = $user ? $user->ID : wp_insert_user( array(
				'user_login' => 'sweep_survivor',
				'user_pass'  => 'correct-horse-battery-staple',
				'user_email' => 'sweep_survivor@example.com',
				'role'       => 'subscriber',
			) );
			update_user_meta( $user_id, 'sweep_survivor_meta', 'keep me' );

			$term = term_exists( 'Sweep survivor term', 'post_tag' );
			if ( ! $term ) {
				$term = wp_insert_term( 'Sweep survivor term', 'post_tag' );
			}
			$term_id = (int) $term['term_id'];
			update_term_meta( $term_id, 'sweep_survivor_meta', 'keep me' );
			wp_set_post_terms( $post_id, array( $term_id ), 'post_tag', false );

			echo '<<<' . wp_json_encode( array(
				'postId'    => (int) $post_id,
				'commentId' => (int) $comment_id,
				'userId'    => (int) $user_id,
				'termId'    => $term_id,
			) ) . '>>>';`,
		),
	);
}

/**
 * What is left of those fixtures.
 *
 * Read as one object rather than as four calls, so a test can compare the whole
 * thing in one assertion and a sweep that took two of them cannot be reported
 * as having taken one.
 *
 * @param {Object} ids What createFixtures() returned.
 * @return {Object} Presence and meta values for each fixture.
 */
function survivors( ids ) {
	return JSON.parse(
		wpEval(
			`$ids = json_decode( base64_decode( '${ encode( ids ) }' ), true );
			global $wpdb;
			echo '<<<' . wp_json_encode( array(
				'post'         => (bool) get_post( $ids['postId'] ),
				'postMeta'     => (string) get_post_meta( $ids['postId'], 'sweep_survivor_meta', true ),
				'comment'      => (bool) get_comment( $ids['commentId'] ),
				'commentMeta'  => (string) get_comment_meta( $ids['commentId'], 'sweep_survivor_meta', true ),
				'user'         => (bool) get_userdata( $ids['userId'] ),
				'userMeta'     => (string) get_user_meta( $ids['userId'], 'sweep_survivor_meta', true ),
				'term'         => (bool) get_term( $ids['termId'], 'post_tag' ),
				'termMeta'     => (string) get_term_meta( $ids['termId'], 'sweep_survivor_meta', true ),
				// Only the survivor's own tag. A published post also carries the
				// default category, which WordPress puts there and which no
				// sweep is about, so counting every relationship would make this
				// number a fact about core rather than about the sweep.
				'relationship' => (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->term_relationships} AS tr
						INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
						WHERE tr.object_id = %d AND tt.term_id = %d",
						$ids['postId'],
						$ids['termId']
					)
				),
			) ) . '>>>';`,
		),
	);
}

/** What survivors() has to say once nothing has gone wrong. */
const INTACT = {
	post: true,
	postMeta: 'keep me',
	comment: true,
	commentMeta: 'keep me',
	user: true,
	userMeta: 'keep me',
	term: true,
	termMeta: 'keep me',
	relationship: 1,
};

/**
 * Create the junk one sweep exists to remove.
 *
 * Every branch here is written against the tables rather than through a
 * WordPress API, because most of this junk is not reachable through one: the
 * meta functions refuse to write a row for an object that does not exist, and
 * they deduplicate a row that already does. That is the whole reason the sweeps
 * exist.
 *
 * @param {string} name Sweep name.
 * @return {number} How many items the sweep can now see.
 */
function createJunk( name ) {
	wpEval(
		`global $wpdb;
		$name = '${ name }';

		switch ( $name ) {
			case 'revisions':
				$id = wp_insert_post( array( 'post_title' => 'Sweep junk revisions', 'post_content' => 'One', 'post_status' => 'publish' ) );
				wp_update_post( array( 'ID' => $id, 'post_content' => 'Two' ) );
				wp_update_post( array( 'ID' => $id, 'post_content' => 'Three' ) );
				break;

			case 'auto_drafts':
				wp_insert_post( array( 'post_title' => 'Sweep junk auto draft', 'post_status' => 'auto-draft' ) );
				break;

			case 'deleted_posts':
				$id = wp_insert_post( array( 'post_title' => 'Sweep junk trashed', 'post_content' => 'Body.', 'post_status' => 'publish' ) );
				wp_trash_post( $id );
				break;

			case 'unapproved_comments':
			case 'spam_comments':
			case 'deleted_comments':
				$statuses = array(
					'unapproved_comments' => '0',
					'spam_comments'       => 'spam',
					'deleted_comments'    => 'trash',
				);
				$post_id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' LIMIT 1" );
				wp_insert_comment( array(
					'comment_post_ID'  => $post_id,
					'comment_author'   => 'Sweep junk commenter',
					'comment_content'  => 'Junk.',
					'comment_approved' => $statuses[ $name ],
					'comment_type'     => 'comment',
				) );
				break;

			case 'transient_options':
				set_transient( 'wp_sweep_e2e_transient', 'junk', 0 );
				break;

			case 'orphan_postmeta':
				$wpdb->insert( $wpdb->postmeta, array( 'post_id' => 987654, 'meta_key' => 'sweep_orphan_meta', 'meta_value' => 'junk' ) );
				break;

			case 'orphan_commentmeta':
				$wpdb->insert( $wpdb->commentmeta, array( 'comment_id' => 987654, 'meta_key' => 'sweep_orphan_meta', 'meta_value' => 'junk' ) );
				break;

			case 'orphan_usermeta':
				$wpdb->insert( $wpdb->usermeta, array( 'user_id' => 987654, 'meta_key' => 'sweep_orphan_meta', 'meta_value' => 'junk' ) );
				break;

			case 'orphan_termmeta':
				$wpdb->insert( $wpdb->termmeta, array( 'term_id' => 987654, 'meta_key' => 'sweep_orphan_meta', 'meta_value' => 'junk' ) );
				break;

			case 'orphan_term_relationships':
				$term = wp_insert_term( 'Sweep junk relationship term ' . wp_rand(), 'post_tag' );
				$tt_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d", (int) $term['term_id'] ) );
				$wpdb->insert( $wpdb->term_relationships, array( 'object_id' => 987654, 'term_taxonomy_id' => $tt_id, 'term_order' => 0 ) );
				break;

			case 'unused_terms':
				wp_insert_term( 'Sweep junk unused term ' . wp_rand(), 'post_tag' );
				break;

			case 'duplicated_postmeta':
				$post_id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' LIMIT 1" );
				$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $post_id, 'meta_key' => 'sweep_duplicated', 'meta_value' => 'same' ) );
				$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $post_id, 'meta_key' => 'sweep_duplicated', 'meta_value' => 'same' ) );
				break;

			case 'duplicated_commentmeta':
				$comment_id = (int) $wpdb->get_var( "SELECT comment_ID FROM {$wpdb->comments} LIMIT 1" );
				$wpdb->insert( $wpdb->commentmeta, array( 'comment_id' => $comment_id, 'meta_key' => 'sweep_duplicated', 'meta_value' => 'same' ) );
				$wpdb->insert( $wpdb->commentmeta, array( 'comment_id' => $comment_id, 'meta_key' => 'sweep_duplicated', 'meta_value' => 'same' ) );
				break;

			case 'duplicated_usermeta':
				$user_id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->users} LIMIT 1" );
				$wpdb->insert( $wpdb->usermeta, array( 'user_id' => $user_id, 'meta_key' => 'sweep_duplicated', 'meta_value' => 'same' ) );
				$wpdb->insert( $wpdb->usermeta, array( 'user_id' => $user_id, 'meta_key' => 'sweep_duplicated', 'meta_value' => 'same' ) );
				break;

			case 'duplicated_termmeta':
				$term_id = (int) $wpdb->get_var( "SELECT term_id FROM {$wpdb->terms} LIMIT 1" );
				$wpdb->insert( $wpdb->termmeta, array( 'term_id' => $term_id, 'meta_key' => 'sweep_duplicated', 'meta_value' => 'same' ) );
				$wpdb->insert( $wpdb->termmeta, array( 'term_id' => $term_id, 'meta_key' => 'sweep_duplicated', 'meta_value' => 'same' ) );
				break;

			case 'oembed_postmeta':
				$post_id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' LIMIT 1" );
				$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $post_id, 'meta_key' => '_oembed_e2e0123456789', 'meta_value' => '<iframe></iframe>' ) );
				break;

			case 'optimize_database':
				// Every install has tables, so there is nothing to arrange.
				break;
		}

		wp_cache_flush();
		echo '<<<' . (int) WP_Sweep::get_instance()->count( $name ) . '>>>';`,
	);

	return sweepCount( name );
}

/**
 * Run one sweep without a browser.
 *
 * Used only to clean up after a test, or to arrange a screen that has nothing
 * left to sweep. Anything asserting that a sweep *works* drives the screen.
 *
 * @param {string} name Sweep name.
 * @return {string} The message the sweep returned.
 */
function sweepDirectly( name ) {
	return wpEval(
		`echo '<<<' . WP_Sweep::get_instance()->sweep( '${ name }' ) . '>>>';`,
	);
}

/**
 * Write one file into the mu-plugins directory.
 *
 * @param {string} name   File name, without the directory.
 * @param {string} source Complete PHP file, opening tag included.
 * @return {void}
 */
function installMuPlugin( name, source ) {
	const encoded = Buffer.from( source, 'utf8' ).toString( 'base64' );

	wpEval(
		`if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			mkdir( WPMU_PLUGIN_DIR, 0777, true );
		}
		file_put_contents( WPMU_PLUGIN_DIR . '/${ name }', base64_decode( '${ encoded }' ) );
		echo '<<<done>>>';`,
	);
}

/**
 * Remove one file from the mu-plugins directory.
 *
 * @param {string} name File name, without the directory.
 * @return {void}
 */
function removeMuPlugin( name ) {
	wpEval(
		`$file = WPMU_PLUGIN_DIR . '/${ name }';
		if ( file_exists( $file ) ) {
			unlink( $file );
		}
		echo '<<<done>>>';`,
	);
}

/**
 * Install a mu-plugin that answers one filter with a fixed value.
 *
 * Every extension point this plugin has is a filter with no screen behind it,
 * so a site owner uses them by writing exactly this sort of file.
 *
 * @param {string} filter The filter name.
 * @param {string} body   PHP for the callback body, returning the new value.
 * @return {void}
 */
function installFilter( filter, body ) {
	installMuPlugin(
		'wp-sweep-e2e-filter.php',
		`<?php
/**
 * Plugin Name: WP-Sweep E2E filter
 * Description: Answers ${ filter } for one test.
 */
add_filter(
	'${ filter }',
	function ( $value, $name = '' ) {
		${ body }
	},
	10,
	2
);
`,
	);
}

/**
 * Remove whichever filter was installed.
 *
 * @return {void}
 */
function removeFilter() {
	removeMuPlugin( 'wp-sweep-e2e-filter.php' );
}

/**
 * Open the Sweep screen.
 *
 * @param {import('@playwright/test').Page} page  Page under test.
 * @param {string}                          group Group filter, or 'all'.
 * @return {Promise<void>} Resolves once the screen is up.
 */
async function openSweepScreen( page, group = 'all' ) {
	await page.goto( `${ SWEEP_URL }&group=${ group }` );

	await expect( page.getByRole( 'heading', { name: 'Sweep', exact: true } ) ).toBeVisible();
}

/**
 * One row of the sweep table.
 *
 * Found by the checkbox rather than by the label, because the label is
 * translated and the checkbox id is the sweep name -- and because a row with
 * nothing to sweep has a checkbox and no buttons, which is a state several
 * tests are about.
 *
 * @param {import('@playwright/test').Page} page Page showing the screen.
 * @param {string}                          name Sweep name.
 * @return {import('@playwright/test').Locator} The row.
 */
function row( page, name ) {
	return page.locator( '#the-list tr', { has: page.locator( `#sweep_${ name }` ) } );
}

/**
 * Click a row's Sweep button and wait for the row to settle.
 *
 * The button is a real, nonced link that works with the script turned off; the
 * script intercepts it and calls admin-ajax.php instead, which is what makes
 * this the surface a person actually uses.
 *
 * @param {import('@playwright/test').Page} page Page showing the screen.
 * @param {string}                          name Sweep name.
 * @return {Promise<void>} Resolves once the message has appeared.
 */
async function sweepRow( page, name ) {
	const target = row( page, name );

	await expect( target.locator( 'a.btn-sweep' ) ).toBeVisible();

	await Promise.all( [
		page.waitForResponse(
			( response ) =>
				response.url().includes( 'admin-ajax.php' ) &&
				response.url().includes( `sweep_name=${ name }` ),
		),
		target.locator( 'a.btn-sweep' ).click(),
	] );

	await expect( page.locator( '.sweep-message .updated' ) ).toBeVisible();
}

/**
 * A name no earlier run can have used.
 *
 * @param {string} base What the title should say.
 * @return {string} That, plus enough to tell this run from the last.
 */
function uniqueTitle( base ) {
	return `${ base } ${ Date.now().toString( 36 ) }`;
}

/**
 * Create a user, or reset the one an earlier run already created.
 *
 * Through WP-CLI rather than REST, because REST answers "that login is taken"
 * with an error and there is no second call that reliably finds the account
 * again -- the suite is run more than once against the same database, so the
 * second run has to be able to log in as the account the first one made.
 *
 * @param {string} username Username.
 * @param {string} role     Role slug.
 * @param {string} password Password.
 * @return {number} The user id.
 */
function ensureUser( username, role, password ) {
	return parseInt(
		wpEval(
			`$login = '${ username }';
			$user = get_user_by( 'login', $login );

			if ( $user ) {
				$id = (int) $user->ID;
				wp_set_password( '${ password }', $id );
				$user = new WP_User( $id );
				$user->set_role( '${ role }' );
			} else {
				$id = (int) wp_insert_user( array(
					'user_login' => $login,
					'user_pass'  => '${ password }',
					'user_email' => $login . '@example.com',
					'role'       => '${ role }',
				) );
			}

			echo '<<<' . $id . '>>>';`,
		),
		10,
	);
}

/**
 * Log a second browser context in as a named user.
 *
 * wp-login.php focuses and *selects* #user_login on a 200ms timer so a visitor
 * can start typing. Filling across that moment puts the password into the
 * username box -- Playwright focuses #user_pass, the timer takes focus back and
 * selects what is there, and the typed text replaces the selection. Waiting for
 * the timer's own effect is the signal that it has already fired.
 *
 * @param {import('@playwright/test').Page} page     Page under test, for its browser.
 * @param {string}                          username Username to log in as.
 * @param {string}                          password That user's password.
 * @return {Promise<import('@playwright/test').Page>} A page carrying that session.
 */
async function loginAs( page, username, password ) {
	const context = await page.context().browser().newContext( { storageState: undefined } );
	const other = await context.newPage();

	await other.goto( '/wp-login.php' );
	await expect( other.locator( '#user_login' ) ).toBeFocused();

	await other.locator( '#user_login' ).fill( username );
	await other.locator( '#user_pass' ).fill( password );
	await other.locator( '#wp-submit' ).click();
	await expect( other.locator( '#wpadminbar' ) ).toBeVisible();

	return other;
}

module.exports = {
	GROUPS,
	GROUP_LABELS,
	INTACT,
	SWEEPS,
	SWEEP_URL,
	createFixtures,
	createJunk,
	ensureUser,
	installFilter,
	installMuPlugin,
	loginAs,
	openSweepScreen,
	removeFilter,
	removeMuPlugin,
	row,
	sweepCount,
	sweepDetails,
	sweepDirectly,
	sweepRow,
	survivors,
	totalCount,
	uniqueTitle,
	wpEval,
	wpEvalJson,
};
