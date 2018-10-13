<?php
/**
 * WP-Sweep sweep type skeleton
 *
 * @package wp-sweep
 */

/**
 * Abstract class WPSweep_Sweep_Type
 */
abstract class WPSweep_Sweep_Type {
	/**
	 * Sweep type slug
	 *
	 * @access public
	 * @var string
	 */
	const SLUG = '';

	/**
	 * Filter priority for sorting types
	 *
	 * @access public
	 * @var int
	 */
	const ORDER = 10;

	/**
	 * Total provided by this sweep type.
	 *
	 * @access public
	 * @var array
	 */
	public $total_dependency = array();

	/**
	 * WPSweep instance.
	 *
	 * @access protected
	 * @var WPSweep $wp_sweep
	 */
	protected $wp_sweep;

	/**
	 * WPDB instance.
	 *
	 * @access protected
	 * @var wpdb $wpdb
	 */
	protected $wp_db;

	/**
	 * Initialize sweep.
	 *
	 * @access public
	 */
	public function __construct( $wp_sweep ) {
		global $wpdb;

		$this->wp_sweep = $wp_sweep;
		$this->wp_db = $wpdb;

		if ( ! empty( $this->total_dependency ) ) {
			add_filter( 'wp_sweep_type_register', array( $this, 'register' ), self::ORDER );
		}
	}

	/**
	 * Register sweep.
	 *
	 * @access public
	 */
	public function register( $classes ) {
		$classes[] = self::class;

		return $classes;
	}

	/**
	 * Register total filters.
	 *
	 * @access public
	 */
	public function register_total() {}

	/**
	 * Return translated sweep type name.
	 *
	 * @access public
	 * @return string Translated name
	 */
	abstract public function get_name();

	/**
	 * Get the HTML description.
	 *
	 * @access public
	 * @return int
	 */
	abstract public function get_description();
}
