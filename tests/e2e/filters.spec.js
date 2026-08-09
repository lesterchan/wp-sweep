/**
 * The extension points, which are the whole configuration surface.
 *
 * WP-Sweep has no settings screen. The one setting it used to have became the
 * wp_sweep_limit_details filter, and everything else a site can change -- which
 * meta keys must never be deleted, which taxonomies and terms are off limits,
 * and the counts, samples and messages themselves -- is a filter too. So a site
 * owner configures this plugin by writing a small mu-plugin, and that is
 * exactly what these tests install.
 *
 * The protected-key filters are the ones that matter most: they are what a site
 * reaches for when a sweep is about to remove something it must not, and a
 * filter that is read by count() but not by sweep() would report the row as
 * protected and delete it anyway. Each of the four is therefore checked in both
 * places.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	createJunk,
	installFilter,
	openSweepScreen,
	removeFilter,
	row,
	sweepCount,
	sweepDetails,
	sweepDirectly,
	wpEval,
	wpEvalJson,
} = require( './helpers.js' );

/** The four meta whitelists, with the sweep and the table each governs. */
const WHITELISTS = [
	[ 'wp_sweep_postmeta_whitelist', 'orphan_postmeta', 'postmeta', 'post_id' ],
	[ 'wp_sweep_commentmeta_whitelist', 'orphan_commentmeta', 'commentmeta', 'comment_id' ],
	[ 'wp_sweep_usermeta_whitelist', 'orphan_usermeta', 'usermeta', 'user_id' ],
	[ 'wp_sweep_termmeta_whitelist', 'orphan_termmeta', 'termmeta', 'term_id' ],
];

/**
 * How many rows of one meta table carry a key.
 *
 * @param {string} table  Meta table property on $wpdb.
 * @param {string} column The object id column.
 * @param {string} key    Meta key.
 * @return {number} The row count.
 */
function metaRows( table, column, key ) {
	return wpEvalJson(
		`(int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->${ table }} WHERE ${ column } = 987654 AND meta_key = %s", '${ key }' ) )`,
	);
}

