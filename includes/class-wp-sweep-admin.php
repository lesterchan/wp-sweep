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
	 * The message the no-JavaScript sweep left behind, if any.
	 *
	 * @var string
	 */
	private static $message = '';

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
		$sweep = WP_Sweep::get_instance();

		self::maybe_sweep();

		?>
		<div class="wrap">
			<h1><?php echo esc_html_x( 'Sweep', 'Page title', 'wp-sweep' ); ?></h1>

			<?php if ( '' !== self::$message ) : ?>
				<div class="notice notice-success">
					<p><?php echo esc_html( self::$message ); ?></p>
				</div>
			<?php endif; ?>

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

			<?php self::render_table(); ?>

			<p>
				<button type="button" class="button button-primary btn-sweep-all"><?php esc_html_e( 'Sweep All', 'wp-sweep' ); ?></button>
			</p>

			<?php self::fire_group_actions(); ?>
		</div>
		<?php
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
	 * Render the list of sweeps.
	 *
	 * @return void
	 */
	private static function render_table() {
		$sweep  = WP_Sweep::get_instance();
		$groups = $sweep->get_sweep_groups();

		?>
		<table class="widefat striped table-sweep">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Sweep', 'wp-sweep' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Group', 'wp-sweep' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Count', 'wp-sweep' ); ?></th>
					<th scope="col"><?php esc_html_e( '% Of', 'wp-sweep' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Action', 'wp-sweep' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $sweep->get_sweeps() as $name => $args ) : ?>
					<?php $count = (int) $sweep->count( $name ); ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $args['label'] ); ?></strong>
							<p class="sweep-details" hidden></p>
						</td>
						<td><?php echo esc_html( $groups[ $args['group'] ] ); ?></td>
						<td><span class="sweep-count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span></td>
						<td><span class="sweep-percentage"><?php echo esc_html( $sweep->format_percentage( $count, $sweep->total_count( $args['type'] ) ) ); ?></span></td>
						<td>
							<?php if ( $count > 0 ) : ?>
								<?php self::render_buttons( $name, $args['type'] ); ?>
							<?php else : ?>
								<?php esc_html_e( 'N/A', 'wp-sweep' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the Sweep and Details buttons for one row.
	 *
	 * @param string $name Sweep name.
	 * @param string $type Sweep type.
	 * @return void
	 */
	private static function render_buttons( $name, $type ) {
		printf(
			'<button type="button" class="button button-primary btn-sweep" data-action="sweep" data-sweep_name="%1$s" data-sweep_type="%2$s" data-nonce="%3$s">%4$s</button> ',
			esc_attr( $name ),
			esc_attr( $type ),
			esc_attr( wp_create_nonce( 'wp_sweep_' . $name ) ),
			esc_html__( 'Sweep', 'wp-sweep' )
		);

		printf(
			'<button type="button" class="button btn-sweep-details" data-action="sweep_details" data-sweep_name="%1$s" data-sweep_type="%2$s" data-nonce="%3$s">%4$s</button>',
			esc_attr( $name ),
			esc_attr( $type ),
			esc_attr( wp_create_nonce( 'wp_sweep_details_' . $name ) ),
			esc_html__( 'Details', 'wp-sweep' )
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
	 * Run the sweep a request without JavaScript asked for.
	 *
	 * The name is validated against the plugin's own list before the referer is
	 * checked, so a crafted value never reaches sweep(). The resulting message
	 * is rendered by render_page(); before 2.0.0 it was assigned and then
	 * silently discarded, which meant this path deleted data and told the user
	 * nothing at all.
	 *
	 * @return void
	 */
	private static function maybe_sweep() {
		self::$message = '';

		$sweep = WP_Sweep::get_instance();

		$name = isset( $_GET['sweep'] ) ? sanitize_key( wp_unslash( $_GET['sweep'] ) ) : '';

		if ( '' === $name || ! $sweep->is_sweep_name_valid( $name ) ) {
			return;
		}

		if ( ! current_user_can( WP_Sweep::capability( 'sweep' ) ) ) {
			return;
		}

		check_admin_referer( 'wp_sweep_' . $name );

		self::$message = $sweep->sweep( $name );
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
