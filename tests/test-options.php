<?php
/**
 * Tests for the option rows and the upgrade routine.
 *
 * @package wp-sweep
 */

/**
 * WP-Sweep stored nothing before 2.0.0, so there is no legacy row to fold in.
 * What these assert is that the two rows it stores now are the right shape, that
 * the upgrade routine is idempotent, and that the settings sanitiser and the
 * version markers cannot reach into each other.
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
	 * The two rows are named after the plugin, with no unprefixed survivor.
	 */
	public function test_the_option_rows_carry_the_plugin_prefix() {
		$this->assertSame( 'wp_sweep_options', WP_Sweep_Options::OPTION, 'The settings row is misnamed.' );
		$this->assertSame( 'wp_sweep_version', WP_Sweep_Options::VERSION, 'The version row is misnamed.' );
	}

	/**
	 * A fresh install ends up with the defaults written out rather than absent.
	 */
	public function test_first_upgrade_writes_the_defaults() {
		WP_Sweep_Options::maybe_upgrade();

		$this->assertSame(
			WP_Sweep_Options::get_defaults(),
			get_option( WP_Sweep_Options::OPTION ),
			'A fresh install did not get the default settings.'
		);
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

		WP_Sweep_Options::update( array( 'limit_details' => 42 ) );

		WP_Sweep_Options::maybe_upgrade();

		$this->assertSame(
			42,
			WP_Sweep_Options::get( 'limit_details' ),
			'A second upgrade run overwrote a stored setting.'
		);
	}

	/**
	 * An upgrade re-sanitises whatever an older schema left in the row.
	 */
	public function test_upgrade_re_sanitises_a_stored_row() {
		update_option( WP_Sweep_Options::OPTION, array( 'limit_details' => -7 ) );
		update_option(
			WP_Sweep_Options::VERSION,
			array(
				'plugin' => '1.2.0',
				'db'     => '0',
			)
		);

		WP_Sweep_Options::maybe_upgrade();

		$this->assertSame(
			1,
			WP_Sweep_Options::get( 'limit_details' ),
			'An out of range value survived the upgrade.'
		);
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
	 * Reading a key that has never been stored falls back to the default.
	 */
	public function test_a_missing_key_falls_back_to_its_default() {
		update_option( WP_Sweep_Options::OPTION, array() );

		$this->assertSame( 500, WP_Sweep_Options::get( 'limit_details' ), 'The default cap was not applied.' );
	}

	/**
	 * A row that is not an array at all does not take the screen down.
	 */
	public function test_a_corrupt_row_falls_back_to_the_defaults() {
		update_option( WP_Sweep_Options::OPTION, 'not-an-array' );

		$this->assertSame( WP_Sweep_Options::get_defaults(), WP_Sweep_Options::get(), 'A corrupt row was not replaced.' );
	}

	/**
	 * An unknown key reads as null rather than as a notice.
	 */
	public function test_an_unknown_key_reads_as_null() {
		$this->assertNull( WP_Sweep_Options::get( 'no_such_setting' ), 'An unknown key did not read as null.' );
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

	// -- The sanitiser. --

	/**
	 * The sanitiser bounds the details cap at both ends.
	 *
	 * @dataProvider data_limits
	 *
	 * @param mixed $input    Submitted value.
	 * @param int   $expected Value that should be stored.
	 */
	public function test_the_details_cap_is_bounded( $input, $expected ) {
		$clean = WP_Sweep_Options::sanitize( array( 'limit_details' => $input ) );

		$this->assertSame( $expected, $clean['limit_details'], 'The details cap was not bounded.' );
	}

	/**
	 * Submitted caps and the values they must be stored as.
	 *
	 * @return array
	 */
	public function data_limits() {
		return array(
			'in range'      => array( 250, 250 ),
			'zero'          => array( 0, 1 ),
			'negative'      => array( -40, 1 ),
			'above the top' => array( 999999, 10000 ),
			'numeric text'  => array( '120', 120 ),
			'not a number'  => array( 'lots', 1 ),
		);
	}

	/**
	 * Anything that is not an array sanitises to the defaults.
	 */
	public function test_a_non_array_sanitises_to_the_defaults() {
		$this->assertSame( WP_Sweep_Options::get_defaults(), WP_Sweep_Options::sanitize( 'nonsense' ), 'A scalar was not replaced.' );
	}

	/**
	 * The sanitiser never stores a version marker.
	 *
	 * This is the regression guard for the bug the standard was written
	 * around: a marker kept inside the settings array has to be rescued from
	 * the stored value on every save, and the save that forgets records the
	 * upgrade as incomplete forever. With a separate row it is impossible by
	 * construction, and this fails the moment someone moves one back.
	 */
	public function test_the_sanitiser_never_stores_version_markers() {
		$clean = WP_Sweep_Options::sanitize(
			array(
				'limit_details' => 100,
				'version'       => '2.0.0',
				'db_version'    => '1',
				'versions'      => array( 'plugin' => '2.0.0' ),
			)
		);

		foreach ( array( 'version', 'db_version', 'versions', 'plugin', 'db' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $clean, "The sanitiser stored a '{$key}' key in the settings row." );
		}
	}

	/**
	 * The sanitiser reads nothing back out of the database.
	 *
	 * A sanitize_callback that calls get_option() is the shape the separate
	 * version row exists to make unnecessary.
	 */
	public function test_the_sanitiser_reads_nothing_back() {
		$code = $this->source_without_comments( '/includes/class-wp-sweep-options.php' );

		$sanitize = substr( $code, strpos( $code, 'public static function sanitize' ) );
		$sanitize = substr( $sanitize, 0, strpos( $sanitize, "\n\t}" ) );

		$this->assertStringNotContainsString( 'get_option(', $sanitize, 'The sanitiser reaches back into the database.' );
	}

	// -- What the rest of the plugin reads. --

	/**
	 * The details cap the engine uses is the one that was stored.
	 */
	public function test_the_engine_reads_the_stored_details_cap() {
		WP_Sweep_Options::update( array( 'limit_details' => 7 ) );

		$this->assertSame( 7, $this->sweep()->limit_details(), 'The engine ignored the stored cap.' );
	}

	/**
	 * The filter still wins over the stored value.
	 */
	public function test_the_details_cap_filter_wins() {
		WP_Sweep_Options::update( array( 'limit_details' => 7 ) );

		add_filter(
			'wp_sweep_limit_details',
			static function () {
				return 3;
			}
		);

		$this->assertSame( 3, $this->sweep()->limit_details(), 'The filter did not override the stored cap.' );
	}
}
