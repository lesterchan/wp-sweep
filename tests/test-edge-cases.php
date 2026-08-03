<?php
/**
 * Tests for the fallback branches that only run when the WordPress APIs
 * refuse to act.
 *
 * @package wp-sweep
 */

/**
 * WP-Sweep exists to delete rows the WordPress APIs cannot see, so its most
 * important code is the part that runs when those APIs decline. Every branch
 * here drops to direct SQL, and every one of them was uncovered.
 */
class WP_Sweep_Edge_Cases_Test extends WP_Sweep_TestCase {

	/**
	 * Registers a taxonomy, then forgets it, leaving its rows behind.
	 *
	 * This is the state a site is left in when a plugin that registered a
	 * taxonomy is deactivated: the terms, the term_taxonomy rows and the
	 * relationships all survive, but taxonomy_exists() is false and the term
	 * API refuses to touch any of it.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $how_many Number of terms.
	 * @return array Term IDs.
	 */
	protected function make_terms_in_a_forgotten_taxonomy( $taxonomy, $how_many = 2 ) {
		register_taxonomy( $taxonomy, 'post' );

		$term_ids = array();
		for ( $i = 0; $i < $how_many; $i++ ) {
			$term_ids[] = self::factory()->term->create(
				array(
					'taxonomy' => $taxonomy,
					'name'     => "sweep-forgotten-{$i}",
				)
			);
		}

		unregister_taxonomy( $taxonomy );

		$this->assertFalse( taxonomy_exists( $taxonomy ), 'The taxonomy really was unregistered, or the fixture is not a forgotten one.' );

		return $term_ids;
	}

