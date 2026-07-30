<?php
/**
 * Tests for the option row and the upgrade routine.
 *
 * @package wp-sweep
 */

/**
 * WP-Sweep stores exactly one row, `wp_sweep_version`, holding the two markers.
 * It has no settings row: its one tunable is the `wp_sweep_limit_details`
 * filter, so there is nothing to save and no settings screen to save it from.
 *
 * What these assert is that the row is the right shape, that the upgrade routine
 * is idempotent, and that the settings row a 2.0.0 beta may have written is
 * cleaned up rather than left behind.
 */
class WP_Sweep_Options_Test extends WP_Sweep_TestCase {

	/**
	 * Starts every test from an install that has never run the plugin.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( WP_Sweep_Options::OPTION );
		delete_option( WP_Sweep_Options::VERSION );
	}

	/**
	 * The rows are named after the plugin, with no unprefixed survivor.
	 */
	public function test_the_option_rows_carry_the_plugin_prefix() {
		$this->assertSame( 'wp_sweep_options', WP_Sweep_Options::OPTION, 'The legacy settings row is misnamed.' );
		$this->assertSame( 'wp_sweep_version', WP_Sweep_Options::VERSION, 'The version row is misnamed.' );
	}

	/**
	 * A settings-less plugin writes no settings row.
	 *
	 * Section 2.1: a plugin with nothing to configure gets no settings row at
	 * all, rather than an empty one that looks like a place to put things.
	 */
	public function test_no_settings_row_is_ever_written() {
		WP_Sweep_Options::maybe_upgrade();

		$this->assertFalse( get_option( WP_Sweep_Options::OPTION, false ), 'A settings row was written for a plugin with no settings.' );
	}

	/**
	 * A row left by a 2.0.0 beta is removed on upgrade.
	 */
	public function test_a_beta_settings_row_is_cleaned_up() {
		update_option( WP_Sweep_Options::OPTION, array( 'limit_details' => 42 ) );

		WP_Sweep_Options::maybe_upgrade();

		$this->assertFalse( get_option( WP_Sweep_Options::OPTION, false ), 'The beta settings row survived the upgrade.' );
	}

	/**
	 * The version row holds the two markers and the values they should hold.
	 */
	public function test_upgrade_stamps_both_version_markers() {
		WP_Sweep_Options::maybe_upgrade();

		$this->assertSame(
			array(
				'plugin' => WP_SWEEP_VERSION,
				'db'     => WP_SWEEP_DB_VERSION,
			),
			get_option( WP_Sweep_Options::VERSION ),
			'The version row does not hold the running version.'
		);
	}

	/**
	 * The version row holds those two keys and nothing else, ever.
	 */
	public function test_version_row_holds_exactly_plugin_and_db() {
		WP_Sweep_Options::maybe_upgrade();

		$this->assertSame(
			array( 'plugin', 'db' ),
			array_keys( get_option( WP_Sweep_Options::VERSION ) ),
			'The version row has grown a third key.'
		);
	}

	/**
	 * Running the upgrade twice changes nothing the second time.
	 */
	public function test_upgrade_is_idempotent() {
		WP_Sweep_Options::maybe_upgrade();

		$first = get_option( WP_Sweep_Options::VERSION );

		WP_Sweep_Options::maybe_upgrade();

		$this->assertSame( $first, get_option( WP_Sweep_Options::VERSION ), 'A second upgrade run rewrote the version row.' );
	}

	/**
	 * An upgrade from an older version leaves no stale marker behind.
	 */
	public function test_upgrade_replaces_an_older_marker() {
		update_option(
			WP_Sweep_Options::VERSION,
			array(
				'plugin' => '1.2.0',
				'db'     => '0',
			)
		);

		WP_Sweep_Options::maybe_upgrade();

		$versions = WP_Sweep_Options::get_versions();

		$this->assertSame( WP_SWEEP_VERSION, $versions['plugin'], 'The plugin marker was not advanced.' );
		$this->assertSame( WP_SWEEP_DB_VERSION, $versions['db'], 'The db marker was not advanced.' );
	}

	/**
	 * The version markers are missing rather than empty before anything runs.
	 */
	public function test_the_markers_read_as_empty_strings_when_unset() {
		$this->assertSame(
			array(
				'plugin' => '',
				'db'     => '',
			),
			WP_Sweep_Options::get_versions(),
			'An install that has never upgraded reported a version.'
		);
	}

	/**
	 * A version row someone corrupted reads as unset rather than fatalling.
	 */
	public function test_a_corrupt_version_row_reads_as_unset() {
		update_option( WP_Sweep_Options::VERSION, 'not-an-array' );

		$this->assertSame(
			array(
				'plugin' => '',
				'db'     => '',
			),
			WP_Sweep_Options::get_versions(),
			'A corrupt version row was not treated as unset.'
		);
	}

	// -- The details cap. --

	/**
	 * The cap defaults to the constant, with no option row involved.
	 */
	public function test_the_details_cap_defaults_to_the_constant() {
		$this->assertSame( 500, WP_Sweep::DEFAULT_LIMIT_DETAILS, 'The documented default moved.' );
		$this->assertSame( 500, $this->sweep()->limit_details(), 'The engine did not use the default cap.' );
	}

	/**
	 * The filter is the only way to change it.
	 */
	public function test_the_details_cap_filter_wins() {
		add_filter(
			'wp_sweep_limit_details',
			static function () {
				return 3;
			}
		);

		$this->assertSame( 3, $this->sweep()->limit_details(), 'The filter did not set the cap.' );
	}

	/**
	 * A filter returning nonsense cannot open an empty details list.
	 *
	 * A cap of zero renders a Details button that opens nothing, which reads as
	 * a broken screen rather than as a setting.
	 */
	public function test_the_details_cap_is_never_below_one() {
		add_filter(
			'wp_sweep_limit_details',
			static function () {
				return -7;
			}
		);

		$this->assertSame( 1, $this->sweep()->limit_details(), 'A negative cap was not floored at one.' );
	}

	/**
	 * Nothing reads the cap out of an option row any more.
	 */
	public function test_the_cap_is_not_read_from_an_option() {
		$code = $this->source_without_comments( '/includes/class-wp-sweep.php' );

		$limit = substr( $code, strpos( $code, 'public function limit_details' ) );
		$limit = substr( $limit, 0, strpos( $limit, "\n\t}" ) );

		$this->assertStringNotContainsString( 'get_option(', $limit, 'The cap is being read from the database again.' );
		$this->assertStringNotContainsString( 'WP_Sweep_Options', $limit, 'The cap is being read from the options class again.' );
	}
}
