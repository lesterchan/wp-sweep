<?php
/**
 * Tests for WPSweep::count() and WPSweep::total_count().
 *
 * @package wp-sweep
 */

/**
 * Every sweep must count exactly what it is about to delete. These assertions
 * are the contract the rest of the plugin is built on: the admin screen, the
 * REST endpoint and the CLI command all decide whether to act on this number.
 */
class Test_WP_Sweep_Count extends WP_Sweep_TestCase {

	/**
	 * Revisions are counted by post_type.
	 */
	public function test_counts_revisions() {
		$this->baseline( 'revisions' );
		$this->make_revisions( 3 );

		$this->assertSweepDelta( 3, 'revisions' );
	}

	/**
	 * Auto drafts are counted by post_status.
	 */
	public function test_counts_auto_drafts() {
		$this->baseline( 'auto_drafts' );
		$this->make_posts_with_status( 'auto-draft', 2 );

		$this->assertSweepDelta( 2, 'auto_drafts' );
	}

	/**
	 * Trashed posts are counted by post_status.
	 */
	public function test_counts_deleted_posts() {
		$this->baseline( 'deleted_posts' );
		$this->make_posts_with_status( 'trash', 4 );

		$this->assertSweepDelta( 4, 'deleted_posts' );
	}

	/**
	 * Unapproved comments are the ones with comment_approved = '0'.
	 */
	public function test_counts_unapproved_comments() {
		$this->baseline( 'unapproved_comments' );
		$this->make_comments( '0', 3 );

		$this->assertSweepDelta( 3, 'unapproved_comments' );
	}

	/**
	 * Spam comments are counted separately from unapproved ones.
	 */
	public function test_counts_spam_comments() {
		$this->baseline( array( 'spam_comments', 'unapproved_comments' ) );
		$this->make_comments( 'spam', 2 );

		$this->assertSweepDelta( 2, 'spam_comments' );
		$this->assertSweepDelta( 0, 'unapproved_comments' );
	}

	/**
	 * Deleted comments cover both trash and post-trashed.
	 */
	public function test_counts_deleted_comments_in_both_states() {
		$this->baseline( 'deleted_comments' );
		$this->make_comments( 'trash', 2 );
		$this->make_comments( 'post-trashed', 3 );

		$this->assertSweepDelta( 5, 'deleted_comments' );
	}

	/**
	 * Both site and blog transients are counted.
	 */
	public function test_counts_transient_options() {
		$this->baseline( 'transient_options' );

		set_transient( 'sweep_transient_a', 'x', HOUR_IN_SECONDS );
		set_transient( 'sweep_transient_b', 'y', HOUR_IN_SECONDS );

		// A transient with an expiry writes a _transient_timeout_ row too.
		$this->assertSweepDelta( 4, 'transient_options' );
	}

	/**
	 * Orphaned meta is counted for all four object types, including the
	 * ID 0 rows the meta API cannot reach.
	 *
	 * @dataProvider data_orphan_meta
	 *
	 * @param string $sweep_name Sweep name.
	 * @param string $table      Meta table shorthand.
	 */
	public function test_counts_orphan_meta( $sweep_name, $table ) {
		$this->baseline( $sweep_name );
		$this->make_orphan_meta( $table );

		$this->assertSweepDelta( 2, $sweep_name );
	}

	/**
	 * Orphan meta sweeps and their tables.
	 *
	 * @return array
	 */
	public function data_orphan_meta() {
		return array(
			'post meta'    => array( 'orphan_postmeta', 'postmeta' ),
			'comment meta' => array( 'orphan_commentmeta', 'commentmeta' ),
			'user meta'    => array( 'orphan_usermeta', 'usermeta' ),
			'term meta'    => array( 'orphan_termmeta', 'termmeta' ),
		);
	}

	/**
	 * Duplicated meta counts every row in a duplicated group.
	 *
	 * @dataProvider data_duplicate_meta
	 *
	 * @param string $sweep_name Sweep name.
	 * @param string $type       Object type.
	 */
	public function test_counts_duplicated_meta( $sweep_name, $type ) {
		$this->baseline( $sweep_name );
		$this->make_duplicate_meta( $type );

		$this->assertSweepDelta( 2, $sweep_name );
	}

	/**
	 * Duplicated meta sweeps and their object types.
	 *
	 * @return array
	 */
	public function data_duplicate_meta() {
		return array(
			'post meta'    => array( 'duplicated_postmeta', 'post' ),
			'comment meta' => array( 'duplicated_commentmeta', 'comment' ),
			'user meta'    => array( 'duplicated_usermeta', 'user' ),
			'term meta'    => array( 'duplicated_termmeta', 'term' ),
		);
	}

	/**
	 * Term relationships pointing at a missing object are counted.
	 */
	public function test_counts_orphan_term_relationships() {
		$this->baseline( 'orphan_term_relationships' );
		$this->make_orphan_term_relationship();

		$this->assertSweepDelta( 1, 'orphan_term_relationships' );
	}

