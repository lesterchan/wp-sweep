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
class Test_WP_Sweep_Regressions extends WP_Sweep_TestCase {

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
		self::$admin = $factory->user->create( array( 'role' => 'administrator' ) );
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
		$api        = new Sweep_Api();
		$reflection = new ReflectionMethod( $api, 'is_sweep_name_valid' );
		$reflection->setAccessible( true );

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

	// -- There is nothing to install, and therefore nothing to uninstall. --

	/**
	 * WP-Sweep cleans up after other plugins and stores nothing of its own.
	 *
	 * That is what makes uninstall.php a legitimate no-op, so it is asserted
	 * rather than assumed. Before 2.0.0 the plugin carried activation,
	 * deactivation and uninstall routines that looped over a multisite
	 * network — capped at 100 sites by get_sites(), and restoring the blog
	 * once after the loop rather than once per switch — to call three empty
	 * function bodies. The loops were deleted rather than fixed. If a future
	 * change starts persisting something, this test fails and says so.
	 */
	public function test_plugin_persists_nothing_of_its_own() {
		$writes = array( 'add_option(', 'update_option(', 'add_site_option(', 'update_site_option(' );

		foreach ( $this->plugin_sources() as $file => $source ) {
			foreach ( $writes as $write ) {
				$this->assertStringNotContainsString(
					$write,
					$source,
					"{$file} now writes an option, so uninstall.php can no longer be a no-op."
				);
			}
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
	 * Nothing calls wp_get_sites(), which was removed in WordPress 5.1 and
	 * fatals on any network that reaches it.
	 */
	public function test_nothing_calls_the_removed_site_function() {
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
}
