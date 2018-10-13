<?php
/**
 * WP-Sweep main
 *
 * @package wp-sweep
 */


/**
 * WP-Sweep class
 *
 * @since 1.0.0
 */
class WPSweep {
	/**
	 * Limit the number of items to show for sweep details
	 *
	 * @since 1.0.3
	 *
	 * @access public
	 * @var int
	 */
	public $limit_details = 500;

	private $admin_page_hook = '';

	/**
	 * Static instance
	 *
	 * @since 1.0.0
	 *
	 * @access private
	 * @var WPSweep $instance
	 */
	private static $instance;

	public $totals = array();

	private $total_dependency = array();
/*
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
*/

	/**
	 * Sweep type instances.
	 *
	 * @var WPSweep_Sweep_Type[]
	*/
	public $types = array();

	/**
	 * Sweep instances per type.
	 *
	 * @var WPSweep_Sweep[][]
	*/
	public $sweeps = array();

	/**
	 * Sweep instances in a flat array.
	 *
	 * @var WPSweep_Sweep[]
     */
	public $all_sweeps = array();

	/**
	 * Constructor method
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function __construct() {
		// Total value for sweeps without percentage
		$this->totals = array(
			'null' => false,
		);

		// Add Plugin Hooks.
		add_action( 'plugins_loaded', array( $this, 'add_hooks' ) );

		// Load Translation.
		load_plugin_textdomain( 'wp-sweep' );
	}

	/**
	 * Initializes the plugin object and returns its instance
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @return object The plugin object instance
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Adds all the plugin hooks
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @return void
	 */
	public function add_hooks() {
		// Actions.
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'wp_ajax_sweep_details', array( $this, 'ajax_sweep_details' ) );
		add_action( 'wp_ajax_sweep', array( $this, 'ajax_sweep' ) );
	}

	/**
	 * Init this plugin
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @return void
	 */
	public function init() {
		require __DIR__ . '/class-wpsweep-sweep-type.php';
		require __DIR__ . '/class-wpsweep-sweep.php';
		$this->register_default_sweep_types();
		$this->load_sweep_types();
		$this->register_default_sweeps();
		$this->load_sweeps();
		// Include class for WP CLI command.
		if ( defined( 'WP_CLI' ) ) {
			require __DIR__ . '/class-wpsweep-command.php';
			WP_CLI::add_command( 'sweep', new WPSweep_Command() );
		}
	}

	/**
	 * Register built-in sweep types.
	 */
	public function register_default_sweep_types() {
		require __DIR__ . '/class-wpsweep-sweep-type-comment.php';
		// Register it.
		add_filter( 'wp_sweep_register_type', function ( $classes ) {
			$classes[] = 'WPSweep_Sweep_Type_Comment';
			return $classes;
		} );
	}

	/**
	 * Instantiate all registered sweep types.
	 */
	public function load_sweep_types() {
		$type_classes = apply_filters( 'wp_sweep_register_type', array() );
		foreach ( $type_classes as $class ) {
			$type = new $class( self::get_instance() );
			$this->types[ $type::SLUG ] = $type;
			$this->total_dependency = array_merge( $this->total_dependency, $type->total_dependency );
		}
	}

	/**
	 * Register built-in sweeps.
	 */
	public function register_default_sweeps() {
		require __DIR__ . '/class-wpsweep-sweep-unapproved-comments.php';
		// Register to its type.
		add_filter( 'wp_sweep_register_comment', function ( $classes ) {
			$classes[] = 'WPSweep_Sweep_Unapproved_Comments';
			return $classes;
		} );
	}

	/**
	 * Instantiate all registered sweeps.
	 */
	public function load_sweeps() {
		foreach ( $this->types as $sweep_type => $sweep_type_instance ) {
			$classes = apply_filters( "wp_sweep_register_{$sweep_type}", array() );
			foreach ( $classes as $class ) {
				$sweep = new $class( self::get_instance() );
				$this->sweeps[ $sweep_type ][ $sweep::SLUG ] = $sweep;
				$this->all_sweeps[ $sweep::SLUG ] = $sweep;
			}
		}
	}

	/**
	 * Enqueue JS/CSS files used for admin
	 *
	 * @since 1.0.3
	 *
	 * @access public
	 * @param string $hook Page hook.
	 * @return void
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( $this->admin_page_hook !== $hook ) {
			return;
		}

		$minify = '.min';
$minify = '';
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$minify = '';
		}
		wp_enqueue_script( 'wp-sweep', plugins_url( "wp-sweep/js/wp-sweep${$minify}.js" ), array( 'jquery' ), WP_SWEEP_VERSION, true );

		wp_localize_script(
			'wp-sweep', 'wp_sweep', array(
				'text_close_warning' => __( 'Sweeping is in progress. If you leave now, the process won\'t be completed.', 'wp-sweep' ),
				'text_sweep'         => __( 'Sweep', 'wp-sweep' ),
				'text_sweep_all'     => __( 'Sweep All', 'wp-sweep' ),
				'text_sweeping'      => __( 'Sweeping...', 'wp-sweep' ),
				'text_na'            => __( 'N/A', 'wp-sweep' ),
			)
		);
	}

	/**
	 * Admin menu
	 *
	 * @since 1.0.3
	 *
	 * @access public
	 * @return void
	 */
	public function admin_menu() {
		require __DIR__ . '/class-wpsweep-admin.php';

		$admin = new WPSweep_Admin( self::get_instance() );
		$this->admin_page_hook = add_management_page(
			_x( 'Sweep', 'Page title', 'wp-sweep' ),
			_x( 'Sweep', 'Menu title', 'wp-sweep' ),
			'manage_options',
			'wp_sweep',
			array( $admin, 'print_page' )
		);
	}

	/**
	 * Sweep Details loaded via AJAX
	 *
	 * @since 1.0.3
	 *
	 * @access public
	 * @return void
	 */
	public function ajax_sweep_details() {
		if ( empty( $_GET['action'] ) && empty( $_GET['sweep_name'] ) ) {
			return;
		}

		// Verify Referer.
		if ( ! check_admin_referer( 'wp_sweep_details_' . $_GET['sweep_name'] ) ) {
			wp_send_json_error(
				array(
					'error' => __( 'Failed to verify referrer.', 'wp-sweep' ),
				)
			);
		}

		if ( 'sweep_details' === $_GET['action'] ) {
			wp_send_json_success( $this->details( $_GET['sweep_name'] ) );
		}
	}

	/**
	 * Sweep via AJAX
	 *
	 * @since 1.0.3
	 *
	 * @access public
	 * @return void
	 */
	public function ajax_sweep() {
		if ( empty( $_GET['action'] ) || empty( $_GET['sweep_name'] ) ) {
			// FIXME error
			return;
		}
		// Verify Referer.
		if ( ! check_admin_referer( 'wp_sweep_' . $_GET['sweep_name'] ) ) {
			wp_send_json_error(
				array(
					'error' => __( 'Failed to verify referrer.', 'wp-sweep' ),
				)
			);
		}

		if ( 'sweep' !== $_GET['action'] ) {
			// FIXME error
			return;
		}

		// Check whether sweep instance exists.
		$all_sweeps = array_values( $this->sweeps );
		if ( ! array_key_exists( $_GET['sweep_name'], $this->all_sweeps ) ) {
			return;
		}

		$sweep       = $this->all_sweeps[ $_GET['sweep_name'] ];
		$sweep_type  = $sweep::TYPE;
		$message     = $sweep->get_message( $sweep->sweep() );
		$count       = $sweep->count();
		$total_count = $this->total_count( $sweep::TOTAL );
		$deps        = $this->total_dependency[ $sweep_type ];
		$total_stats = array();
		foreach ( $deps as $dep ) {
			$total_stats[ $dep ] = $this->total_count( $dep );
		}

		wp_send_json_success(
			array(
				'sweep'      => $sweep,
				'count'      => $count,
				'total'      => $total_count,
				'percentage' => $this->format_percentage( $count, $total_count ),
				'stats'      => $total_stats,
			)
		);
	}

	/**
	 * Count the number of total items belonging to each sweep
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @param string $name Total name.
	 * @return int Number of items belonging to each total
	 */
	public function total_count( $name ) {
		$tag = 'wp_sweep_' . $name;

		// FIXME Show Error
		if ( false === has_filter( $tag ) ) {
			return 0;
		}

		$count = apply_filters( $tag, 0 );

		$this->totals[ $name ] = apply_filters( 'wp_sweep_total_count', $count, $name );

		return $this->totals[ $name ];
	}

	/**
	 * Count the number of items belonging to each sweep
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @param string $name Sweep name.
	 * @return int Number of items belonging to each sweep
	 */
	public function count( $name ) {
		$count = $this->all_sweeps[ $name ]->count();

		return apply_filters( 'wp_sweep_count', $count, $name );
	}

	/**
	 * Return more details about a sweep
	 *
	 * @since 1.0.3
	 *
	 * @access public
	 * @param string $name Sweep name.
	 * @return array Details of items belonging to each sweep
	 */
	public function details( $name ) {
		$details = $this->all_sweeps[ $name ]->details();

		return apply_filters( 'wp_sweep_details', $details, $name );
	}

	/**
	 * Does the sweeping/cleaning up
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @param string $name Sweep name.
	 * @return string Processed message
	 */
	public function sweep( $name ) {
		$message = $this->all_sweeps[ $name ]->get_message( $this->all_sweeps[ $name ]->sweep() );

		return apply_filters( 'wp_sweep_sweep', $message, $name );
	}

	/**
	 * Format number to percentage, taking care of division by 0.
	 * Props @barisunver https://github.com/barisunver
	 *
	 * @since 1.0.2
	 *
	 * @access public
	 * @param int $current Current number.
	 * @param int|bool $total Total number.
	 * @return string|bool Number with percentage sign or false if not using total
	 */
	public function format_percentage( $current, $total ) {
		// No total for this sweep.
		if ( false === $total ) {
			return false;
		}

		$value = 0;
		if ( $total > 0 ) {
			$value = round( ( $current / $total ) * 100, 2 );
		}

		return strval( $value ) . '%';
	}
}