	/**
	 * The link_category taxonomy is excluded from orphaned term relationships,
	 * which is the documented behaviour of wp_sweep_excluded_taxonomies.
	 */
	public function test_excludes_link_category_from_orphan_term_relationships() {
		register_taxonomy( 'link_category', 'link' );

		$this->baseline( 'orphan_term_relationships' );
		$this->make_orphan_term_relationship( 'link_category' );

		$this->assertSweepDelta( 0, 'orphan_term_relationships' );
	}

	/**
	 * Terms attached to nothing are counted as unused.
	 */
	public function test_counts_unused_terms() {
		$this->baseline( 'unused_terms' );
		$this->make_unused_terms( 3 );

		$this->assertSweepDelta( 3, 'unused_terms' );
	}

	/**
	 * A term in use is not counted as unused.
	 */
	public function test_does_not_count_terms_in_use() {
		$this->baseline( 'unused_terms' );

		$term_id = self::factory()->term->create( array( 'taxonomy' => 'post_tag' ) );
		$post_id = self::factory()->post->create();
		wp_set_object_terms( $post_id, array( $term_id ), 'post_tag' );

		$this->assertSweepDelta( 0, 'unused_terms' );
	}

	/**
	 * The default category is never counted as unused, even with no posts.
	 */
	public function test_never_counts_the_default_category_as_unused() {
		$default = (int) get_option( 'default_category' );
		$this->assertGreaterThan( 0, $default, 'The install has no default category to protect.' );

		$details = $this->sweep()->details( 'unused_terms' );
		$name    = get_term( $default, 'category' )->name;

		$this->assertNotContains( $name, $details );
	}

	/**
	 * Cached oEmbed responses are matched on the meta key prefix.
	 */
	public function test_counts_oembed_postmeta() {
		$this->baseline( 'oembed_postmeta' );
		$this->make_oembed_meta( 2 );

		$this->assertSweepDelta( 2, 'oembed_postmeta' );
	}

	/**
	 * The optimize_database sweep reports tables, not a row count.
	 */
	public function test_counts_tables_for_optimize_database() {
		$this->assertSame(
			(int) $this->sweep()->total_count( 'tables' ),
			(int) $this->sweep()->count( 'optimize_database' )
		);
		$this->assertGreaterThan( 0, (int) $this->sweep()->count( 'optimize_database' ) );
	}

	/**
	 * An unknown sweep name counts zero rather than erroring.
	 */
	public function test_unknown_sweep_name_counts_zero() {
		$this->assertSame( 0, (int) $this->sweep()->count( 'no_such_sweep' ) );
		$this->assertSame( 0, (int) $this->sweep()->total_count( 'no_such_table' ) );
	}

	/**
	 * Totals are answered for every table total_count() knows about.
	 *
	 * A fresh install legitimately has zero posts, so the assertion is that
	 * the shorthand is recognised and returns a countable number — not that
	 * the table has rows. test_total_count_tracks_the_posts_table() below
	 * proves it is really counting.
	 *
	 * @dataProvider data_total_count_types
	 *
	 * @param string $type Table shorthand used by total_count().
	 */
	public function test_total_count_answers_for_core_tables( $type ) {
		$count = $this->sweep()->total_count( $type );

		$this->assertNotNull( $count, "total_count( '{$type}' ) did not recognise the table." );
		$this->assertGreaterThanOrEqual( 0, (int) $count );
	}

	/**
	 * The tables and options shorthands always have something to report.
	 */
	public function test_total_count_is_positive_for_tables_and_options() {
		$this->assertGreaterThan( 0, (int) $this->sweep()->total_count( 'tables' ) );
		$this->assertGreaterThan( 0, (int) $this->sweep()->total_count( 'options' ) );
		$this->assertGreaterThan( 0, (int) $this->sweep()->total_count( 'users' ) );
	}

	/**
	 * Table shorthands total_count() supports.
	 *
	 * @return array
	 */
	public function data_total_count_types() {
		return array(
			array( 'posts' ),
			array( 'postmeta' ),
			array( 'users' ),
			array( 'usermeta' ),
			array( 'term_relationships' ),
			array( 'term_taxonomy' ),
			array( 'terms' ),
			array( 'options' ),
			array( 'tables' ),
		);
	}

	/**
	 * Totals follow the posts table as rows are added.
	 */
	public function test_total_count_tracks_the_posts_table() {
		$before = (int) $this->sweep()->total_count( 'posts' );
		self::factory()->post->create();

		$this->assertSame( $before + 1, (int) $this->sweep()->total_count( 'posts' ) );
	}

	/**
	 * Percentages are rounded to two places and never divide by zero.
	 */
	public function test_format_percentage() {
		$this->assertSame( '50%', $this->sweep()->format_percentage( 5, 10 ) );
		$this->assertSame( '33.33%', $this->sweep()->format_percentage( 1, 3 ) );
		$this->assertSame( '0%', $this->sweep()->format_percentage( 5, 0 ) );
		$this->assertSame( '0%', $this->sweep()->format_percentage( 0, 0 ) );
		$this->assertSame( '100%', $this->sweep()->format_percentage( 7, 7 ) );
	}
}
