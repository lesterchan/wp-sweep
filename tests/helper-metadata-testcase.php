<?php
/**
 * The metadata contract every one of the nineteen plugins signs.
 *
 * This file is a TEMPLATE. A byte-identical copy lives in every plugin's
 * tests/ directory, because the collection has no autoloader shared between
 * plugins - a copy per plugin is the delivery mechanism, and the point is that
 * the nineteen copies are identical and therefore diffable. Edit
 * _standards/templates/helper-metadata-testcase.php and copy it out again;
 * never edit one copy.
 *
 * Everything that can be derived from the plugin directory is derived here:
 * the slug is the directory name, the main file is named after it, the PHP
 * constants are the slug upper-cased, and the option rows are the slug with
 * hyphens turned into underscores. A plugin therefore declares only what a
 * machine cannot work out - the version it is shipping, its class prefix, the
 * identifiers its Upgrade Notice has to name, and the handful of documented
 * opt-outs below.
 *
 * The parent class is reached through the Plugin_TestCase alias, which each
 * plugin's tests/bootstrap.php sets to its own fixture base class immediately
 * before requiring this file. That indirection is what lets the nineteen
 * copies be identical: the fixture base class is named after the plugin, and
 * this file must not be.
 *
 * @package lesterchan
 */

/**
 * Everything §7.2 of the standard asks every plugin to assert.
 *
 * Three tiers live here:
 *
 * 1. UNIVERSAL - asserted in all nineteen. Three of them carry a documented
 *    opt-out (has_version_row(), has_settings_row()), taken by the four
 *    plugins that store nothing at all.
 * 2. FAMILY - the two WP-Stats contracts, asserted by the seven plugins that
 *    share the stats_display row and skipped, with a reason, by the rest.
 * 3. PER-PLUGIN DATA feeding a shared assertion - upgrade_notice_subjects().
 *
 * A plugin's own tests/test-metadata.php holds only what is genuinely specific
 * to it. If an assertion would read the same in a second plugin, it belongs
 * here instead.
 */
abstract class Plugin_Metadata_TestCase extends Plugin_TestCase {

	/**
	 * The WordPress floor every plugin in the collection declares (§1.1).
	 *
	 * @var string
	 */
	const WORDPRESS_FLOOR = '6.8';

	/**
	 * The PHP floor every plugin in the collection declares (§1.1).
	 *
	 * @var string
	 */
	const PHP_FLOOR = '8.2';

	/**
	 * Directory names the walks never descend into.
	 *
	 * Playwright writes traces, screenshots and the stored admin
	 * session from a local end-to-end run. It is gitignored and never shipped,
	 * so it has no index.php and no business being walked. It used to be
	 * spelled five different ways across the collection, and the first plugin
	 * to gain an e2e suite started failing a metadata test that had nothing to
	 * do with e2e - only on the machine that had run the suite, so CI stayed
	 * green. One list, in one file, is the fix.
	 *
	 * @var string[]
	 */
	const SKIPPED_DIRECTORIES = array(
		'.git',
		'.github',
		'.claude',
		'artifacts',
		'languages',
		'node_modules',
		'vendor',
	);

	/**
	 * The plugin header fields, in the order they must appear (§3.1).
	 *
	 * Neither alphabetical nor intuitive - Requires at least and Requires PHP
	 * sit before Author - so it is copied, never composed.
	 *
	 * @var string[]
	 */
	const PLUGIN_HEADER_FIELDS = array(
		'Plugin Name',
		'Plugin URI',
		'Description',
		'Version',
		'Requires at least',
		'Requires PHP',
		'Author',
		'Author URI',
		'License',
		'License URI',
		'Text Domain',
		'Domain Path',
	);

	/**
	 * The readme header fields, in the order they must appear (§3.2).
	 *
	 * The order differs from the PHP one on purpose: Requires PHP comes after
	 * Stable tag here. They are not to be harmonised.
	 *
	 * @var string[]
	 */
	const README_HEADER_FIELDS = array(
		'Contributors',
		'Donate link',
		'Tags',
		'Requires at least',
		'Tested up to',
		'Stable tag',
		'Requires PHP',
		'License',
		'License URI',
	);

	/**
	 * The level two readme headings, in order, and nothing else (§3.3).
	 *
	 * @var string[]
	 */
	const README_SECTIONS = array(
		'Description',
		'Installation',
		'Usage',
		'Frequently Asked Questions',
		'Screenshots',
		'Changelog',
		'Upgrade Notice',
	);

	/**
	 * The five changelog bullet prefixes, and nothing else (§3.3).
	 *
	 * @var string[]
	 */
	const CHANGELOG_PREFIXES = array( 'BREAKING:', 'NEW:', 'CHANGED:', 'FIXED:', 'NOTE:' );

	/**
	 * The keys a settings row must never carry (§2.1).
	 *
	 * @var string[]
	 */
	const VERSION_MARKER_KEYS = array( 'version', 'db_version', 'versions' );

	// -----------------------------------------------------------------------
	// What a plugin has to declare, because it cannot be derived.
	// -----------------------------------------------------------------------

	/**
	 * The version this release ships.
	 *
	 * Written out rather than read from the constant, so that a bump has to be
	 * made in the test as well as in the code and cannot happen by accident.
	 *
	 * @return string
	 */
	abstract protected function expected_version();

	/**
	 * The prefix every class the plugin declares carries (§2.4).
	 *
	 * Not derivable from the slug: wp-relativedate is WP_RelativeDate and
	 * wp-dbmanager is WP_DBManager, and no casing rule produces both.
	 *
	 * @return string
	 */
	abstract protected function class_prefix();

