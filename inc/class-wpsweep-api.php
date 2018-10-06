<?php
/**
 * WP-Sweep WP-API
 *
 * @package wp-sweep
 */

/**
 * Class WPSweep_Api
 */
class WPSweep_Api {
	/**
	 * WP-Sweep WP Rest API namespace
	 *
	 * @var string
	 */
	private $namespace = 'sweep/v1';

	/**
	 * List of sweeps
	 *
	 * @var array
	 */
	private $sweeps = array(
		'revisions',
		'auto_drafts',
		'deleted_posts',
		'unapproved_comments',
		'spam_comments',
		'deleted_comments',
		'transient_options',
		'orphan_postmeta',
		'orphan_commentmeta',
		'orphan_usermeta',
		'orphan_termmeta',
		'orphan_term_relationships',
		'unused_terms',
		'duplicated_postmeta',
		'duplicated_commentmeta',
		'duplicated_usermeta',
		'duplicated_termmeta',
		'optimize_database',
		'oembed_postmeta',
	);

	/**
	 * Register WP-Sweep API Routes
	 *
	 * @since 1.1.0
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		add_action( 'rest_api_init', function() {
			register_rest_route( $this->namespace, 'count/(?P<name>\w+)', array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'count' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'name' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'is_sweep_name_valid' ),
					),
				),
			));
			register_rest_route( $this->namespace, 'details/(?P<name>\w+)', array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'details' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'name' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'is_sweep_name_valid' ),
					),
				),
			));
			register_rest_route( $this->namespace, 'sweep/(?P<name>\w+)', array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'sweep' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'name' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'is_sweep_name_valid' ),
					),
				),
			));
		});
	}
	/**
	 * Sweep item count
	 *
	 * @since 1.1.0
	 *
	 * @access public
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function count( $request ) {
		$params = $request->get_params();

		$sweep = new WPSweep();
		$count = (int) $sweep->count( $params['name'] );

		return new WP_REST_Response( array(
			'name'  => $params['name'],
			'count' => $count,
		) );
	}

	/**
	 * Sweep details
	 *
	 * @since 1.1.0
	 *
	 * @access public
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function details( $request ) {
		$params = $request->get_params();

		$sweep   = new WPSweep();
		$details = $sweep->details( $params['name'] );

		return new WP_REST_Response( array(
			'name'  => $params['name'],
			'count' => count( $details ),
			'data'  => $details,
		) );
	}

	/**
	 * Lets do the sweeping
	 *
	 * @since 1.1.0
	 *
	 * @access public
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function sweep( $request ) {
		$params = $request->get_params();

		$sweep   = new WPSweep();
		$results = $sweep->sweep( $params['name'] );

		return new WP_REST_Response( array(
			'success' => ! empty( $results ),
			'name'    => $params['name'],
			'message' => empty( $results ) ? __( 'No items left to sweep.', 'wp-sweep' ) : $results,
		) );
	}

	/**
	 * Check whether a sweep is valid
	 *
	 * @since 1.1.0
	 *
	 * @access public
	 * @param string $name Sweep name.
	 * @return bool Is the sweep name valid?
	 */
	public function is_sweep_name_valid( $name ) {
		return in_array( $name, $this->sweeps, true );
	}

	/**
	 * Check whether the function is allowed to be run. Must have either capabilities to enact action, or a valid nonce.
	 *
	 * @since 1.1.0
	 *
	 * @access public
	 * @return bool Does the user has access to sweep?
	 */
	public function permission_check() {
		return current_user_can( 'manage_options' );
	}
}
