<?php
/**
 * Guards against the several lists of sweep names drifting apart.
 *
 * @package wp-sweep
 */

/**
 * The same nineteen names are spelled out in the REST allow list, the WP-CLI
 * command and the switch statements in the core class. A name added to one and
 * missed in another is silently unavailable rather than an error, so the lists
 * are compared here instead of trusted.
 */
class Test_WP_Sweep_Sweep_Names extends WP_Sweep_TestCase {

	/**
	 * Every sweep the plugin is supposed to offer.
	 *
	 * @var array
	 */
	protected static $expected = array(
		'revisions',
		'auto_drafts',
		'deleted_posts',
		'unapproved_comments',
		'spam_comments',
		'deleted_comments',
		'transient_options',
		'orphan_postmeta',
		'orphan_commentmeta',
		'orphan_usermeta',
		'orphan_termmeta',
		'orphan_term_relationships',
		'unused_terms',
		'duplicated_postmeta',
		'duplicated_commentmeta',
		'duplicated_usermeta',
		'duplicated_termmeta',
		'optimize_database',
		'oembed_postmeta',
	);

	/**
	 * Reads the sweep names the REST API accepts.
	 *
	 * @return array
	 */
	protected function rest_sweep_names() {
		$api        = new WPSweep_Api();
		$reflection = new ReflectionProperty( $api, 'sweeps' );
		$reflection->setAccessible( true );

		return $reflection->getValue( $api );
	}

	/**
	 * Reads the sweep names the WP-CLI command iterates.
	 *
	 * The command class extends WP_CLI_Command, which does not exist in the
	 * test run, so the list is read from the source rather than the class.
	 *
	 * @return array
	 */
	protected function cli_sweep_names() {
		$source = file_get_contents( dirname( __DIR__ ) . '/inc/class-wpsweep-command.php' );

		preg_match( "/\\\$default_items\s*=\s*array\((.*?)\);/s", $source, $matches );
		$this->assertNotEmpty( $matches, 'Could not find $default_items in the CLI command.' );

		preg_match_all( "/=>\s*'([a-z_]+)'/", $matches[1], $names );

		return $names[1];
	}

	/**
	 * The REST allow list holds exactly the expected names.
	 */
	public function test_rest_api_lists_every_sweep() {
		$this->assertSame( self::$expected, $this->rest_sweep_names() );
	}

	/**
	 * The CLI command iterates exactly the expected names.
	 */
	public function test_cli_command_lists_every_sweep() {
		$this->assertSame( self::$expected, $this->cli_sweep_names() );
	}

	/**
	 * The two lists agree with each other.
	 */
	public function test_rest_and_cli_lists_agree() {
		$this->assertSame( $this->rest_sweep_names(), $this->cli_sweep_names() );
	}

	/**
	 * Every listed name is handled by all three switch statements.
	 *
	 * A name missing from one of them is invisible at runtime: count() and
	 * details() fall through to their empty defaults and sweep() returns an
	 * empty message, so the CLI reports success having deleted nothing. The
	 * only way to tell the difference is to look at the source.
	 *
	 * @dataProvider data_expected_names
	 *
	 * @param string $name Sweep name.
	 */
	public function test_every_listed_name_is_implemented( $name ) {
		$source = file_get_contents( dirname( __DIR__ ) . '/inc/class-wpsweep.php' );

		$occurrences = substr_count( $source, "case '{$name}':" );

		$this->assertGreaterThanOrEqual(
			3,
			$occurrences,
			"'{$name}' is missing from count(), details() or sweep()."
		);
	}

	/**
	 * Expected sweep names, one per data set.
	 *
	 * @return array
	 */
	public function data_expected_names() {
		return array_map(
			static function ( $name ) {
				return array( $name );
			},
			self::$expected
		);
	}

	/**
	 * The admin screen offers a button for every sweep except the two it
	 * deliberately leaves out of the tables it renders.
	 */
	public function test_admin_screen_covers_every_sweep() {
		$source = file_get_contents( dirname( __DIR__ ) . $this->admin_page );

		foreach ( self::$expected as $name ) {
			$this->assertStringContainsString(
				'data-sweep_name="' . $name . '"',
				$source,
				"The admin screen has no row for '{$name}'."
			);
		}
	}
}
