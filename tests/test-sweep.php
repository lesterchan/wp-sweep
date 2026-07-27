<?php
/**
 * Tests for Sweep::sweep() — the half that actually deletes things.
 *
 * @package wp-sweep
 */

/**
 * Deletion is irreversible, so these assertions check two things every time:
 * that the rubbish is gone, and that nothing standing next to it went with it.
 */
class Test_WP_Sweep_Sweep extends WP_Sweep_TestCase {

	/**
	 * Revisions go, the parent post stays.
	 */
	public function test_sweeps_revisions_but_keeps_the_parent() {
		$this->baseline( 'revisions' );
		$revisions = $this->make_revisions( 2 );
		$parent    = (int) get_post( $revisions[0] )->post_parent;

		$message = $this->sweep()->sweep( 'revisions' );

		$this->assertSweepDelta( 0, 'revisions' );
		$this->assertStringContainsString( 'Revisions Processed', $message );
		$this->assertInstanceOf( WP_Post::class, get_post( $parent ) );

		foreach ( $revisions as $id ) {
			$this->assertNull( get_post( $id ) );
		}
	}

	/**
	 * Auto drafts are deleted outright, not trashed.
	 */
	public function test_sweeps_auto_drafts() {
		$this->baseline( 'auto_drafts' );
		$ids = $this->make_posts_with_status( 'auto-draft', 2 );

		$message = $this->sweep()->sweep( 'auto_drafts' );

		$this->assertSweepDelta( 0, 'auto_drafts' );
		$this->assertStringContainsString( 'Auto Drafts Processed', $message );

		foreach ( $ids as $id ) {
			$this->assertNull( get_post( $id ) );
		}
	}

	/**
	 * Trashed posts go; published posts do not.
	 */
	public function test_sweeps_deleted_posts_only() {
		$this->baseline( 'deleted_posts' );
		$trashed   = $this->make_posts_with_status( 'trash', 2 );
		$published = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->sweep()->sweep( 'deleted_posts' );

		$this->assertSweepDelta( 0, 'deleted_posts' );
		$this->assertNull( get_post( $trashed[0] ) );
		$this->assertInstanceOf( WP_Post::class, get_post( $published ) );
	}