	/**
	 * Terms belonging to a taxonomy nothing registers any more are swept.
	 *
	 * Core refuses to wp_delete_term() an unregistered taxonomy, so the
	 * plugin deletes the term_taxonomy rows directly and then clears the
	 * terms left with no taxonomy at all.
	 */
	public function test_sweeps_terms_from_an_unregistered_taxonomy() {
		global $wpdb;

		$term_ids = $this->make_terms_in_a_forgotten_taxonomy( 'sweep_gone_tax', 2 );

		$this->sweep()->sweep( 'unused_terms' );

		foreach ( $term_ids as $term_id ) {
			$this->assertSame(
				'0',
				$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $term_id ) ),
				'A term_taxonomy row survived for an unregistered taxonomy.'
			);
			$this->assertSame(
				'0',
				$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} WHERE term_id = %d", $term_id ) ),
				'A terms row was left behind with no taxonomy pointing at it.'
			);
		}
	}

	/**
	 * Clearing the orphaned terms rows must not take live terms with it.
	 *
	 * The cleanup runs `DELETE FROM terms WHERE term_id NOT IN (SELECT
	 * term_id FROM term_taxonomy)`, which touches every term on the site.
	 */
	public function test_unregistered_taxonomy_cleanup_spares_live_terms() {
		$live = self::factory()->term->create( array( 'taxonomy' => 'post_tag' ) );
		wp_set_object_terms( self::factory()->post->create(), array( $live ), 'post_tag' );

		$this->make_terms_in_a_forgotten_taxonomy( 'sweep_gone_tax_two', 1 );

		$this->sweep()->sweep( 'unused_terms' );

		$this->assertInstanceOf( WP_Term::class, get_term( $live, 'post_tag' ), 'A term in a live taxonomy is spared.' );
		$this->assertInstanceOf( WP_Term::class, get_term( (int) get_option( 'default_category' ), 'category' ), 'The default category is spared.' );
	}

	/**
	 * A relationship in an unregistered taxonomy is removed too.
	 *
	 * Core returns a WP_Error from wp_remove_object_terms() for a taxonomy it does not
	 * know, so the plugin falls back to deleting the row itself.
	 */
	public function test_sweeps_orphan_relationships_in_an_unregistered_taxonomy() {
		global $wpdb;

		register_taxonomy( 'sweep_rel_tax', 'post' );

		$term_id = self::factory()->term->create( array( 'taxonomy' => 'sweep_rel_tax' ) );
		$term    = get_term( $term_id, 'sweep_rel_tax' );
		$ttid    = (int) $term->term_taxonomy_id;

		// object_id 999999 is a post that no longer exists.
		$wpdb->insert(
			$wpdb->term_relationships,
			array(
				'object_id'        => 999999,
				'term_taxonomy_id' => $ttid,
				'term_order'       => 0,
			)
		);

		unregister_taxonomy( 'sweep_rel_tax' );

		$this->sweep()->sweep( 'orphan_term_relationships' );

		$this->assertSame(
			'0',
			$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d", $ttid ) ),
			'The orphan relationship is swept even though its taxonomy is not registered.'
		);
	}

	/**
	 * Cached oEmbed rows keyed to post ID 0 are deleted with direct SQL.
	 *
	 * The meta API will not act on ID 0, so without the fallback these
	 * rows are counted forever and never removed — the count never reaches
	 * zero and the button never goes away.
	 */
	public function test_sweeps_oembed_meta_attached_to_post_id_zero() {
		global $wpdb;

		$this->baseline( 'oembed_postmeta' );

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $wpdb->postmeta ( post_id, meta_key, meta_value ) VALUES ( %d, %s, %s )",
				0,
				'_oembed_' . md5( 'sweep-zero' ),
				'<iframe></iframe>'
			)
		);

		$this->assertSweepDelta( 1, 'oembed_postmeta' );

		$this->sweep()->sweep( 'oembed_postmeta' );

		$this->assertSweepDelta( 0, 'oembed_postmeta' );

		$this->assertSame(
			'0',
			$wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = 0 AND meta_key LIKE '\_oembed\_%'" ),
			'oEmbed meta attached to post zero is swept; it belongs to no post at all.'
		);
	}

	/**
	 * Site transients are removed through delete_site_transient(), not
	 * delete_transient(). The two write different option names, and using
	 * the wrong one leaves the row in place.
	 *
	 * The reach of that differs by install type, and the difference is not a
	 * bug in the sweep. On a single site set_site_transient() writes
	 * `_site_transient_*` into wp_options, so the sweep finds it. On a network
	 * it writes to wp_sitemeta instead, which this sweep never reads: it reads
	 * the options table of the one site being swept. So a network transient
	 * survives, and that is the outcome to want — a site transient on a network
	 * is shared by every site on it, and one site's Sweep screen has no business
	 * purging a cache the whole network is relying on.
	 */
	public function test_sweeps_site_transients() {
		set_site_transient( 'sweep_site_transient', 'value', HOUR_IN_SECONDS );
		set_transient( 'sweep_blog_transient', 'value', HOUR_IN_SECONDS );

		$this->assertSame( 'value', get_site_transient( 'sweep_site_transient' ), 'the site transient was never stored' );

		$this->sweep()->sweep( 'transient_options' );

		$this->assertFalse( get_transient( 'sweep_blog_transient' ), 'the blog transient survived the sweep' );

		if ( is_multisite() ) {
			$this->assertSame(
				'value',
				get_site_transient( 'sweep_site_transient' ),
				'sweeping one site purged a transient the whole network shares'
			);

			return;
		}

		$this->assertFalse( get_site_transient( 'sweep_site_transient' ), 'the site transient survived the sweep' );
	}

	/**
	 * A term that is the parent of another is never swept, whichever
	 * taxonomy it lives in.
	 *
	 * @dataProvider data_hierarchical_taxonomies
	 *
	 * @param string $taxonomy Taxonomy name.
	 */
	public function test_parent_terms_are_protected( $taxonomy ) {
		$parent = self::factory()->term->create( array( 'taxonomy' => $taxonomy ) );
		$child  = self::factory()->term->create(
			array(
				'taxonomy' => $taxonomy,
				'parent'   => $parent,
			)
		);

		$this->sweep()->sweep( 'unused_terms' );

		$this->assertInstanceOf( WP_Term::class, get_term( $parent, $taxonomy ), 'A term with children is protected from the sweep.' );
		$this->assertNull( get_term( $child, $taxonomy ), 'The unused child should still go.' );
	}

	/**
	 * Hierarchical taxonomies to test parent protection in.
	 *
	 * @return array
	 */
	public function data_hierarchical_taxonomies() {
		return array(
			'category' => array( 'category' ),
		);
	}

	/**
	 * Details are listed for every duplicated meta type, not just post meta.
	 *
	 * @dataProvider data_duplicate_meta_details
	 *
	 * @param string $sweep_name Sweep name.
	 * @param string $type       Object type.
	 */
	public function test_details_list_duplicated_meta_keys( $sweep_name, $type ) {
		$this->make_duplicate_meta( $type );

		$this->assertContains( 'sweep_dupe', $this->sweep()->details( $sweep_name ), 'The ' . $sweep_name . ' details list the duplicated key.' );
	}

	/**
	 * Duplicated meta sweeps and their object types.
	 *
	 * @return array
	 */
	public function data_duplicate_meta_details() {
		return array(
			'comment meta' => array( 'duplicated_commentmeta', 'comment' ),
			'user meta'    => array( 'duplicated_usermeta', 'user' ),
			'term meta'    => array( 'duplicated_termmeta', 'term' ),
		);
	}

	/**
	 * A filter returning something that is not an array does not fatal.
	 *
	 * The get_excluded_termids() method guards both halves of its merge, as the two
	 * helpers it calls can return a non-array on an unusual install.
	 */
	public function test_excluded_termids_survives_a_non_array_filter() {
		add_filter( 'wp_sweep_excluded_termids', '__return_false' );

		$this->assertNotNull( $this->sweep()->count( 'unused_terms' ), 'A non-array from the filter is survivable and the count still answers.' );
	}
}
