<?php
/**
 * The REST API routes.
 *
 * @package WP-Sweep
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exposes count, details and sweep over the REST API.
 *
 * The namespace is the plugin slug rather than a bare noun, for the same
 * reason every hook carries the plugin prefix: `sweep/v1` is a name any plugin
 * could have claimed, and two plugins claiming it is not something WordPress
 * detects.
 */
class WP_Sweep_API {

	/**
	 * The REST namespace every route is registered under.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'wp-sweep/v1';

	/**
	 * Register the routes once the REST API is up.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the plugin's routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$name = array(
			'name' => array(
				'required'          => true,
				'validate_callback' => array( $this, 'is_sweep_name_valid' ),
			),
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'count/(?P<name>\w+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'count' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => $name,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'details/(?P<name>\w+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'details' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => $name,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'sweep/(?P<name>\w+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'sweep' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => $name,
			)
		);
	}

	/**
	 * How many items a sweep would remove.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function count( $request ) {
		$name = $request->get_param( 'name' );

		return new WP_REST_Response(
			array(
				'name'  => $name,
				'count' => (int) WP_Sweep::get_instance()->count( $name ),
			)
		);
	}

	/**
	 * A sample of the items a sweep would remove.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function details( $request ) {
		$name    = $request->get_param( 'name' );
		$details = WP_Sweep::get_instance()->details( $name );

		return new WP_REST_Response(
			array(
				'name'  => $name,
				'count' => count( $details ),
				'data'  => $details,
			)
		);
	}

	/**
	 * Run a sweep.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function sweep( $request ) {
		$name    = $request->get_param( 'name' );
		$results = WP_Sweep::get_instance()->sweep( $name );

		return new WP_REST_Response(
			array(
				'success' => ! empty( $results ),
				'name'    => $name,
				'message' => empty( $results ) ? __( 'No items left to sweep.', 'wp-sweep' ) : $results,
			)
		);
	}

	/**
	 * Whether a sweep name is one this plugin implements.
	 *
	 * @param string $name Sweep name.
	 * @return bool
	 */
	public function is_sweep_name_valid( $name ) {
		return WP_Sweep::get_instance()->is_sweep_name_valid( $name );
	}

	/**
	 * Whether the caller may sweep.
	 *
	 * @return bool
	 */
	public function permission_check() {
		return current_user_can( WP_Sweep::capability( 'rest' ) );
	}
}
