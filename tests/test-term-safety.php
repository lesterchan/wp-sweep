<?php
/**
 * Tests for the rows the two term sweeps must not delete.
 *
 * @package WP-Sweep
 */

/**
 * The term sweeps delete data that does not come back, and each of them used a
 * proxy for "unused" that was not the same question. Every test here seeds a
 * row that is genuinely in use and asserts the sweep leaves it alone.
 */
class WP_Sweep_Term_Safety_Test extends WP_Sweep_TestCase {

	/**
	 * A relationship in a taxonomy of users is not an orphan.
	 *
	 * The sweep's test is "no row in wp_posts with this ID", which is only a
	 * test for orphanhood when object_id is a post ID. Register a taxonomy
	 * against users and object_id is a user ID; user 1 exists on every install
	 * and its number means nothing in wp_posts.
	 */
	public function test_a_user_taxonomy_relationship_is_not_an_orphan() {
		global $wpdb;

		$this->baseline( 'orphan_term_relationships' );

		register_taxonomy( 'sweep_user_tax', 'user' );

		$term = get_term( self::factory()->term->create( array( 'taxonomy' => 'sweep_user_tax' ) ), 'sweep_user_tax' );
		$ttid = (int) $term->term_taxonomy_id;
		$user = self::factory()->user->create();

		// A taxonomy of users is attached with the same table and no post exists at this ID.
		$wpdb->insert(
			$wpdb->term_relationships,
			array(
				'object_id'        => $user,
				'term_taxonomy_id' => $ttid,
				'term_order'       => 0,
			)
		);

		$this->assertNull( get_post( $user ), 'The fixture only proves anything while no post shares the user ID.' );

		$this->assertSweepDelta( 0, 'orphan_term_relationships' );

		$this->sweep()->sweep( 'orphan_term_relationships' );

		$this->assertSame(
			'1',
			$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d", $ttid ) ),
			"A live user's taxonomy relationship was swept as an orphaned post relationship."
		);

		unregister_taxonomy( 'sweep_user_tax' );
	}

	/**
	 * A taxonomy shared between posts and users is left alone too.
	 *
	 * Half its object IDs are posts and half are users, and the row does not
	 * say which. There is no safe half to sweep.
	 */
	public function test_a_taxonomy_of_both_posts_and_users_is_left_alone() {
		register_taxonomy( 'sweep_mixed_tax', array( 'post', 'user' ) );

		$this->assertContains(
			'sweep_mixed_tax',
			$this->excluded_taxonomies(),
			'A taxonomy attached to users as well as posts is excluded from the orphan sweep.'
		);

		unregister_taxonomy( 'sweep_mixed_tax' );
	}

	/**
	 * An ordinary post taxonomy is still swept.
	 *
	 * The exclusion is generated rather than listed, so it has to be shown not
	 * to have swallowed the sweep's actual subject.
	 */
	public function test_a_post_taxonomy_is_still_swept() {
		$this->assertNotContains( 'post_tag', $this->excluded_taxonomies(), 'post_tag is a taxonomy of posts and must stay in the sweep.' );
		$this->assertNotContains( 'category', $this->excluded_taxonomies(), 'category is a taxonomy of posts and must stay in the sweep.' );
	}

	/**
	 * A term used only by a draft is not unused.
	 *
	 * Core's own counter counts published posts, so tt.count is zero here while
	 * the term is on a post the author is still writing. wp_delete_term() takes
	 * the relationship with it, so the draft comes back untagged and nothing
	 * says why.
	 *
	 * @dataProvider data_unpublished_statuses
	 *
	 * @param string $status Post status the term's only post is in.
	 */
	public function test_a_term_used_only_by_an_unpublished_post_is_not_unused( $status ) {
		$this->baseline( 'unused_terms' );

		$term = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'name'     => "sweep-safety-{$status}",
			)
		);

		$post = self::factory()->post->create( array( 'post_status' => $status ) );
		wp_set_object_terms( $post, array( $term ), 'post_tag' );

		$this->assertSame( 0, (int) get_term( $term, 'post_tag' )->count, "The fixture only proves anything while the {$status} post leaves the count at zero." );

		$this->assertSweepDelta( 0, 'unused_terms' );

		$this->sweep()->sweep( 'unused_terms' );

		$this->assertInstanceOf( WP_Term::class, get_term( $term, 'post_tag' ), "A term used by a {$status} post was swept as unused." );
		$this->assertSame( array( $term ), wp_get_object_terms( $post, 'post_tag', array( 'fields' => 'ids' ) ), "The {$status} post lost its term." );
	}

	/**
	 * The post statuses core's term counter does not count.
	 *
	 * @return array Test data.
	 */
	public function data_unpublished_statuses() {
		return array(
			'draft'   => array( 'draft' ),
			'pending' => array( 'pending' ),
			'private' => array( 'private' ),
		);
	}

	/**
	 * A term attached to nothing at all is still swept.
	 *
	 * The relationship test is an additional condition, not a replacement for
	 * the count, and this is what would break if it were read as one.
	 */
	public function test_a_term_attached_to_nothing_is_still_swept() {
		$this->baseline( 'unused_terms' );

		$term = self::factory()->term->create( array( 'taxonomy' => 'post_tag' ) );

		$this->assertSweepDelta( 1, 'unused_terms' );

		$this->sweep()->sweep( 'unused_terms' );

		$this->assertNull( get_term( $term, 'post_tag' ), 'A term attached to nothing is what this sweep is for.' );
	}

	/**
	 * The orphaned-terms cleanup deletes only what this pass orphaned.
	 *
	 * It used to be an unqualified DELETE ... WHERE term_id NOT IN ( SELECT
	 * term_id FROM term_taxonomy ), which deletes every stray wp_terms row on
	 * the site whenever any unregistered taxonomy is swept. Those rows were
	 * neither counted nor listed in Details, so the screen said two and the
	 * statement deleted however many the site had.
	 */
	public function test_the_orphaned_terms_cleanup_spares_rows_it_did_not_count() {
		global $wpdb;

		// A wp_terms row with no term_taxonomy row: what a half-finished
		// import or another plugin's direct write leaves behind.
		$wpdb->insert(
			$wpdb->terms,
			array(
				'name'       => 'sweep-stray-term',
				'slug'       => 'sweep-stray-term',
				'term_group' => 0,
			)
		);

		$stray = (int) $wpdb->insert_id;

		register_taxonomy( 'sweep_safety_gone_tax', 'post' );
		$doomed = self::factory()->term->create( array( 'taxonomy' => 'sweep_safety_gone_tax' ) );
		unregister_taxonomy( 'sweep_safety_gone_tax' );

		$this->sweep()->sweep( 'unused_terms' );

		$this->assertSame(
			'1',
			$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} WHERE term_id = %d", $stray ) ),
			'A stray wp_terms row the sweep never counted was deleted anyway.'
		);
		$this->assertSame(
			'0',
			$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} WHERE term_id = %d", $doomed ) ),
			'The term this pass orphaned was left behind.'
		);

		$wpdb->delete( $wpdb->terms, array( 'term_id' => $stray ) );
	}

	/**
	 * The taxonomies the orphan sweep will not touch, as the sweep sees them.
	 *
	 * @return array Taxonomy names.
	 */
	protected function excluded_taxonomies() {
		$method = new ReflectionMethod( WP_Sweep::class, 'excluded_taxonomies_for_sql' );
		$method->setAccessible( true );

		return $method->invoke( $this->sweep() );
	}
}
