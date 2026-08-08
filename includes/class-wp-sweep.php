<?php
/**
 * Plugin bootstrap and the sweep engine.
 *
 * @package WP-Sweep
 */

defined( 'ABSPATH' ) || exit;

/**
 * Counts, describes and deletes the rubbish WordPress leaves behind.
 *
 * Everything the plugin knows how to clean up is described once, in
 * get_sweeps(), and three parallel switch statements act on it: count() says
 * how much there is, details() shows a sample, and sweep() deletes it. The
 * REST routes, the WP-CLI command and the admin screen are all thin callers of
 * those three.
 */
class WP_Sweep {

	/**
	 * The capability required to count, inspect or run a sweep.
	 *
	 * Not manage_options: since 1.1.3 this has been activate_plugins, because
	 * update_plugins returns false when DISALLOW_FILE_MODS is set and that took
	 * the screen away from the very administrators who needed it. The settings
	 * screen is a separate surface and takes manage_options, per the standard.
	 *
	 * @var string
	 */
	const CAPABILITY = 'activate_plugins';

	/**
	 * How many items a details list shows unless wp_sweep_limit_details says otherwise.
	 *
	 * The whole sample is held in memory and written into the page, which is why
	 * there is a limit at all.
	 *
	 * @var int
	 */
	const DEFAULT_LIMIT_DETAILS = 500;

	/**
	 * The LIKE pattern matching WordPress's oEmbed response caches.
	 *
	 * Two things were wrong with the `%_oembed_%` it replaces, and escaping the
	 * underscores only fixes one of them.
	 *
	 * In a LIKE pattern `_` matches any single character, and
	 * `$wpdb->prepare()` escapes quotes and backslashes but has no opinion about
	 * wildcards -- so the pattern really meant "contains oembed with at least
	 * one character either side", and `xoembedy` matched. The transient sweep
	 * next door had this right (`%\_transient\_%`), which is what makes it an
	 * oversight rather than a decision.
	 *
	 * Escaping alone still leaves it a *contains* match, though, and
	 * `plugin_oembed_settings` contains `_oembed_` quite literally. Core writes
	 * these caches as `_oembed_{hash}` and `_oembed_time_{hash}` -- always that
	 * prefix -- so the pattern is anchored at the start. A key belonging to
	 * somebody else that merely mentions the word is no longer this sweep's
	 * business, which it never should have been.
	 *
	 * @var string
	 */
	const OEMBED_LIKE = '\_oembed\_%';

	/**
	 * Static instance.
	 *
	 * @var WP_Sweep|null
	 */
	private static $instance;

	/**
	 * Wire the plugin up.
	 *
	 * There is no load_plugin_textdomain() call. Since WordPress 6.7, loading a
	 * text domain this early triggers _doing_it_wrong; core loads translations
	 * for plugins hosted on WordPress.org by itself, at the right moment.
	 */
	public function __construct() {
		require_once WP_SWEEP_DIR . 'includes/class-wp-sweep-api.php';

		new WP_Sweep_API();

		// Must be registered at file-load time, which is when this runs.

		add_action( 'plugins_loaded', array( $this, 'add_hooks' ) );
	}

	/**
	 * Initialise the plugin object and return its instance.
	 *
	 * @return WP_Sweep The plugin object instance.
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Add the plugin's hooks.
	 *
	 * @return void
	 */
	public function add_hooks() {
		add_action( 'init', array( $this, 'init' ) );

		/*
		 * Not gated on is_admin(). The admin-ajax.php endpoint is an admin
		 * request, so the gate would hold in production -- but it also makes
		 * whether the plugin answers AJAX at all depend on when is_admin()
		 * happens to be decided, which is exactly the kind of thing that works
		 * everywhere except the one place it does not. The hooks the class
		 * registers simply never fire on a front end request.
		 */
		require_once WP_SWEEP_DIR . 'includes/class-wp-sweep-admin.php';

		WP_Sweep_Admin::init();
	}

	/**
	 * Register the WP-CLI command.
	 *
	 * @return void
	 */
	public function init() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once WP_SWEEP_DIR . 'includes/class-wp-sweep-command.php';

