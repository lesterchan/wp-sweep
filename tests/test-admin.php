<?php
/**
 * Tests for the Tools -> Sweep admin screen.
 *
 * @package WP-Sweep
 */

/**
 * The admin screen is the only interface most people ever use, and it is the
 * least covered part of a plugin like this. These tests render it for real and
 * assert it comes back clean under PHP 8 — not merely non-empty.
 */
class WP_Sweep_Admin_Test extends WP_Sweep_TestCase {

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
		self::$admin = self::create_admin( $factory );
	}

	/**
	 * Renders as an administrator, since the template calls capability-gated
	 * functions and would otherwise take the runner down with it.
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin );
		set_current_screen( 'tools_page_wp-sweep' );

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
	 * Every row action is a real link, so the screen works without JavaScript.
	 */
	public function test_row_actions_are_real_nonced_links() {
		$this->make_revisions( 2 );
		$html = $this->render_admin_page();

		$this->assertStringContainsString( 'row-actions', $html, 'The list table rendered no row actions.' );
		$this->assertMatchesRegularExpression(
			'/<a href="[^"]*sweep=revisions[^"]*_wpnonce=[a-f0-9]+"/',
			$html,
			'The Sweep row action is not a nonced link.'
		);
	}

	/**
	 * The screen offers a bulk action, because every row on it deletes data.
	 */
	public function test_the_screen_offers_a_bulk_sweep() {
		$this->make_revisions( 2 );
		$html = $this->render_admin_page();

		$this->assertStringContainsString( 'name="action"', $html, 'There is no bulk action control.' );
		$this->assertStringContainsString( 'value="sweep"', $html, 'The bulk action does not offer Sweep.' );
		$this->assertStringContainsString( 'name="sweep[]"', $html, 'The rows carry no checkbox to act on.' );
	}

	/**
	 * A group filter narrows the table to that group's sweeps.
	 */
	public function test_a_group_filter_narrows_the_table() {
		$html = $this->render_admin_page( array( 'group' => 'users' ) );

		$this->assertStringContainsString( 'Orphaned User Meta', $html, 'The user group filter dropped a user sweep.' );
		$this->assertStringNotContainsString( 'Revisions', $html, 'The user group filter kept a post sweep.' );
	}

	/**
	 * A table with nothing on it says so rather than rendering a void.
	 *
	 * Reached with a page number past the last one, which is not a contrivance:
	 * the pagination links are ordinary GET URLs, so a bookmarked or shared page
	 * 2 outlives the rows that justified it. A group filter cannot get here --
	 * get_sweeps() is a fixed list and every group in it has members, so
	 * filtering by group always leaves something to show.
	 */
	public function test_an_empty_table_says_so() {
		$html = $this->render_admin_page( array( 'paged' => '2' ) );

		$this->assertStringContainsString( 'no-items', $html, 'An empty table did not render the no-items row.' );
		$this->assertStringContainsString( 'Nothing here needs cleaning up', $html, 'The no-items row carried no message.' );
	}

	/**
	 * The group filters are rendered above the table.
	 */
	public function test_the_group_filters_are_rendered() {
		$html = $this->render_admin_page();

		$this->assertStringContainsString( 'subsubsub', $html, 'The list table rendered no views.' );
		$this->assertStringContainsString( 'group=posts', $html, 'There is no filter for the post sweeps.' );
	}

	/**
	 * The screen carries no inline style, width, valign or align attribute.
	 */
	public function test_the_markup_carries_no_presentational_attributes() {
		$this->make_revisions( 2 );
		$html = $this->render_admin_page();

		foreach ( array( 'style=', 'valign=', 'align=', '<style' ) as $attribute ) {
			$this->assertStringNotContainsString(
				$attribute,
				$html,
				"The screen still renders a presentational {$attribute} attribute."
			);
		}
	}

	/**
	 * The screen renders without raising a single diagnostic.
	 */
	public function test_admin_page_renders_cleanly() {
		$html = $this->render_admin_page();

		$this->assertSame( array(), $this->admin_page_notices, 'The screen raised no PHP notice while rendering.' );
		$this->assertNotEmpty( $html, 'The screen rendered at all, or the markup assertions below are vacuous.' );
	}

	/**
	 * The rendered markup carries none of the damage that parses fine, lints
	 * clean and still reaches the user's screen.
	 */
	public function test_admin_page_markup_is_undamaged() {
		$this->make_revisions( 2 );
		$html = $this->render_admin_page();

		$this->assertStringNotContainsString( 'translators:', $html, 'No translator comment leaked into the markup.' );
		$this->assertStringNotContainsString( '<?php', $html, 'No PHP tag reached the page, which would mean a template was echoed unparsed.' );
		$this->assertStringNotContainsString( '&amp;amp;', $html, 'No ampersand is encoded twice.' );
		$this->assertStringNotContainsString( '&amp;nbsp;', $html, 'And no entity, which is what a second escaping pass would leave.' );
		$this->assertStringNotContainsString( 'Fatal error', $html, 'No PHP diagnostic reached the page.' );
		$this->assertDoesNotMatchRegularExpression( '/Warning<\/b>|Notice<\/b>/', $html, 'A PHP diagnostic leaked into the screen markup.' );
	}

	/**
	 * Every section heading is present, so no table has silently vanished.
	 *
	 * @dataProvider data_section_headings
	 *
	 * @param string $heading Heading text.
	 */
	public function test_admin_page_has_every_section( $heading ) {
		$this->assertStringContainsString( $heading, $this->render_admin_page(), 'The ' . $heading . ' section is missing from the screen.' );
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
		);
	}

	/**
	 * A sweep with items to clean renders both row actions, carrying the name,
	 * the type and a nonce. The script reads all three from the markup.
	 */
	public function test_populated_row_renders_both_row_actions_with_a_nonce() {
		$this->make_revisions( 2 );
		$html = $this->render_admin_page();

		$this->assertStringContainsString( 'data-sweep-name="revisions"', $html, 'The row names the sweep it acts on.' );
		$this->assertStringContainsString( 'data-sweep-type="posts"', $html, 'And the table it belongs to, which the stats read.' );
		$this->assertStringContainsString( 'data-action="sweep"', $html, 'It offers the sweep action.' );
		$this->assertStringContainsString( 'data-action="sweep_details"', $html, 'And the details action, so both are on a populated row.' );

		$this->assertMatchesRegularExpression(
			'/data-action="sweep"[^>]*data-sweep-name="revisions"[^>]*data-nonce="[a-f0-9]{10}"/',
			$html,
			'A populated row draws its sweep action with a nonce on it.'
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
			$html,
			'The sweep nonce is the one the sweep handler checks.'
		);
		$this->assertStringContainsString(
			'data-nonce="' . wp_create_nonce( 'wp_sweep_details_revisions' ) . '"',
			$html,
			'And the details nonce is its own, so one cannot authorise the other.'
		);
	}

	/**
	 * A sweep with nothing to clean offers no row action at all.
	 */
	public function test_empty_row_offers_no_row_action() {
		$html = $this->render_admin_page();

		$this->assertStringNotContainsString( 'data-sweep-name="revisions"', $html, 'A row with nothing to sweep offers no action at all.' );
	}

	/**
	 * Counts are rendered through number_format_i18n().
	 */
	public function test_counts_are_localised_in_the_markup() {
		$this->make_revisions( 3 );
		$html = $this->render_admin_page();

		// A <strong> carrying the class rather than a <span> wrapping one: a
		// non-zero count is emphasised, and the emphasis has to be the element
		// the script updates or it is stripped by the first sweep.
		$this->assertMatchesRegularExpression(
			'/<strong class="sweep-count">' . preg_quote( number_format_i18n( 3 ), '/' ) . '<\/strong>/',
			$html,
			'The count is rendered through number_format_i18n rather than raw.'
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
			$this->render_admin_page(),
			'The ' . $type . ' stat has no span for the script to update.'
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

		$this->assertStringContainsString( 'backup your database', $html, 'The screen tells the reader to back up first.' );
		$this->assertStringContainsString( 'irreversible', $html, 'And says plainly that the sweeps cannot be undone.' );
		$this->assertStringContainsString( 'notice notice-warning', $html, 'As a core warning notice, so it reads as one.' );
	}

	/**
	 * The details cap is stated on screen and matches the real limit.
	 */
	public function test_details_cap_is_stated_and_accurate() {
		$html = $this->render_admin_page();

		$this->assertStringContainsString(
			number_format_i18n( $this->sweep()->limit_details() ),
			$html,
			'The stated cap is the cap the code actually applies.'
		);
	}

	/**
	 * The admin script loads on the plugin's own screen.
	 */
	public function test_script_is_enqueued_on_the_sweep_screen() {
		WP_Sweep_Admin::admin_enqueue_scripts( $this->register_admin_menu() );

		$this->assertTrue( wp_script_is( 'wp-sweep-admin', 'enqueued' ), 'The admin script is enqueued on the sweep screen.' );
	}

	/**
	 * It loads nowhere else. This screen is the only thing that uses it.
	 *
	 * @dataProvider data_other_admin_screens
	 *
	 * @param string $hook Hook suffix of some other screen.
	 */
	public function test_script_is_not_enqueued_elsewhere( $hook ) {
		WP_Sweep_Admin::admin_enqueue_scripts( $hook );

		$this->assertFalse( wp_script_is( 'wp-sweep-admin', 'enqueued' ), 'The admin script is not enqueued on other screens.' );
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
		WP_Sweep_Admin::admin_enqueue_scripts( $this->register_admin_menu() );

		$script = wp_scripts()->registered['wp-sweep-admin'];

		$this->assertSame( array(), $script->deps, 'The script declares no dependencies.' );
		$this->assertNotContains( 'jquery', $script->deps, 'jQuery least of all, which this plugin no longer uses.' );
	}

	/**
	 * One unminified file ships, not a hand-maintained minified twin that
	 * drifts out of sync with it.
	 */
	public function test_script_is_the_unminified_source() {
		WP_Sweep_Admin::admin_enqueue_scripts( $this->register_admin_menu() );

		$script = wp_scripts()->registered['wp-sweep-admin'];

		$this->assertStringEndsWith( '/js/wp-sweep-admin.js', $script->src, 'The registered source is the shipped file.' );
		$this->assertStringNotContainsString( '.min.js', $script->src, 'And not a minified twin, which would drift from it.' );
		$this->assertFileDoesNotExist( dirname( __DIR__ ) . '/js/wp-sweep-admin.min.js', 'No minified twin ships; the registered file is the source.' );
	}

	/**
	 * The script carries no jQuery in its source either.
	 */
	public function test_script_source_uses_no_jquery() {
		$source = file_get_contents( dirname( __DIR__ ) . '/js/wp-sweep-admin.js' );

		$this->assertStringNotContainsString( 'jQuery', $source, 'The shipped script is vanilla.' );
		$this->assertStringNotContainsString( '$(', $source, 'With no jQuery shorthand either, which would fail silently without the library.' );
	}

	/**
	 * The details list is built from text nodes. Assembling it by string
	 * concatenation put a comment author's markup into the administrator's
	 * browser; the JS suite covers the behaviour, this guards the source.
	 */
	public function test_script_never_assigns_innerhtml() {
		$source = file_get_contents( dirname( __DIR__ ) . '/js/wp-sweep-admin.js' );

		$this->assertStringNotContainsString( 'innerHTML', $source, 'The script never assigns innerHTML, which is how a sweep name would become markup.' );
		$this->assertStringContainsString( 'textContent', $source, 'It writes text instead, which cannot be parsed as HTML.' );
	}

	/**
	 * Every string the script shows the user is localised.
	 */
	public function test_script_is_localised() {
		WP_Sweep_Admin::admin_enqueue_scripts( $this->register_admin_menu() );

		$data = wp_scripts()->registered['wp-sweep-admin']->extra['data'];

		$this->assertStringContainsString( 'wpSweepL10n', $data, 'The localised object is attached under the name the script reads.' );

		foreach ( array( 'textCloseWarning', 'textSweep', 'textSweeping', 'textNa' ) as $key ) {
			$this->assertStringContainsString( $key, $data, $key . ' is missing from the localised strings.' );
		}
	}

	/**
	 * The top level menu entry is registered and gated on activate_plugins.
	 */
	public function test_the_screen_is_registered_under_tools() {
		global $submenu;

		$submenu = array();
		do_action( 'admin_menu' );

		$this->assertArrayHasKey( 'tools.php', $submenu, 'Nothing was registered under Tools.' );

		$entries = wp_list_filter( $submenu['tools.php'], array( 2 => WP_Sweep_Admin::PAGE ) );

		$this->assertCount( 1, $entries, 'The Sweep screen is not under Tools, or is there more than once.' );

		$entry = reset( $entries );

		$this->assertSame( 'activate_plugins', $entry[1], 'The Sweep screen is gated on the wrong capability.' );
		$this->assertSame( 'WP-Sweep', $entry[0], 'The Tools entry is not named after the plugin.' );
	}

	/**
	 * Sweep claims no top-level sidebar slot, and files nothing under Settings.
	 *
	 * It is one screen doing maintenance against the installation, which is what
	 * Tools is for. There is no settings screen to scatter: the plugin's one
	 * tunable is the wp_sweep_limit_details filter.
	 */
	public function test_no_top_level_menu_and_no_settings_entry() {
		global $menu, $submenu;

		$menu    = array();
		$submenu = array();

		do_action( 'admin_menu' );

		$this->assertNotContains( WP_Sweep_Admin::PAGE, wp_list_pluck( $menu, 2 ), 'Sweep took a top-level menu slot.' );
		$this->assertArrayNotHasKey( WP_Sweep_Admin::PAGE, $submenu, 'Sweep is parenting submenus of its own.' );

		$options = isset( $submenu['options-general.php'] ) ? wp_list_pluck( $submenu['options-general.php'], 2 ) : array();

		$this->assertNotContains( WP_Sweep_Admin::PAGE, $options, 'Sweep filed a screen under Settings.' );
	}

	/**
	 * The screen is registered under a plain menu slug.
	 *
	 * Until 2.0.0 it used the legacy "plugin file as menu slug" form,
	 * 'wp-sweep/admin.php', which put the installation directory name into
	 * the page URL.
	 */
	public function test_menu_uses_a_plain_slug() {
		global $submenu;

		$submenu = array();
		do_action( 'admin_menu' );

		$slugs = wp_list_pluck( $submenu['tools.php'], 2 );

		$this->assertContains( WP_Sweep_Admin::PAGE, $slugs, 'The screen is not registered under Tools.' );
		$this->assertSame( 'wp-sweep', WP_Sweep_Admin::PAGE, 'The page slug changed.' );
		$this->assertNotContains( 'wp-sweep/admin.php', $slugs, 'The legacy plugin-file slug is back.' );
	}

	/**
	 * The hook suffix constant really is what add_management_page() returns.
	 *
	 * Getting this wrong is silent: the script simply never loads, and the
	 * screen renders with dead buttons.
	 */
	public function test_hook_suffix_is_recorded_not_guessed() {
		// No assertion about the value before admin_menu() runs: the plugin is
		// a singleton, so an earlier test in this class has already set it.
		$hook = $this->register_admin_menu();

		$this->assertNotSame( '', $hook, 'A hook suffix was recorded at all.' );
		$this->assertStringEndsWith( '_page_' . WP_Sweep_Admin::PAGE, $hook, 'And it is the suffix core generated for this page.' );
		$this->assertSame( $hook, WP_Sweep_Admin::get_hook_suffix(), 'Which is what the accessor hands back, so the two cannot drift.' );
	}

	/**
	 * Nothing compares the hook against a hardcoded suffix.
	 */
	public function test_hook_suffix_is_not_hardcoded() {
		$code = $this->source_without_comments( '/includes/class-wp-sweep-admin.php' );

		$this->assertStringNotContainsString( 'toplevel_page_', $code, 'The suffix is not hardcoded as a top-level page.' );
		$this->assertStringNotContainsString( 'admin_page_wp-sweep', $code, 'Nor as any other guess; core is asked rather than predicted.' );
	}

	/**
	 * Nothing builds a path or URL out of the literal directory name.
	 *
	 * The plugin used to assemble 'wp-sweep/js/wp-sweep.js' by hand, so the
	 * script 404ed for anyone who installed it under any other directory
	 * name — renamed by hand, or unzipped as wp-sweep-2.0.0. Asserting on
	 * the rendered markup cannot catch this, because a 404 URL is still
	 * well-formed markup.
	 *
	 * There is no exception to this. The REST namespace used to be one - it is
	 * an identifier a client sends rather than a path anything is read from, so
	 * it could carry the slug safely - but it is `sweep/v1` now, the name the
	 * released 1.2.0 shipped, and so it matches nothing here either.
	 */
	public function test_no_php_file_hardcodes_the_directory_name() {
		$root  = dirname( __DIR__ );
		$files = array_merge(
			array( $root . '/wp-sweep.php', $root . '/uninstall.php' ),
			glob( $root . '/includes/*.php' )
		);

		foreach ( $files as $file ) {
			$code = $this->source_without_comments( str_replace( $root, '', $file ) );

			preg_match_all( "#'wp-sweep/[^']*'#", $code, $matches );

			$this->assertSame(
				array(),
				array_values( array_unique( $matches[0] ) ),
				basename( $file ) . ' builds a path from the literal directory name.'
			);
		}
	}

	/**
	 * The script URL is derived from the main file, so it follows the plugin
	 * wherever it is installed.
	 */
	public function test_script_url_is_derived_from_the_main_file() {
		WP_Sweep_Admin::admin_enqueue_scripts( $this->register_admin_menu() );

		$this->assertSame(
			plugins_url( 'js/wp-sweep-admin.js', WP_SWEEP_MAIN_FILE ),
			wp_scripts()->registered['wp-sweep-admin']->src,
			'The script URL is derived from the main file, so moving the plugin cannot break it.'
		);
	}

	/**
	 * The path constants point at the real plugin directory.
	 */
	public function test_path_constants_are_consistent() {
		$this->assertSame( dirname( __DIR__ ) . '/', WP_SWEEP_DIR, 'The directory constant resolves to the plugin directory, with its trailing slash.' );
		$this->assertSame( 'wp-sweep', WP_SWEEP_SLUG, 'The slug constant is the plugin slug.' );
		$this->assertStringEndsWith( '/', WP_SWEEP_URL, 'The URL constant carries its trailing slash, so concatenation is safe.' );
		$this->assertFileExists( WP_SWEEP_DIR . 'includes/class-wp-sweep-admin.php', 'WP_SWEEP_DIR points at the directory the classes actually live in.' );
	}

	/**
	 * Translations are left to WordPress.
	 *
	 * Since WordPress 6.7 an early load_plugin_textdomain() call triggers
	 * _doing_it_wrong, and core loads translations for WordPress.org-hosted
	 * plugins by itself at the right moment.
	 */
	public function test_plugin_does_not_load_its_own_textdomain() {
		$code = $this->source_without_comments( '/includes/class-wp-sweep.php' );

		$this->assertStringNotContainsString( 'load_plugin_textdomain(', $code, 'No textdomain is loaded by hand; WordPress has done that since 4.6.' );
	}

	/**
	 * The form carries exactly one nonce field, and it is the one that is checked.
	 *
	 * This is the regression test for a bug that every unit test missed and every
	 * browser hit. WP_List_Table::display_tablenav() prints its own
	 * wp_nonce_field(), named _wpnonce. The screen printed a second one beside it
	 * under the same name, so PHP kept only the last and check_admin_referer()
	 * was handed a nonce for an action it was not checking: every bulk sweep died
	 * with "The link you followed has expired". The tests passed throughout,
	 * because they built the nonce themselves instead of reading the one the form
	 * actually emits.
	 *
	 * So this reads the value out of the rendered markup and verifies it, which
	 * is the only version of this test with any teeth.
	 */
	public function test_the_bulk_form_emits_one_nonce_and_it_verifies() {
		$this->make_revisions( 1 );

		$html = $this->render_admin_page( array( 'page' => 'wp-sweep' ) );

		preg_match_all( '/name="_wpnonce" value="([^"]+)"/', $html, $matches );

		$this->assertCount( 1, $matches[1], 'The form carries more than one _wpnonce field, so only the last one survives the post.' );

		$table = new WP_Sweep_List_Table();

		$this->assertSame(
			1,
			wp_verify_nonce( $matches[1][0], $table->bulk_nonce_action() ),
			'The nonce the form emits does not verify against the action the bulk handler checks.'
		);
	}

	/**
	 * The bulk action sweeps everything that was checked.
	 */
	public function test_bulk_sweep_runs_every_checked_sweep() {
		$revisions = $this->make_revisions( 2 );
		$this->make_posts_with_status( 'trash', 1 );

		$html = $this->render_admin_page(
			array( 'page' => 'wp-sweep' ),
			array(
				'action'   => 'sweep',
				'sweep'    => array( 'revisions', 'deleted_posts' ),
				'_wpnonce' => wp_create_nonce( 'bulk-sweeps' ),
			)
		);

		$this->assertNull( get_post( $revisions[0] ), 'The bulk action did not sweep the revisions.' );
		$this->assertStringContainsString( 'Revisions Processed', $html, 'A bulk sweep runs the first checked sweep.' );
		$this->assertStringContainsString( 'Deleted Posts Processed', $html, 'And the second, so it is every checked one rather than the first.' );
	}

	/**
	 * Bulk sweeping an empty sweep says so, rather than claiming nothing was ticked.
	 *
	 * Reachable because every row carries a checkbox now, empty or not. The two
	 * outcomes used to share one message, and "Nothing was selected" is simply
	 * false when something was.
	 */
	public function test_bulk_sweeping_an_empty_sweep_does_not_claim_nothing_was_selected() {
		$html = $this->render_admin_page(
			array( 'page' => 'wp-sweep' ),
			array(
				'action'   => 'sweep',
				'sweep'    => array( 'revisions' ),
				'_wpnonce' => wp_create_nonce( 'bulk-sweeps' ),
			)
		);

		$this->assertStringContainsString( 'nothing left to sweep', $html, 'An empty sweep did not say it was empty.' );
		$this->assertStringNotContainsString( 'Nothing was selected', $html, 'A ticked row was reported as nothing selected.' );
	}

	/**
	 * Ticking nothing still says nothing was ticked.
	 */
	public function test_bulk_sweeping_with_nothing_ticked_says_so() {
		$html = $this->render_admin_page(
			array( 'page' => 'wp-sweep' ),
			array(
				'action'   => 'sweep',
				'_wpnonce' => wp_create_nonce( 'bulk-sweeps' ),
			)
		);

		$this->assertStringContainsString( 'Nothing was selected', $html, 'An empty selection did not say so.' );
	}

	/**
	 * The bulk action refuses a name that is not one of the plugin's own.
	 */
	public function test_bulk_sweep_ignores_an_unknown_name() {
		$revisions = $this->make_revisions( 1 );

		$html = $this->render_admin_page(
			array( 'page' => 'wp-sweep' ),
			array(
				'action'   => 'sweep',
				'sweep'    => array( 'no_such_sweep' ),
				'_wpnonce' => wp_create_nonce( 'bulk-sweeps' ),
			)
		);

		$this->assertInstanceOf( WP_Post::class, get_post( $revisions[0] ), 'An unknown bulk name deleted something.' );
		$this->assertStringContainsString( 'nothing was swept', $html, 'An unknown name in a bulk sweep is reported rather than silently skipped.' );
	}

	/**
	 * The no-JavaScript path sweeps and says what it did.
	 *
	 * Before 2.0.0 the result was assigned to $message and then discarded, so
	 * this path deleted data and rendered nothing at all.
	 */
	public function test_no_javascript_sweep_runs_and_reports() {
		$revisions = $this->make_revisions( 2 );

		$html = $this->render_admin_page(
			array(
				'sweep'    => 'revisions',
				'_wpnonce' => wp_create_nonce( 'wp_sweep_revisions' ),
			)
		);

		$this->assertNull( get_post( $revisions[0] ), 'The no-JavaScript form runs the sweep it names.' );
		$this->assertStringContainsString( '2 Revisions Processed', $html, 'The no-JavaScript path runs the sweep and reports the count.' );
		$this->assertStringContainsString( 'notice notice-success', $html, 'As a core success notice.' );
		$this->assertSame( array(), $this->admin_page_notices, 'And raises no PHP notice doing it.' );
	}

	/**
	 * With no sweep requested, no success notice is rendered.
	 */
	public function test_no_success_notice_without_a_sweep() {
		$html = $this->render_admin_page();

		$this->assertStringNotContainsString( 'notice notice-success', $html, 'With no sweep asked for, no success notice is drawn.' );
	}

	/**
	 * A sweep name that is not on the plugin's list is ignored outright, and
	 * never reaches check_admin_referer() or sweep().
	 */
	public function test_no_javascript_path_ignores_an_unknown_name() {
		$revisions = $this->make_revisions( 1 );

		$html = $this->render_admin_page(
			array(
				'sweep'    => 'no_such_sweep',
				'_wpnonce' => wp_create_nonce( 'wp_sweep_no_such_sweep' ),
			)
		);

		$this->assertInstanceOf( WP_Post::class, get_post( $revisions[0] ), 'An unknown sweep name from the no-JavaScript form deletes nothing.' );
		$this->assertStringNotContainsString( 'notice notice-success', $html, 'An unknown name reports no success, since nothing was swept.' );
		$this->assertSame( array(), $this->admin_page_notices, 'And raises no PHP notice either.' );
	}

	/**
	 * The result message is escaped on its way to the screen.
	 */
	public function test_no_javascript_message_is_escaped() {
		$this->make_revisions( 1 );

		add_filter(
			'wp_sweep_sweep',
			static function () {
				return '<script>window.pwned = 1</script>';
			}
		);

		$html = $this->render_admin_page(
			array(
				'sweep'    => 'revisions',
				'_wpnonce' => wp_create_nonce( 'wp_sweep_revisions' ),
			)
		);

		$this->assertStringNotContainsString( '<script>window.pwned', $html, 'A hostile sweep name never renders as markup in the notice.' );
		$this->assertStringContainsString( '&lt;script&gt;', $html, 'It renders as text, so the reader can see what was asked for.' );
	}

	/**
	 * The region a sweep reports into sits outside the form the table is in,
	 * and every Sweep row action names it.
	 *
	 * This is the shape tests/js/helpers.js transcribes, and it is asserted
	 * here because the transcription was wrong for the whole of the 2.0.0 work
	 * and nothing caught it. The script used to find the region by walking back
	 * through the table's previous siblings; the table is inside the form and
	 * the region is outside it, so the walk ran out of siblings inside the form
	 * and no sweep ever reported its result. The
	 * vitest suite agreed with the script because its fixture had been built to
	 * suit it -- and it can never settle the question, having no WordPress to
	 * render the screen with. This test is where that question is settled.
	 */
	public function test_the_sweep_message_region_is_outside_the_form() {
		$this->make_revisions( 2 );
		$html = $this->render_admin_page();

		$id     = 'id="' . WP_Sweep_Admin::MESSAGE_ID . '"';
		$region = strpos( $html, $id );
		$form   = strpos( $html, '<form method="post"' );
		$table  = strpos( $html, 'table-sweep' );

		$this->assertNotFalse( $region, 'The screen rendered no region for a finished sweep to report into.' );
		$this->assertSame( 1, substr_count( $html, $id ), 'The screen rendered the region more than once, and a repeated id decides for itself which one is written into.' );
		$this->assertNotFalse( $form, 'The screen rendered no form, so there is nothing for the bulk sweep to post.' );

		/*
		 * And directly under the heading, where settings_errors() has just
		 * printed the reload path's message. A bulk sweep posts and reloads; a
		 * row's Sweep button does not and the script writes here instead, so the
		 * two paths must report in the same place. This region sat below the
		 * warning, the description and the totals table -- one screen answering
		 * the same question in two positions, which Lester spotted on the live
		 * site.
		 */
		$this->assertLessThan(
			strpos( $html, 'notice notice-warning' ),
			$region,
			'The message region has drifted below the permanent warning, so a sweep reports somewhere other than where a bulk sweep does.'
		);
		$this->assertLessThan(
			strpos( $html, 'sweep-totals' ),
			$region,
			'And below the totals table.'
		);
		$this->assertLessThan( $form, $region, 'The message region has moved inside the form. tests/js/helpers.js transcribes it as outside; correct the fixture with it.' );
		$this->assertGreaterThan( $form, $table, 'The list table is no longer inside the form. tests/js/helpers.js transcribes it as inside; correct the fixture with it.' );
		$this->assertStringContainsString(
			'role="status"',
			$html,
			'The region is not a live region, so a sweep finishing is announced to nobody using a screen reader.'
		);
		$this->assertStringContainsString(
			'aria-controls="' . WP_Sweep_Admin::MESSAGE_ID . '"',
			$html,
			'A Sweep row action does not name the region it reports into, which is the only way the script finds it.'
		);
	}

	/**
	 * WP_Sweep_Admin::render_page() is the callback add_menu_page() registers,
	 * and it renders the same screen.
	 */
	public function test_admin_page_callback_renders_the_screen() {
		ob_start();
		WP_Sweep_Admin::render_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Post Sweep', $html, 'The callback renders the screen headings.' );
		$this->assertStringContainsString( 'table-sweep', $html, 'And the tables the script binds to.' );
	}
}
