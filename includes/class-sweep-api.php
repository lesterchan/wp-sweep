<?php
/**
 * WP-Sweep REST API routes.
 *
 * @package WP-Sweep
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Sweep_Api
 */
class Sweep_Api {
	/**
	 * WP-Sweep WP Rest API namespace
	 *
	 * @var string
	 */
	private $namespace = 'sweep/v1';

	/**
	 * Register WP-Sweep API Routes
	 *
	 * @since 1.1.0
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					$this->namespace,
					'count/(?P<name>\w+)',
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'count' ),
						'permission_callback' => array( $this, 'permission_check' ),
						'args'                => array(
							'name' => array(
								'required'          => true,
								'validate_callback' => array( $this, 'is_sweep_name_valid' ),
							),
						),
					)
				);
				register_rest_route(
					$this->namespace,
					'details/(?P<name>\w+)',
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'details' ),
						'permission_callback' => array( $this, 'permission_check' ),
						'args'                => array(
							'name' => array(
								'required'          => true,
								'validate_callback' => array( $this, 'is_sweep_name_valid' ),
							),
						),
					)
				);
				register_rest_route(
					$this->namespace,
					'sweep/(?P<name>\w+)',
					array(
						'methods'             => WP_REST_Server::DELETABLE,
						'callback'            => array( $this, 'sweep' ),
						'permission_callback' => array( $this, 'permission_check' ),
						'args'                => array(
							'name' => array(
								'required'          => true,
								'validate_callback' => array( $this, 'is_sweep_name_valid' ),
							),
						),
					)
				);
			}
		);
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

		$sweep = Sweep::get_instance();
		$count = (int) $sweep->count( $params['name'] );

		return new WP_REST_Response(
			array(
				'name'  => $params['name'],
				'count' => $count,
			)
		);
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

		$sweep   = Sweep::get_instance();
		$details = $sweep->details( $params['name'] );

		return new WP_REST_Response(
			array(
				'name'  => $params['name'],
				'count' => count( $details ),
				'data'  => $details,
			)
		);
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

		$sweep   = Sweep::get_instance();
		$results = $sweep->sweep( $params['name'] );

		return new WP_REST_Response(
			array(
				'success' => ! empty( $results ),
				'name'    => $params['name'],
				'message' => empty( $results ) ? __( 'No items left to sweep.', 'wp-sweep' ) : $results,
			)
		);
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
		return Sweep::get_instance()->is_sweep_name_valid( $name );
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
		return current_user_can( 'activate_plugins' );
	}
}