	/**
	 * The identifiers this plugin's Upgrade Notice has to name (§14.1).
	 *
	 * Every break a site owner updating from the released version would notice
	 * - raised floors, renamed option rows, renamed hooks, moved screens,
	 * dropped template tags. The list is per-plugin; the assertion is shared.
	 * This is the test that caught a readme rewrite dropping stats_display.
	 *
	 * @return string[]
	 */
	abstract protected function upgrade_notice_subjects();

	/**
	 * Whether the plugin keeps a version marker row (§2.1).
	 *
	 * The documented opt-out. Four plugins store nothing at all - they have no
	 * settings, no schema and no migration, so there is nothing for a marker
	 * to mark. They override this to false and assert the row's absence.
	 *
	 * @return bool
	 */
	protected function has_version_row() {
		return true;
	}

	/**
	 * Whether the plugin keeps an options settings row (§2.1).
	 *
	 * The same four plugins override this to false. §7.2 says so out loud:
	 * the plugins with no settings row have no sanitiser, and assert instead
	 * that no options row is ever created.
	 *
	 * @return bool
	 */
	protected function has_settings_row() {
		return true;
	}

	/**
	 * Write the option rows uninstall is expected to remove.
	 *
	 * Called before run_uninstall(). A plugin with a settings row seeds it
	 * here; a plugin that stores nothing writes by hand the row a pre-release
	 * build left behind, which is the only thing its uninstaller will ever
	 * find.
	 *
	 * @return void
	 */
	protected function seed_option_rows() {}

	/**
	 * Write the version marker row.
	 *
	 * Whatever the plugin calls its upgrade routine - maybe_upgrade(),
	 * update_markers(), save_markers(). Not called when has_version_row() is
	 * false.
	 *
	 * @return void
	 */
	protected function write_version_row() {}

	/**
	 * Round-trip the plugin's settings sanitiser.
	 *
	 * The callback lives on WP_Foo_Options in some plugins and WP_Foo_Settings
	 * in others, so the call itself cannot be derived.
	 *
	 * @param array $input What the settings form is pretending to have posted.
	 * @return array What would be stored.
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.InvalidNoReturn -- The return type documents the overrides; this body only fails.
	protected function sanitize_settings( array $input ) {
		$this->fail( 'A plugin with a settings row must override sanitize_settings().' );
	}

	/**
	 * Real settings keys to send through the sanitiser beside the poison.
	 *
	 * A sanitiser that rejects an array with none of its own keys would pass
	 * the marker assertions without ever running, so each plugin hands over a
	 * field or two of its own.
	 *
	 * @return array
	 */
	protected function settings_fixture() {
		return array();
	}

	/**
	 * Register whatever scripts and styles the plugin registers.
	 *
	 * There is no shared way to do this: some register on wp_enqueue_scripts,
	 * some need a screen and a logged-in user first, and one builds its script
	 * from an inline string. A plugin that registers nothing leaves this alone
	 * and the source assertions carry the whole test.
	 *
	 * @return void
	 */
	protected function register_plugin_assets() {}

	/**
	 * Script handles this plugin's own scripts may legitimately depend on.
	 *
	 * §6 wants an empty dependency array, and eighteen plugins have one. The
	 * exception is a script that extends a core editor API - wp-downloadmanager
	 * registers a quicktag - where the dependency is the whole point.
	 *
	 * @return string[]
	 */
	protected function allowed_script_dependencies() {
		return array();
	}

	/**
	 * Script handles the block registry owns, for blocks this plugin registers.
	 *
	 * These handles are not the plugin's to write: core mints them from
	 * block.json, and the dependency array behind them is whatever the build
	 * wrote into the asset manifest beside the script. They are held to the
	 * block rule below rather than to the empty-array rule §6 puts on a script
	 * the plugin hand-registers.
	 *
	 * @return string[]
	 */
	protected function block_script_handles() {
		$handles = array();

		if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
			return $handles;
		}

		$fields = array( 'editor_script_handles', 'script_handles', 'view_script_handles' );

		foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $block ) {
			if ( 0 !== strpos( $name, $this->plugin_slug() . '/' ) ) {
				continue;
			}

			foreach ( $fields as $field ) {
				$handles = array_merge( $handles, (array) $block->$field );
			}
		}

		return $handles;
	}

	/**
	 * What every built block script declares it depends on, as file => handles.
	 *
	 * Read off disk rather than out of wp_scripts(), because whether a block's
	 * handle is still in that registry when this test runs depends on which
	 * test last rebuilt the global - several plugins do, in set_up(), for the
	 * reasons §7.2.1 gives. The manifest is what the build wrote and what the
	 * release ships, so it answers the same question and answers it the same
	 * way in every plugin and every run order.
	 *
	 * @return array<string, string[]>
	 */
	protected function block_script_dependencies() {
		$found = array();

		foreach ( (array) glob( $this->metadata_root() . '/build/*/*.asset.php' ) as $asset ) {
			$manifest = include $asset;
			$relative = 'build/' . basename( dirname( $asset ) ) . '/' . basename( $asset );

			$found[ $relative ] = isset( $manifest['dependencies'] ) ? (array) $manifest['dependencies'] : array();
		}

		return $found;
	}

	/**
	 * The handles WordPress registers for itself, block editor packages included.
	 *
	 * A pristine WP_Scripts is core's own registry and nothing else: its
	 * constructor fires wp_default_scripts, which is where core adds every
	 * script it ships. Built once and remembered, because building it is not
	 * free and it cannot change during a run.
	 *
	 * @return string[]
	 */
	protected function core_script_handles() {
		static $handles = null;

		if ( null === $handles ) {
			$core    = new WP_Scripts();
			$handles = array_keys( $core->registered );
		}

		return $handles;
	}

	/**
	 * Dependencies in $deps that a block script has no business declaring.
	 *
	 * §6 exists to keep jQuery out and to stop a plugin pulling in what it does
	 * not need. A block editor script declaring wp-blocks and wp-block-editor
	 * is neither of those things - it is what a block *is*, and the handles are
	 * core's own, produced by the build rather than typed by anybody. So the
	 * rule for these is that every dependency must be a script WordPress
	 * itself provides, and none of them may be jQuery: core ships jquery, so
	 * "core provides it" alone would let jQuery back in through a block.
	 *
	 * @param string[] $deps Dependency handles.
	 * @return string[]
	 */
	protected function forbidden_block_dependencies( array $deps ) {
		$core = $this->core_script_handles();

		return array_values(
			array_filter(
				$deps,
				static function ( $handle ) use ( $core ) {
					return ! in_array( $handle, $core, true ) || 0 === strpos( $handle, 'jquery' );
				}
			)
		);
	}

	/**
	 * Fire `init` again, the way a second request would.
	 *
	 * `init` has already fired once before any test runs - the bootstrap loads
	 * the plugin and then finishes booting WordPress. A test that fires it
	 * again, to watch what a plain front end request does, also re-runs the
	 * once-per-process work hooked there, and block registration is exactly
	 * that: registering a block type twice is a _doing_it_wrong() notice, which
	 * this suite turns into a failure. A real second request starts with an
	 * empty block registry, so empty it of this plugin's blocks first and the
	 * second init then does the same work the first one did.
	 *
	 * @return void
	 */
	protected function fire_init() {
		if ( class_exists( 'WP_Block_Type_Registry' ) ) {
			$registry = WP_Block_Type_Registry::get_instance();

			foreach ( array_keys( $registry->get_all_registered() ) as $name ) {
				if ( 0 === strpos( $name, $this->plugin_slug() . '/' ) ) {
					$registry->unregister( $name );
				}
			}
		}

		do_action( 'init' );
	}

	/**
	 * Whether the plugin is one of the seven sharing the WP-Stats surface.
	 *
	 * The seven make one promise between them in their Upgrade Notices, and it
	 * is only true if all seven make it.
	 *
	 * @return bool
	 */
	protected function wp_stats_family() {
		return false;
	}

	/**
	 * The unprefixed WP-Stats rows this plugin reads but does not own.
	 *
	 * Six plugins read stats_display and stats_mostlimit; deleting either on
	 * uninstall takes a setting away from a sibling that is still installed
	 * (§13.2). WP-Stats itself owns the rows and returns an empty list.
	 *
	 * @return string[]
	 */
	protected function shared_wp_stats_rows() {
		return array();
	}

	// -----------------------------------------------------------------------
	// Everything below is derived from the plugin directory.
	// -----------------------------------------------------------------------

	/**
	 * The plugin root, whatever the checkout is called.
	 *
	 * @return string
	 */
	protected function metadata_root() {
		return dirname( __DIR__ );
	}

	/**
	 * The plugin slug, which is the directory name.
	 *
	 * @return string
	 */
	protected function plugin_slug() {
		return basename( $this->metadata_root() );
	}

	/**
	 * The main plugin file, which is named after the directory.
	 *
	 * That is what wordpress.org installs it as, so it is a derivation rather
	 * than a convention.
	 *
	 * @return string
	 */
	protected function main_file() {
		return $this->metadata_root() . '/' . $this->plugin_slug() . '.php';
	}

	/**
	 * The prefix of the plugin's PHP constants (§2.3).
	 *
	 * @return string
	 */
	protected function constant_prefix() {
		return strtoupper( str_replace( '-', '_', $this->plugin_slug() ) );
	}

	/**
	 * The prefix of the plugin's option rows (§2.1).
	 *
	 * @return string
	 */
	protected function option_prefix() {
		return str_replace( '-', '_', $this->plugin_slug() ) . '_';
	}

	/**
	 * One of the plugin's constants, by suffix.
	 *
	 * @param string $suffix Constant name without the plugin prefix.
	 * @return mixed
	 */
	protected function plugin_constant( $suffix ) {
		$name = $this->constant_prefix() . '_' . $suffix;

		$this->assertTrue( defined( $name ), $name . ' is not defined.' );

		return constant( $name );
	}

	/**
	 * The main plugin file, as text.
	 *
	 * @return string
	 */
	protected function plugin_file() {
		return (string) file_get_contents( $this->main_file() );
	}

	/**
	 * The readme, as text.
	 *
	 * @return string
	 */
	protected function readme() {
		return (string) file_get_contents( $this->metadata_root() . '/README.md' );
	}

	/**
	 * The readme header: every line between the h1 and the first blank line.
	 *
	 * @return string[]
	 */
	protected function readme_header_lines() {
		$lines  = explode( "\n", $this->readme() );
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
	 * One field out of the plugin file's header docblock.
	 *
	 * @param string $field Field name, without the colon.
	 * @return string
	 */
	protected function header_field( $field ) {
		preg_match(
			'/^\s*\*\s*' . preg_quote( $field, '/' ) . ':\s*(.+?)\s*$/m',
			$this->plugin_file(),
			$matches
		);

		return isset( $matches[1] ) ? $matches[1] : '';
	}

	/**
	 * One field out of the readme header.
	 *
	 * @param string $field Field name, without the colon.
	 * @return string
	 */
	protected function readme_field( $field ) {
		preg_match(
			'/^' . preg_quote( $field, '/' ) . ':\s*(.+?)\s*$/m',
			$this->readme(),
			$matches
		);

		return isset( $matches[1] ) ? $matches[1] : '';
	}

	/**
	 * The readme from the Upgrade Notice heading to the end.
	 *
	 * @return string
	 */
	protected function upgrade_notice() {
		$readme = $this->readme();
		$at     = strpos( $readme, '## Upgrade Notice' );

		$this->assertNotFalse( $at, 'The readme must carry an Upgrade Notice section.' );

		return substr( $readme, (int) $at );
	}

	/**
	 * Every PHP file the plugin ships: the root files and includes/.
	 *
	 * @return string[] Absolute paths.
	 */
	protected function metadata_source_files() {
		return array_merge(
			(array) glob( $this->metadata_root() . '/*.php' ),
			(array) glob( $this->metadata_root() . '/includes/*.php' )
		);
	}

	/**
	 * Every PHP file the plugin ships, concatenated, with comments stripped.
	 *
	 * @return string
	 */
	protected function metadata_source() {
		$source = '';

		foreach ( $this->metadata_source_files() as $file ) {
			$source .= (string) file_get_contents( $file );
		}

		return $this->without_php_comments( $source );
	}

	/**
	 * PHP source with every comment removed.
	 *
	 * A test that greps the source for a call it does not want otherwise finds
	 * the comment explaining why the call is absent, and fails the plugin for
	 * documenting itself. wp-sweep says "There is no load_plugin_textdomain()
	 * call" and was failed for saying so. Tokenising is the only honest way to
	 * tell code from prose about code, and every source grep in this file goes
	 * through it - including the jQuery one, which had the same latent defect.
	 *
	 * @param string $source PHP source.
	 * @return string
	 */
	protected function without_php_comments( $source ) {
		$code = '';

		foreach ( token_get_all( $source ) as $token ) {
			if ( is_array( $token ) ) {
				if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
					continue;
				}

				$code .= $token[1];
				continue;
			}

			$code .= $token;
		}

		return $code;
	}

	/**
	 * JavaScript source with every comment removed.
	 *
	 * Same reason as the PHP form: two of the scripts carry a header recording
	 * that they were "rewritten without jQuery", which is the opposite of what
	 * a substring search reads it as.
	 *
	 * @param string $file Absolute path to a script.
	 * @return string
	 */
	protected function without_js_comments( $file ) {
		return (string) preg_replace(
			'#/\*.*?\*/|//[^\n]*#s',
			'',
			(string) file_get_contents( $file )
		);
	}

	/**
	 * Every directory the plugin ships, the root included.
	 *
	 * The iterator PRUNES rather than filtering after the fact. A plain filter
	 * descends into node_modules and vendor before discarding them, which is
	 * slow enough to look like a hang.
	 *
	 * @return string[] Absolute paths.
	 */
	protected function shipped_directories() {
		$root  = $this->metadata_root();
		$found = array( $root );

		$iterator = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
				static function ( $current ) {
					if ( ! $current->isDir() ) {
						return false;
					}

					$name = $current->getFilename();

					return ! in_array( $name, self::SKIPPED_DIRECTORIES, true )
						&& 0 !== strpos( $name, '.' );
				}
			),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $directory ) {
			$found[] = $directory->getPathname();
		}

		return $found;
	}

	/**
	 * Every option row the plugin owns, read straight from the table.
	 *
	 * A LIKE over wp_options rather than a list of delete_option() checks: a
	 * row added later and forgotten in uninstall.php is exactly the failure
	 * the uninstall test exists to catch.
	 *
	 * @return string[]
	 */
	protected function stored_option_names() {
		global $wpdb;

		return (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $this->option_prefix() ) . '%'
			)
		);
	}

	// -----------------------------------------------------------------------
	// Tier one: the universal assertions.
	// -----------------------------------------------------------------------

	/**
	 * Eight of the nine readme header lines end in two spaces; the ninth does
	 * not.
	 *
	 * Markdown joins consecutive lines into one paragraph unless each is ended
	 * with a hard line break, so a missing pair renders as
	 * "License: GPLv2 or later License URI: https://..." on wordpress.org. It
	 * is invisible in the source and in a diff, which is exactly why it wants
	 * a test. The last line needs none, having nothing after it to run into.
	 */
	public function test_every_readme_header_line_keeps_its_line_break() {
		$header = $this->readme_header_lines();

		$this->assertCount( 9, $header, 'The readme header is nine fields (§3.2).' );

		foreach ( self::README_HEADER_FIELDS as $index => $field ) {
			$this->assertStringStartsWith(
				$field . ':',
				trim( $header[ $index ] ),
				'Readme header field ' . ( $index + 1 ) . ' should be ' . $field . '.'
			);
		}

		foreach ( array_slice( $header, 0, 8 ) as $line ) {
			$this->assertStringEndsWith(
				'  ',
				$line,
				'"' . trim( $line ) . '" needs two trailing spaces or it merges with the line below.'
			);
		}

		$last = $header[8];

		$this->assertSame( rtrim( $last ), $last, 'The last header line takes no trailing spaces.' );
	}

	/**
	 * The four canonical URLs, which have drifted to http over twenty years.
	 */
	public function test_canonical_lesterchan_urls() {
		$this->assertSame(
			'https://lesterchan.net/portfolio/programming/php/',
			$this->header_field( 'Plugin URI' ),
			'The Plugin URI is not the canonical portfolio URL.'
		);
		$this->assertSame(
			'https://lesterchan.net',
			$this->header_field( 'Author URI' ),
			'The Author URI is not canonical.'
		);
		$this->assertSame(
			'https://lesterchan.net/site/donation/',
			$this->readme_field( 'Donate link' ),
			'The Donate link is not canonical.'
		);
		$this->assertSame(
			'https://www.gnu.org/licenses/gpl-2.0.html',
			$this->header_field( 'License URI' ),
			'The header License URI is not the canonical GPLv2 URL.'
		);
		$this->assertSame(
			'https://www.gnu.org/licenses/gpl-2.0.html',
			$this->readme_field( 'License URI' ),
			'The readme License URI is not the canonical GPLv2 URL.'
		);
	}

	/**
	 * One name, in every plugin.
	 *
	 * A second contributor has to be added on wordpress.org as well, so a name
	 * here that is not on the listing silently does nothing.
	 */
	public function test_contributors_is_gamerz_only() {
		$this->assertSame(
			'GamerZ',
			$this->readme_field( 'Contributors' ),
			'Contributors is GamerZ and nothing else.'
		);
	}

	/**
	 * The text domain is the slug, and the slug is the directory name.
	 */
	public function test_text_domain_is_the_plugin_slug() {
		$slug = $this->plugin_slug();

		$this->assertSame( $slug, $this->header_field( 'Text Domain' ), 'The text domain is the plugin slug.' );
		$this->assertSame( '/languages', $this->header_field( 'Domain Path' ), 'The domain path is /languages.' );
		$this->assertSame( $slug, $this->plugin_constant( 'SLUG' ), 'The SLUG constant is the plugin slug.' );
	}

	/**
	 * The header, the readme, the constant and the changelog all agree.
	 *
	 * This is the check the release pre-flight makes, kept here so a mismatch
	 * fails in CI first.
	 */
	public function test_version_matches_everywhere() {
		$version = $this->expected_version();

		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $version, 'The version is three numbers (§14).' );
		$this->assertSame( $version, $this->header_field( 'Version' ), 'The plugin header disagrees with the shipped version.' );
		$this->assertSame( $version, $this->readme_field( 'Stable tag' ), 'The readme Stable tag disagrees with the shipped version.' );
		$this->assertSame( $version, $this->plugin_constant( 'VERSION' ), 'The VERSION constant disagrees with the shipped version.' );
		$this->assertStringContainsString(
			'### ' . $version . "\n",
			$this->readme(),
			'The changelog has no section for the version being shipped.'
		);
		$this->assertSame(
			0,
			preg_match( '/^### Version /m', $this->readme() ),
			'Changelog headings are bare versions: "### 1.2.3", never "### Version 1.2.3".'
		);
	}

	/**
	 * The floors agree between the plugin header and the readme, and both are
	 * the collection's floors (§1.1).
	 */
	public function test_requires_headers_match_readme() {
		$this->assertSame( self::WORDPRESS_FLOOR, $this->header_field( 'Requires at least' ), 'The header WordPress floor is wrong.' );
		$this->assertSame( self::WORDPRESS_FLOOR, $this->readme_field( 'Requires at least' ), 'The readme WordPress floor is wrong.' );
		$this->assertSame( self::PHP_FLOOR, $this->header_field( 'Requires PHP' ), 'The header PHP floor is wrong.' );
		$this->assertSame( self::PHP_FLOOR, $this->readme_field( 'Requires PHP' ), 'The readme PHP floor is wrong.' );
	}

	/**
	 * The level two headings are a closed set in a fixed order.
	 *
	 * Level three ones are free: Features, Donations, the usage subsections
	 * and every changelog version live below these.
	 */
	public function test_readme_sections_are_the_canonical_set() {
		preg_match_all( '/^## (.+?)\s*$/m', $this->readme(), $sections );

		$this->assertSame(
			self::README_SECTIONS,
			$sections[1],
			'The readme level two headings are not the canonical set, in order (§3.3).'
		);
	}

	/**
	 * Five prefixes, and nothing else.
	 *
	 * The listing on wordpress.org renders the changelog verbatim, so a stray
	 * "IMPORTANT:" or a lowercase "New:" is visible to every reader of it.
	 */
	public function test_changelog_prefixes_are_canonical() {
		$readme    = $this->readme();
		$changelog = substr( $readme, (int) strpos( $readme, '## Changelog' ) );
		$changelog = substr( $changelog, 0, (int) strpos( $changelog, "\n## Upgrade Notice" ) );

		preg_match_all( '/^\* (.+)$/m', $changelog, $bullets );

		$this->assertNotEmpty( $bullets[1], 'The changelog must carry bullets.' );

		$offences = array();

		foreach ( $bullets[1] as $bullet ) {
			foreach ( self::CHANGELOG_PREFIXES as $prefix ) {
				if ( 0 === strpos( $bullet, $prefix . ' ' ) ) {
					continue 2;
				}
			}

			$offences[] = substr( $bullet, 0, 60 );
		}

		$this->assertSame(
			array(),
			$offences,
			'Changelog bullets start with one of: ' . implode( ' ', self::CHANGELOG_PREFIXES )
		);
	}

	/**
	 * Nothing the plugin registers pulls jQuery in, and nothing it ships
	 * reaches for one at runtime.
	 *
	 * Both halves matter: a source grep alone passes a dependency array built
	 * at runtime, and a dependency check alone passes a script that reaches
	 * for window.jQuery once somebody else has loaded it. A plugin that
	 * registers no script at all takes the stronger third form - the call is
	 * absent from the source entirely.
	 *
	 * Keep the opening bracket on wp_enqueue_script(. Without it the needle is
	 * also a substring of the ACTION name wp_enqueue_scripts, which a plugin
	 * legitimately hooks to enqueue a stylesheet.
	 *
	 * A block's scripts take the third form: their handles and their
	 * dependencies come from core and the build rather than from the plugin,
	 * so they are held to forbidden_block_dependencies() instead, and read off
	 * disk so the answer does not depend on run order.
	 */
	public function test_no_jquery_is_enqueued() {
		$source = $this->metadata_source();

		$this->assertStringNotContainsStringIgnoringCase(
			'jquery',
			$source,
			'The plugin source mentions jQuery.'
		);

		$scripts = (array) glob( $this->metadata_root() . '/js/*.js' );

		foreach ( $scripts as $script ) {
			$code = $this->without_js_comments( $script );

			$this->assertStringNotContainsStringIgnoringCase( 'jquery', $code, basename( $script ) . ' still references jQuery.' );
			$this->assertStringNotContainsString( '$(', $code, basename( $script ) . ' still uses the jQuery alias.' );
		}

		$this->register_plugin_assets();

		$allowed = $this->allowed_script_dependencies();
		$blocks  = $this->block_script_handles();
		$checked = 0;

		foreach ( wp_scripts()->registered as $handle => $script ) {
			if ( 0 !== strpos( $handle, $this->plugin_slug() ) ) {
				continue;
			}

			++$checked;

			$this->assertNotContains( 'jquery', (array) $script->deps, $handle . ' depends on jQuery.' );

			if ( in_array( $handle, $blocks, true ) ) {
				continue;
			}

			$this->assertSame(
				array(),
				array_diff( (array) $script->deps, $allowed ),
				$handle . ' declares a dependency §6 does not allow.'
			);
		}

		foreach ( $this->block_script_dependencies() as $manifest => $deps ) {
			++$checked;

			$this->assertSame(
				array(),
				$this->forbidden_block_dependencies( $deps ),
				$manifest . ' declares a dependency §6 does not allow.'
			);
		}

		if ( 0 === $checked && array() === $scripts ) {
			$this->assertStringNotContainsString(
				'wp_enqueue_script(',
				$source,
				'The plugin registers no scripts, so it can declare no dependencies.'
			);
		}
	}

	/**
	 * Every shipped directory carries a silence-is-golden index.php, in the
	 * docblock form phpcbf can keep tidy.
	 */
	public function test_every_directory_has_an_index_php() {
		$directories = $this->shipped_directories();

		$this->assertNotEmpty( $directories, 'The walk found no directories at all, which cannot be right.' );

		foreach ( $directories as $directory ) {
			$relative = str_replace( $this->metadata_root(), '', $directory );
			$relative = '' === $relative ? '/' : $relative;

			$this->assertFileExists( $directory . '/index.php', $relative . ' has no index.php.' );

			$guard = (string) file_get_contents( $directory . '/index.php' );

			$this->assertStringContainsString( '/**', $guard, $relative . '/index.php must use the docblock form.' );
			$this->assertStringContainsString( 'Silence is golden.', $guard, $relative . '/index.php must say so.' );
		}
	}

	/**
	 * No plugin ships a second, mirrored stylesheet.
	 *
	 * The front end uses CSS logical properties instead, so one sheet serves
	 * both directions (§5.1).
	 */
	public function test_no_rtl_stylesheet_is_registered() {
		$root = $this->metadata_root();

		$this->assertSame( array(), (array) glob( $root . '/*-rtl.css' ), 'No plugin ships an RTL stylesheet.' );
		$this->assertSame( array(), (array) glob( $root . '/css/*-rtl.css' ), 'No plugin ships an RTL stylesheet.' );
		$this->assertStringNotContainsString(
			'wp_style_add_data',
			$this->metadata_source(),
			"No plugin registers 'rtl' style data."
		);

		$this->register_plugin_assets();

		foreach ( wp_styles()->registered as $handle => $style ) {
			if ( 0 !== strpos( $handle, $this->plugin_slug() ) ) {
				continue;
			}

			$this->assertArrayNotHasKey( 'rtl', (array) $style->extra, $handle . ' registers rtl style data.' );
			$this->assertStringNotContainsString( '-rtl.css', (string) $style->src, $handle . ' points at a mirrored sheet.' );
		}
	}

	/**
	 * The GPL text ships alongside the header that points at it.
	 */
	public function test_the_gpl_licence_is_shipped() {
		$path = $this->metadata_root() . '/LICENSE';

		$this->assertFileExists( $path, 'The GPL text must ship with the plugin.' );

		$licence = (string) file_get_contents( $path );

		$this->assertStringContainsString( 'GNU GENERAL PUBLIC LICENSE', $licence, 'LICENSE is not the GPL.' );
		$this->assertStringContainsString( 'Version 2, June 1991', $licence, 'The licence must be GPLv2, matching the plugin header.' );
	}

	/**
	 * The plugin header fields appear in the canonical order (§3.1).
	 */
	public function test_the_plugin_header_fields_are_in_the_canonical_order() {
		$this->assertFileExists( $this->main_file(), 'The main plugin file is named after the plugin directory.' );

		preg_match( '#^<\?php\s*/\*\*(.+?)\*/#s', $this->plugin_file(), $matches );

		$this->assertNotEmpty( $matches, 'The plugin file must open with a docblock header.' );

		preg_match_all( '/^\s*\*\s*([A-Z][A-Za-z ]*?):\s/m', $matches[1], $fields );

		$this->assertSame(
			self::PLUGIN_HEADER_FIELDS,
			$fields[1],
			'Plugin header fields must appear in the canonical order.'
		);
	}

	/**
	 * The plugin leaves loading its translations to WordPress.
	 *
	 * WordPress has loaded them for wordpress.org plugins itself since 4.6, so
	 * a load_plugin_textdomain() call is dead weight - and since 6.7, calling
	 * it this early trips _doing_it_wrong.
	 */
	public function test_the_plugin_does_not_load_its_own_textdomain() {
		$this->assertStringNotContainsString(
			'load_plugin_textdomain',
			$this->metadata_source(),
			'WordPress loads the textdomain itself since 4.6.'
		);
	}

	/**
	 * No build, editor or translation artefacts ship.
	 *
	 * The catalogue is built by translate.wordpress.org, and Travis has been
	 * dead for these repos for years.
	 */
	public function test_no_abandoned_build_or_translation_artefacts_ship() {
		$root = $this->metadata_root();

		$this->assertFileDoesNotExist( $root . '/.travis.yml', 'CI is GitHub Actions.' );
		$this->assertFileDoesNotExist( $root . '/.wp-env.override.json', 'A personal wp-env override must not ship.' );
		$this->assertDirectoryDoesNotExist( $root . '/languages', 'translate.wordpress.org builds the catalogue.' );
		$this->assertDirectoryDoesNotExist( $root . '/.idea', 'Editor settings must not ship.' );

		foreach ( array( 'pot', 'po', 'mo' ) as $extension ) {
			$this->assertSame(
				array(),
				(array) glob( $root . '/*.' . $extension ),
				"No .{$extension} files: translate.wordpress.org builds the catalogue."
			);
		}
	}

	/**
	 * Only the three files the standard allows sit loose at the plugin root.
	 *
	 * Two plugins, wp-email and wp-print, carried this same rule under two
	 * different names.
	 *
	 * Playwright's config file is the one exception, and it is not a script the
	 * plugin ships: §7.5 puts it at the plugin root because that is where
	 * Playwright looks for it, and it resolves every path in it relative to
	 * itself. bin/verify.py draws the same line, allowing *.config.js and
	 * *.config.mjs at the root and nothing else - the two agreeing is the
	 * point. A metadata rule written before e2e existed will fire on e2e
	 * scaffolding; widen the rule, do not move the file.
	 */
	public function test_the_plugin_root_holds_no_loose_files() {
		$root = $this->metadata_root();

		// Both sides sorted: glob() returns alphabetical order, so comparing
		// against a hand-written list quietly asserts the slug sorts after
		// uninstall.php. True of every wp-* plugin and false of
		// freemyinternet, which is a fact about the alphabet rather than
		// about where its files live.
		$expected = array( 'index.php', 'uninstall.php', $this->plugin_slug() . '.php' );
		$actual   = array_map( 'basename', (array) glob( $root . '/*.php' ) );

		sort( $expected );
		sort( $actual );

		$this->assertSame(
			$expected,
			$actual,
			'Only the entry point, the uninstaller and the silence guard sit loose at the root; the rest belongs in includes/.'
		);

		$this->assertSame( array(), (array) glob( $root . '/*.css' ), 'Stylesheets live in css/.' );

		$loose = array_values(
			array_filter(
				(array) glob( $root . '/*.js' ),
				static function ( $file ) {
					return ! preg_match( '/\.config\.js$/', basename( $file ) );
				}
			)
		);

		$this->assertSame( array(), $loose, 'Scripts live in js/. Only *.config.js may sit at the root.' );
	}

	/**
	 * Every class the plugin declares carries its prefix and lives in the file
	 * §2.4 names for it.
	 */
	public function test_every_class_lives_in_the_file_named_after_it() {
		$files = (array) glob( $this->metadata_root() . '/includes/class-*.php' );

		$this->assertNotEmpty( $files, 'The plugin declares at least one class of its own.' );

		foreach ( $files as $file ) {
			$name = basename( $file );

			preg_match_all(
				'/^\s*(?:final\s+|abstract\s+)?class\s+(\w+)/m',
				(string) file_get_contents( $file ),
				$matches
			);

			$this->assertCount( 1, $matches[1], $name . ' does not declare exactly one class.' );

			$class = $matches[1][0];

			$this->assertStringStartsWith(
				$this->class_prefix(),
				$class,
				$class . ' is not prefixed with the plugin class prefix.'
			);
			$this->assertSame(
				'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php',
				$name,
				$class . ' is not in the file §2.4 names for it.'
			);
		}
	}

	/**
	 * Deleting the plugin leaves nothing behind.
	 *
	 * The uninstaller is reached through run_uninstall(), which every plugin
	 * defines in helper-testcase.php. That indirection is load-bearing:
	 * uninstall.php declares global functions in most plugins, so a second
	 * test file requiring it would fatal on redeclare - and a require_once
	 * that has already fired is a silent no-op, which proves nothing. Where
	 * the uninstaller drops a table, run_uninstall() performs the deletions
	 * itself rather than including the file, because including it would
	 * destroy the table the rest of the suite runs against; the plugin then
	 * asserts separately that uninstall.php names the same rows.
	 *
	 * The multisite half of this is the same test under
	 * phpunit-multisite.xml.dist, where uninstall.php takes its get_sites()
	 * branch.
	 */
	public function test_uninstall_removes_every_option_row() {
		$this->seed_option_rows();

		$this->assertNotEmpty(
			$this->stored_option_names(),
			'There should be rows to remove before uninstall runs, or this proves nothing.'
		);

		$this->run_uninstall();

		wp_cache_flush();

		$survivors = $this->stored_option_names();

		$this->assertSame(
			array(),
			$survivors,
			'uninstall.php left rows behind: ' . implode( ', ', $survivors )
		);
	}

	/**
	 * The upgrade markers live in a row of their own, holding those two keys
	 * and no others.
	 *
	 * Anything else in here means a marker has drifted back into the settings
	 * array, which is the bug this shape exists to make impossible (§2.1).
	 */
	public function test_version_row_holds_exactly_plugin_and_db() {
		$row_name = $this->option_prefix() . 'version';

		if ( ! $this->has_version_row() ) {
			do_action( 'plugins_loaded' );
			$this->fire_init();

			$this->assertFalse(
				get_option( $row_name, false ),
				'This plugin stores nothing; a version row appearing means the upgrade machinery has to come back with it.'
			);

			return;
		}

		$this->write_version_row();

		$markers = get_option( $row_name );

		$this->assertIsArray( $markers, $row_name . ' must be an array.' );

		$keys = array_keys( $markers );
		sort( $keys );

		$this->assertSame( array( 'db', 'plugin' ), $keys, $row_name . ' holds these two keys and nothing else.' );
		$this->assertSame( $this->expected_version(), $markers['plugin'], 'The plugin marker is not the running version.' );
		$this->assertSame( $this->plugin_constant( 'DB_VERSION' ), $markers['db'], 'The db marker is not the running schema version.' );
	}

	/**
	 * The sanitiser never stores a version marker.
	 *
	 * A sanitize_callback is a function from what the form posted to what gets
	 * stored, and the settings form never posts a marker. This is the
	 * regression guard for the wp-useronline bug: with the markers inside the
	 * settings array, every save had to rescue them by hand, and the save that
	 * forgot made the upgrade re-run on every request.
	 *
	 * Skipped by the plugins with no settings row, which have no sanitiser;
	 * they assert instead that no options row is ever created (§7.2).
	 */
	public function test_settings_sanitizer_never_stores_version_markers() {
		if ( ! $this->has_settings_row() ) {
			do_action( 'plugins_loaded' );
			$this->fire_init();

			$this->assertFalse(
				get_option( $this->option_prefix() . 'options', false ),
				'This plugin is exempt from the settings row; reinstating one brings the sanitiser test back with it.'
			);
			$this->assertStringNotContainsString(
				'register_setting',
				$this->metadata_source(),
				'A plugin with no settings row registers no setting.'
			);

			return;
		}

		$clean = $this->sanitize_settings(
			array_merge(
				$this->settings_fixture(),
				array(
					'version'    => '9.9.9',
					'db_version' => '99',
					'versions'   => array( 'plugin' => '9.9.9' ),
				)
			)
		);

		foreach ( self::VERSION_MARKER_KEYS as $marker ) {
			$this->assertArrayNotHasKey(
				$marker,
				(array) $clean,
				"The sanitiser stored a '{$marker}' key; the upgrade markers belong in " . $this->option_prefix() . 'version.'
			);
		}
	}

	// -----------------------------------------------------------------------
	// Tier three: per-plugin data, shared assertion.
	// -----------------------------------------------------------------------

	/**
	 * Every break a site owner updating from the released version would notice
	 * is findable under Upgrade Notice, not only in the changelog (§14.1).
	 */
	public function test_the_upgrade_notice_covers_the_breaking_changes() {
		$notice   = $this->upgrade_notice();
		$subjects = $this->upgrade_notice_subjects();

		$this->assertNotEmpty( $subjects, 'Every plugin names the breaks its Upgrade Notice has to cover.' );

		$this->assertStringContainsString(
			'### ' . $this->expected_version(),
			$notice,
			'The Upgrade Notice needs a section for the version being shipped.'
		);

		foreach ( $subjects as $subject ) {
			$this->assertStringContainsString(
				$subject,
				$notice,
				"The Upgrade Notice must tell a site owner about {$subject}."
			);
		}
	}

	// -----------------------------------------------------------------------
	// Tier two: the WP-Stats family contracts.
	// -----------------------------------------------------------------------

	/**
	 * The shared WP-Stats rows are not this plugin's to delete.
	 *
	 * Seven plugins meet in stats_display and stats_mostlimit. An uninstall
	 * that cleared either would take a setting away from a sibling that is
	 * still installed, and the site owner would have no way to know which
	 * plugin did it (§13.2).
	 *
	 * Asserted behaviourally rather than by grepping uninstall.php: every one
	 * of these uninstallers delegates to an installer class, so the row name
	 * the file must not contain is not in the file either way.
	 */
	public function test_uninstall_leaves_the_shared_wp_stats_rows_alone() {
		$shared = $this->shared_wp_stats_rows();

		if ( array() === $shared ) {
			$this->markTestSkipped( 'This plugin reads none of the shared WP-Stats rows, or owns them.' );
		}

		foreach ( $shared as $row ) {
			update_option( $row, array( 'sibling' => 1 ) );
		}

		$this->run_uninstall();

		wp_cache_flush();

		foreach ( $shared as $row ) {
			$this->assertSame(
				array( 'sibling' => 1 ),
				get_option( $row ),
				"Uninstalling took the shared {$row} row with it; six sibling plugins read it."
			);
		}
	}

	/**
	 * The seven say so, in the one place a site owner will look.
	 *
	 * Seven plugins read the shared rows and each migrates them away. Updating
	 * one and not the others leaves a site with a setting that has moved for
	 * some plugins and not for the rest, so the readme has to say to update
	 * them together - and the promise is only true if all seven make it.
	 */
	public function test_the_upgrade_notice_says_to_update_the_wp_stats_plugins_together() {
		if ( ! $this->wp_stats_family() ) {
			$this->markTestSkipped( 'This plugin is not one of the seven sharing the WP-Stats surface.' );
		}

		$this->assertStringContainsString(
			'Update all seven WP-Stats plugins together',
			$this->upgrade_notice(),
			'Each of the seven has to tell a site owner to update the set together.'
		);
	}
}
