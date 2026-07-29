<?php
/**
 * The Sweep screen.
 *
 * @package WP-Sweep
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the admin menu and renders the screen every sweep is run from.
 *
 * Until 2.0.0 this was includes/admin.php, a template required at global scope
 * that reached for globals it never declared and repeated the same eleven
 * lines of markup nineteen times. The rows are now generated from
 * WP_Sweep::get_sweeps(), so adding a sweep is one entry in one list.
 */
class WP_Sweep_Admin {

	/**
	 * The menu and page slug.
	 *
	 * Until 2.0.0 the screen was registered with 'wp-sweep/admin.php' -- the
	 * legacy "plugin file as menu slug" form. That put the installation
	 * directory name into the page URL and into the hook suffix handed back to
	 * admin_enqueue_scripts, so both broke for anyone who installed the plugin
	 * under a different directory name.
	 *
	 * @var string
	 */
	const PAGE = 'wp-sweep';

	/**
	 * The hook suffix WordPress handed back when the menu was registered.
	 *
	 * Recorded rather than assumed. get_plugin_page_hookname() derives the
	 * prefix from $admin_page_hooks, so the suffix is 'toplevel_page_wp-sweep'
	 * on a real admin request but something else anywhere the admin menu has
	 * not been built. Comparing against a hardcoded string means the script
	 * silently fails to load in the cases that do not match, and the screen
	 * renders with dead buttons.
	 *
	 * @var string
	 */
	private static $hook_suffix = '';

	/**
	 * The details a row action asked for, keyed by sweep name.
	 *
	 * @var array
	 */
	private static $details = array();

	/**
	 * Hook the screen into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
		add_action( 'wp_ajax_sweep', array( __CLASS__, 'ajax_sweep' ) );
		add_action( 'wp_ajax_sweep_details', array( __CLASS__, 'ajax_sweep_details' ) );
	}

	/**
	 * The list table, built once the screen is being rendered.
	 *
	 * @return WP_Sweep_List_Table
	 */
	private static function list_table() {
		require_once WP_SWEEP_DIR . 'includes/class-wp-sweep-list-table.php';

		return new WP_Sweep_List_Table();
	}

	/**
	 * Register the menu.
	 *
	 * @return void
	 */
	public static function admin_menu() {
		self::$hook_suffix = add_menu_page(
			_x( 'Sweep', 'Page title', 'wp-sweep' ),
			_x( 'Sweep', 'Menu title', 'wp-sweep' ),
			WP_Sweep::capability( 'sweep' ),
			self::PAGE,
			array( __CLASS__, 'render_page' ),
			'dashicons-editor-removeformatting'
		);

		// The data screen first, Settings last.
		add_submenu_page(
			self::PAGE,
			_x( 'Sweep', 'Page title', 'wp-sweep' ),
			_x( 'Sweep', 'Menu title', 'wp-sweep' ),
			WP_Sweep::capability( 'sweep' ),
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);

		add_submenu_page(
			self::PAGE,
			__( 'Sweep Settings', 'wp-sweep' ),
			__( 'Settings', 'wp-sweep' ),
			WP_Sweep_Settings::capability( 'settings' ),
			WP_Sweep_Settings::PAGE,
			array( 'WP_Sweep_Settings', 'render_page' )
		);
	}

	/**
	 * The hook suffix WordPress gave the Sweep screen.
	 *
	 * @return string Hook suffix, or an empty string before admin_menu runs.
	 */
	public static function get_hook_suffix() {
		return self::$hook_suffix;
	}

