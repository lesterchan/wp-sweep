<?php
/**
 * Tests for the filters and actions WP-Sweep exposes.
 *
 * @package wp-sweep
 */

/**
 * These hook names are the plugin's public API. Sites use them to protect
 * metadata from deletion, so a rename here is a data-loss bug in someone
 * else's install. Every documented name is pinned.
 */
class Test_WP_Sweep_Filters extends WP_Sweep_TestCase {

	/**
	 * The wp_sweep_count filter overrides a count and is passed the name.
	 */
	public function test_wp_sweep_count_filter_receives_the_name() {
		$seen = array();

		add_filter(
			'wp_sweep_count',
			static function ( $count, $name ) use ( &$seen ) {
				$seen[] = $name;
				return 'revisions' === $name ? 4242 : $count;
			},
			10,
			2
		);

		$this->assertSame( 4242, (int) $this->sweep()->count( 'revisions' ) );
		$this->assertContains( 'revisions', $seen );
	}

	/**
	 * The wp_sweep_total_count filter overrides a table total.
	 */
	public function test_wp_sweep_total_count_filter() {
		add_filter(
			'wp_sweep_total_count',
			static function ( $count, $name ) {
				return 'posts' === $name ? 999 : $count;
			},
			10,
			2
		);

		$this->assertSame( 999, (int) $this->sweep()->total_count( 'posts' ) );
	}

	/**
	 * The wp_sweep_details filter replaces the sample list.
	 */
	public function test_wp_sweep_details_filter() {
		add_filter(
			'wp_sweep_details',
			static function ( $details, $name ) {
				return 'revisions' === $name ? array( 'replaced' ) : $details;
			},
			10,
			2
		);

		$this->assertSame( array( 'replaced' ), $this->sweep()->details( 'revisions' ) );
	}

	/**
	 * The wp_sweep_sweep filter rewrites the result message.
	 */
	public function test_wp_sweep_sweep_filter() {
		$this->make_revisions( 1 );

		add_filter(
			'wp_sweep_sweep',
			static function ( $message, $name ) {
				return 'revisions' === $name ? 'rewritten' : $message;
			},
			10,
			2
		);

		$this->assertSame( 'rewritten', $this->sweep()->sweep( 'revisions' ) );
	}

	/**
	 * The wp_sweep_excluded_taxonomies filter keeps a taxonomy out of the
	 * term relationships sweep.
	 */
	public function test_wp_sweep_excluded_taxonomies_filter() {
		$this->baseline( 'orphan_term_relationships' );
		$this->make_orphan_term_relationship( 'post_tag' );

		$this->assertSweepDelta( 1, 'orphan_term_relationships' );

		add_filter(
			'wp_sweep_excluded_taxonomies',
			static function ( $taxonomies ) {
				$taxonomies[] = 'post_tag';
				return $taxonomies;
			}
		);

		$this->assertSweepDelta( 0, 'orphan_term_relationships' );
	}

	/**
	 * The link_category taxonomy is that filter's documented default.
	 */
	public function test_excluded_taxonomies_defaults_to_link_category() {
		$seen = null;

		add_filter(
			'wp_sweep_excluded_taxonomies',
			static function ( $taxonomies ) use ( &$seen ) {
				$seen = $taxonomies;
				return $taxonomies;
			}
		);

		$this->sweep()->count( 'orphan_term_relationships' );

		$this->assertSame( array( 'link_category' ), $seen );
	}

	/**
	 * The wp_sweep_excluded_termids filter protects a term from that sweep.
	 */
	public function test_wp_sweep_excluded_termids_filter() {
		$this->baseline( 'unused_terms' );
		$terms = $this->make_unused_terms( 2 );

		$this->assertSweepDelta( 2, 'unused_terms' );

		add_filter(
			'wp_sweep_excluded_termids',
			static function ( $ids ) use ( $terms ) {
				return array_merge( $ids, $terms );
			}
		);

		$this->assertSweepDelta( 0, 'unused_terms' );

		$this->sweep()->sweep( 'unused_terms' );

		foreach ( $terms as $term_id ) {
			$this->assertInstanceOf( WP_Term::class, get_term( $term_id, 'post_tag' ) );
		}
	}

	/**
	 * The default excluded term IDs cover the default taxonomy terms.
	 */
	public function test_excluded_termids_defaults_include_the_default_category() {
		$seen = null;

		add_filter(
			'wp_sweep_excluded_termids',
			static function ( $ids ) use ( &$seen ) {
				$seen = $ids;
				return $ids;
			}
		);

		$this->sweep()->count( 'unused_terms' );

		$this->assertContains( (int) get_option( 'default_category' ), array_map( 'intval', $seen ) );
	}

