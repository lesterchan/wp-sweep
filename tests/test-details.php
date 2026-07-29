<?php
/**
 * Tests for WP_Sweep::details().
 *
 * @package wp-sweep
 */

/**
 * The Details button is the only chance a user gets to look before deleting,
 * so it has to name the right rows and it has to stay bounded.
 */
class Test_WP_Sweep_Details extends WP_Sweep_TestCase {

	/**
	 * Revision details list post titles.
	 */
	public function test_details_lists_revision_titles() {
		$this->make_revisions( 2 );

		$details = $this->sweep()->details( 'revisions' );

		$this->assertContains( 'sweep-revision-0', $details );
		$this->assertContains( 'sweep-revision-1', $details );
	}

	/**
	 * Comment details list comment authors.
	 */
	public function test_details_lists_comment_authors() {
		$this->make_comments( 'spam', 1 );

		$this->assertContains( 'sweep-author-spam-0', $this->sweep()->details( 'spam_comments' ) );
	}

	/**
	 * Orphan meta details list meta keys.
	 */
	public function test_details_lists_orphan_meta_keys() {
		$this->make_orphan_meta( 'postmeta', 'sweep_orphan_detail' );

		$details = $this->sweep()->details( 'orphan_postmeta' );

		$this->assertContains( 'sweep_orphan_detail', $details );
	}

	/**
	 * Duplicated meta details list meta keys.
	 */
	public function test_details_lists_duplicated_meta_keys() {
		$this->make_duplicate_meta( 'post' );

		$this->assertContains( 'sweep_dupe', $this->sweep()->details( 'duplicated_postmeta' ) );
	}

	/**
	 * Unused term details list term names.
	 */
	public function test_details_lists_unused_term_names() {
		$this->make_unused_terms( 1 );

		$this->assertContains( 'sweep-unused-term-0', $this->sweep()->details( 'unused_terms' ) );
	}

	/**
	 * Transient details list option names.
	 */
	public function test_details_lists_transient_option_names() {
		set_transient( 'sweep_detail_transient', 'x', HOUR_IN_SECONDS );

		$this->assertContains( '_transient_sweep_detail_transient', $this->sweep()->details( 'transient_options' ) );
	}

	/**
	 * Term relationship details list the taxonomy.
	 */
	public function test_details_lists_orphan_relationship_taxonomies() {
		$this->make_orphan_term_relationship( 'post_tag' );

		$this->assertContains( 'post_tag', $this->sweep()->details( 'orphan_term_relationships' ) );
	}

	/**
	 * The optimize_database details list the table names.
	 */
	public function test_details_lists_tables_for_optimize_database() {
		global $wpdb;

		$details = $this->sweep()->details( 'optimize_database' );

		$this->assertContains( $wpdb->posts, $details );
		$this->assertContains( $wpdb->options, $details );
	}

	/**
	 * Details never return more than limit_details rows, whatever the count.
	 * The admin screen promises this in so many words.
	 */
	public function test_details_are_capped_at_limit_details() {
		// Shrink the cap rather than seeding 500 rows.
		add_filter(
			'wp_sweep_limit_details',
			static function () {
				return 3;
			}
		);

		$this->make_revisions( 5 );

		$this->assertCount( 3, $this->sweep()->details( 'revisions' ), 'The details list ignored the cap.' );
		$this->assertSame( 5, (int) $this->sweep()->count( 'revisions' ), 'The cap must not change the count.' );
	}

	/**
	 * The documented default cap is 500.
	 */
	public function test_default_details_limit_is_500() {
		$this->assertSame( 500, $this->sweep()->limit_details() );
	}

	/**
	 * An unknown sweep name yields an empty list rather than an error.
	 */
	public function test_unknown_sweep_name_has_no_details() {
		$this->assertSame( array(), $this->sweep()->details( 'no_such_sweep' ) );
	}

	/**
	 * Details are always a list, so callers can count() them unconditionally.
	 * The REST endpoint does exactly that.
	 *
	 * @dataProvider data_every_sweep_name
	 *
	 * @param string $name Sweep name.
	 */
	public function test_details_are_always_an_array( $name ) {
		$this->assertIsArray( $this->sweep()->details( $name ) );
	}

	/**
	 * Every sweep name the plugin advertises.
	 *
	 * @return array
	 */
	public function data_every_sweep_name() {
		return array_map(
			static function ( $name ) {
				return array( $name );
			},
			array(
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
			)
		);
	}
}
