<?php
/**
 * What is true of WP-Sweep and of no other plugin.
 *
 * The twenty-three assertions §7.2 asks of all nineteen live in
 * Plugin_Metadata_TestCase, a byte-identical copy of
 * _standards/templates/helper-metadata-testcase.php. What is left here is the
 * three declarations that class cannot derive, the two documented opt-outs, and
 * the checks that are genuinely this plugin's - all of which follow from the
 * same fact: WP-Sweep stores nothing at all.
 *
 * @package WP-Sweep
 */

/**
 * WP-Sweep against §7.2.
 */
class WP_Sweep_Metadata_Test extends Plugin_Metadata_TestCase {

	/**
	 * The version this release ships.
	 *
	 * @return string
	 */
	protected function expected_version() {
		return '2.0.1';
	}

	/**
	 * The prefix every class the plugin declares carries.
	 *
	 * @return string
	 */
	protected function class_prefix() {
		return 'WP_Sweep';
	}

	/**
	 * Every break a site owner updating from the released 1.2.0 would notice.
	 *
	 * The screen's address changed, Sweep All is gone, and code holding the
	 * singleton has to be edited, because the old spellings fail rather than
	 * falling back. The WP-CLI command and the REST namespace are deliberately
	 * absent: 2.0.0 keeps the names 1.2.0 shipped, so a scripted caller of
	 * either goes on working and there is nothing to notice.
	 *
	 * @return string[]
	 */
	protected function upgrade_notice_subjects() {
		return array(
			'6.8',
			'8.2',
			// The screen, old address and new.
			'tools.php?page=wp-sweep/admin.php',
			'tools.php?page=wp-sweep',
			// The control that went away.
			'Sweep All',
			// The class rename and the property that became a method.
			'WPSweep::get_instance()',
			'WP_Sweep::get_instance()',
			'limit_details',
			// The whitelist matching that stopped treating _ as a wildcard.
			'_my_key',
		);
	}

	/**
	 * WP-Sweep keeps no marker row: there is nothing to migrate (§2.1).
	 *
	 * @return bool
	 */
	protected function has_version_row() {
		return false;
	}

	/**
	 * And no settings row: its one tunable is a filter (§2.1).
	 *
	 * The single field it briefly had did not earn a screen, an option row, a
	 * register_setting() and a sanitiser.
	 *
	 * @return bool
	 */
	protected function has_settings_row() {
		return false;
	}

	/**
	 * Write by hand the two rows a 2.0.0 beta left behind.
	 *
	 * Nothing in the plugin writes either of these now, and no released version
	 * ever did. They are the only rows the uninstaller will ever find, so they
	 * are the only rows the uninstall test can be given to remove.
	 *
	 * @return void
	 */
	protected function seed_option_rows() {
		update_option( 'wp_sweep_options', array( 'limit_details' => 500 ) );
		update_option( 'wp_sweep_version', '2.0.0' );
	}

	/**
	 * Register the one script the plugin registers.
	 *
	 * A screen has to be set first. WP_UnitTestCase_Base::tear_down() nulls
	 * $current_screen - its own comment says a test wanting one is expected to
	 * call set_current_screen() - and core's own listener on this action,
	 * WP_Site_Health::enqueue_scripts(), reads get_current_screen()->id
	 * unguarded. Firing the action with no screen therefore fails inside core
	 * before the plugin's own callback is ever reached.
	 *
	 * @return void
	 */
	protected function register_plugin_assets() {
		set_current_screen( WP_Sweep_Admin::get_hook_suffix() );

		do_action( 'admin_enqueue_scripts', WP_Sweep_Admin::get_hook_suffix() );
	}

	/**
	 * Not one option row, under any name.
	 *
	 * The shared opt-outs assert the two named rows never come back. This is
	 * the whole rule they are two cases of: WP-Sweep has no settings, no
	 * schema, no capabilities and no scheduled events, so booting it must leave
	 * wp_options exactly as it found it.
	 */
	public function test_the_plugin_owns_no_option_rows_at_all() {
		do_action( 'plugins_loaded' );
		do_action( 'init' );

		$this->assertSame(
			array(),
			$this->stored_option_names(),
			'WP-Sweep wrote an option row; it has no settings and no tables, so it stores nothing.'
		);
	}

	/**
	 * The uninstaller names both rows a pre-release build wrote.
	 *
	 * Nothing writes either of these now. An early build of the unreleased
	 * 2.0.0 wrote both, and uninstall is the only thing that will ever take
	 * them off a site that ran that build - so the file has to keep naming
	 * them even though nothing else in the plugin does.
	 */
	public function test_uninstall_names_both_rows_a_pre_release_build_wrote() {
		$uninstall = (string) file_get_contents( $this->metadata_root() . '/uninstall.php' );

		$this->assertStringContainsString( "delete_option( 'wp_sweep_options' )", $uninstall, 'uninstall.php does not delete the pre-release settings row.' );
		$this->assertStringContainsString( "delete_option( 'wp_sweep_version' )", $uninstall, 'uninstall.php does not delete the pre-release version row.' );
	}

	/**
	 * The uninstaller walks the whole network, not the first hundred sites.
	 *
	 * A source guard because the thing it guards cannot be exercised: building
	 * a 101-site network to prove the hundred-and-first is reached is not on.
	 */
	public function test_uninstall_walks_the_whole_network() {
		$uninstall = (string) file_get_contents( $this->metadata_root() . '/uninstall.php' );

		$this->assertStringContainsString( 'is_multisite()', $uninstall, 'uninstall.php does not branch on multisite.' );
		$this->assertStringContainsString( "'number' => 0", $uninstall, 'uninstall.php stops at the default hundred sites.' );
		$this->assertStringContainsString( "'fields' => 'ids'", $uninstall, 'uninstall.php hydrates whole site objects to read one column.' );
		$this->assertMatchesRegularExpression(
			'/switch_to_blog\([^}]*restore_current_blog\(\)/s',
			$uninstall,
			'uninstall.php closes a block between switch_to_blog() and restore_current_blog().'
		);
	}

	/**
	 * The copyright block agrees with the header two lines above it.
	 */
	public function test_the_licence_block_is_the_or_later_variant() {
		$this->assertSame( 'GPLv2 or later', $this->header_field( 'License' ), 'The header licence is not GPLv2 or later.' );
		$this->assertStringContainsString(
			'(at your option) any later version',
			$this->plugin_file(),
			'The GPL block is the version 2 only variant, which contradicts the header two lines above it.'
		);
	}

	/**
	 * Donations is the last h3 of the Description, with one exact wording.
	 */
	public function test_donations_is_the_last_h3_of_the_description() {
		$readme      = $this->readme();
		$description = substr( $readme, (int) strpos( $readme, '## Description' ) );
		$description = substr( $description, 0, (int) strpos( $description, '## Usage' ) );

		preg_match_all( '/^### .+$/m', $description, $matches );

		$this->assertSame( '### Donations', rtrim( (string) end( $matches[0] ) ), 'Donations is not the last h3 of the description.' );
		$this->assertStringContainsString(
			'I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.',
			$description,
			'The Donations paragraph is not the agreed wording.'
		);
	}

	/**
	 * The raised floors are a BREAKING changelog line, not only a notice.
	 *
	 * A site below them is not offered the update at all, so it is the one
	 * change that has to be findable in both places.
	 */
	public function test_the_raised_floors_are_recorded_as_a_breaking_change() {
		$this->assertStringContainsString(
			'BREAKING: Requires WordPress 6.8 and PHP 8.2',
			$this->readme(),
			'The raised floors are not a BREAKING changelog line.'
		);
	}
}