			WP_CLI::add_command( 'sweep', 'WP_Sweep_Command' );
		}
	}

	/**
	 * The capability required for a given context.
	 *
	 * @param string $context What the capability is being checked for.
	 * @return string The required capability.
	 */
	public static function capability( $context = 'sweep' ) {
		/**
		 * Filters the capability required to count, inspect or run a sweep.
		 *
		 * @since 2.0.0
		 *
		 * @param string $capability The required capability.
		 * @param string $context    What the capability is being checked for.
		 */
		return apply_filters( 'wp_sweep_capability', self::CAPABILITY, $context );
	}

	/**
	 * Run one of this plugin's own statements against the database.
	 *
	 * Every database call the plugin makes goes through here, which is the one
	 * place to look when you want to know what it touches.
	 *
	 * WP-Sweep exists to find rows that no WordPress API can see -- meta whose
	 * object was deleted, terms attached to nothing, relationships pointing at
	 * posts that are gone. There is no cached equivalent of any of it, and a
	 * cached count would be wrong the moment anything else on the site wrote,
	 * which is why phpcs.xml excuses WordPress.DB.DirectDatabaseQuery inside
	 * includes/ across the whole collection.
	 *
	 * Values are bound by the caller, which is where the literal SQL lives, so
	 * $sql arrives here already through $wpdb->prepare() whenever it carries
	 * anything a filter or a request could have influenced. That half is not
	 * excused anywhere: interpolating into SQL stays an error.
	 *
	 * @param string $method One of get_var, get_col, get_results or query.
	 * @param string $sql    The statement to run.
	 * @return mixed Whatever $wpdb's method returns.
	 */
	private function db( $method, $sql ) {
		global $wpdb;

		return $wpdb->$method( $sql );
	}

	/**
	 * Every sweep this plugin knows how to run.
	 *
	 * The single list the REST API, the WP-CLI command, the AJAX handlers and
	 * the admin screen all work from. Keeping one copy is what stops a name
	 * being added to the switch statements but missed in an allow list, where
	 * it is silently unavailable rather than an error.
	 *
	 * The order matters: posts are deleted before the sweeps that hunt for the
	 * meta that deleting them just orphaned.
	 *
	 * @return array Sweep descriptors keyed by sweep name, each carrying a
	 *               translated label, the table type its percentage is measured
	 *               against, and the group it is shown under.
	 */
	public function get_sweeps() {
		return array(
			'revisions'                 => array(
				'label'       => __( 'Revisions', 'wp-sweep' ),
				'description' => __( 'Older copies of a post kept every time you saved it. The post itself is untouched.', 'wp-sweep' ),
				'type'        => 'posts',
				'group'       => 'posts',
			),
			'auto_drafts'               => array(
				'label'       => __( 'Auto Drafts', 'wp-sweep' ),
				'description' => __( 'Blank posts WordPress created when you opened the editor and never published.', 'wp-sweep' ),
				'type'        => 'posts',
				'group'       => 'posts',
			),
			'deleted_posts'             => array(
				'label'       => __( 'Deleted Posts', 'wp-sweep' ),
				'description' => __( 'Posts and pages already in the Trash. Emptying the Trash by hand does the same thing.', 'wp-sweep' ),
				'type'        => 'posts',
				'group'       => 'posts',
			),
			'unapproved_comments'       => array(
				'label'       => __( 'Unapproved Comments', 'wp-sweep' ),
				'description' => __( 'Comments still waiting in the moderation queue. Read them before you sweep.', 'wp-sweep' ),
				'type'        => 'comments',
				'group'       => 'comments',
			),
			'spam_comments'             => array(
				'label'       => __( 'Spammed Comments', 'wp-sweep' ),
				'description' => __( 'Comments already marked as spam.', 'wp-sweep' ),
				'type'        => 'comments',
				'group'       => 'comments',
			),
			'deleted_comments'          => array(
				'label'       => __( 'Deleted Comments', 'wp-sweep' ),
				'description' => __( 'Comments already in the Trash.', 'wp-sweep' ),
				'type'        => 'comments',
				'group'       => 'comments',
			),
			'transient_options'         => array(
				'label'       => __( 'Transient Options', 'wp-sweep' ),
				'description' => __( 'Expired cached values. WordPress and your plugins rebuild whatever they still need.', 'wp-sweep' ),
				'type'        => 'options',
				'group'       => 'options',
			),
			'orphan_postmeta'           => array(
				'label'       => __( 'Orphaned Post Meta', 'wp-sweep' ),
				'description' => __( 'Custom fields whose post no longer exists.', 'wp-sweep' ),
				'type'        => 'postmeta',
				'group'       => 'posts',
			),
			'orphan_commentmeta'        => array(
				'label'       => __( 'Orphaned Comment Meta', 'wp-sweep' ),
				'description' => __( 'Comment metadata whose comment no longer exists.', 'wp-sweep' ),
				'type'        => 'commentmeta',
				'group'       => 'comments',
			),
			'orphan_usermeta'           => array(
				'label'       => __( 'Orphaned User Meta', 'wp-sweep' ),
				'description' => __( 'User metadata whose user no longer exists.', 'wp-sweep' ),
				'type'        => 'usermeta',
				'group'       => 'users',
			),
			'orphan_termmeta'           => array(
				'label'       => __( 'Orphaned Term Meta', 'wp-sweep' ),
				'description' => __( 'Term metadata whose category or tag no longer exists.', 'wp-sweep' ),
				'type'        => 'termmeta',
				'group'       => 'terms',
			),
			'orphan_term_relationships' => array(
				'label'       => __( 'Orphaned Term Relationships', 'wp-sweep' ),
				'description' => __( 'Links between a category or tag and a post that is gone.', 'wp-sweep' ),
				'type'        => 'term_relationships',
				'group'       => 'terms',
			),
			'unused_terms'              => array(
				'label'       => __( 'Unused Terms', 'wp-sweep' ),
				'description' => __( 'Terms used only by drafts look unused, so check your drafts first.', 'wp-sweep' ),
				'type'        => 'terms',
				'group'       => 'terms',
			),
			'duplicated_postmeta'       => array(
				'label'       => __( 'Duplicated Post Meta', 'wp-sweep' ),
				'description' => __( 'Custom fields stored more than once on the same post. One copy is kept.', 'wp-sweep' ),
				'type'        => 'postmeta',
				'group'       => 'posts',
			),
			'duplicated_commentmeta'    => array(
				'label'       => __( 'Duplicated Comment Meta', 'wp-sweep' ),
				'description' => __( 'Comment metadata stored more than once on the same comment. One copy is kept.', 'wp-sweep' ),
				'type'        => 'commentmeta',
				'group'       => 'comments',
			),
			'duplicated_usermeta'       => array(
				'label'       => __( 'Duplicated User Meta', 'wp-sweep' ),
				'description' => __( 'User metadata stored more than once on the same user. One copy is kept.', 'wp-sweep' ),
				'type'        => 'usermeta',
				'group'       => 'users',
			),
			'duplicated_termmeta'       => array(
				'label'       => __( 'Duplicated Term Meta', 'wp-sweep' ),
				'description' => __( 'Term metadata stored more than once on the same term. One copy is kept.', 'wp-sweep' ),
				'type'        => 'termmeta',
				'group'       => 'terms',
			),
			'optimize_database'         => array(
				'label'       => __( 'Optimize Tables', 'wp-sweep' ),
				'description' => __( 'Reclaims space MySQL is still holding after deletions.', 'wp-sweep' ),
				'type'        => 'tables',
				'group'       => 'database',
			),
			'oembed_postmeta'           => array(
				'label'       => __( 'oEmbed Caches In Post Meta', 'wp-sweep' ),
				'description' => __( 'Cached copies of embedded tweets, videos and the like. They are fetched again when needed.', 'wp-sweep' ),
				'type'        => 'postmeta',
				'group'       => 'posts',
			),
		);
	}

	/**
	 * The names of every sweep, in the order they must run.
	 *
	 * @return array Sweep names.
	 */
	public function get_sweep_names() {
		return array_keys( $this->get_sweeps() );
	}

	/**
	 * Whether a sweep name came from the list above.
	 *
	 * @param string $name Sweep name.
	 * @return bool Whether the name is one this plugin implements.
	 */
	public function is_sweep_name_valid( $name ) {
		return in_array( $name, $this->get_sweep_names(), true );
	}

	/**
	 * The table shorthands total_count() understands.
	 *
	 * @return array Sweep types.
	 */
	public function get_sweep_types() {
		return array(
			'posts',
			'postmeta',
			'comments',
			'commentmeta',
			'users',
			'usermeta',
			'term_relationships',
			'term_taxonomy',
			'terms',
			'termmeta',
			'options',
			'tables',
		);
	}

	/**
	 * Whether a sweep type came from the list above.
	 *
	 * @param string $type Sweep type.
	 * @return bool Whether the type is one this plugin counts.
	 */
	public function is_sweep_type_valid( $type ) {
		return in_array( $type, $this->get_sweep_types(), true );
	}

	/**
	 * The groups the admin screen files the sweeps under.
	 *
	 * @return array Translated group labels keyed by group name.
	 */
	public function get_sweep_groups() {
		return array(
			'posts'    => __( 'Post Sweep', 'wp-sweep' ),
			'comments' => __( 'Comment Sweep', 'wp-sweep' ),
			'users'    => __( 'User Sweep', 'wp-sweep' ),
			'terms'    => __( 'Term Sweep', 'wp-sweep' ),
			'options'  => __( 'Option Sweep', 'wp-sweep' ),
			'database' => __( 'Database Sweep', 'wp-sweep' ),
		);
	}

	/**
	 * The dashicon shown beside a group.
	 *
	 * A dashicon rather than an SVG of our own: core already loads the font in
	 * wp-admin, so this ships no asset at all, inherits the admin colour scheme
	 * without a stylesheet, and cannot fall foul of §11's ban on raster images.
	 * All six exist in the WordPress 6.8 floor.
	 *
	 * Kept apart from get_sweep_groups() rather than folded into it. That method
	 * returns group => label and two tests read it with array_keys(); widening
	 * the value to an array would break every `as $group => $label` loop for the
	 * sake of one more field.
	 *
	 * The icon is decoration. Every caller prints it `aria-hidden` beside the
	 * label, never instead of it -- a screen reader gets the words, and so does
	 * anyone whose browser has not loaded the font.
	 *
	 * @param string $group Group name.
	 * @return string Dashicon class suffix, or an empty string for an unknown group.
	 */
	public function get_sweep_group_icon( $group ) {
		$icons = array(
			'posts'    => 'admin-post',
			'comments' => 'admin-comments',
			'users'    => 'admin-users',
			'terms'    => 'tag',
			'options'  => 'admin-settings',
			'database' => 'database',
		);

		return isset( $icons[ $group ] ) ? $icons[ $group ] : '';
	}

	/**
	 * How many items a details list may show.
	 *
	 * @return int Maximum number of sample items.
	 */
	public function limit_details() {
		/**
		 * Filters how many items a details list may show.
		 *
		 * A filter and not a setting on purpose: one field does not earn a
		 * screen, an option row and a sanitiser. See DEFAULT_LIMIT_DETAILS.
		 *
		 * @since 2.0.0
		 *
		 * @param int $limit Maximum number of sample items.
		 */
		return max( 1, (int) apply_filters( 'wp_sweep_limit_details', self::DEFAULT_LIMIT_DETAILS ) );
	}

	/**
	 * Count the total number of rows in the table a sweep is measured against.
	 *
	 * @param string $name Sweep type.
	 * @return int Number of rows in that table.
	 */
	public function total_count( $name ) {
		global $wpdb;

		$count = 0;

		switch ( $name ) {
			case 'posts':
				$count = $this->db( 'get_var', "SELECT COUNT(*) FROM $wpdb->posts" );
				break;
			case 'postmeta':
				$count = $this->db( 'get_var', "SELECT COUNT(*) FROM $wpdb->postmeta" );
				break;
			case 'comments':
				$count = $this->db( 'get_var', "SELECT COUNT(*) FROM $wpdb->comments" );
				break;
			case 'commentmeta':
				$count = $this->db( 'get_var', "SELECT COUNT(*) FROM $wpdb->commentmeta" );
				break;
			case 'users':
				$count = $this->db( 'get_var', "SELECT COUNT(*) FROM $wpdb->users" );
				break;
			case 'usermeta':
				$count = $this->db( 'get_var', "SELECT COUNT(*) FROM $wpdb->usermeta" );
				break;
			case 'term_relationships':
				$count = $this->db( 'get_var', "SELECT COUNT(*) FROM $wpdb->term_relationships" );
				break;
			case 'term_taxonomy':
				$count = $this->db( 'get_var', "SELECT COUNT(*) FROM $wpdb->term_taxonomy" );
				break;
			case 'terms':
				$count = $this->db( 'get_var', "SELECT COUNT(*) FROM $wpdb->terms" );
				break;
			case 'termmeta':
				$count = $this->db( 'get_var', "SELECT COUNT(*) FROM $wpdb->termmeta" );
				break;
			case 'options':
				$count = $this->db( 'get_var', "SELECT COUNT(*) FROM $wpdb->options" );
				break;
			case 'tables':
				$count = count( $this->tables() );
				break;
		}

		/**
		 * Filters the total number of rows a sweep's percentage is measured against.
		 *
		 * @since 1.0.4
		 *
		 * @param int    $count The row count.
		 * @param string $name  The sweep type.
		 */
		return apply_filters( 'wp_sweep_total_count', $count, $name );
	}

	/**
	 * Count the number of items belonging to a sweep.
	 *
	 * @param string $name Sweep name.
	 * @return int Number of items the sweep would remove.
	 */
	public function count( $name ) {
		global $wpdb;

		$count = 0;

		switch ( $name ) {
			case 'revisions':
				$count = $this->db( 'get_var', $wpdb->prepare( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = %s", 'revision' ) );
				break;
			case 'auto_drafts':
				$count = $this->db( 'get_var', $wpdb->prepare( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_status = %s", 'auto-draft' ) );
				break;
			case 'deleted_posts':
				$count = $this->db( 'get_var', $wpdb->prepare( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_status = %s", 'trash' ) );
				break;
			case 'unapproved_comments':
				$count = $this->db( 'get_var', $wpdb->prepare( "SELECT COUNT(comment_ID) FROM $wpdb->comments WHERE comment_approved = %s", '0' ) );
				break;
			case 'spam_comments':
				$count = $this->db( 'get_var', $wpdb->prepare( "SELECT COUNT(comment_ID) FROM $wpdb->comments WHERE comment_approved = %s", 'spam' ) );
				break;
			case 'deleted_comments':
				$count = $this->db( 'get_var', $wpdb->prepare( "SELECT COUNT(comment_ID) FROM $wpdb->comments WHERE ( comment_approved = %s OR comment_approved = %s )", 'trash', 'post-trashed' ) );
				break;
			case 'transient_options':
				$count = $this->db( 'get_var', $wpdb->prepare( "SELECT COUNT(option_id) FROM $wpdb->options WHERE option_name LIKE %s", '%\_transient\_%' ) );
				break;
			case 'orphan_postmeta':
			case 'orphan_commentmeta':
			case 'orphan_usermeta':
			case 'orphan_termmeta':
				$count = $this->count_protected_meta( $this->orphan_meta_counts( $name ), $name );
				break;
			case 'orphan_term_relationships':
				$excluded = $this->excluded_taxonomies_for_sql();
				$count    = $this->db(
					'get_var',
					$wpdb->prepare(
						"SELECT COUNT(tr.object_id) FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy NOT IN (" . implode( ', ', array_fill( 0, count( $excluded ), '%s' ) ) . ") AND tr.object_id NOT IN ( SELECT ID FROM $wpdb->posts )",
						$excluded
					)
				);
				break;
			case 'unused_terms':
				$count = count( $this->unused_terms() );
				break;
			case 'duplicated_postmeta':
			case 'duplicated_commentmeta':
			case 'duplicated_usermeta':
			case 'duplicated_termmeta':
				$count = array_sum( wp_list_pluck( $this->duplicated_meta( $name ), 'num' ) );
				break;
			case 'optimize_database':
				$count = count( $this->tables() );
				break;
			case 'oembed_postmeta':
				$count = $this->count_protected_meta( $this->oembed_meta_counts(), $name );
				break;
		}

		/**
		 * Filters the number of items a sweep would remove.
		 *
		 * @since 1.0.4
		 *
		 * @param int    $count The item count.
		 * @param string $name  The sweep name.
		 */
		return apply_filters( 'wp_sweep_count', $count, $name );
	}

	/**
	 * Return a sample of the items a sweep would remove.
	 *
	 * The sample is capped at the details limit and the protected meta keys are
	 * dropped from it afterwards, so a site that protects a very common key
	 * sees fewer entries here than count() reports. The list is a preview; the
	 * count and the sweep itself are exact.
	 *
	 * @param string $name Sweep name.
	 * @return array Details of the items belonging to the sweep.
	 */
	public function details( $name ) {
		global $wpdb;

		$details = array();
		$limit   = $this->limit_details();

		switch ( $name ) {
			case 'revisions':
				$details = $this->db( 'get_col', $wpdb->prepare( "SELECT post_title FROM $wpdb->posts WHERE post_type = %s LIMIT %d", 'revision', $limit ) );
				break;
			case 'auto_drafts':
				$details = $this->db( 'get_col', $wpdb->prepare( "SELECT post_title FROM $wpdb->posts WHERE post_status = %s LIMIT %d", 'auto-draft', $limit ) );
				break;
			case 'deleted_posts':
				$details = $this->db( 'get_col', $wpdb->prepare( "SELECT post_title FROM $wpdb->posts WHERE post_status = %s LIMIT %d", 'trash', $limit ) );
				break;
			case 'unapproved_comments':
				$details = $this->db( 'get_col', $wpdb->prepare( "SELECT comment_author FROM $wpdb->comments WHERE comment_approved = %s LIMIT %d", '0', $limit ) );
				break;
			case 'spam_comments':
				$details = $this->db( 'get_col', $wpdb->prepare( "SELECT comment_author FROM $wpdb->comments WHERE comment_approved = %s LIMIT %d", 'spam', $limit ) );
				break;
			case 'deleted_comments':
				$details = $this->db( 'get_col', $wpdb->prepare( "SELECT comment_author FROM $wpdb->comments WHERE ( comment_approved = %s OR comment_approved = %s ) LIMIT %d", 'trash', 'post-trashed', $limit ) );
				break;
			case 'transient_options':
				$details = $this->db( 'get_col', $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s LIMIT %d", '%\_transient\_%', $limit ) );
				break;
			case 'orphan_postmeta':
			case 'orphan_commentmeta':
			case 'orphan_usermeta':
			case 'orphan_termmeta':
				$details = $this->drop_protected_meta( $this->orphan_meta_keys( $name, $limit ), $name );
				break;
			case 'orphan_term_relationships':
				$excluded = $this->excluded_taxonomies_for_sql();
				$details  = $this->db(
					'get_col',
					$wpdb->prepare(
						"SELECT tt.taxonomy FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy NOT IN (" . implode( ', ', array_fill( 0, count( $excluded ), '%s' ) ) . ") AND tr.object_id NOT IN ( SELECT ID FROM $wpdb->posts ) LIMIT %d",
						array_merge( $excluded, array( $limit ) )
					)
				);
				break;
			case 'unused_terms':
				$details = array_slice( wp_list_pluck( $this->unused_terms(), 'name' ), 0, $limit );
				break;
			case 'duplicated_postmeta':
			case 'duplicated_commentmeta':
			case 'duplicated_usermeta':
			case 'duplicated_termmeta':
				$details = array_slice( wp_list_pluck( $this->duplicated_meta( $name ), 'meta_key' ), 0, $limit );
				break;
			case 'optimize_database':
				$details = $this->tables();
				break;
			case 'oembed_postmeta':
				$details = array_slice( wp_list_pluck( $this->drop_protected_meta( $this->oembed_meta_counts(), $name ), 'meta_key' ), 0, $limit );
				break;
		}

		/**
		 * Filters the sample list of items shown for a sweep.
		 *
		 * @since 1.0.3
		 *
		 * @param array  $details The sample items.
		 * @param string $name    The sweep name.
		 */
		return apply_filters( 'wp_sweep_details', $details, $name );
	}

	/**
	 * Do the sweeping.
	 *
	 * @param string $name Sweep name.
	 * @return string Translated result message, or an empty string when there
	 *                was nothing to remove.
	 */
	public function sweep( $name ) {
		global $wpdb;

		$message = '';

		switch ( $name ) {
			case 'revisions':
				$query = $this->db( 'get_col', $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s", 'revision' ) );
				if ( $query ) {
					foreach ( $query as $id ) {
						wp_delete_post_revision( (int) $id );
					}

					// translators: %s is the number of revisions.
					$message = sprintf( __( '%s Revisions Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'auto_drafts':
				$query = $this->db( 'get_col', $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status = %s", 'auto-draft' ) );
				if ( $query ) {
					foreach ( $query as $id ) {
						wp_delete_post( (int) $id, true );
					}

					// translators: %s is the number of auto drafts.
					$message = sprintf( __( '%s Auto Drafts Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'deleted_posts':
				$query = $this->db( 'get_col', $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status = %s", 'trash' ) );
				if ( $query ) {
					foreach ( $query as $id ) {
						wp_delete_post( (int) $id, true );
					}

					// translators: %s is the number of deleted posts.
					$message = sprintf( __( '%s Deleted Posts Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'unapproved_comments':
				$query = $this->db( 'get_col', $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_approved = %s", '0' ) );
				if ( $query ) {
					foreach ( $query as $id ) {
						wp_delete_comment( (int) $id, true );
					}

					// translators: %s is the number of unapproved comments.
					$message = sprintf( __( '%s Unapproved Comments Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'spam_comments':
				$query = $this->db( 'get_col', $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_approved = %s", 'spam' ) );
				if ( $query ) {
					foreach ( $query as $id ) {
						wp_delete_comment( (int) $id, true );
					}

					// translators: %s is the number of spam comments.
					$message = sprintf( __( '%s Spam Comments Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'deleted_comments':
				$query = $this->db( 'get_col', $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE ( comment_approved = %s OR comment_approved = %s )", 'trash', 'post-trashed' ) );
				if ( $query ) {
					foreach ( $query as $id ) {
						wp_delete_comment( (int) $id, true );
					}

					// translators: %s is the number of trashed comments.
					$message = sprintf( __( '%s Trash Comments Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'transient_options':
				$query = $this->db( 'get_col', $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s", '%\_transient\_%' ) );
				if ( $query ) {
					foreach ( $query as $option_name ) {
						if ( strpos( $option_name, '_site_transient_' ) !== false ) {
							delete_site_transient( str_replace( '_site_transient_', '', $option_name ) );
						} else {
							delete_transient( str_replace( '_transient_', '', $option_name ) );
						}
					}

					// translators: %s is the number of transient options.
					$message = sprintf( __( '%s Transient Options Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'orphan_postmeta':
				$query = $this->drop_protected_meta( $this->orphan_meta_rows( $name ), $name );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$this->delete_meta( 'post', (int) $meta->object_id, $meta->meta_key );
					}

					// translators: %s is the number of orphaned post meta rows.
					$message = sprintf( __( '%s Orphaned Post Meta Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'orphan_commentmeta':
				$query = $this->drop_protected_meta( $this->orphan_meta_rows( $name ), $name );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$this->delete_meta( 'comment', (int) $meta->object_id, $meta->meta_key );
					}

					// translators: %s is the number of orphaned comment meta rows.
					$message = sprintf( __( '%s Orphaned Comment Meta Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'orphan_usermeta':
				$query = $this->drop_protected_meta( $this->orphan_meta_rows( $name ), $name );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$this->delete_meta( 'user', (int) $meta->object_id, $meta->meta_key );
					}

					// translators: %s is the number of orphaned user meta rows.
					$message = sprintf( __( '%s Orphaned User Meta Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'orphan_termmeta':
				$query = $this->drop_protected_meta( $this->orphan_meta_rows( $name ), $name );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$this->delete_meta( 'term', (int) $meta->object_id, $meta->meta_key );
					}

					// translators: %s is the number of orphaned term meta rows.
					$message = sprintf( __( '%s Orphaned Term Meta Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'orphan_term_relationships':
				$excluded = $this->excluded_taxonomies_for_sql();
				$query    = $this->db(
					'get_results',
					$wpdb->prepare(
						"SELECT tr.object_id, tr.term_taxonomy_id, tt.term_id, tt.taxonomy FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy NOT IN (" . implode( ', ', array_fill( 0, count( $excluded ), '%s' ) ) . ") AND tr.object_id NOT IN ( SELECT ID FROM $wpdb->posts )",
						$excluded
					)
				);
				if ( $query ) {
					foreach ( $query as $tax ) {
						if ( true !== wp_remove_object_terms( (int) $tax->object_id, (int) $tax->term_id, $tax->taxonomy ) ) {
							$this->db( 'query', $wpdb->prepare( "DELETE FROM $wpdb->term_relationships WHERE object_id = %d AND term_taxonomy_id = %d", (int) $tax->object_id, (int) $tax->term_taxonomy_id ) );
						}
					}

					// translators: %s is the number of orphaned term relationships.
					$message = sprintf( __( '%s Orphaned Term Relationships Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'unused_terms':
				$query = $this->unused_terms();
				if ( $query ) {
					$orphaned = array();
					foreach ( $query as $tax ) {
						if ( taxonomy_exists( $tax->taxonomy ) ) {
							wp_delete_term( (int) $tax->term_id, $tax->taxonomy );
						} else {
							$this->db( 'query', $wpdb->prepare( "DELETE FROM $wpdb->term_taxonomy WHERE term_taxonomy_id = %d", (int) $tax->term_taxonomy_id ) );
							$orphaned[] = (int) $tax->term_id;
						}
					}

					// A term row left behind by an invalid taxonomy has nothing
					// pointing at it any more and wp_delete_term() never saw it.
					// Only the terms this pass just orphaned: an unqualified
					// NOT IN deletes every stray wp_terms row on the site,
					// which is neither what was counted nor what was ticked.
					$orphaned = array_values( array_unique( $orphaned ) );
					if ( $orphaned ) {
						$this->db(
							'query',
							$wpdb->prepare(
								"DELETE FROM $wpdb->terms WHERE term_id IN (" . implode( ', ', array_fill( 0, count( $orphaned ), '%d' ) ) . ") AND term_id NOT IN ( SELECT term_id FROM $wpdb->term_taxonomy )",
								$orphaned
							)
						);
					}

					// translators: %s is the number of unused terms.
					$message = sprintf( __( '%s Unused Terms Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'duplicated_postmeta':
			case 'duplicated_commentmeta':
			case 'duplicated_usermeta':
			case 'duplicated_termmeta':
				$query = $this->duplicated_meta( $name );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$this->delete_duplicate_meta( $name, $meta );
					}

					$message = $this->duplicated_meta_message( $name, count( $query ) );
				}
				break;
			case 'optimize_database':
				$query = $this->tables();
				if ( $query ) {
					$this->db( 'query', $wpdb->prepare( 'OPTIMIZE TABLE ' . implode( ', ', array_fill( 0, count( $query ), '%i' ) ), $query ) );

					// translators: %s is the number of database tables.
					$message = sprintf( __( '%s Tables Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'oembed_postmeta':
				$query = $this->drop_protected_meta(
					$this->db( 'get_results', $wpdb->prepare( "SELECT post_id AS object_id, meta_key FROM $wpdb->postmeta WHERE meta_key LIKE %s", self::OEMBED_LIKE ) ),
					$name
				);
				if ( $query ) {
					foreach ( $query as $meta ) {
						$this->delete_meta( 'post', (int) $meta->object_id, $meta->meta_key );
					}

					// translators: %s is the number of oEmbed caches.
					$message = sprintf( __( '%s oEmbed Caches In Post Meta Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
		}

		/**
		 * Filters the message returned after a sweep has run.
		 *
		 * @since 1.0.4
		 *
		 * @param string $message The result message.
		 * @param string $name    The sweep name.
		 */
		return apply_filters( 'wp_sweep_sweep', $message, $name );
	}

	/**
	 * Format a number as a percentage, taking care of division by zero.
	 *
	 * Props @barisunver https://github.com/barisunver
	 *
	 * @param int $current Current number.
	 * @param int $total   Total number.
	 * @return string The number as a percentage.
	 */
	public function format_percentage( $current, $total ) {
		return ( $total > 0 ? round( ( $current / $total ) * 100, 2 ) : 0 ) . '%';
	}

	/**
	 * Every table in the database.
	 *
	 * @return array Table names.
	 */
	private function tables() {
		global $wpdb;

		/*
		 * Scoped to this install's prefix. A bare SHOW TABLES is every table in
		 * the *schema*, and sharing one database between several WordPress
		 * installs -- or between WordPress and something else entirely -- is an
		 * ordinary hosting arrangement rather than an exotic one. So the details
		 * route published every co-tenant's table names and prefixes to anyone
		 * who could reach it, and running the sweep issued OPTIMIZE TABLE
		 * against installs the administrator here does not administer: a full
		 * rebuild on InnoDB, a write lock on MyISAM.
		 *
		 * esc_like() because a prefix may legitimately contain an underscore --
		 * the default one does -- and an unescaped underscore is a single
		 * character wildcard, which is how this would have gone on matching a
		 * neighbour's tables.
		 */
		$like = $wpdb->esc_like( $wpdb->prefix ) . '%';

		$tables = (array) $this->db( 'get_col', $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );

		/**
		 * Filters the tables the optimize sweep operates on.
		 *
		 * The default is this install's own tables. A site with tables outside
		 * its prefix that it does want optimised adds them here -- and takes
		 * responsibility for their being its own.
		 *
		 * @since 1.2.0
		 *
		 * @param array  $tables Table names.
		 * @param string $prefix This install's table prefix.
		 */
		return (array) apply_filters( 'wp_sweep_optimize_tables', $tables, $wpdb->prefix );
	}

	/**
	 * Delete one meta row, reaching past the API when it has to.
	 *
	 * The WordPress meta functions refuse to act on object ID 0, and orphaned
	 * meta keyed to 0 is exactly the kind this plugin exists to remove, so that
	 * one case is deleted with SQL instead.
	 *
	 * @param string $type      One of post, comment, user or term.
	 * @param int    $object_id The object the meta claims to belong to.
	 * @param string $meta_key  Meta key.
	 * @return void
	 */
	private function delete_meta( $type, $object_id, $meta_key ) {
		global $wpdb;

		if ( 0 !== $object_id ) {
			$callbacks = array(
				'post'    => 'delete_post_meta',
				'comment' => 'delete_comment_meta',
				'user'    => 'delete_user_meta',
				'term'    => 'delete_term_meta',
			);

			call_user_func( $callbacks[ $type ], $object_id, $meta_key );

			return;
		}

		switch ( $type ) {
			case 'post':
				$this->db( 'query', $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s", $object_id, $meta_key ) );
				break;
			case 'comment':
				$this->db( 'query', $wpdb->prepare( "DELETE FROM $wpdb->commentmeta WHERE comment_id = %d AND meta_key = %s", $object_id, $meta_key ) );
				break;
			case 'user':
				$this->db( 'query', $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE user_id = %d AND meta_key = %s", $object_id, $meta_key ) );
				break;
			case 'term':
				$this->db( 'query', $wpdb->prepare( "DELETE FROM $wpdb->termmeta WHERE term_id = %d AND meta_key = %s", $object_id, $meta_key ) );
				break;
		}
	}

	/**
	 * The oEmbed cache keys, with how many rows each accounts for.
	 *
	 * Shaped like orphan_meta_counts() so the same protection helpers work on
	 * it. This sweep was the one meta sweep that never consulted
	 * drop_protected_meta(), so a key a site had put on
	 * wp_sweep_postmeta_whitelist was deleted anyway -- the whitelist was read
	 * for it, since the name matches the postmeta test, and then never used.
	 *
	 * @since 1.2.0
	 *
	 * @return array Rows carrying a meta_key and a num.
	 */
	private function oembed_meta_counts() {
		global $wpdb;

		return (array) $this->db(
			'get_results',
			$wpdb->prepare(
				"SELECT meta_key, COUNT(meta_id) AS num FROM $wpdb->postmeta WHERE meta_key LIKE %s GROUP BY meta_key",
				self::OEMBED_LIKE
			)
		);
	}

	/**
	 * How many orphaned meta rows there are, broken down by meta key.
	 *
	 * Counting per key rather than in total is what lets the protected keys be
	 * dropped in PHP, where the wildcard matching already lives, instead of
	 * being turned into a variable-length SQL clause.
	 *
	 * @param string $name Sweep name.
	 * @return array Rows carrying a meta_key and a num.
	 */
	private function orphan_meta_counts( $name ) {
		global $wpdb;

		switch ( $name ) {
			case 'orphan_postmeta':
				return (array) $this->db( 'get_results', "SELECT meta_key, COUNT(meta_id) AS num FROM $wpdb->postmeta WHERE post_id NOT IN ( SELECT ID FROM $wpdb->posts ) GROUP BY meta_key" );
			case 'orphan_commentmeta':
				return (array) $this->db( 'get_results', "SELECT meta_key, COUNT(meta_id) AS num FROM $wpdb->commentmeta WHERE comment_id NOT IN ( SELECT comment_ID FROM $wpdb->comments ) GROUP BY meta_key" );
			case 'orphan_usermeta':
				return (array) $this->db( 'get_results', "SELECT meta_key, COUNT(umeta_id) AS num FROM $wpdb->usermeta WHERE user_id NOT IN ( SELECT ID FROM $wpdb->users ) GROUP BY meta_key" );
			case 'orphan_termmeta':
				return (array) $this->db( 'get_results', "SELECT meta_key, COUNT(meta_id) AS num FROM $wpdb->termmeta WHERE term_id NOT IN ( SELECT term_id FROM $wpdb->terms ) GROUP BY meta_key" );
		}

		return array();
	}

	/**
	 * A sample of the meta keys on the orphaned rows.
	 *
	 * @param string $name  Sweep name.
	 * @param int    $limit Maximum number of rows to read.
	 * @return array Meta keys.
	 */
	private function orphan_meta_keys( $name, $limit ) {
		global $wpdb;

		switch ( $name ) {
			case 'orphan_postmeta':
				return (array) $this->db( 'get_col', $wpdb->prepare( "SELECT meta_key FROM $wpdb->postmeta WHERE post_id NOT IN ( SELECT ID FROM $wpdb->posts ) LIMIT %d", $limit ) );
			case 'orphan_commentmeta':
				return (array) $this->db( 'get_col', $wpdb->prepare( "SELECT meta_key FROM $wpdb->commentmeta WHERE comment_id NOT IN ( SELECT comment_ID FROM $wpdb->comments ) LIMIT %d", $limit ) );
			case 'orphan_usermeta':
				return (array) $this->db( 'get_col', $wpdb->prepare( "SELECT meta_key FROM $wpdb->usermeta WHERE user_id NOT IN ( SELECT ID FROM $wpdb->users ) LIMIT %d", $limit ) );
			case 'orphan_termmeta':
				return (array) $this->db( 'get_col', $wpdb->prepare( "SELECT meta_key FROM $wpdb->termmeta WHERE term_id NOT IN ( SELECT term_id FROM $wpdb->terms ) LIMIT %d", $limit ) );
		}

		return array();
	}

	/**
	 * Every orphaned meta row, as an object id and a meta key.
	 *
	 * The id column is aliased to object_id so one loop can delete all four
	 * kinds without a switch inside it.
	 *
	 * @param string $name Sweep name.
	 * @return array Rows carrying an object_id and a meta_key.
	 */
	private function orphan_meta_rows( $name ) {
		global $wpdb;

		switch ( $name ) {
			case 'orphan_postmeta':
				return (array) $this->db( 'get_results', "SELECT post_id AS object_id, meta_key FROM $wpdb->postmeta WHERE post_id NOT IN ( SELECT ID FROM $wpdb->posts )" );
			case 'orphan_commentmeta':
				return (array) $this->db( 'get_results', "SELECT comment_id AS object_id, meta_key FROM $wpdb->commentmeta WHERE comment_id NOT IN ( SELECT comment_ID FROM $wpdb->comments )" );
			case 'orphan_usermeta':
				return (array) $this->db( 'get_results', "SELECT user_id AS object_id, meta_key FROM $wpdb->usermeta WHERE user_id NOT IN ( SELECT ID FROM $wpdb->users )" );
			case 'orphan_termmeta':
				return (array) $this->db( 'get_results', "SELECT term_id AS object_id, meta_key FROM $wpdb->termmeta WHERE term_id NOT IN ( SELECT term_id FROM $wpdb->terms )" );
		}

		return array();
	}

	/**
	 * Meta rows that appear more than once with the same key and value.
	 *
	 * @param string $name Sweep name.
	 * @return array Rows carrying ids, object_id, meta_key and num.
	 */
	private function duplicated_meta( $name ) {
		global $wpdb;

		switch ( $name ) {
			case 'duplicated_postmeta':
				$rows = $this->db( 'get_results', "SELECT GROUP_CONCAT(meta_id ORDER BY meta_id DESC) AS ids, post_id AS object_id, meta_key, COUNT(meta_id) AS num FROM $wpdb->postmeta GROUP BY post_id, meta_key, meta_value HAVING num > 1" );
				break;
			case 'duplicated_commentmeta':
				$rows = $this->db( 'get_results', "SELECT GROUP_CONCAT(meta_id ORDER BY meta_id DESC) AS ids, comment_id AS object_id, meta_key, COUNT(meta_id) AS num FROM $wpdb->commentmeta GROUP BY comment_id, meta_key, meta_value HAVING num > 1" );
				break;
			case 'duplicated_usermeta':
				$rows = $this->db( 'get_results', "SELECT GROUP_CONCAT(umeta_id ORDER BY umeta_id DESC) AS ids, user_id AS object_id, meta_key, COUNT(umeta_id) AS num FROM $wpdb->usermeta GROUP BY user_id, meta_key, meta_value HAVING num > 1" );
				break;
			case 'duplicated_termmeta':
				$rows = $this->db( 'get_results', "SELECT GROUP_CONCAT(meta_id ORDER BY meta_id DESC) AS ids, term_id AS object_id, meta_key, COUNT(meta_id) AS num FROM $wpdb->termmeta GROUP BY term_id, meta_key, meta_value HAVING num > 1" );
				break;
			default:
				return array();
		}

		return $this->drop_protected_meta( (array) $rows, $name );
	}

	/**
	 * Delete every copy of a duplicated meta row but the oldest.
	 *
	 * @param string $name Sweep name.
	 * @param object $meta One row from duplicated_meta().
	 * @return void
	 */
	private function delete_duplicate_meta( $name, $meta ) {
		global $wpdb;

		$ids = array_map( 'intval', explode( ',', $meta->ids ) );

		// GROUP_CONCAT ordered them newest first, so popping the last one keeps
		// the row that was there originally.
		array_pop( $ids );

		if ( empty( $ids ) ) {
			return;
		}

		$args = array_merge( $ids, array( (int) $meta->object_id ) );

		switch ( $name ) {
			case 'duplicated_postmeta':
				$this->db( 'query', $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_id IN (" . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ') AND post_id = %d', $args ) );
				break;
			case 'duplicated_commentmeta':
				$this->db( 'query', $wpdb->prepare( "DELETE FROM $wpdb->commentmeta WHERE meta_id IN (" . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ') AND comment_id = %d', $args ) );
				break;
			case 'duplicated_usermeta':
				$this->db( 'query', $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE umeta_id IN (" . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ') AND user_id = %d', $args ) );
				break;
			case 'duplicated_termmeta':
				$this->db( 'query', $wpdb->prepare( "DELETE FROM $wpdb->termmeta WHERE meta_id IN (" . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ') AND term_id = %d', $args ) );
				break;
		}
	}

	/**
	 * The message a duplicated meta sweep reports.
	 *
	 * @param string $name  Sweep name.
	 * @param int    $count Number of rows processed.
	 * @return string Translated message.
	 */
	private function duplicated_meta_message( $name, $count ) {
		$count = number_format_i18n( $count );

		switch ( $name ) {
			case 'duplicated_postmeta':
				// translators: %s is the number of duplicated post meta rows.
				return sprintf( __( '%s Duplicated Post Meta Processed', 'wp-sweep' ), $count );
			case 'duplicated_commentmeta':
				// translators: %s is the number of duplicated comment meta rows.
				return sprintf( __( '%s Duplicated Comment Meta Processed', 'wp-sweep' ), $count );
			case 'duplicated_usermeta':
				// translators: %s is the number of duplicated user meta rows.
				return sprintf( __( '%s Duplicated User Meta Processed', 'wp-sweep' ), $count );
			case 'duplicated_termmeta':
				// translators: %s is the number of duplicated term meta rows.
				return sprintf( __( '%s Duplicated Term Meta Processed', 'wp-sweep' ), $count );
		}

		return '';
	}

	/**
	 * The taxonomies the orphaned term relationships sweep leaves alone.
	 *
	 * The wp_sweep_excluded_taxonomies filter is public API, so a site is
	 * entitled to filter it down to nothing. NOT IN () is a syntax error rather
	 * than a clause that matches everything, and the empty count that produced
	 * was rendered as a blank cell. A taxonomy name can never be the empty
	 * string, so carrying one keeps the list valid and excludes nothing.
	 *
	 * The taxonomies of non-post objects are added to it, because the sweep's
	 * test for an orphan is "no row in wp_posts with this ID" — and for a
	 * taxonomy registered against users or comments, object_id is a user or a
	 * comment ID. Those numbers collide with post IDs by accident and nothing
	 * else, so every relationship whose user happened to outnumber the posts
	 * looked orphaned and was deleted. link_category is the case core ships and
	 * the reason the hardcoded exclusion above exists; this generalises it to
	 * whatever the site has registered.
	 *
	 * A taxonomy nothing has registered is still swept. Its object type is
	 * unknowable and a leftover row is exactly what the sweep is for.
	 *
	 * @return array Taxonomy names, never empty.
	 */
	private function excluded_taxonomies_for_sql() {
		$excluded = array_map( 'strval', (array) $this->get_excluded_taxonomies() );

		$excluded[] = '';

		return array_values( array_unique( array_merge( $excluded, $this->non_post_taxonomies() ) ) );
	}

	/**
	 * Registered taxonomies attached to something other than a post type.
	 *
	 * A taxonomy with no object type at all counts as one: nothing says its
	 * object IDs are posts either.
	 *
	 * @return array Taxonomy names.
	 */
	private function non_post_taxonomies() {
		$names = array();

		foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy ) {
			$objects = (array) $taxonomy->object_type;

			if ( ! $objects ) {
				$names[] = $taxonomy->name;
				continue;
			}

			foreach ( $objects as $object ) {
				if ( ! post_type_exists( $object ) ) {
					$names[] = $taxonomy->name;
					break;
				}
			}
		}

		return $names;
	}

	/**
	 * Terms attached to nothing, minus the ones that must be kept.
	 *
	 * A count of zero is not on its own the question. Core's own counter,
	 * _update_post_term_count(), counts posts that are published, so a term
	 * used only by drafts, pending posts, private posts or posts in the trash
	 * has a count of zero while being very much in use — and wp_delete_term()
	 * takes its relationships with it, so those posts come back from the trash
	 * uncategorised. Requiring the relationship table to be empty too is the
	 * literal reading of "attached to nothing".
	 *
	 * The exclusions are applied here rather than in SQL because the list is
	 * filterable and can legitimately be empty, and because the terms tables
	 * are small enough that reading them costs nothing.
	 *
	 * @return array Rows carrying a term_taxonomy_id, a term_id, a name and a taxonomy.
	 */
	private function unused_terms() {
		global $wpdb;

		$rows = (array) $this->db( 'get_results', "SELECT tt.term_taxonomy_id, t.term_id, t.name, tt.taxonomy FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.count = 0 AND NOT EXISTS ( SELECT 1 FROM $wpdb->term_relationships AS tr WHERE tr.term_taxonomy_id = tt.term_taxonomy_id )" );

		$excluded = array_flip( array_filter( array_map( 'intval', (array) $this->get_excluded_termids() ) ) );

		if ( empty( $excluded ) ) {
			return $rows;
		}

		return array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $excluded ) {
					return ! isset( $excluded[ (int) $row->term_id ] );
				}
			)
		);
	}

	/**
	 * Taxonomies kept out of the orphaned term relationships sweep.
	 *
	 * @return array Excluded taxonomy names.
	 */
	private function get_excluded_taxonomies() {
		$excluded_taxonomies = array( 'link_category' );

		/**
		 * Filters the taxonomies excluded from the orphaned term relationships sweep.
		 *
		 * @since 1.0.8
		 *
		 * @param array $excluded_taxonomies Taxonomy names.
		 */
		return apply_filters( 'wp_sweep_excluded_taxonomies', $excluded_taxonomies );
	}

	/**
	 * Term IDs kept out of the unused terms sweep.
	 *
	 * @return array Excluded term IDs.
	 */
	private function get_excluded_termids() {
		$excluded_termids = array_merge(
			$this->get_default_taxonomy_termids(),
			$this->get_parent_termids()
		);

		/**
		 * Filters the term IDs excluded from the unused terms sweep.
		 *
		 * @since 1.1.1
		 *
		 * @param array $excluded_termids Term IDs.
		 */
		return apply_filters( 'wp_sweep_excluded_termids', $excluded_termids );
	}

	/**
	 * The term each taxonomy nominates as its default.
	 *
	 * A `default_<taxonomy>` option is only worth protecting if it still names a
	 * term that exists in that taxonomy. WordPress ships `default_link_category`
	 * set to 2, pointing at the "Blogroll" term that installs have not had since
	 * the Links Manager was retired in 3.5 -- so before 2.0.0 this returned 2 on
	 * essentially every site, and whatever term happened to hold ID 2 was
	 * silently excluded from the unused terms sweep forever.
	 *
	 * term_exists() checks the taxonomy as well as the ID, so an option left
	 * pointing at a term that has since moved taxonomies no longer protects an
	 * unrelated one.
	 *
	 * @return array Default taxonomy term IDs.
	 */
	private function get_default_taxonomy_termids() {
		$default_term_ids = array();

		foreach ( array_keys( get_taxonomies() ) as $taxonomy ) {
			// Built first rather than inline, so a scan for the plugin's own
			// option rows does not read this core one as one of them.
			$option  = 'default_' . $taxonomy;
			$term_id = (int) get_option( $option );

			if ( $term_id > 0 && null !== term_exists( $term_id, $taxonomy ) ) {
				$default_term_ids[] = $term_id;
			}
		}

		return $default_term_ids;
	}

	/**
	 * Terms that are the parent of another term.
	 *
	 * @return array Parent term IDs.
	 */
	private function get_parent_termids() {
		global $wpdb;

		return (array) $this->db( 'get_col', "SELECT tt.parent FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.parent > 0" );
	}

	/**
	 * The meta keys a site has asked never to be deleted.
	 *
	 * @param string $name Sweep name.
	 * @return array Meta key patterns, `*` meaning any run of characters.
	 */
	private function get_protected_meta_keys( $name ) {
		if ( strpos( $name, 'postmeta' ) !== false ) {
			/**
			 * Filters the post meta keys WP-Sweep must never delete.
			 *
			 * @since 1.2.0
			 *
			 * @param array $meta_keys Meta keys, `*` matching any run of characters.
			 */
			return (array) apply_filters( 'wp_sweep_postmeta_whitelist', array() );
		}

		if ( strpos( $name, 'commentmeta' ) !== false ) {
			/**
			 * Filters the comment meta keys WP-Sweep must never delete.
			 *
			 * @since 1.2.0
			 *
			 * @param array $meta_keys Meta keys, `*` matching any run of characters.
			 */
			return (array) apply_filters( 'wp_sweep_commentmeta_whitelist', array() );
		}

		if ( strpos( $name, 'usermeta' ) !== false ) {
			/**
			 * Filters the user meta keys WP-Sweep must never delete.
			 *
			 * @since 1.2.0
			 *
			 * @param array $meta_keys Meta keys, `*` matching any run of characters.
			 */
			return (array) apply_filters( 'wp_sweep_usermeta_whitelist', array() );
		}

		if ( strpos( $name, 'termmeta' ) !== false ) {
			/**
			 * Filters the term meta keys WP-Sweep must never delete.
			 *
			 * @since 1.2.0
			 *
			 * @param array $meta_keys Meta keys, `*` matching any run of characters.
			 */
			return (array) apply_filters( 'wp_sweep_termmeta_whitelist', array() );
		}

		return array();
	}

	/**
	 * Whether a meta key is protected from this sweep.
	 *
	 * Matching is done in PHP rather than with SQL LIKE, which treated an
	 * underscore in a pattern as a single-character wildcard. Meta keys are
	 * full of underscores, so `_my_key` protected a great deal more than it
	 * looked like it did.
	 *
	 * @param string $meta_key Meta key.
	 * @param array  $patterns Protected patterns.
	 * @return bool Whether the key must be kept.
	 */
	private function is_meta_key_protected( $meta_key, $patterns ) {
		foreach ( $patterns as $pattern ) {
			$pattern = (string) $pattern;

			if ( strpos( $pattern, '*' ) === false ) {
				if ( $meta_key === $pattern ) {
					return true;
				}

				continue;
			}

			$regex = '/^' . str_replace( '\*', '.*', preg_quote( $pattern, '/' ) ) . '$/';

			if ( preg_match( $regex, $meta_key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Drop the protected keys from a list of rows or meta keys.
	 *
	 * Accepts either plain meta keys or rows carrying a meta_key property, so
	 * count(), details() and sweep() all filter the same way.
	 *
	 * @param array  $items Meta keys, or rows with a meta_key property.
	 * @param string $name  Sweep name.
	 * @return array The items that may be deleted.
	 */
	private function drop_protected_meta( $items, $name ) {
		$protected = $this->get_protected_meta_keys( $name );

		if ( empty( $protected ) ) {
			return $items;
		}

		$kept = array();

		foreach ( $items as $item ) {
			$meta_key = is_object( $item ) ? $item->meta_key : $item;

			if ( ! $this->is_meta_key_protected( (string) $meta_key, $protected ) ) {
				$kept[] = $item;
			}
		}

		return $kept;
	}

	/**
	 * Sum a per-key breakdown, skipping the keys a site has protected.
	 *
	 * @param array  $rows Rows carrying a meta_key and a num.
	 * @param string $name Sweep name.
	 * @return int The number of rows that may be deleted.
	 */
	private function count_protected_meta( $rows, $name ) {
		$count = 0;

		foreach ( $this->drop_protected_meta( $rows, $name ) as $row ) {
			$count += (int) $row->num;
		}

		return $count;
	}
}
