<?php
/**
 * Shared base class for the WP-Sweep test suite.
 *
 * @package wp-sweep
 */

/**
 * Seeds the kinds of rubbish WP-Sweep is meant to find, and measures counts as
 * deltas so a shared test database that already holds a stray orphan row cannot
 * turn an assertion red.
 */
abstract class WP_Sweep_TestCase extends WP_UnitTestCase {
	/**
	 * Sweep counts captured before a test seeds anything.
	 *
	 * @var array
	 */
	protected $baseline = array();

	/**
	 * Returns the plugin singleton.
	 *
	 * Every test goes through this one accessor rather than naming the class,
	 * so renaming the class is a one-line change here instead of a hundred
	 * call sites that a missed reference could hide in.
	 *
	 * @return Sweep The plugin instance.
	 */
	protected function sweep() {
		return Sweep::get_instance();
	}

	/**
	 * Records the current count for a sweep so later assertions can be deltas.
	 *
	 * @param string|array $names One or more sweep names.
	 * @return void
	 */
	protected function baseline( $names ) {
		foreach ( (array) $names as $name ) {
			$this->baseline[ $name ] = (int) $this->sweep()->count( $name );
		}
	}

	/**
	 * Asserts how many items a sweep has gained since baseline() was called.
	 *
	 * @param int    $expected Expected increase.
	 * @param string $name     Sweep name.
	 * @return void
	 */
	protected function assertSweepDelta( $expected, $name ) {
		$this->assertArrayHasKey( $name, $this->baseline, "baseline( '{$name}' ) was never called." );

		$actual = (int) $this->sweep()->count( $name ) - $this->baseline[ $name ];

		$this->assertSame( $expected, $actual, "Unexpected count delta for '{$name}'." );
	}

	/**
	 * Diagnostics raised while the admin page rendered.
	 *
	 * @var array
	 */
	protected $admin_page_notices = array();

	/**
	 * Path to the admin screen template, relative to the plugin root.
	 *
	 * Kept in one place so moving the file is a one-line change here rather
	 * than a rename that a missed reference could hide in.
	 *
	 * @var string
	 */
	protected $admin_page = '/includes/admin.php';

	/**
	 * The hook suffix WordPress hands the admin screen.
	 *
	 * Kept beside $admin_page for the same reason: it changes when the menu
	 * registration changes, and once here is better than in every test.
	 *
	 * @var string
	 */
	protected $admin_hook_suffix = '';

	/**
	 * Registers the admin menu and returns the hook suffix WordPress gave it.
	 *
	 * Always ask rather than hardcode: get_plugin_page_hookname() builds the
	 * prefix from $admin_page_hooks, so the same menu slug yields
	 * 'tools_page_wp-sweep' on a real admin request and 'admin_page_wp-sweep'
	 * where the admin menu has not been built, as in this test run.
	 *
	 * @return string The hook suffix.
	 */
	protected function register_admin_menu() {
		$GLOBALS['submenu'] = array();

		$this->sweep()->admin_menu();

		$this->admin_hook_suffix = $this->sweep()->get_hook_suffix();

		return $this->admin_hook_suffix;
	}

	/**
	 * Reads a plugin source file with its comments removed.
	 *
	 * Assertions about what the code does must not be satisfied — or broken —
	 * by a comment that merely mentions the thing. A docblock explaining why
	 * load_plugin_textdomain() is gone otherwise reads as a call to it.
	 *
	 * @param string $file Plugin-relative path.
	 * @return string The source, comments stripped.
	 */
	protected function source_without_comments( $file ) {
		$source = file_get_contents( dirname( __DIR__ ) . $file );

		$code = '';
		foreach ( token_get_all( $source ) as $token ) {
			if ( is_array( $token ) ) {
				if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
					continue;
				}
				$code .= $token[1];
			} else {
				$code .= $token;
			}
		}