	/**
	 * An exact meta key on the whitelist is neither counted nor deleted.
	 *
	 * @dataProvider data_meta_whitelists
	 *
	 * @param string $filter     Filter name.
	 * @param string $sweep_name Sweep name.
	 * @param string $table      Meta table shorthand.
	 */
	public function test_meta_key_whitelist_protects_an_exact_key( $filter, $sweep_name, $table ) {
		add_filter(
			$filter,
			static function ( $keys ) {
				$keys[] = 'sweep_protected';
				return $keys;
			}
		);

		$this->baseline( $sweep_name );
		$this->make_orphan_meta( $table, 'sweep_protected' );

		$this->assertSweepDelta( 0, $sweep_name );

		$this->sweep()->sweep( $sweep_name );

		$this->assertSame( 2, $this->count_meta_rows( $table, 'sweep_protected' ) );
	}

	/**
	 * A wildcard pattern on the whitelist protects every matching key.
	 *
	 * @dataProvider data_meta_whitelists
	 *
	 * @param string $filter     Filter name.
	 * @param string $sweep_name Sweep name.
	 * @param string $table      Meta table shorthand.
	 */
	public function test_meta_key_whitelist_supports_wildcards( $filter, $sweep_name, $table ) {
		add_filter(
			$filter,
			static function ( $keys ) {
				$keys[] = '_acme_*';
				return $keys;
			}
		);

		$this->baseline( $sweep_name );
		$this->make_orphan_meta( $table, '_acme_setting' );
		$this->make_orphan_meta( $table, 'unprotected_key' );

		$this->assertSweepDelta( 2, $sweep_name );

		$this->sweep()->sweep( $sweep_name );

		$this->assertSame( 2, $this->count_meta_rows( $table, '_acme_setting' ) );
		$this->assertSame( 0, $this->count_meta_rows( $table, 'unprotected_key' ) );
	}

	/**
	 * A protected key does not appear in the Details list either.
	 */
	public function test_meta_key_whitelist_hides_the_key_from_details() {
		add_filter(
			'wp_sweep_postmeta_whitelist',
			static function ( $keys ) {
				$keys[] = 'sweep_protected';
				return $keys;
			}
		);

		$this->make_orphan_meta( 'postmeta', 'sweep_protected' );
		$this->make_orphan_meta( 'postmeta', 'sweep_visible' );

		$details = $this->sweep()->details( 'orphan_postmeta' );

		$this->assertNotContains( 'sweep_protected', $details );
		$this->assertContains( 'sweep_visible', $details );
	}

	/**
	 * Per-type meta key whitelists and the sweeps they guard.
	 *
	 * @return array
	 */
	public function data_meta_whitelists() {
		return array(
			'post meta'    => array( 'wp_sweep_postmeta_whitelist', 'orphan_postmeta', 'postmeta' ),
			'comment meta' => array( 'wp_sweep_commentmeta_whitelist', 'orphan_commentmeta', 'commentmeta' ),
			'user meta'    => array( 'wp_sweep_usermeta_whitelist', 'orphan_usermeta', 'usermeta' ),
			'term meta'    => array( 'wp_sweep_termmeta_whitelist', 'orphan_termmeta', 'termmeta' ),
		);
	}

	/**
	 * A whitelist for one object type must not leak into another.
	 */
	public function test_meta_key_whitelists_are_scoped_to_their_own_type() {
		add_filter(
			'wp_sweep_postmeta_whitelist',
			static function ( $keys ) {
				$keys[] = 'sweep_scoped';
				return $keys;
			}
		);

		$this->baseline( array( 'orphan_postmeta', 'orphan_usermeta' ) );
		$this->make_orphan_meta( 'postmeta', 'sweep_scoped' );
		$this->make_orphan_meta( 'usermeta', 'sweep_scoped' );

		$this->assertSweepDelta( 0, 'orphan_postmeta' );
		$this->assertSweepDelta( 2, 'orphan_usermeta' );
	}

	/**
	 * The whitelist filters default to an empty list, so nothing is protected
	 * unless a site says so.
	 *
	 * @dataProvider data_meta_whitelists
	 *
	 * @param string $filter     Filter name.
	 * @param string $sweep_name Sweep name.
	 */
	public function test_meta_key_whitelists_default_to_empty( $filter, $sweep_name ) {
		$seen = null;

		add_filter(
			$filter,
			static function ( $keys ) use ( &$seen ) {
				$seen = $keys;
				return $keys;
			}
		);

		$this->sweep()->count( $sweep_name );

		$this->assertSame( array(), $seen );
	}
}
