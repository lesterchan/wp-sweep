<?php
/**
 * The checks every plugin in the collection carries.
 *
 * @package wp-sweep
 */

/**
 * Asserts the things that are the same in all nineteen plugins: the readme
 * header, the canonical section list, the option rows, the absence of jQuery
 * and of an RTL stylesheet. None of it is specific to sweeping; all of it is
 * the sort of drift nobody notices until a release goes out with it.
 */
class WP_Sweep_Metadata_Test extends WP_Sweep_TestCase {

	/**
	 * The plugin root.
	 *
	 * @var string
	 */
	private $root;

	/**
	 * Records the plugin root once per test.
	 */
	public function set_up() {
		parent::set_up();

		$this->root = dirname( __DIR__ );
	}

	/**
	 * The readme header, as an array of raw lines.
	 *
	 * @return array
	 */
	private function readme_header() {
		$lines = explode( "\n", file_get_contents( $this->root . '/README.md' ) );

		// Line 0 is the "# WP-Sweep" heading; the header runs to the first blank.
		$header = array();

		foreach ( array_slice( $lines, 1 ) as $line ) {
			if ( '' === trim( $line ) ) {
				break;
			}

			$header[] = $line;
		}

		return $header;
	}

	/**
	 * One field from the plugin header comment.
	 *
	 * @param string $field Field name, including the colon.
	 * @return string
	 */
	private function plugin_header( $field ) {
		preg_match(
			'/^\s*\*\s*' . preg_quote( $field, '/' ) . '\s*(.+?)\s*$/m',
			file_get_contents( $this->root . '/wp-sweep.php' ),
			$matches
		);

		return isset( $matches[1] ) ? $matches[1] : '';
	}

	/**
	 * One field from the readme header.
	 *
	 * @param string $field Field name, including the colon.
	 * @return string
	 */
	private function readme_field( $field ) {
		preg_match(
			'/^' . preg_quote( $field, '/' ) . '\s*(.+?)\s*$/m',
			file_get_contents( $this->root . '/README.md' ),
			$matches
		);

		return isset( $matches[1] ) ? $matches[1] : '';
	}

	public function test_every_readme_header_line_keeps_its_line_break() {
		$header = $this->readme_header();

		$this->assertCount( 9, $header, 'The readme header is not nine fields long.' );

		foreach ( array_slice( $header, 0, 8 ) as $line ) {
			$this->assertStringEndsWith(
				'  ',
				$line,
				'"' . trim( $line ) . '" lost the two trailing spaces that keep its line break.'
			);
		}

		$last = $header[8];

		$this->assertSame( rtrim( $last ), $last, 'The last header line must not have trailing spaces.' );
	}

	public function test_canonical_lesterchan_urls() {
		$this->assertSame( 'https://lesterchan.net/portfolio/programming/php/', $this->plugin_header( 'Plugin URI:' ), 'The Plugin URI is not the canonical one.' );
		$this->assertSame( 'https://lesterchan.net', $this->plugin_header( 'Author URI:' ), 'The Author URI is not the canonical one.' );
		$this->assertSame( 'https://lesterchan.net/site/donation/', $this->readme_field( 'Donate link:' ), 'The Donate link is not the canonical one.' );
		$this->assertSame( 'https://www.gnu.org/licenses/gpl-2.0.html', $this->plugin_header( 'License URI:' ), 'The header License URI is not the canonical one.' );
		$this->assertSame( 'https://www.gnu.org/licenses/gpl-2.0.html', $this->readme_field( 'License URI:' ), 'The readme License URI is not the canonical one.' );
	}

	public function test_contributors_is_gamerz_only() {
		$this->assertSame( 'GamerZ', $this->readme_field( 'Contributors:' ), 'The Contributors field is not exactly GamerZ.' );
	}

	public function test_text_domain_is_the_plugin_slug() {
		$this->assertSame( 'wp-sweep', $this->plugin_header( 'Text Domain:' ), 'The text domain is not the plugin slug.' );
		$this->assertSame( '/languages', $this->plugin_header( 'Domain Path:' ), 'The domain path is not /languages.' );
		$this->assertSame( 'wp-sweep', WP_SWEEP_SLUG, 'WP_SWEEP_SLUG is not the plugin slug.' );
	}

	public function test_version_matches_everywhere() {
		$this->assertSame( WP_SWEEP_VERSION, $this->plugin_header( 'Version:' ), 'The header version and WP_SWEEP_VERSION disagree.' );
		$this->assertSame( WP_SWEEP_VERSION, $this->readme_field( 'Stable tag:' ), 'The readme stable tag and WP_SWEEP_VERSION disagree.' );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', WP_SWEEP_VERSION, 'The version is not three numbers.' );
	}

