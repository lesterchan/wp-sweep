<?php
/**
 * Tests for the wp-sweep/v1 REST routes.
 *
 * @package wp-sweep
 */

/**
 * The REST surface is documented in the readme and scripted against, so the
 * route shapes, the response keys and above all the capability gate are pinned
 * here. Every route deletes or discloses data about the whole install.
 */
class WP_Sweep_REST_API_Test extends WP_Sweep_TestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	protected static $subscriber;

	/**
	 * Creates the users the capability tests need.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 * @return void
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin      = self::create_admin( $factory );
		self::$subscriber = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Boots a REST server for each test.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	/**
	 * Tears the REST server down again.
	 */
	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Dispatches a request and returns the response.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route path.
	 * @return WP_REST_Response
	 */
	protected function dispatch( $method, $route ) {
		return rest_get_server()->dispatch( new WP_REST_Request( $method, $route ) );
	}

	/**
	 * All three documented routes are registered under wp-sweep/v1.
	 */
	public function test_routes_are_registered() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp-sweep/v1/count/(?P<name>\w+)', $routes, 'The count route is registered.' );
		$this->assertArrayHasKey( '/wp-sweep/v1/details/(?P<name>\w+)', $routes, 'The details route is registered.' );
		$this->assertArrayHasKey( '/wp-sweep/v1/sweep/(?P<name>\w+)', $routes, 'The sweep route is registered.' );
	}

	/**
	 * The sweep route is DELETE, matching the readme.
	 */
	public function test_sweep_route_uses_delete() {
		$routes = rest_get_server()->get_routes();
		$route  = $routes['/wp-sweep/v1/sweep/(?P<name>\w+)'];

		$this->assertTrue( $route[0]['methods']['DELETE'], 'The sweep route answers DELETE, which is what a destructive route should take.' );
		$this->assertArrayNotHasKey( 'GET', $route[0]['methods'], 'The sweep route does not answer GET, which a browser could be made to send.' );
	}

	/**
	 * A logged out request is refused.
	 *
	 * @dataProvider data_all_routes
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route path.
	 */
	public function test_routes_reject_anonymous_requests( $method, $route ) {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->dispatch( $method, $route )->get_status() );
	}

	/**
	 * A user without activate_plugins is refused.
	 *
	 * @dataProvider data_all_routes
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route path.
	 */
	public function test_routes_reject_users_without_the_capability( $method, $route ) {
		wp_set_current_user( self::$subscriber );

		$this->assertSame( 403, $this->dispatch( $method, $route )->get_status() );
	}

	/**
	 * Every route, for the capability tests.
	 *
	 * @return array
	 */
	public function data_all_routes() {
		return array(
			'count'   => array( 'GET', '/wp-sweep/v1/count/revisions' ),
			'details' => array( 'GET', '/wp-sweep/v1/details/revisions' ),
			'sweep'   => array( 'DELETE', '/wp-sweep/v1/sweep/revisions' ),
		);
	}

	/**
	 * A subscriber cannot delete data through the sweep route.
	 */
	public function test_subscriber_cannot_sweep_anything() {
		$revisions = $this->make_revisions( 2 );

		wp_set_current_user( self::$subscriber );
		$this->dispatch( 'DELETE', '/wp-sweep/v1/sweep/revisions' );

		$this->assertInstanceOf( WP_Post::class, get_post( $revisions[0] ), 'A subscriber sweeps nothing through the REST route either.' );
	}

	/**
	 * The count route returns the name and an integer count.
	 */
	public function test_count_route_returns_the_count() {
		$this->make_revisions( 3 );
		wp_set_current_user( self::$admin );

		$response = $this->dispatch( 'GET', '/wp-sweep/v1/count/revisions' );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'revisions', $data['name'] );
		$this->assertSame( 3, $data['count'] );
		$this->assertIsInt( $data['count'], 'The count route answers with an integer.' );
	}

	/**
	 * The details route returns the sample rows and how many there are.
	 */
	public function test_details_route_returns_the_sample() {
		$this->make_revisions( 2 );
		wp_set_current_user( self::$admin );

		$data = $this->dispatch( 'GET', '/wp-sweep/v1/details/revisions' )->get_data();

		$this->assertSame( 'revisions', $data['name'] );
		$this->assertSame( 2, $data['count'] );
		$this->assertContains( 'sweep-revision-0', $data['data'] );
	}

	/**
	 * The sweep route deletes and reports success.
	 */
	public function test_sweep_route_deletes() {
		$revisions = $this->make_revisions( 2 );
		wp_set_current_user( self::$admin );

		$data = $this->dispatch( 'DELETE', '/wp-sweep/v1/sweep/revisions' )->get_data();

		$this->assertTrue( $data['success'], 'The sweep route reports success.' );
		$this->assertSame( 'revisions', $data['name'] );
		$this->assertStringContainsString( 'Revisions Processed', $data['message'] );
		$this->assertNull( get_post( $revisions[0] ), 'The sweep route actually deleted the revision.' );
	}

	/**
	 * Sweeping an empty set reports failure with a readable message rather
	 * than an empty string.
	 */
	public function test_sweep_route_reports_nothing_to_do() {
		wp_set_current_user( self::$admin );
		$this->dispatch( 'DELETE', '/wp-sweep/v1/sweep/revisions' );

		$data = $this->dispatch( 'DELETE', '/wp-sweep/v1/sweep/revisions' )->get_data();

		$this->assertFalse( $data['success'], 'With nothing to sweep the route reports that rather than a success.' );
		$this->assertSame( 'No items left to sweep.', $data['message'] );
	}

	/**
	 * An unknown sweep name is rejected by the validator, not passed through.
	 *
	 * @dataProvider data_all_routes
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route path.
	 */
	public function test_routes_reject_unknown_sweep_names( $method, $route ) {
		wp_set_current_user( self::$admin );

		$route    = str_replace( 'revisions', 'no_such_sweep', $route );
		$response = $this->dispatch( $method, $route );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Every sweep the plugin implements is reachable over REST. A name added
	 * to the switch statements but missed in the API allow list would be
	 * silently unavailable.
	 *
	 * @dataProvider data_every_sweep_name
	 *
	 * @param string $name Sweep name.
	 */
	public function test_every_sweep_name_is_accepted( $name ) {
		wp_set_current_user( self::$admin );

		$response = $this->dispatch( 'GET', '/wp-sweep/v1/count/' . $name );

		$this->assertSame( 200, $response->get_status(), "'{$name}' is not exposed over REST." );
	}

	/**
	 * Every sweep name the plugin advertises.
	 *
	 * @return array
	 */
	public function data_every_sweep_name() {
		return array_map(
			static function ( $name ) {
				return array( $name );
			},
			array(
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
			)
		);
	}
}