	/**
	 * Comment sweeps remove only the state they target.
	 *
	 * @dataProvider data_comment_sweeps
	 *
	 * @param string $sweep_name Sweep name.
	 * @param string $approved   comment_approved value that should be swept.
	 */
	public function test_sweeps_comments_by_state( $sweep_name, $approved ) {
		$this->baseline( $sweep_name );

		$doomed   = $this->make_comments( $approved, 2 );
		$survivor = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::factory()->post->create(),
				'comment_approved' => '1',
			)
		);

		$this->sweep()->sweep( $sweep_name );

		$this->assertSweepDelta( 0, $sweep_name );
		$this->assertNull( get_comment( $doomed[0] ) );
		$this->assertInstanceOf( WP_Comment::class, get_comment( $survivor ) );
	}

	/**
	 * Comment sweeps and the state each one targets.
	 *
	 * @return array
	 */
	public function data_comment_sweeps() {
		return array(
			'unapproved' => array( 'unapproved_comments', '0' ),
			'spam'       => array( 'spam_comments', 'spam' ),
			'trash'      => array( 'deleted_comments', 'trash' ),
		);
	}

	/**
	 * Transients are removed through the transient API.
	 */
	public function test_sweeps_transient_options() {
		set_transient( 'sweep_me', 'value', HOUR_IN_SECONDS );
		$this->assertSame( 'value', get_transient( 'sweep_me' ) );

		$message = $this->sweep()->sweep( 'transient_options' );

		$this->assertFalse( get_transient( 'sweep_me' ) );
		$this->assertStringContainsString( 'Transient Options Processed', $message );
	}

	/**
	 * Orphaned meta is deleted for every object type, and crucially the row
	 * keyed to ID 0 goes too — delete_post_meta() and friends refuse to act on
	 * ID 0, so the plugin falls back to direct SQL for exactly that row.
	 *
	 * @dataProvider data_orphan_meta_sweeps
	 *
	 * @param string $sweep_name Sweep name.
	 * @param string $table      Meta table shorthand.
	 * @param string $label      Fragment of the result message.
	 */
	public function test_sweeps_orphan_meta_including_id_zero( $sweep_name, $table, $label ) {
		$this->baseline( $sweep_name );
		$this->make_orphan_meta( $table, 'sweep_orphan' );

		$this->assertSame( 2, $this->count_meta_rows( $table, 'sweep_orphan' ) );

		$message = $this->sweep()->sweep( $sweep_name );

		$this->assertSweepDelta( 0, $sweep_name );
		$this->assertSame( 0, $this->count_meta_rows( $table, 'sweep_orphan' ) );
		$this->assertStringContainsString( $label, $message );
	}

	/**
	 * Orphan meta sweeps, their tables and their message labels.
	 *
	 * @return array
	 */
	public function data_orphan_meta_sweeps() {
		return array(
			'post meta'    => array( 'orphan_postmeta', 'postmeta', 'Orphaned Post Meta Processed' ),
			'comment meta' => array( 'orphan_commentmeta', 'commentmeta', 'Orphaned Comment Meta Processed' ),
			'user meta'    => array( 'orphan_usermeta', 'usermeta', 'Orphaned User Meta Processed' ),
			'term meta'    => array( 'orphan_termmeta', 'termmeta', 'Orphaned Term Meta Processed' ),
		);
	}

	/**
	 * Meta belonging to a live object is never treated as orphaned.
	 */
	public function test_leaves_live_postmeta_alone() {
		$post_id = self::factory()->post->create();
		add_post_meta( $post_id, 'keep_me', 'important' );

		$this->make_orphan_meta( 'postmeta' );
		$this->sweep()->sweep( 'orphan_postmeta' );

		$this->assertSame( 'important', get_post_meta( $post_id, 'keep_me', true ) );
	}

	/**
	 * Duplicated meta collapses to a single surviving row with the value intact.
	 *
	 * @dataProvider data_duplicate_meta_sweeps
	 *
	 * @param string $sweep_name Sweep name.
	 * @param string $type       Object type.
	 * @param string $table      Meta table shorthand.
	 */
	public function test_sweeps_duplicated_meta_leaving_one( $sweep_name, $type, $table ) {
		$this->baseline( $sweep_name );
		$this->make_duplicate_meta( $type );

		$this->assertSame( 2, $this->count_meta_rows( $table, 'sweep_dupe' ) );

		$this->sweep()->sweep( $sweep_name );

		$this->assertSweepDelta( 0, $sweep_name );
		$this->assertSame( 1, $this->count_meta_rows( $table, 'sweep_dupe' ) );
	}

	/**
	 * Duplicated meta sweeps, object types and tables.
	 *
	 * @return array
	 */
	public function data_duplicate_meta_sweeps() {
		return array(
			'post meta'    => array( 'duplicated_postmeta', 'post', 'postmeta' ),
			'comment meta' => array( 'duplicated_commentmeta', 'comment', 'commentmeta' ),
			'user meta'    => array( 'duplicated_usermeta', 'user', 'usermeta' ),
			'term meta'    => array( 'duplicated_termmeta', 'term', 'termmeta' ),
		);
	}

	/**
	 * The surviving duplicate keeps its value, so nothing reads back empty.
	 */
	public function test_duplicated_postmeta_survivor_keeps_its_value() {
		$post_id = $this->make_duplicate_meta( 'post' );

		$this->sweep()->sweep( 'duplicated_postmeta' );

		$this->assertSame( array( 'same' ), get_post_meta( $post_id, 'sweep_dupe', false ) );
	}

	/**
	 * Meta that merely shares a key is not a duplicate; the value must match too.
	 */
	public function test_same_key_different_values_is_not_a_duplicate() {
		$this->baseline( 'duplicated_postmeta' );

		$post_id = self::factory()->post->create();
		add_post_meta( $post_id, 'sweep_pair', 'one' );
		add_post_meta( $post_id, 'sweep_pair', 'two' );

		$this->assertSweepDelta( 0, 'duplicated_postmeta' );
	}

	/**
	 * Orphaned term relationships are removed.
	 */
	public function test_sweeps_orphan_term_relationships() {
		global $wpdb;

		$this->baseline( 'orphan_term_relationships' );
		list( $object_id, $term_taxonomy_id ) = $this->make_orphan_term_relationship();

		$message = $this->sweep()->sweep( 'orphan_term_relationships' );

		$this->assertSweepDelta( 0, 'orphan_term_relationships' );
		$this->assertStringContainsString( 'Orphaned Term Relationships Processed', $message );

		$remaining = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = %d AND term_taxonomy_id = %d",
				$object_id,
				$term_taxonomy_id
			)
		);
		$this->assertSame( 0, $remaining );
	}

	/**
	 * Unused terms are deleted; a term attached to a post is not.
	 */
	public function test_sweeps_unused_terms_only() {
		$this->baseline( 'unused_terms' );

		$unused = $this->make_unused_terms( 2 );
		$used   = self::factory()->term->create( array( 'taxonomy' => 'post_tag' ) );
		wp_set_object_terms( self::factory()->post->create(), array( $used ), 'post_tag' );

		$message = $this->sweep()->sweep( 'unused_terms' );

		$this->assertSweepDelta( 0, 'unused_terms' );
		$this->assertStringContainsString( 'Unused Terms Processed', $message );

		foreach ( $unused as $term_id ) {
			$this->assertNull( get_term( $term_id, 'post_tag' ) );
		}

		$this->assertInstanceOf( WP_Term::class, get_term( $used, 'post_tag' ) );
	}

	/**
	 * The default category survives a sweep of unused terms.
	 */
	public function test_sweeping_unused_terms_keeps_the_default_category() {
		$default = (int) get_option( 'default_category' );

		$this->sweep()->sweep( 'unused_terms' );

		$this->assertInstanceOf( WP_Term::class, get_term( $default, 'category' ) );
	}

	/**
	 * A parent term is kept even when nothing is filed under it directly,
	 * because deleting it would reparent its children.
	 */
	public function test_sweeping_unused_terms_keeps_parents() {
		$parent = self::factory()->term->create( array( 'taxonomy' => 'category' ) );
		self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'parent'   => $parent,
			)
		);

		$this->sweep()->sweep( 'unused_terms' );

		$this->assertInstanceOf( WP_Term::class, get_term( $parent, 'category' ) );
	}

	/**
	 * Cached oEmbed responses go; ordinary meta on the same post does not.
	 */
	public function test_sweeps_oembed_postmeta_only() {
		$this->baseline( 'oembed_postmeta' );

		$post_id = $this->make_oembed_meta( 2 );
		add_post_meta( $post_id, 'not_an_oembed', 'keep' );

		$message = $this->sweep()->sweep( 'oembed_postmeta' );

		$this->assertSweepDelta( 0, 'oembed_postmeta' );
		$this->assertStringContainsString( 'oEmbed Caches In Post Meta Processed', $message );
		$this->assertSame( 'keep', get_post_meta( $post_id, 'not_an_oembed', true ) );
	}

	/**
	 * Optimising the database reports every table and destroys no data.
	 *
	 * This is the one sweep that issues DDL, so it runs against a fixture
	 * created and verified inside the same test rather than relying on the
	 * transaction rollback that isolates the others.
	 */
	public function test_optimize_database_reports_tables_and_keeps_data() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'sweep-survives-optimize' ) );

		$message = $this->sweep()->sweep( 'optimize_database' );

		$this->assertStringContainsString( 'Tables Processed', $message );
		$this->assertStringContainsString(
			number_format_i18n( $this->sweep()->total_count( 'tables' ) ),
			$message
		);
		$this->assertSame( 'sweep-survives-optimize', get_post( $post_id )->post_title );
	}

	/**
	 * With nothing to do, a sweep returns an empty message. The REST endpoint
	 * turns that into "No items left to sweep."
	 */
	public function test_sweeping_nothing_returns_an_empty_message() {
		$this->sweep()->sweep( 'revisions' );

		$this->assertSame( '', $this->sweep()->sweep( 'revisions' ) );
	}

	/**
	 * An unknown sweep name is a no-op rather than an error.
	 */
	public function test_unknown_sweep_name_is_a_no_op() {
		$this->assertSame( '', $this->sweep()->sweep( 'no_such_sweep' ) );
	}

	/**
	 * Counts in messages are run through number_format_i18n(), so a four
	 * figure sweep reads as "1,234" rather than "1234".
	 */
	public function test_message_counts_are_localised() {
		$this->make_revisions( 2 );

		$message = $this->sweep()->sweep( 'revisions' );

		$this->assertStringContainsString( number_format_i18n( 2 ), $message );
	}
}