	/**
	 * Load the screen's script, and only on the screen that needs it.
	 *
	 * @param string $hook Hook suffix of the screen being rendered.
	 * @return void
	 */
	public static function admin_enqueue_scripts( $hook ) {
		if ( '' === self::$hook_suffix || self::$hook_suffix !== $hook ) {
			return;
		}

		/*
		 * One unminified file, no dependencies. There is no build step in this
		 * plugin, so a hand-minified twin drifts out of sync with the source it
		 * is supposed to mirror -- and under a kilobyte gzipped the saving was
		 * noise.
		 *
		 * The URL comes from WP_SWEEP_URL, which is derived from the main file.
		 * Building it from the literal 'wp-sweep/js/...' meant the script 404ed
		 * for anyone who installed the plugin under a different directory name.
		 */
		wp_enqueue_script( 'wp-sweep', WP_SWEEP_URL . 'js/wp-sweep.js', array(), WP_SWEEP_VERSION, true );

		wp_localize_script(
			'wp-sweep',
			'wpSweepL10n',
			array(
				'text_close_warning' => __( 'Sweeping is in progress. If you leave now, the process won\'t be completed.', 'wp-sweep' ),
				'text_sweep'         => __( 'Sweep', 'wp-sweep' ),
				'text_sweep_all'     => __( 'Sweep All', 'wp-sweep' ),
				'text_sweeping'      => __( 'Sweeping...', 'wp-sweep' ),
				'text_na'            => __( 'N/A', 'wp-sweep' ),
			)
		);
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( WP_Sweep::capability( 'sweep' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to sweep this site.', 'wp-sweep' ) );
		}

		$sweep = WP_Sweep::get_instance();

		self::handle_request();

		$table = self::list_table();
		$table->prepare_items();

		?>
		<div class="wrap">
			<h1><?php echo esc_html_x( 'Sweep', 'Page title', 'wp-sweep' ); ?></h1>

			<?php settings_errors( 'wp_sweep' ); ?>

			<div class="notice notice-warning">
				<p>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: %s is the URL of the WP-DBManager plugin. */
							__( 'Before you do any sweep, please <a href="%s">backup your database</a> first, because any sweep done is irreversible.', 'wp-sweep' ),
							'https://wordpress.org/plugins/wp-dbmanager/'
						)
					);
					?>
				</p>
			</div>

			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s is the maximum number of sample items. */
						__( 'For performance reasons, only %s items are listed when you ask for details.', 'wp-sweep' ),
						number_format_i18n( $sweep->limit_details() )
					)
				);
				?>
			</p>

			<?php self::render_totals(); ?>

			<div class="sweep-message"></div>

			<?php $table->views(); ?>

			<form method="post" action="<?php echo esc_url( self::page_url() ); ?>">
				<?php
				wp_nonce_field( 'wp_sweep_bulk' );
				$table->display();
				?>
			</form>

			<p>
				<button type="button" class="button button-primary btn-sweep-all"><?php esc_html_e( 'Sweep All', 'wp-sweep' ); ?></button>
			</p>

			<?php self::fire_group_actions(); ?>
		</div>
		<?php
	}

	/**
	 * The screen's own URL, carrying the group currently being shown.
	 *
	 * @return string
	 */
	private static function page_url() {
		return add_query_arg(
			array(
				'page'  => self::PAGE,
				'group' => WP_Sweep_List_Table::current_group(),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Render the running totals each group is measured against.
	 *
	 * The spans carry the class the script writes updated totals back into
	 * after a sweep, so the numbers do not go stale without a reload.
	 *
	 * @return void
	 */
	private static function render_totals() {
		$sweep = WP_Sweep::get_instance();

		echo '<ul class="sweep-totals">';

		foreach ( self::totals() as $label => $types ) {
			$parts = array();

			foreach ( $types as $type => $type_label ) {
				$parts[] = sprintf(
					'<span class="sweep-count-type-%1$s">%2$s</span> %3$s',
					esc_attr( $type ),
					esc_html( number_format_i18n( $sweep->total_count( $type ) ) ),
					esc_html( $type_label )
				);
			}

			printf(
				'<li><strong>%1$s</strong> %2$s</li>',
				esc_html( $label ),
				wp_kses_post( implode( ', ', $parts ) )
			);
		}

		echo '</ul>';
	}

	/**
	 * The table types each group reports a total for.
	 *
	 * @return array Type label keyed by type, keyed by group label.
	 */
	private static function totals() {
		$groups = WP_Sweep::get_instance()->get_sweep_groups();

		return array(
			$groups['posts']    => array(
				'posts'    => __( 'Posts', 'wp-sweep' ),
				'postmeta' => __( 'Post Meta', 'wp-sweep' ),
			),
			$groups['comments'] => array(
				'comments'    => __( 'Comments', 'wp-sweep' ),
				'commentmeta' => __( 'Comment Meta', 'wp-sweep' ),
			),
			$groups['users']    => array(
				'users'    => __( 'Users', 'wp-sweep' ),
				'usermeta' => __( 'User Meta', 'wp-sweep' ),
			),
			$groups['terms']    => array(
				'terms'              => __( 'Terms', 'wp-sweep' ),
				'termmeta'           => __( 'Term Meta', 'wp-sweep' ),
				'term_taxonomy'      => __( 'Term Taxonomy', 'wp-sweep' ),
				'term_relationships' => __( 'Term Relationships', 'wp-sweep' ),
			),
			$groups['options']  => array(
				'options' => __( 'Options', 'wp-sweep' ),
			),
			$groups['database'] => array(
				'tables' => __( 'Tables', 'wp-sweep' ),
			),
		);
	}

	/**
	 * Fire the extension points other plugins hang their own rows off.
	 *
	 * @return void
	 */
	private static function fire_group_actions() {
		/**
		 * Fires below the sweep table, where the post sweeps used to end.
		 *
		 * @since 1.0.4
		 */
		do_action( 'wp_sweep_admin_post_sweep' );

		/**
		 * Fires below the sweep table, where the comment sweeps used to end.
		 *
		 * @since 1.0.4
		 */
		do_action( 'wp_sweep_admin_comment_sweep' );

		/**
		 * Fires below the sweep table, where the user sweeps used to end.
		 *
		 * @since 1.0.4
		 */
		do_action( 'wp_sweep_admin_user_sweep' );

		/**
		 * Fires below the sweep table, where the term sweeps used to end.
		 *
		 * @since 1.0.4
		 */
		do_action( 'wp_sweep_admin_term_sweep' );

		/**
		 * Fires below the sweep table, where the option sweeps used to end.
		 *
		 * @since 1.0.4
		 */
		do_action( 'wp_sweep_admin_option_sweep' );

		/**
		 * Fires below the sweep table, where the database sweeps used to end.
		 *
		 * @since 1.0.4
		 */
		do_action( 'wp_sweep_admin_database_sweep' );
	}

	/**
	 * Act on whatever the request asked for, before anything is rendered.
	 *
	 * Every one of these paths works with JavaScript turned off: the row
	 * actions are real nonced links and the bulk action is a real form post.
	 * The script intercepts the links so the screen does not reload nineteen
	 * times, but nothing depends on it being there.
	 *
	 * @return void
	 */
	private static function handle_request() {
		self::$details = array();

		self::handle_bulk_sweep();
		self::handle_single_sweep();
		self::handle_details();
	}

	/**
	 * Sweep everything that was checked.
	 *
	 * @return void
	 */
	private static function handle_bulk_sweep() {
		$table = self::list_table();

		if ( 'sweep' !== $table->current_action() ) {
			return;
		}

		check_admin_referer( 'wp_sweep_bulk' );

		$sweep    = WP_Sweep::get_instance();
		$posted   = isset( $_POST['sweep'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['sweep'] ) ) : array();
		$messages = array();

		// Filtered from the canonical list rather than taken as given, so the
		// sweeps run in the order they have to and an unknown name is dropped
		// rather than passed on.
		foreach ( array_intersect( $sweep->get_sweep_names(), $posted ) as $name ) {
			$message = $sweep->sweep( $name );

			if ( '' !== $message ) {
				$messages[] = $message;
			}
		}

		if ( empty( $messages ) ) {
			add_settings_error( 'wp_sweep', 'wp_sweep_nothing', __( 'Nothing was selected, so nothing was swept.', 'wp-sweep' ), 'warning' );

			return;
		}

		/*
		 * Escaped here, not by settings_errors(), which prints the message
		 * straight into the page. Every one of these comes back through the
		 * wp_sweep_sweep filter, which is public API and can return anything.
		 */
		add_settings_error( 'wp_sweep', 'wp_sweep_swept', esc_html( implode( ' ', $messages ) ), 'success' );
	}

	/**
	 * Run the one sweep a row action asked for.
	 *
	 * The name is validated against the plugin's own list before the referer is
	 * checked, so a crafted value never reaches sweep(). The resulting message
	 * is rendered by render_page(); before 2.0.0 it was assigned and then
	 * silently discarded, which meant this path deleted data and told the user
	 * nothing at all.
	 *
	 * @return void
	 */
	private static function handle_single_sweep() {
		$sweep = WP_Sweep::get_instance();
		$name  = self::requested( 'sweep' );

		if ( '' === $name || ! $sweep->is_sweep_name_valid( $name ) ) {
			return;
		}

		check_admin_referer( 'wp_sweep_' . $name );

		$message = $sweep->sweep( $name );

		if ( '' === $message ) {
			add_settings_error( 'wp_sweep', 'wp_sweep_nothing', __( 'There was nothing left to sweep.', 'wp-sweep' ), 'warning' );

			return;
		}

		// Escaped for the reason given in handle_bulk_sweep().
		add_settings_error( 'wp_sweep', 'wp_sweep_swept', esc_html( $message ), 'success' );
	}

	/**
	 * Collect the details a row action asked for.
	 *
	 * @return void
	 */
	private static function handle_details() {
		$sweep = WP_Sweep::get_instance();
		$name  = self::requested( 'sweep_details' );

		if ( '' === $name || ! $sweep->is_sweep_name_valid( $name ) ) {
			return;
		}

		check_admin_referer( 'wp_sweep_details_' . $name );

		self::$details = array( $name => (array) $sweep->details( $name ) );
	}

	/**
	 * The sweep name a given request parameter names, if any.
	 *
	 * The referer is checked by the caller the moment the name turns out to be
	 * one this plugin implements, which is why reading it here needs no nonce
	 * of its own.
	 *
	 * @param string $key Request parameter to read.
	 * @return string A sanitised sweep name, or an empty string.
	 */
	private static function requested( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only used to decide which nonce to check; check_admin_referer() runs before the value is acted on.
		$query = wp_unslash( $_GET );

		return isset( $query[ $key ] ) ? sanitize_key( $query[ $key ] ) : '';
	}

	/**
	 * The details a row action asked for, keyed by sweep name.
	 *
	 * @return array
	 */
	public static function requested_details() {
		return self::$details;
	}

	/**
	 * Return the details of a sweep over AJAX.
	 *
	 * @return void
	 */
	public static function ajax_sweep_details() {
		$sweep = WP_Sweep::get_instance();

		$name = isset( $_GET['sweep_name'] ) ? sanitize_key( wp_unslash( $_GET['sweep_name'] ) ) : '';

		// Permissions and the name are checked before the referer, so a caller
		// without the capability gets a JSON error rather than the nonce
		// failure screen.
		if ( ! current_user_can( WP_Sweep::capability( 'ajax' ) ) || ! $sweep->is_sweep_name_valid( $name ) ) {
			wp_send_json_error( array( 'error' => __( 'Invalid AJAX request.', 'wp-sweep' ) ) );
		}

		check_admin_referer( 'wp_sweep_details_' . $name );

		wp_send_json_success( $sweep->details( $name ) );
	}

	/**
	 * Run a sweep over AJAX and hand back the numbers the screen has to update.
	 *
	 * @return void
	 */
	public static function ajax_sweep() {
		$sweep = WP_Sweep::get_instance();

		$name = isset( $_GET['sweep_name'] ) ? sanitize_key( wp_unslash( $_GET['sweep_name'] ) ) : '';
		$type = isset( $_GET['sweep_type'] ) ? sanitize_key( wp_unslash( $_GET['sweep_type'] ) ) : '';

		if (
			! current_user_can( WP_Sweep::capability( 'ajax' ) )
			|| ! $sweep->is_sweep_name_valid( $name )
			|| ! $sweep->is_sweep_type_valid( $type )
		) {
			wp_send_json_error( array( 'error' => __( 'Invalid AJAX request.', 'wp-sweep' ) ) );
		}

		check_admin_referer( 'wp_sweep_' . $name );

		$message     = $sweep->sweep( $name );
		$count       = (int) $sweep->count( $name );
		$total_count = (int) $sweep->total_count( $type );

		wp_send_json_success(
			array(
				'sweep'      => $message,
				'count'      => $count,
				'total'      => $total_count,
				'percentage' => $sweep->format_percentage( $count, $total_count ),
				'stats'      => self::related_totals( $type ),
			)
		);
	}

	/**
	 * The totals that move when a sweep of a given type runs.
	 *
	 * Deleting a post takes its meta with it, so the postmeta total is stale
	 * the moment a post sweep finishes even though nothing swept postmeta.
	 *
	 * @param string $type Sweep type.
	 * @return array Row counts keyed by type.
	 */
	private static function related_totals( $type ) {
		$related = array(
			'posts'              => array( 'posts', 'postmeta' ),
			'postmeta'           => array( 'posts', 'postmeta' ),
			'comments'           => array( 'comments', 'commentmeta' ),
			'commentmeta'        => array( 'comments', 'commentmeta' ),
			'users'              => array( 'users', 'usermeta' ),
			'usermeta'           => array( 'users', 'usermeta' ),
			'term_relationships' => array( 'term_relationships', 'term_taxonomy', 'terms', 'termmeta' ),
			'term_taxonomy'      => array( 'term_relationships', 'term_taxonomy', 'terms', 'termmeta' ),
			'terms'              => array( 'term_relationships', 'term_taxonomy', 'terms', 'termmeta' ),
			'termmeta'           => array( 'term_relationships', 'term_taxonomy', 'terms', 'termmeta' ),
			'options'            => array( 'options' ),
			'tables'             => array( 'tables' ),
		);

		if ( ! isset( $related[ $type ] ) ) {
			return array();
		}

		$sweep  = WP_Sweep::get_instance();
		$totals = array();

		foreach ( $related[ $type ] as $related_type ) {
			$totals[ $related_type ] = (int) $sweep->total_count( $related_type );
		}

		return $totals;
	}
}