	public function test_requires_headers_match_readme() {
		$this->assertSame( '6.8', $this->plugin_header( 'Requires at least:' ), 'The header WordPress floor is not 6.8.' );
		$this->assertSame( '8.2', $this->plugin_header( 'Requires PHP:' ), 'The header PHP floor is not 8.2.' );
		$this->assertSame( $this->plugin_header( 'Requires at least:' ), $this->readme_field( 'Requires at least:' ), 'The header and readme disagree about the WordPress floor.' );
		$this->assertSame( $this->plugin_header( 'Requires PHP:' ), $this->readme_field( 'Requires PHP:' ), 'The header and readme disagree about the PHP floor.' );
	}

	public function test_the_licence_block_is_the_or_later_variant() {
		$source = file_get_contents( $this->root . '/wp-sweep.php' );

		$this->assertSame( 'GPLv2 or later', $this->plugin_header( 'License:' ), 'The header licence is not GPLv2 or later.' );
		$this->assertStringContainsString(
			'(at your option) any later version',
			$source,
			'The GPL block is the version 2 only variant, which contradicts the header two lines above it.'
		);
	}

	public function test_readme_sections_are_the_canonical_set() {
		preg_match_all( '/^## .+$/m', file_get_contents( $this->root . '/README.md' ), $matches );

		$this->assertSame(
			array(
				'## Description',
				'## Usage',
				'## Frequently Asked Questions',
				'## Screenshots',
				'## Changelog',
				'## Upgrade Notice',
			),
			array_map( 'rtrim', $matches[0] ),
			'The readme level two headings are not the canonical set, in order.'
		);
	}

	public function test_donations_is_the_last_h3_of_the_description() {
		$readme      = file_get_contents( $this->root . '/README.md' );
		$description = substr( $readme, strpos( $readme, '## Description' ) );
		$description = substr( $description, 0, strpos( $description, '## Usage' ) );

		preg_match_all( '/^### .+$/m', $description, $matches );

		$this->assertSame( '### Donations', rtrim( end( $matches[0] ) ), 'Donations is not the last h3 of the description.' );
		$this->assertStringContainsString(
			'I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.',
			$description,
			'The Donations paragraph is not the agreed wording.'
		);
	}

	public function test_changelog_prefixes_are_canonical() {
		$readme    = file_get_contents( $this->root . '/README.md' );
		$changelog = substr( $readme, strpos( $readme, '## Changelog' ) );
		$changelog = substr( $changelog, 0, strpos( $changelog, '## Upgrade Notice' ) );

		preg_match_all( '/^\* (.+)$/m', $changelog, $matches );

		$this->assertNotEmpty( $matches[1], 'The changelog has no entries at all.' );

		foreach ( $matches[1] as $entry ) {
			$this->assertMatchesRegularExpression(
				'/^(BREAKING|NEW|CHANGED|FIXED|NOTE): /',
				$entry,
				'"' . $entry . '" does not start with one of the five allowed prefixes.'
			);
		}
	}

	public function test_the_raised_floors_are_recorded_as_a_breaking_change() {
		$readme = file_get_contents( $this->root . '/README.md' );

		$this->assertStringContainsString(
			'BREAKING: Requires WordPress 6.8 and PHP 8.2',
			$readme,
			'The raised floors are not a BREAKING changelog line.'
		);

		$notice = substr( $readme, strpos( $readme, '## Upgrade Notice' ) );

		$this->assertStringContainsString( '6.8', $notice, 'The upgrade notice does not mention the WordPress floor.' );
		$this->assertStringContainsString( '8.2', $notice, 'The upgrade notice does not mention the PHP floor.' );
	}

	public function test_no_jquery_is_enqueued() {
		/*
		 * A screen has to be set before admin_enqueue_scripts is fired.
		 * WP_UnitTestCase_Base::tear_down() nulls $current_screen -- its own
		 * comment says a test wanting one is expected to call
		 * set_current_screen() -- and core's own listener on this action,
		 * WP_Site_Health::enqueue_scripts(), reads get_current_screen()->id
		 * unguarded. Firing the action with no screen therefore fails inside core
		 * before the plugin's own callback is ever reached.
		 */
		set_current_screen( WP_Sweep_Admin::get_hook_suffix() );

		do_action( 'admin_enqueue_scripts', WP_Sweep_Admin::get_hook_suffix() );

		foreach ( wp_scripts()->registered as $handle => $script ) {
			if ( 0 !== strpos( $handle, 'wp-sweep' ) ) {
				continue;
			}

			$this->assertNotContains( 'jquery', $script->deps, "The '{$handle}' script declares a jQuery dependency." );
		}

		foreach ( glob( $this->root . '/js/*.js' ) as $file ) {
			$source = file_get_contents( $file );

			$this->assertStringNotContainsString( 'jQuery', $source, basename( $file ) . ' still references jQuery.' );
			$this->assertStringNotContainsString( '$(', $source, basename( $file ) . ' still uses the jQuery alias.' );
		}
	}