test.describe( 'The filters', () => {
	test.afterEach( async () => {
		removeFilter();
	} );

	test( 'the fixture really is a mu-plugin the plugin reads a filter from', () => {
		// Every test here installs a filter and expects the plugin to see it. If
		// mu-plugins ever stopped loading in this environment, all of them would
		// pass by finding the unfiltered value they were trying to change.
		installFilter( 'wp_sweep_limit_details', 'return 3;' );

		expect( wpEvalJson( 'WP_Sweep::get_instance()->limit_details()' ) ).toBe( 3 );
	} );

	test( 'the details limit is a filter, and the screen says what it is', async ( { page } ) => {
		installFilter( 'wp_sweep_limit_details', 'return 7;' );

		// Enough transients that a limit of seven has something to cut.
		wpEval(
			`for ( $i = 0; $i < 12; $i++ ) {
				set_transient( 'wp_sweep_e2e_limit_' . $i, 'junk', 0 );
			}
			echo '<<<done>>>';`,
		);

		try {
			expect( sweepDetails( 'transient_options' ) ).toHaveLength( 7 );
			// The count is exact even though the sample is capped: the list is a
			// preview, the count and the sweep itself are not.
			expect( sweepCount( 'transient_options' ) ).toBeGreaterThan( 7 );

			await openSweepScreen( page );
			await expect( page.locator( 'p.description' ).first() ).toContainText(
				'a sample of up to 7 items',
			);
		} finally {
			sweepDirectly( 'transient_options' );
		}
	} );

	test( 'a limit below one is floored rather than emptying every sample', () => {
		installFilter( 'wp_sweep_limit_details', 'return 0;' );

		// LIMIT 0 returns nothing at all, so a filter that answered zero would
		// make every details list empty and look like a plugin that had
		// forgotten how to read.
		expect( wpEvalJson( 'WP_Sweep::get_instance()->limit_details()' ) ).toBe( 1 );
	} );

	for ( const [ filter, sweep, table, column ] of WHITELISTS ) {
		test( `${ filter } keeps a protected key out of the count and out of the sweep`, async () => {
			// Two orphaned rows, one of which the site has asked to keep.
			wpEval(
				`global $wpdb;
				$wpdb->insert( $wpdb->${ table }, array( '${ column }' => 987654, 'meta_key' => 'sweep_protected_key', 'meta_value' => 'keep' ) );
				$wpdb->insert( $wpdb->${ table }, array( '${ column }' => 987654, 'meta_key' => 'sweep_ordinary_key', 'meta_value' => 'go' ) );
				echo '<<<done>>>';`,
			);

			installFilter( filter, "return array( 'sweep_protected_key' );" );

			try {
				// The count and the sample both drop it, and the sweep leaves the
				// row behind while taking its neighbour -- which is the pair that
				// matters. A filter read in one place and not the other reports a
				// row as safe and deletes it anyway.
				expect( sweepDetails( sweep ) ).not.toContain( 'sweep_protected_key' );
				expect( sweepDetails( sweep ) ).toContain( 'sweep_ordinary_key' );

				sweepDirectly( sweep );

				expect( metaRows( table, column, 'sweep_protected_key' ) ).toBe( 1 );
				expect( metaRows( table, column, 'sweep_ordinary_key' ) ).toBe( 0 );
			} finally {
				removeFilter();
				wpEval(
					`global $wpdb;
					$wpdb->query( "DELETE FROM {$wpdb->${ table }} WHERE ${ column } = 987654" );
					echo '<<<done>>>';`,
				);
			}
		} );
	}

	test( 'a protected pattern matches on a wildcard and not on an underscore', async () => {
		// Matching is done in PHP rather than with SQL LIKE, which treated an
		// underscore in a pattern as a single-character wildcard. Meta keys are
		// full of underscores, so "_my_key" protected a great deal more than it
		// looked like it did.
		wpEval(
			`global $wpdb;
			$wpdb->insert( $wpdb->postmeta, array( 'post_id' => 987654, 'meta_key' => 'keep_this_one', 'meta_value' => 'keep' ) );
			$wpdb->insert( $wpdb->postmeta, array( 'post_id' => 987654, 'meta_key' => 'keepXthisXone', 'meta_value' => 'go' ) );
			$wpdb->insert( $wpdb->postmeta, array( 'post_id' => 987654, 'meta_key' => 'keep_anything_at_all', 'meta_value' => 'keep' ) );
			echo '<<<done>>>';`,
		);

		installFilter(
			'wp_sweep_postmeta_whitelist',
			"return array( 'keep_this_one', 'keep_anything*' );",
		);

		try {
			sweepDirectly( 'orphan_postmeta' );

			// The literal name is kept, the wildcard keeps its family, and the
			// key that only matches if an underscore is a wildcard goes.
			expect( metaRows( 'postmeta', 'post_id', 'keep_this_one' ) ).toBe( 1 );
			expect( metaRows( 'postmeta', 'post_id', 'keep_anything_at_all' ) ).toBe( 1 );
			expect( metaRows( 'postmeta', 'post_id', 'keepXthisXone' ) ).toBe( 0 );
		} finally {
			removeFilter();
			wpEval(
				`global $wpdb;
				$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id = 987654" );
				echo '<<<done>>>';`,
			);
		}
	} );

	test( 'link categories are left out of the orphaned relationships sweep', async () => {
		// The shipped exclusion, and the only one. A relationship pointing at a
		// post that is gone is still swept unless its taxonomy is excused.
		const counts = JSON.parse(
			wpEval(
				`global $wpdb;
				$tag = wp_insert_term( 'Sweep filter tag ' . wp_rand(), 'post_tag' );
				$cat = wp_insert_term( 'Sweep filter link cat ' . wp_rand(), 'link_category' );
				foreach ( array( $tag, $cat ) as $term ) {
					$tt_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d", (int) $term['term_id'] ) );
					$wpdb->insert( $wpdb->term_relationships, array( 'object_id' => 987654, 'term_taxonomy_id' => $tt_id, 'term_order' => 0 ) );
				}
				echo '<<<' . wp_json_encode( array(
					'before' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = 987654" ),
					'sweep'  => (int) WP_Sweep::get_instance()->count( 'orphan_term_relationships' ),
				) ) . '>>>';`,
			),
		);

		expect( counts.before ).toBe( 2 );
		// One of the two, because the other is a link category.
		expect( counts.sweep ).toBe( 1 );

		sweepDirectly( 'orphan_term_relationships' );

		const after = wpEvalJson(
			'(int) $GLOBALS["wpdb"]->get_var( "SELECT COUNT(*) FROM {$GLOBALS[\'wpdb\']->term_relationships} WHERE object_id = 987654" )',
		);
		expect( after ).toBe( 1 );

		// And a site can widen the exclusion, which is what the filter is for.
		installFilter(
			'wp_sweep_excluded_taxonomies',
			"return array( 'link_category', 'post_tag' );",
		);
		expect( sweepCount( 'orphan_term_relationships' ) ).toBe( 0 );

		removeFilter();
		wpEval(
			`global $wpdb;
			$wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE object_id = 987654" );
			echo '<<<done>>>';`,
		);
	} );

	test( 'an empty exclusion list still produces valid SQL', () => {
		// The filter is public API, so a site is entitled to filter it down to
		// nothing -- and NOT IN () is a syntax error rather than a clause that
		// matches everything. The count coming back as a number rather than an
		// empty cell is the whole assertion.
		installFilter( 'wp_sweep_excluded_taxonomies', 'return array();' );

		expect( typeof sweepCount( 'orphan_term_relationships' ) ).toBe( 'number' );
		expect( sweepDetails( 'orphan_term_relationships' ) ).toEqual( [] );
	} );

	test( 'a term another term is the parent of is never unused', async () => {
		const ids = JSON.parse(
			wpEval(
				`$parent = wp_insert_term( 'Sweep parent ' . wp_rand(), 'category' );
				$child  = wp_insert_term( 'Sweep child ' . wp_rand(), 'category', array( 'parent' => (int) $parent['term_id'] ) );
				echo '<<<' . wp_json_encode( array( 'parent' => (int) $parent['term_id'], 'child' => (int) $child['term_id'] ) ) . '>>>';`,
			),
		);

		try {
			const names = sweepDetails( 'unused_terms' );

			// Both have a count of zero, so both look unused; the parent is
			// excluded because deleting it would reparent its child to the top
			// of the tree without anybody asking.
			expect( names.some( ( name ) => name.startsWith( 'Sweep child' ) ) ).toBe( true );
			expect( names.some( ( name ) => name.startsWith( 'Sweep parent' ) ) ).toBe( false );

			sweepDirectly( 'unused_terms' );

			expect( wpEvalJson( `(bool) get_term( ${ ids.parent }, 'category' )` ) ).toBe( true );
			expect( wpEvalJson( `(bool) get_term( ${ ids.child }, 'category' )` ) ).toBe( false );
		} finally {
			wpEval( `wp_delete_term( ${ ids.parent }, 'category' ); echo '<<<done>>>';` );
		}
	} );

	test( 'a term id a site protects is kept even with nothing attached to it', async () => {
		const termId = parseInt(
			wpEval(
				`$term = wp_insert_term( 'Sweep protected term ' . wp_rand(), 'post_tag' );
				echo '<<<' . (int) $term['term_id'] . '>>>';`,
			),
			10,
		);

		installFilter( 'wp_sweep_excluded_termids', `return array( ${ termId } );` );

		try {
			expect( sweepDetails( 'unused_terms' ).some( ( n ) => n.includes( 'protected term' ) ) ).toBe(
				false,
			);

			sweepDirectly( 'unused_terms' );

			expect( wpEvalJson( `(bool) get_term( ${ termId }, 'post_tag' )` ) ).toBe( true );
		} finally {
			removeFilter();
			wpEval( `wp_delete_term( ${ termId }, 'post_tag' ); echo '<<<done>>>';` );
		}
	} );

	test( 'the count filter changes what the screen reports', async ( { page } ) => {
		installFilter(
			'wp_sweep_count',
			"return 'revisions' === $name ? 4242 : $value;",
		);

		await openSweepScreen( page );

		// A filtered count reaches the screen, the percentage beside it and the
		// buttons the row offers -- so a plugin that adds its own sweeps can
		// make a row appear sweepable, which is what this hook is for.
		await expect( row( page, 'revisions' ).locator( '.sweep-count' ) ).toHaveText( '4,242' );
		await expect( row( page, 'revisions' ).locator( 'a.btn-sweep' ) ).toBeVisible();
	} );

	test( 'the details filter changes the sample the screen lists', async ( { page } ) => {
		// The filter replaces the sample, but a row only offers a Details
		// button when its count says there is something to look at.
		createJunk( 'revisions' );

		installFilter(
			'wp_sweep_details',
			"return 'revisions' === $name ? array( 'a filtered detail' ) : $value;",
		);

		await openSweepScreen( page );

		await row( page, 'revisions' ).locator( 'a.btn-sweep-details' ).click();
		await expect( row( page, 'revisions' ).locator( '.sweep-details' ) ).toContainText(
			'a filtered detail',
		);
	} );

	test( 'the message filter changes what a finished sweep says', async ( { page } ) => {
		createJunk( 'transient_options' );

		installFilter(
			'wp_sweep_sweep',
			"return 'transient_options' === $name ? 'a filtered message' : $value;",
		);

		await openSweepScreen( page );

		await row( page, 'transient_options' ).locator( 'a.btn-sweep' ).click();

		// The message comes back through the AJAX response and is written into
		// the notice as a text node, so a filter returning markup cannot become
		// markup -- which is why the screen escapes it on the PHP side too.
		await expect( page.locator( '.sweep-message .notice-success' ) ).toContainText(
			'a filtered message',
		);
	} );

	test( 'the total count filter changes the running totals above the table', async ( { page } ) => {
		installFilter( 'wp_sweep_total_count', "return 'posts' === $name ? 1234 : $value;" );

		await openSweepScreen( page );

		await expect( page.locator( '.sweep-totals .sweep-count-type-posts' ) ).toHaveText( '1,234' );
	} );
} );
