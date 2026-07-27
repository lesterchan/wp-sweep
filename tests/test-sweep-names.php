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
	 * The canonical list holds exactly the expected names, in order.
	 *
	 * Order matters for the CLI: `wp sweep --all` walks it top to bottom, and
	 * the sweeps are sequenced so that deleting posts comes before hunting
	 * for the post meta that deletion just orphaned.
	 */
	public function test_canonical_list_holds_every_sweep() {
		$this->assertSame( self::$expected, $this->sweep()->get_sweep_names() );
	}

	/**
	 * The REST API validates against the canonical list rather than a copy
	 * of it. Before 2.0.0 there were three hand-maintained copies of these
	 * nineteen names.
	 */
	public function test_rest_api_defers_to_the_canonical_list() {
		$source = file_get_contents( dirname( __DIR__ ) . '/inc/class-wpsweep-api.php' );

		$this->assertStringNotContainsString(
			"'oembed_postmeta'",
			$source,
			'The REST API keeps its own copy of the sweep names again.'
		);

		$api = new WPSweep_Api();
		foreach ( self::$expected as $name ) {
			$this->assertTrue( $api->is_sweep_name_valid( $name ) );
		}
		$this->assertFalse( $api->is_sweep_name_valid( 'no_such_sweep' ) );
	}

	/**
	 * The WP-CLI command iterates the canonical list rather than a copy.
	 *
	 * The command class extends WP_CLI_Command, which does not exist in the
	 * test run, so this is read from the source.
	 */
	public function test_cli_command_defers_to_the_canonical_list() {
		$source = file_get_contents( dirname( __DIR__ ) . '/inc/class-wpsweep-command.php' );

		$this->assertMatchesRegularExpression(
			'/\$default_items\s*=\s*WPSweep::get_instance\(\)->get_sweep_names\(\);/',
			$source,
			'The WP-CLI command keeps its own copy of the sweep names again.'
		);
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
