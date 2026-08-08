<?php
/**
 * Tests for the filters and actions WP-Sweep exposes.
 *
 * @package WP-Sweep
 */

/**
 * These hook names are the plugin's public API. Sites use them to protect
 * metadata from deletion, so a rename here is a data-loss bug in someone
 * else's install. Every documented name is pinned.
 */
class WP_Sweep_Filters_Test extends WP_Sweep_TestCase {

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

		$this->assertSame( 4242, (int) $this->sweep()->count( 'revisions' ), 'The count filter replaces the count entirely.' );
		$this->assertContains( 'revisions', $seen, 'And is told which sweep it is answering for.' );
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

		$this->assertSame( 999, (int) $this->sweep()->total_count( 'posts' ), 'The total count filter replaces the total.' );
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

		$this->assertSame( array( 'replaced' ), $this->sweep()->details( 'revisions' ), 'The details filter replaces the sample.' );
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

		$this->assertSame( 'rewritten', $this->sweep()->sweep( 'revisions' ), 'And the sweep filter replaces the message, so the sweep itself is overridable.' );
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

		$this->assertSame( array( 'link_category' ), $seen, 'Link categories are excluded by default, since core hides them.' );
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
			$this->assertInstanceOf( WP_Term::class, get_term( $term_id, 'post_tag' ), 'A term named by the excluded_termids filter survives the sweep.' );
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

		$this->assertContains( (int) get_option( 'default_category' ), array_map( 'intval', $seen ), 'The default category is in the excluded ids by default, so it survives a sweep.' );
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

		$this->assertSame( 2, $this->count_meta_rows( $table, 'sweep_protected' ), 'An exact key on the allow list is protected from the sweep.' );
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

		$this->assertSame( 2, $this->count_meta_rows( $table, '_acme_setting' ), 'A wildcard on the allow list protects the keys it matches.' );
		$this->assertSame( 0, $this->count_meta_rows( $table, 'unprotected_key' ), 'While a key it does not match is still swept.' );
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

		$this->assertNotContains( 'sweep_protected', $details, 'A protected key is hidden from the details too, not only from the sweep.' );
		$this->assertContains( 'sweep_visible', $details, 'While an unprotected one is still listed.' );
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