		return $code;
	}

	/**
	 * Renders the admin screen and captures anything PHP complained about.
	 *
	 * The template runs at global scope under wp-admin and reaches for globals
	 * it never declares, so they have to be pulled in here or it renders half
	 * a page. Collecting diagnostics is what makes this more than a smoke
	 * test: a clean render under PHP 8 is the actual assertion.
	 *
	 * @param array $get Query parameters for the request.
	 * @return string The rendered HTML.
	 */
	protected function render_admin_page( $get = array() ) {
		global $wpdb, $wp_locale, $hook_suffix;

		$this->admin_page_notices = array();

		$_GET     = $get;
		$_POST    = array();
		$_REQUEST = $get;

		set_error_handler(
			function ( $errno, $errstr, $errfile, $errline ) {
				$this->admin_page_notices[] = $errstr . ' in ' . basename( $errfile ) . ':' . $errline;
				return true;
			}
		);

		try {
			ob_start();
			require dirname( __DIR__ ) . $this->admin_page;
			return ob_get_clean();
		} finally {
			restore_error_handler();
			$_GET     = array();
			$_POST    = array();
			$_REQUEST = array();
		}
	}

	// -- Fixtures. --

	/**
	 * Creates post revisions attached to a real parent post.
	 *
	 * @param int $how_many Number of revisions.
	 * @return array Revision post IDs.
	 */
	protected function make_revisions( $how_many = 2 ) {
		$parent = self::factory()->post->create();
		$ids    = array();

		for ( $i = 0; $i < $how_many; $i++ ) {
			$ids[] = wp_insert_post(
				array(
					'post_type'   => 'revision',
					'post_status' => 'inherit',
					'post_parent' => $parent,
					'post_title'  => "sweep-revision-{$i}",
					'post_name'   => "{$parent}-revision-{$i}",
				)
			);
		}

		return $ids;
	}

	/**
	 * Creates posts in a given status.
	 *
	 * @param string $status   Post status.
	 * @param int    $how_many Number of posts.
	 * @return array Post IDs.
	 */
	protected function make_posts_with_status( $status, $how_many = 2 ) {
		$ids = array();

		for ( $i = 0; $i < $how_many; $i++ ) {
			$ids[] = self::factory()->post->create(
				array(
					'post_status' => $status,
					'post_title'  => "sweep-{$status}-{$i}",
				)
			);
		}

		return $ids;
	}

	/**
	 * Creates comments in a given approval state.
	 *
	 * @param string $approved Value for comment_approved.
	 * @param int    $how_many Number of comments.
	 * @return array Comment IDs.
	 */
	protected function make_comments( $approved, $how_many = 2 ) {
		$post = self::factory()->post->create();
		$ids  = array();

		for ( $i = 0; $i < $how_many; $i++ ) {
			$ids[] = self::factory()->comment->create(
				array(
					'comment_post_ID'  => $post,
					'comment_approved' => $approved,
					'comment_author'   => "sweep-author-{$approved}-{$i}",
				)
			);
		}

		return $ids;
	}

	/**
	 * Inserts meta rows pointing at an object that does not exist.
	 *
	 * Includes a row keyed to ID 0, which the WordPress meta API refuses to
	 * touch and which the plugin therefore deletes with direct SQL.
	 *
	 * @param string $table    One of postmeta, commentmeta, usermeta, termmeta.
	 * @param string $meta_key Meta key to use.
	 * @return int Number of rows inserted.
	 */
	protected function make_orphan_meta( $table, $meta_key = 'sweep_orphan' ) {
		global $wpdb;

		$columns = array(
			'postmeta'    => array( $wpdb->postmeta, 'post_id' ),
			'commentmeta' => array( $wpdb->commentmeta, 'comment_id' ),
			'usermeta'    => array( $wpdb->usermeta, 'user_id' ),
			'termmeta'    => array( $wpdb->termmeta, 'term_id' ),
		);

		list( $table_name, $id_column ) = $columns[ $table ];

		// 999999 is an object that was deleted; 0 is the row the meta API cannot reach.
		foreach ( array( 999999, 0 ) as $object_id ) {
			$wpdb->insert(
				$table_name,
				array(
					$id_column   => $object_id,
					'meta_key'   => $meta_key,
					'meta_value' => 'orphaned',
				)
			);
		}

		return 2;
	}

	/**
	 * Adds the same meta key/value twice so the pair counts as duplicated.
	 *
	 * @param string $type One of post, comment, user, term.
	 * @return int The object ID the duplicates hang off.
	 */
	protected function make_duplicate_meta( $type ) {
		switch ( $type ) {
			case 'post':
				$object_id = self::factory()->post->create();
				add_post_meta( $object_id, 'sweep_dupe', 'same' );
				add_post_meta( $object_id, 'sweep_dupe', 'same' );
				break;
			case 'comment':
				$object_id = self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) );
				add_comment_meta( $object_id, 'sweep_dupe', 'same' );
				add_comment_meta( $object_id, 'sweep_dupe', 'same' );
				break;
			case 'user':
				$object_id = self::factory()->user->create();
				add_user_meta( $object_id, 'sweep_dupe', 'same' );
				add_user_meta( $object_id, 'sweep_dupe', 'same' );
				break;
			case 'term':
				$object_id = self::factory()->term->create( array( 'taxonomy' => 'post_tag' ) );
				add_term_meta( $object_id, 'sweep_dupe', 'same' );
				add_term_meta( $object_id, 'sweep_dupe', 'same' );
				break;
		}

		return $object_id;
	}

	/**
	 * Creates a term relationship whose object no longer exists.
	 *
	 * @param string $taxonomy Taxonomy to hang the relationship off.
	 * @return array Tuple of [ int $object_id, int $term_taxonomy_id ].
	 */
	protected function make_orphan_term_relationship( $taxonomy = 'post_tag' ) {
		global $wpdb;

		$term_id = self::factory()->term->create( array( 'taxonomy' => $taxonomy ) );
		$term    = get_term( $term_id, $taxonomy );

		$object_id = 999999;

		$wpdb->insert(
			$wpdb->term_relationships,
			array(
				'object_id'        => $object_id,
				'term_taxonomy_id' => $term->term_taxonomy_id,
				'term_order'       => 0,
			)
		);

		return array( $object_id, (int) $term->term_taxonomy_id );
	}

	/**
	 * Creates terms attached to nothing, which is what "unused" means here.
	 *
	 * @param int $how_many Number of terms.
	 * @return array Term IDs.
	 */
	protected function make_unused_terms( $how_many = 2 ) {
		$ids = array();

		for ( $i = 0; $i < $how_many; $i++ ) {
			$ids[] = self::factory()->term->create(
				array(
					'taxonomy' => 'post_tag',
					'name'     => "sweep-unused-term-{$i}",
				)
			);
		}

		return $ids;
	}

	/**
	 * Adds oEmbed cache rows to a real post.
	 *
	 * @param int $how_many Number of cache rows.
	 * @return int The post ID.
	 */
	protected function make_oembed_meta( $how_many = 2 ) {
		$post_id = self::factory()->post->create();

		for ( $i = 0; $i < $how_many; $i++ ) {
			add_post_meta( $post_id, '_oembed_' . md5( "sweep-{$i}" ), '<iframe></iframe>' );
		}

		return $post_id;
	}

	/**
	 * Counts rows in a meta table for a given meta key.
	 *
	 * @param string $table    One of postmeta, commentmeta, usermeta, termmeta.
	 * @param string $meta_key Meta key to count.
	 * @return int Row count.
	 */
	protected function count_meta_rows( $table, $meta_key ) {
		global $wpdb;

		$tables = array(
			'postmeta'    => $wpdb->postmeta,
			'commentmeta' => $wpdb->commentmeta,
			'usermeta'    => $wpdb->usermeta,
			'termmeta'    => $wpdb->termmeta,
		);

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM `' . $tables[ $table ] . '` WHERE meta_key = %s',
				$meta_key
			)
		);
	}
}
