<?php
/**
 * Tests that WP-Sweep stores nothing at all.
 *
 * @package wp-sweep
 */

/**
 * WP-Sweep has no settings and no tables, so under STANDARDS.md 2.1 it writes no
 * option row -- not a settings row, and not the version markers either. Those
 * exist to tell a migration what it is upgrading from, and there is no migration
 * and nothing to migrate.
 *
 * Two rows did exist during 2.0.0 development, and neither was ever released:
 * 1.2.0 stored nothing. So both are cleaned up on uninstall and nowhere else.
 */
class WP_Sweep_Options_Test extends WP_Sweep_TestCase {

	/**
	 * The row names a pre-release build wrote.
	 *
	 * @var string[]
	 */
	const LEGACY_ROWS = array( 'wp_sweep_options', 'wp_sweep_version' );

	/**
	 * Starts every test from an install that has never run the plugin.
	 */
	public function set_up() {
		parent::set_up();

		foreach ( self::LEGACY_ROWS as $row ) {
			delete_option( $row );
		}
	}

	/**
	 * Every option row whose name starts with the plugin's prefix.
	 *
	 * A LIKE rather than two delete_option() checks: a row added later and
	 * forgotten is exactly the failure this is here to catch.
	 *
	 * @return string[]
	 */
	private function stored_rows() {
		global $wpdb;

		return (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( 'wp_sweep_' ) . '%'
			)
		);
	}

	public function test_the_plugin_stores_nothing() {
		do_action( 'plugins_loaded' );
		do_action( 'init' );

		$this->assertSame( array(), $this->stored_rows(), 'WP-Sweep wrote an option row; it is meant to store nothing at all.' );
	}

	/**
	 * Running a sweep does not start storing state either.
	 */
	public function test_sweeping_stores_nothing() {
		$this->make_revisions( 2 );

		$this->sweep()->sweep( 'revisions' );

		$this->assertSame( array(), $this->stored_rows(), 'A sweep left an option row behind.' );
	}

	/**
	 * There is no upgrade routine, because there is nothing to upgrade.
	 */
	public function test_there_is_no_upgrade_class() {
		$this->assertFalse( class_exists( 'WP_Sweep_Options' ), 'The options class is back without anything to store.' );
		$this->assertFalse( defined( 'WP_SWEEP_DB_VERSION' ), 'The schema counter is back without a schema to count.' );
	}

	/**
	 * Nothing hooks an upgrade check onto admin_init or activation.
	 */
	public function test_nothing_hooks_an_upgrade_check() {
		$code = $this->source_without_comments( '/includes/class-wp-sweep.php' );

		$this->assertStringNotContainsString( 'maybe_upgrade', $code, 'An upgrade check is hooked up again.' );
		$this->assertStringNotContainsString( 'register_activation_hook', $code, 'An activation hook is back.' );
	}

	/**
	 * Uninstall still clears both rows a pre-release build wrote.
	 */
	public function test_uninstall_clears_a_pre_release_row() {
		foreach ( self::LEGACY_ROWS as $row ) {
			update_option( $row, array( 'plugin' => '2.0.0' ) );
		}

		$this->assertNotEmpty( $this->stored_rows(), 'There should be rows to remove before uninstall runs.' );

		// Through the shared helper rather than a bare require. uninstall.php
		// declares a global function, so a second test file requiring the same
		// file fatals on redeclare - and a require_once that has already fired
		// is a silent no-op, which proves nothing. run_uninstall() includes it
		// once and drives the deletion by calling the function.
		$this->run_uninstall();

		wp_cache_flush();

		$this->assertSame( array(), $this->stored_rows(), 'uninstall.php must remove every wp_sweep_* row.' );
	}
}