	public function test_no_rtl_stylesheet_is_registered() {
		$this->assertSame( array(), glob( $this->root . '/css/*-rtl.css' ), 'The plugin ships an RTL stylesheet.' );

		foreach ( wp_styles()->registered as $handle => $style ) {
			if ( 0 !== strpos( $handle, 'wp-sweep' ) ) {
				continue;
			}

			$this->assertArrayNotHasKey( 'rtl', $style->extra, "The '{$handle}' style registers rtl data." );
		}
	}

	public function test_every_directory_has_an_index_php() {
		$directories = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $this->root, FilesystemIterator::SKIP_DOTS ),
				static function ( $file ) {
					$name = $file->getFilename();

					return ! in_array( $name, array( 'vendor', 'node_modules', '.git', '.github', '.claude' ), true )
						&& 0 !== strpos( $name, '.' );
				}
			),
			RecursiveIteratorIterator::SELF_FIRST
		);

		$checked = 0;

		foreach ( $directories as $file ) {
			if ( ! $file->isDir() ) {
				continue;
			}

			++$checked;

			$this->assertFileExists(
				$file->getPathname() . '/index.php',
				str_replace( $this->root . '/', '', $file->getPathname() ) . ' has no index.php.'
			);
		}

		$this->assertGreaterThan( 0, $checked, 'No directories were checked at all.' );
		$this->assertFileExists( $this->root . '/index.php', 'The plugin root has no index.php.' );
	}

	public function test_the_plugin_owns_exactly_two_option_rows() {
		global $wpdb;

		WP_Sweep_Options::maybe_upgrade();

		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s", 'wp\_sweep\_%' ) );

		sort( $rows );

		$this->assertSame(
			array( WP_Sweep_Options::OPTION, WP_Sweep_Options::VERSION ),
			$rows,
			'The plugin owns option rows beyond its settings and its version markers.'
		);
	}

	public function test_uninstall_removes_every_option_row() {
		global $wpdb;

		WP_Sweep_Options::maybe_upgrade();

		$this->assertNotEmpty(
			$wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s", 'wp\_sweep\_%' ) ),
			'There was nothing to uninstall, so this proves nothing.'
		);

		// uninstall.php guards on WP_UNINSTALL_PLUGIN and defining it would
		// take the rest of the run with it, so the deletions it performs are
		// run here instead and the file is asserted to name the same rows.
		delete_option( WP_Sweep_Options::OPTION );
		delete_option( WP_Sweep_Options::VERSION );

		$this->assertSame(
			array(),
			$wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s", 'wp\_sweep\_%' ) ),
			'A wp_sweep_ option row survived the uninstall.'
		);

		$uninstall = file_get_contents( $this->root . '/uninstall.php' );

		$this->assertStringContainsString( "delete_option( '" . WP_Sweep_Options::OPTION . "' )", $uninstall, 'uninstall.php does not delete the settings row.' );
		$this->assertStringContainsString( "delete_option( '" . WP_Sweep_Options::VERSION . "' )", $uninstall, 'uninstall.php does not delete the version row.' );
	}

	public function test_uninstall_walks_the_whole_network() {
		$uninstall = file_get_contents( $this->root . '/uninstall.php' );

		$this->assertStringContainsString( 'is_multisite()', $uninstall, 'uninstall.php does not branch on multisite.' );
		$this->assertStringContainsString( "'number' => 0", $uninstall, 'uninstall.php stops at the default hundred sites.' );
		$this->assertStringContainsString( "'fields' => 'ids'", $uninstall, 'uninstall.php hydrates whole site objects to read one column.' );
		$this->assertMatchesRegularExpression(
			'/switch_to_blog\([^}]*restore_current_blog\(\)/s',
			$uninstall,
			'uninstall.php closes a block between switch_to_blog() and restore_current_blog().'
		);
	}

	public function test_version_row_holds_exactly_plugin_and_db() {
		WP_Sweep_Options::maybe_upgrade();

		$row = get_option( WP_Sweep_Options::VERSION );

		$this->assertIsArray( $row, 'The version row is not an array.' );
		$this->assertSame( array( 'plugin', 'db' ), array_keys( $row ), 'The version row does not hold exactly the plugin and db markers.' );
	}

	public function test_settings_sanitizer_never_stores_version_markers() {
		$clean = WP_Sweep_Options::sanitize(
			array(
				'limit_details' => 200,
				'version'       => '2.0.0',
				'db_version'    => '1',
				'versions'      => array( 'plugin' => '2.0.0' ),
			)
		);

		foreach ( array( 'version', 'db_version', 'versions' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $clean, "The sanitiser stored a '{$key}' key in the settings row." );
		}
	}
}