		$this->assertSame( array(), $seen, 'The allow lists ship empty, so nothing is protected until a site says so.' );
	}
	// -- The hooks as a published interface. --

	/**
	 * Every hook the plugin fires, and nothing else.
	 *
	 * These names are the plugin's public API: they appear in the readme, and
	 * third-party code is hooked to them on sites this plugin will never see.
	 * Renaming one is a silent break -- the old hook simply stops firing and
	 * nothing anywhere reports it -- so the list is pinned here rather than
	 * left to be noticed.
	 */
	public function test_the_fired_hooks_are_exactly_the_documented_set() {
		$expected = array(
			'wp_sweep_admin_comment_sweep',
			'wp_sweep_admin_database_sweep',
			'wp_sweep_admin_option_sweep',
			'wp_sweep_admin_post_sweep',
			'wp_sweep_admin_term_sweep',
			'wp_sweep_admin_user_sweep',
			'wp_sweep_capability',
			'wp_sweep_commentmeta_whitelist',
			'wp_sweep_count',
			'wp_sweep_details',
			'wp_sweep_excluded_taxonomies',
			'wp_sweep_excluded_termids',
			'wp_sweep_limit_details',
			'wp_sweep_optimize_tables',
			'wp_sweep_postmeta_whitelist',
			'wp_sweep_sweep',
			'wp_sweep_termmeta_whitelist',
			'wp_sweep_total_count',
			'wp_sweep_usermeta_whitelist',
		);

		$found = array();

		foreach ( $this->plugin_source_files() as $file ) {
			preg_match_all(
				"/(?:apply_filters|do_action)\(\s*'([a-z0-9_]+)'/",
				file_get_contents( $file ),
				$matches
			);

			$found = array_merge( $found, $matches[1] );
		}

		$found = array_values( array_unique( $found ) );
		sort( $found );

		$this->assertSame( $expected, $found, 'The set of hooks the plugin fires has changed.' );
	}

	/**
	 * Every hook is prefixed with the plugin slug.
	 */
	public function test_every_fired_hook_carries_the_plugin_prefix() {
		foreach ( $this->plugin_source_files() as $file ) {
			preg_match_all(
				"/(?:apply_filters|do_action)\(\s*'([a-z0-9_]+)'/",
				file_get_contents( $file ),
				$matches
			);

			foreach ( $matches[1] as $hook ) {
				$this->assertStringStartsWith(
					'wp_sweep_',
					$hook,
					"'{$hook}' in " . basename( $file ) . ' is not prefixed with the plugin slug.'
				);
			}
		}
	}

	/**
	 * Every hook carries a docblock recording the version it appeared in.
	 *
	 * A hook without a @since is a hook nobody can safely depend on, because
	 * there is no way to tell which releases have it.
	 *
	 * A hook fired from more than one place is documented once and pointed at
	 * from the others, which is WordPress core's own convention and what WPCS
	 * expects: `\/** This filter is documented in <file> *\/`. Repeating the full
	 * block at every site is how two copies of the same @since drift apart. The
	 * pointer is not taken on trust -- the file it names has to exist, fire the
	 * same hook, and be the one carrying the @since.
	 */
	public function test_every_fired_hook_documents_the_version_it_appeared_in() {
		foreach ( $this->plugin_source_files() as $file ) {
			$lines = explode( "\n", file_get_contents( $file ) );

			foreach ( $lines as $number => $line ) {
				if ( ! preg_match( "/(?:apply_filters|do_action)\(\s*'(wp_sweep_[a-z0-9_]+)'/", $line, $fired ) ) {
					continue;
				}

				$above = implode( "\n", array_slice( $lines, max( 0, $number - 12 ), min( 12, $number ) ) );
				$opens = strrpos( $above, '/**' );

				$this->assertNotFalse( $opens, basename( $file ) . " line {$number} fires a hook with no docblock above it." );

				$docblock = substr( $above, $opens );

				if ( preg_match( '#(?:filter|action) is documented in (\S+?)\s*\*/#', $docblock, $pointer ) ) {
					$this->assert_hook_is_documented_in( $fired[1], $pointer[1], basename( $file ), $number );

					continue;
				}

				$this->assertStringContainsString(
					'@since',
					$docblock,
					basename( $file ) . " line {$number} fires a hook whose docblock has no @since."
				);
			}
		}
	}

	/**
	 * Follow a "documented in" pointer and assert it leads somewhere real.
	 *
	 * @param string $hook      Hook name the pointer belongs to.
	 * @param string $target    Plugin-relative path the docblock names.
	 * @param string $from      File the pointer was found in, for the message.
	 * @param int    $line      Line the pointer was found on, for the message.
	 * @return void
	 */
	private function assert_hook_is_documented_in( $hook, $target, $from, $line ) {
		$path = dirname( __DIR__ ) . '/' . ltrim( $target, '/' );

		$this->assertFileExists( $path, "{$from} line {$line} points at {$target}, which does not exist." );

		$source = file_get_contents( $path );

		$this->assertStringContainsString(
			"'" . $hook . "'",
			$source,
			"{$from} line {$line} points at {$target}, which does not fire {$hook}."
		);
		$this->assertStringContainsString(
			'@since',
			$source,
			"{$from} line {$line} points at {$target}, which carries no @since for {$hook}."
		);
	}

	/**
	 * Every PHP file the plugin ships.
	 *
	 * @return array Absolute paths.
	 */
	protected function plugin_source_files() {
		$root = dirname( __DIR__ );

		return array_merge(
			array( $root . '/wp-sweep.php', $root . '/uninstall.php' ),
			glob( $root . '/includes/*.php' )
		);
	}
}
