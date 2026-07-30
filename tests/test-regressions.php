<?php
/**
 * Tests for bugs found while modernizing the plugin.
 *
 * @package wp-sweep
 */

/**
 * Each of these failed against the code as it stood before 2.0.0. They are
 * kept apart from the behaviour suite so it stays obvious which assertions
 * describe a fix rather than something that always worked.
 */
class WP_Sweep_Regressions_Test extends WP_Sweep_TestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin;

	/**
	 * Creates an administrator.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 * @return void
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin = self::create_admin( $factory );
	}

	// -- An empty excluded term ID list built invalid SQL. --

	/**
	 * The wp_sweep_excluded_termids filter is public API, so a site may
	 * filter it down to nothing. The IDs were interpolated straight into
	 * `NOT IN (...)`, so an empty list produced `NOT IN ()` — a syntax error.
	 * The count came back NULL and the admin screen rendered a blank cell.
	 *
	 * @dataProvider data_unused_terms_entry_points
	 *
	 * @param string $method Method on the plugin instance.
	 */
	public function test_empty_excluded_termids_does_not_break_the_query( $method ) {
		global $wpdb;

		add_filter( 'wp_sweep_excluded_termids', '__return_empty_array' );

		$this->make_unused_terms( 2 );

		$wpdb->last_error = '';
		$result           = $this->sweep()->$method( 'unused_terms' );

		$this->assertSame( '', $wpdb->last_error, 'The unused terms query errored.' );
		$this->assertNotNull( $result );
	}

	/**
	 * Every entry point that builds the unused terms query.
	 *
	 * @return array
	 */
	public function data_unused_terms_entry_points() {
		return array(
			'count'   => array( 'count' ),
			'details' => array( 'details' ),
			'sweep'   => array( 'sweep' ),
		);
	}

	/**
	 * With nothing excluded, an unused term really is swept — the guard must
	 * not quietly turn the sweep into a no-op.
	 */
	public function test_empty_excluded_termids_still_sweeps() {
		add_filter( 'wp_sweep_excluded_termids', '__return_empty_array' );

		$terms = $this->make_unused_terms( 2 );
		$this->sweep()->sweep( 'unused_terms' );

		$this->assertNull( get_term( $terms[0], 'post_tag' ) );
	}

	/**
	 * The same filter returning a single ID still protects that term.
	 */
	public function test_single_excluded_termid_is_honoured() {
		$terms = $this->make_unused_terms( 2 );

		add_filter(
			'wp_sweep_excluded_termids',
			static function () use ( $terms ) {
				return array( $terms[0] );
			}
		);

		$this->sweep()->sweep( 'unused_terms' );

		$this->assertInstanceOf( WP_Term::class, get_term( $terms[0], 'post_tag' ) );
		$this->assertNull( get_term( $terms[1], 'post_tag' ) );
	}

	// -- The sweep name from the request was never validated. --

	/**
	 * There is one canonical list of sweep names, and everything that
	 * accepts a name from outside checks against it.
	 */
	public function test_plugin_exposes_a_canonical_sweep_name_list() {
		$names = $this->sweep()->get_sweep_names();

		$this->assertIsArray( $names );
		$this->assertContains( 'revisions', $names );
		$this->assertContains( 'oembed_postmeta', $names );
		$this->assertCount( 19, $names );
	}

	/**
	 * The REST allow list is that same list rather than a second copy.
	 */
	public function test_rest_api_uses_the_canonical_list() {
		$api        = new WP_Sweep_API();
		$reflection = new ReflectionMethod( $api, 'is_sweep_name_valid' );

		foreach ( $this->sweep()->get_sweep_names() as $name ) {
			$this->assertTrue( $reflection->invoke( $api, $name ) );
		}

		$this->assertFalse( $reflection->invoke( $api, 'no_such_sweep' ) );
	}

	/**
	 * A name that is not on the list is rejected outright.
	 */
	public function test_unknown_sweep_names_are_rejected() {
		$this->assertFalse( $this->sweep()->is_sweep_name_valid( 'no_such_sweep' ) );
		$this->assertFalse( $this->sweep()->is_sweep_name_valid( '' ) );
		$this->assertFalse( $this->sweep()->is_sweep_name_valid( 'revisions; DROP TABLE' ) );
		$this->assertTrue( $this->sweep()->is_sweep_name_valid( 'revisions' ) );
	}

	// -- What the plugin stores, and what uninstall has to remove. --

	/**
	 * Nothing in the plugin writes an option row.
	 *
	 * WP-Sweep has no settings and no tables, so under STANDARDS.md 2.1 it
	 * stores nothing at all -- not even version markers. A write appearing
	 * anywhere is a row nothing would ever clean up.
	 */
	public function test_nothing_writes_an_option() {
		$writes = array( 'add_option(', 'update_option(', 'add_site_option(', 'update_site_option(' );

		foreach ( $this->plugin_sources() as $file => $source ) {
			foreach ( $writes as $write ) {
				$this->assertStringNotContainsString(
					$write,
					$source,
					"{$file} writes an option row that uninstall.php does not know about."
				);
			}
		}
	}

	/**
	 * Every option row the plugin writes is one uninstall.php deletes.
	 *
	 * Read from the source rather than the database, because uninstall.php is
	 * never loaded during a test run -- it guards on WP_UNINSTALL_PLUGIN, and
	 * defining that would take the rest of the suite with it.
	 */
	public function test_uninstall_deletes_every_row_the_plugin_writes() {
		$uninstall = file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );

		foreach ( array( 'wp_sweep_options', 'wp_sweep_version' ) as $row ) {
			$this->assertStringContainsString(
				"delete_option( '{$row}' )",
				$uninstall,
				"uninstall.php leaves '{$row}' behind."
			);
		}
	}

	/**
	 * No nonce action can ever be mistaken for one of the option rows.
	 *
	 * The nonce actions are built as 'wp_sweep_' . $name, so a sweep named
	 * `options` or `version` would produce the string 'wp_sweep_options' or
	 * 'wp_sweep_version' -- identical to an option row name, in a codebase
	 * where both are just strings. Nothing in WordPress would notice.
	 */
	public function test_no_nonce_action_reads_as_an_option_row() {
		$rows = array( 'wp_sweep_options', 'wp_sweep_version' );

		foreach ( $this->sweep()->get_sweep_names() as $name ) {
			$this->assertNotContains(
				'wp_sweep_' . $name,
				$rows,
				"The nonce action for '{$name}' is spelled the same as an option row."
			);
			$this->assertNotContains(
				'wp_sweep_details_' . $name,
				$rows,
				"The details nonce action for '{$name}' is spelled the same as an option row."
			);
		}
	}

	/**
	 * The plugin owns no database table either.
	 */
	public function test_plugin_creates_no_tables() {
		foreach ( $this->plugin_sources() as $file => $source ) {
			$this->assertStringNotContainsString( 'CREATE TABLE', $source, "{$file} creates a table." );
			$this->assertStringNotContainsString( 'dbDelta(', $source, "{$file} runs dbDelta()." );
		}
	}

	/**
	 * Nothing calls wp_get_sites(), which has been deprecated since WordPress
	 * 4.6 and still ships in ms-deprecated.php. It does not fatal -- it returns
	 * only the first 100 sites, so a network larger than that is swept in part
	 * and reports success, which is the worse failure of the two.
	 */
	public function test_nothing_calls_the_deprecated_site_function() {
		foreach ( $this->plugin_sources() as $file => $source ) {
			$this->assertStringNotContainsString( 'wp_get_sites(', $source, "{$file} calls wp_get_sites()." );
		}
	}

	/**
	 * Any surviving switch_to_blog() restores the blog in the same breath.
	 *
	 * A switch_to_blog() call pushes onto a stack, so a restore placed after the
	 * loop rather than inside it leaves the stack unwound by exactly one.
	 * Counting the two calls is not enough to catch that — the check is that
	 * no block closes between the switch and its restore.
	 */
	public function test_any_switch_to_blog_restores_inside_the_loop() {
		foreach ( $this->plugin_sources() as $file => $source ) {
			// str_contains() is PHP 8.0; the floor here is 7.4.
			if ( false === strpos( $source, 'switch_to_blog(' ) ) {
				continue;
			}

			$this->assertMatchesRegularExpression(
				'/switch_to_blog\([^}]*restore_current_blog\(\)/s',
				$source,
				"{$file} closes a block between switch_to_blog() and restore_current_blog()."
			);
		}
	}

	/**
	 * The uninstall file refuses to run outside the uninstall request.
	 */
	public function test_uninstall_guards_against_direct_access() {
		$source = file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );

		$this->assertStringContainsString( 'WP_UNINSTALL_PLUGIN', $source );
	}

	/**
	 * Every PHP file the plugin ships, keyed by name.
	 *
	 * @return array
	 */
	protected function plugin_sources() {
		$root  = dirname( __DIR__ );
		$files = array( $root . '/wp-sweep.php', $root . '/uninstall.php' );

		// The class files and the admin screen move to includes/ in 2.0.0;
		// both locations are globbed so this keeps working across the move.
		foreach ( array( '/inc/*.php', '/includes/*.php', '/admin.php' ) as $pattern ) {
			$matches = glob( $root . $pattern );

			if ( is_array( $matches ) ) {
				$files = array_merge( $files, $matches );
			}
		}

		$sources = array();
		foreach ( $files as $file ) {
			if ( is_readable( $file ) ) {
				$sources[ basename( $file ) ] = file_get_contents( $file );
			}
		}

		$this->assertNotEmpty( $sources );

		return $sources;
	}

	// -- A stale default_<taxonomy> option protected an unrelated term. --

	/**
	 * A default_<taxonomy> option is only honoured if the term really exists
	 * in that taxonomy.
	 *
	 * WordPress ships default_link_category set to 2, naming the "Blogroll"
	 * term that installs have not had since the Links Manager was retired in
	 * 3.5. Before 2.0.0 the plugin trusted every default_<taxonomy> option
	 * blindly, so term ID 2 was excluded from the unused terms sweep on
	 * essentially every site — and on most sites term 2 is an ordinary
	 * category or tag, which simply refused to sweep with no explanation.
	 */
	public function test_stale_default_option_does_not_protect_an_unrelated_term() {
		$this->assertSame(
			2,
			(int) get_option( 'default_link_category' ),
			'WordPress no longer ships default_link_category as 2; revisit this test.'
		);
		$this->assertNull(
			term_exists( 2, 'link_category' ),
			'This install really has a link_category term 2; revisit this test.'
		);

		$excluded = $this->excluded_termids();

		$this->assertNotContains( 2, $excluded, 'A term is protected by a stale option.' );
	}

	/**
	 * The real default category is still protected.
	 */
	public function test_live_default_option_still_protects_its_term() {
		$default = (int) get_option( 'default_category' );

		$this->assertNotNull( term_exists( $default, 'category' ) );
		$this->assertContains( $default, $this->excluded_termids() );
	}

	/**
	 * A term that happens to hold the ID of a stale default option sweeps
	 * like any other unused term.
	 */
	public function test_a_term_holding_a_stale_default_id_can_be_swept() {
		$excluded = $this->excluded_termids();

		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'name'     => 'sweep-collides-with-stale-default',
			)
		);

		$this->assertNotContains( $term_id, $excluded );

		$this->sweep()->sweep( 'unused_terms' );

		$this->assertNull( get_term( $term_id, 'post_tag' ) );
	}

	/**
	 * Reads the private list of excluded term IDs.
	 *
	 * @return array Excluded term IDs, as integers.
	 */
	protected function excluded_termids() {
		$method = new ReflectionMethod( $this->sweep(), 'get_excluded_termids' );

		return array_map( 'intval', (array) $method->invoke( $this->sweep() ) );
	}
}
