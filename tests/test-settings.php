<?php
/**
 * Tests for the settings screen.
 *
 * @package wp-sweep
 */

/**
 * The settings screen is built entirely from the Settings API, so what is
 * worth asserting is that the registration really happened -- a hand-written
 * form table would render identically and behave differently.
 */
class WP_Sweep_Settings_Test extends WP_Sweep_TestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin;

	/**
	 * Creates an administrator to render the screen as.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 * @return void
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Registers the settings, as admin_init would.
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin );
		set_current_screen( 'toplevel_page_wp-sweep' );

		$GLOBALS['wp_settings_sections'] = array();
		$GLOBALS['wp_settings_fields']   = array();

		WP_Sweep_Settings::register_settings();
	}

	/**
	 * Restores the screen.
	 */
	public function tear_down() {
		unset( $GLOBALS['current_screen'] );

		parent::tear_down();
	}

	/**
	 * The settings group is the settings row name, as the standard requires.
	 */
	public function test_the_group_is_the_settings_row_name() {
		$this->assertSame( WP_Sweep_Options::OPTION, WP_Sweep_Settings::GROUP, 'The group and the row name have drifted apart.' );
	}

	/**
	 * The setting is registered, with the sanitiser as its callback.
	 */
	public function test_the_setting_is_registered_with_a_sanitiser() {
		$registered = get_registered_settings();

		$this->assertArrayHasKey( WP_Sweep_Options::OPTION, $registered, 'The settings row is not registered.' );
		$this->assertSame(
			array( 'WP_Sweep_Options', 'sanitize' ),
			$registered[ WP_Sweep_Options::OPTION ]['sanitize_callback'],
			'The registered sanitiser is not the one the options class exposes.'
		);
	}

	/**
	 * The section and its field are registered against the settings page.
	 */
	public function test_the_section_and_field_are_registered() {
		global $wp_settings_sections, $wp_settings_fields;

		$this->assertArrayHasKey(
			WP_Sweep_Settings::SECTION_DETAILS,
			$wp_settings_sections[ WP_Sweep_Settings::PAGE ],
			'The details section is not registered.'
		);

		$this->assertArrayHasKey(
			'limit_details',
			$wp_settings_fields[ WP_Sweep_Settings::PAGE ][ WP_Sweep_Settings::SECTION_DETAILS ],
			'The details cap field is not registered.'
		);
	}

	/**
	 * The section constant is named for the plugin.
	 */
	public function test_the_section_constant_carries_the_plugin_prefix() {
		$this->assertStringStartsWith( 'wp_sweep_', WP_Sweep_Settings::SECTION_DETAILS, 'The section constant is not prefixed.' );
	}

	/**
	 * The screen renders the registered field rather than its own table.
	 */
	public function test_the_screen_renders_the_registered_field() {
		ob_start();
		WP_Sweep_Settings::render_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="wp_sweep_options[limit_details]"', $html, 'The registered field did not render.' );
		$this->assertStringContainsString( 'form-table', $html, 'do_settings_sections() did not emit a form table.' );
		$this->assertStringContainsString( 'action="options.php"', $html, 'The form does not post to options.php.' );
	}

	/**
	 * The screen hand-writes no form table of its own.
	 */
	public function test_the_screen_hand_writes_no_form_table() {
		$code = $this->source_without_comments( '/includes/class-wp-sweep-settings.php' );

		$this->assertStringNotContainsString( '<table', $code, 'The settings screen writes its own table.' );
		$this->assertStringContainsString( 'do_settings_sections(', $code );
		$this->assertStringContainsString( 'settings_fields(', $code );
		$this->assertStringContainsString( 'submit_button(', $code );
	}

	/**
	 * The settings screen takes manage_options, not the sweep capability.
	 */
	public function test_the_settings_screen_requires_manage_options() {
		$this->assertSame( 'manage_options', WP_Sweep_Settings::CAPABILITY, 'The settings screen is not gated on manage_options.' );
		$this->assertSame( 'activate_plugins', WP_Sweep::CAPABILITY, 'The sweep screen changed capability.' );
	}

	/**
	 * Both capabilities go through the one filter.
	 *
	 * @dataProvider data_capability_contexts
	 *
	 * @param string $owner   Class exposing the capability.
	 * @param string $context Context to ask about.
	 */
	public function test_every_capability_check_goes_through_one_filter( $owner, $context ) {
		add_filter(
			'wp_sweep_capability',
			static function () {
				return 'edit_theme_options';
			}
		);

		$this->assertSame(
			'edit_theme_options',
			call_user_func( array( $owner, 'capability' ), $context ),
			"The {$context} capability ignores the wp_sweep_capability filter."
		);
	}

	/**
	 * The two capability surfaces.
	 *
	 * @return array
	 */
	public function data_capability_contexts() {
		return array(
			'sweep'    => array( 'WP_Sweep', 'sweep' ),
			'settings' => array( 'WP_Sweep_Settings', 'settings' ),
		);
	}

	/**
	 * The filter is told which surface is being asked about.
	 */
	public function test_the_capability_filter_is_given_its_context() {
		$seen = array();

		add_filter(
			'wp_sweep_capability',
			static function ( $capability, $context ) use ( &$seen ) {
				$seen[] = $context;
				return $capability;
			},
			10,
			2
		);

		WP_Sweep::capability( 'rest' );
		WP_Sweep_Settings::capability( 'settings' );

		$this->assertSame( array( 'rest', 'settings' ), $seen, 'The filter was not given the context.' );
	}

	/**
	 * The Plugins screen gets a Settings link pointing at the right page.
	 */
	public function test_a_settings_link_is_added_to_the_plugins_screen() {
		$links = WP_Sweep_Settings::action_links( array( '<a href="#">Deactivate</a>' ) );

		$this->assertCount( 2, $links, 'No Settings link was added.' );
		$this->assertStringContainsString( 'page=' . WP_Sweep_Settings::PAGE, $links[0], 'The Settings link points somewhere else.' );
	}

	/**
	 * A user without the capability gets no Settings link.
	 */
	public function test_no_settings_link_without_the_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertCount( 1, WP_Sweep_Settings::action_links( array( '<a href="#">Deactivate</a>' ) ), 'A subscriber was offered the Settings link.' );
	}

	/**
	 * Settings live on one page. The standard allows no second submenu for them.
	 */
	public function test_the_settings_are_one_submenu_under_one_top_level_menu() {
		global $menu, $submenu;

		$menu    = array();
		$submenu = array();

		do_action( 'admin_menu' );

		$this->assertArrayHasKey( WP_Sweep_Admin::PAGE, $submenu, 'The plugin registered no submenu.' );

		$slugs = wp_list_pluck( $submenu[ WP_Sweep_Admin::PAGE ], 2 );

		$this->assertSame(
			array( WP_Sweep_Admin::PAGE, WP_Sweep_Settings::PAGE ),
			$slugs,
			'The data screen must come first and Settings last.'
		);
	}
}
