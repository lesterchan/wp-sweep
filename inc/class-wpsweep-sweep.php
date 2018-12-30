<?php
/**
 * WP-Sweep sweep skeleton
 *
 * @package wp-sweep
 */

/**
 * Abstract class WPSweep_Sweep
 */
abstract class WPSweep_Sweep {
	/**
	 * Sweep slug
	 *
	 * @access public
	 * @var string
	 */
	const SLUG = '';

	/**
	 * The total the sweep pertains to.
	 *
	 * @access public
	 * @var string
	 */
	const TOTAL = 'null';

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
	}

	/**
	 * Return count of items to be swept.
	 *
	 * @access public
	 * @return int
	 */
	abstract public function count();

	/**
	 * Return details about the sweep.
	 *
	 * @access public
	 * @return string
	 */
	abstract public function details();

	/**
	 * Does the sweeping.
	 *
	 * @access public
	 * @return int Number of items swept
	 */
	abstract public function sweep();

	/**
	 * Return translated sweep name.
	 *
	 * @access public
	 * @return string Translated name
	 */
	abstract public function get_name();

	/**
	 * Return translated "Processed" message.
	 *
	 * @param int $swept
	 * @access public
	 * @return string Translated name
	 */
	public function get_message( $swept ) {
		// translators: first %s is swept count, second %s is sweep name.
		return sprintf( '%d %s Processed', $swept, $this->get_name() );
	}
}
