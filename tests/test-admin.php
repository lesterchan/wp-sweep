<?php
/**
 * Tests for the Tools -> Sweep admin screen.
 *
 * @package wp-sweep
 */

/**
 * The admin screen is the only interface most people ever use, and it is the
 * least covered part of a plugin like this. These tests render it for real and
 * assert it comes back clean under PHP 8 — not merely non-empty.
 */
class Test_WP_Sweep_Admin extends WP_Sweep_TestCase {

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
	 * Renders as an administrator, since the template calls capability-gated
	 * functions and would otherwise take the runner down with it.
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin );
		set_current_screen( 'tools' );

		// WP_Scripts is a global that survives the transaction rollback, so a
		// handle enqueued by one test is still enqueued in the next one.
		$GLOBALS['wp_scripts'] = null;
	}

	/**
	 * Restores the screen.
	 */
	public function tear_down() {
		unset( $GLOBALS['current_screen'] );

		parent::tear_down();
	}

	/**
	 * The screen renders without raising a single diagnostic.
	 */
	public function test_admin_page_renders_cleanly() {
		$html = $this->render_admin_page();

		$this->assertSame( array(), $this->admin_page_notices );
		$this->assertNotEmpty( $html );
	}

	/**
	 * The rendered markup carries none of the damage that parses fine, lints
	 * clean and still reaches the user's screen.
	 */
	public function test_admin_page_markup_is_undamaged() {
		$this->make_revisions( 2 );
		$html = $this->render_admin_page();

		$this->assertStringNotContainsString( 'translators:', $html );
		$this->assertStringNotContainsString( '<?php', $html );
		$this->assertStringNotContainsString( '&amp;amp;', $html );
		$this->assertStringNotContainsString( '&amp;nbsp;', $html );
		$this->assertStringNotContainsString( 'Fatal error', $html );
		$this->assertDoesNotMatchRegularExpression( '/Warning<\/b>|Notice<\/b>/', $html );
	}

	/**
	 * Every section heading is present, so no table has silently vanished.
	 *
	 * @dataProvider data_section_headings
	 *
	 * @param string $heading Heading text.
	 */
	public function test_admin_page_has_every_section( $heading ) {
		$this->assertStringContainsString( $heading, $this->render_admin_page() );
	}

	/**
	 * The screen's section headings.
	 *
	 * @return array
	 */
	public function data_section_headings() {
		return array(
			array( 'Post Sweep' ),
			array( 'Comment Sweep' ),
			array( 'User Sweep' ),
			array( 'Term Sweep' ),
			array( 'Option Sweep' ),
			array( 'Database Sweep' ),
			array( 'Sweep All' ),
		);
	}

	/**
	 * A sweep with items to clean renders both buttons, carrying the name,
	 * the type and a nonce. The script reads all three from the markup.
	 */
	public function test_populated_row_renders_both_buttons_with_a_nonce() {
		$this->make_revisions( 2 );
		$html = $this->render_admin_page();

		$this->assertStringContainsString( 'data-sweep_name="revisions"', $html );
		$this->assertStringContainsString( 'data-sweep_type="posts"', $html );
		$this->assertStringContainsString( 'data-action="sweep"', $html );
		$this->assertStringContainsString( 'data-action="sweep_details"', $html );

		$this->assertMatchesRegularExpression(
			'/data-action="sweep"[^>]*data-sweep_name="revisions"[^>]*data-nonce="[a-f0-9]{10}"/',
			$html
		);
	}

	/**
	 * The nonces in the markup are the ones the AJAX handlers verify.
	 */
	public function test_row_nonces_match_what_the_handlers_check() {
		$this->make_revisions( 1 );
		$html = $this->render_admin_page();

		$this->assertStringContainsString(
			'data-nonce="' . wp_create_nonce( 'wp_sweep_revisions' ) . '"',
			$html
		);
		$this->assertStringContainsString(
			'data-nonce="' . wp_create_nonce( 'wp_sweep_details_revisions' ) . '"',
			$html
		);
	}

	/**
	 * A sweep with nothing to clean offers no button at all.
	 */
	public function test_empty_row_offers_no_button() {
		$html = $this->render_admin_page();

		$this->assertStringNotContainsString( 'data-sweep_name="revisions"', $html );
	}

	/**
	 * Counts are rendered through number_format_i18n().
	 */
	public function test_counts_are_localised_in_the_markup() {
		$this->make_revisions( 3 );
		$html = $this->render_admin_page();

		$this->assertMatchesRegularExpression(
			'/<span class="sweep-count">' . preg_quote( number_format_i18n( 3 ), '/' ) . '<\/span>/',
			$html
		);
	}

	/**
	 * The stat spans the script updates after a sweep are all present.
	 *
	 * @dataProvider data_stat_types
	 *
	 * @param string $type Sweep type.
	 */
	public function test_markup_carries_the_stat_span_for_each_type( $type ) {
		$this->assertStringContainsString(
			'class="sweep-count-type-' . $type . '"',
			$this->render_admin_page()
		);
	}

	/**
	 * Types the script writes totals back into.
	 *
	 * @return array
	 */
	public function data_stat_types() {
		return array(
			array( 'posts' ),
			array( 'postmeta' ),
			array( 'comments' ),
			array( 'commentmeta' ),
			array( 'users' ),
			array( 'usermeta' ),
			array( 'terms' ),
			array( 'termmeta' ),
			array( 'term_taxonomy' ),
			array( 'term_relationships' ),
			array( 'options' ),
			array( 'tables' ),
		);
	}

	/**
	 * The extension points the readme documents still fire.
	 *
	 * @dataProvider data_admin_actions
	 *
	 * @param string $action Action name.
	 */
	public function test_admin_extension_points_fire( $action ) {
		$fired = false;

		add_action(
			$action,
			static function () use ( &$fired ) {
				$fired = true;
			}
		);

		$this->render_admin_page();

		$this->assertTrue( $fired, "'{$action}' did not fire." );
	}

	/**
	 * Actions the screen exposes for other plugins to hang rows off.
	 *
	 * @return array
	 */
	public function data_admin_actions() {
		return array(
			array( 'wp_sweep_admin_post_sweep' ),
			array( 'wp_sweep_admin_comment_sweep' ),
			array( 'wp_sweep_admin_user_sweep' ),
			array( 'wp_sweep_admin_term_sweep' ),
			array( 'wp_sweep_admin_option_sweep' ),
			array( 'wp_sweep_admin_database_sweep' ),
		);
	}

	/**
	 * The backup warning stays on the screen. Every sweep is irreversible and
	 * this notice is the only thing standing between a user and that.
	 */
	public function test_backup_warning_is_shown() {
		$html = $this->render_admin_page();

		$this->assertStringContainsString( 'backup your database', $html );
		$this->assertStringContainsString( 'irreversible', $html );
		$this->assertStringContainsString( 'notice notice-warning', $html );
	}

	/**
	 * The details cap is stated on screen and matches the real limit.
	 */
	public function test_details_cap_is_stated_and_accurate() {
		$html = $this->render_admin_page();

		$this->assertStringContainsString(
			number_format_i18n( $this->sweep()->limit_details ),
			$html
		);
	}

	/**
	 * The admin script loads on the plugin's own screen.
	 */
	public function test_script_is_enqueued_on_the_sweep_screen() {
		$this->sweep()->admin_enqueue_scripts( $this->admin_hook_suffix );

		$this->assertTrue( wp_script_is( 'wp-sweep', 'enqueued' ) );
	}

	/**
	 * It loads nowhere else. This screen is the only thing that uses it.
	 *
	 * @dataProvider data_other_admin_screens
	 *
	 * @param string $hook Hook suffix of some other screen.
	 */
	public function test_script_is_not_enqueued_elsewhere( $hook ) {
		$this->sweep()->admin_enqueue_scripts( $hook );

		$this->assertFalse( wp_script_is( 'wp-sweep', 'enqueued' ) );
	}

	/**
	 * Admin screens the plugin has no business loading its script on.
	 *
	 * @return array
	 */
	public function data_other_admin_screens() {
		return array(
			array( 'index.php' ),
			array( 'tools.php' ),
			array( 'edit.php' ),
			array( 'plugins.php' ),
			array( 'settings_page_something-else' ),
		);
	}

	/**
	 * The script depends on nothing. It was written against jQuery until
	 * 2.0.0, and jQuery was the only reason this screen loaded it at all.
	 */
	public function test_script_has_no_dependencies() {
		$this->sweep()->admin_enqueue_scripts( $this->admin_hook_suffix );

		$script = wp_scripts()->registered['wp-sweep'];

		$this->assertSame( array(), $script->deps );
		$this->assertNotContains( 'jquery', $script->deps );
	}

	/**
	 * One unminified file ships, not a hand-maintained minified twin that
	 * drifts out of sync with it.
	 */
	public function test_script_is_the_unminified_source() {
		$this->sweep()->admin_enqueue_scripts( $this->admin_hook_suffix );

		$script = wp_scripts()->registered['wp-sweep'];

		$this->assertStringEndsWith( '/js/wp-sweep.js', $script->src );
		$this->assertStringNotContainsString( '.min.js', $script->src );
		$this->assertFileDoesNotExist( dirname( __DIR__ ) . '/js/wp-sweep.min.js' );
	}

	/**
	 * The script carries no jQuery in its source either.
	 */
	public function test_script_source_uses_no_jquery() {
		$source = file_get_contents( dirname( __DIR__ ) . '/js/wp-sweep.js' );

		$this->assertStringNotContainsString( 'jQuery', $source );
		$this->assertStringNotContainsString( '$(', $source );
	}

	/**
	 * The details list is built from text nodes. Assembling it by string
	 * concatenation put a comment author's markup into the administrator's
	 * browser; the JS suite covers the behaviour, this guards the source.
	 */
	public function test_script_never_assigns_innerhtml() {
		$source = file_get_contents( dirname( __DIR__ ) . '/js/wp-sweep.js' );

		$this->assertStringNotContainsString( 'innerHTML', $source );
		$this->assertStringContainsString( 'textContent', $source );
	}

	/**
	 * Every string the script shows the user is localised.
	 */
	public function test_script_is_localised() {
		$this->sweep()->admin_enqueue_scripts( $this->admin_hook_suffix );

		$data = wp_scripts()->registered['wp-sweep']->extra['data'];

		$this->assertStringContainsString( 'wpSweepL10n', $data );

		foreach ( array( 'text_close_warning', 'text_sweep', 'text_sweep_all', 'text_sweeping', 'text_na' ) as $key ) {
			$this->assertStringContainsString( $key, $data );
		}
	}

	/**
	 * The Tools menu entry is registered and gated on activate_plugins.
	 */
	public function test_management_page_is_registered() {
		global $submenu;

		$submenu = array();
		do_action( 'admin_menu' );

		$this->assertArrayHasKey( 'tools.php', $submenu );

		$capabilities = wp_list_pluck( $submenu['tools.php'], 1 );
		$this->assertContains( 'activate_plugins', $capabilities );
	}
}
