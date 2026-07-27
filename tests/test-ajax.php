<?php
/**
 * Tests for the admin-ajax.php endpoints the admin screen drives.
 *
 * @package wp-sweep
 */

/**
 * Both endpoints delete data or disclose it, so the interesting assertions are
 * the ones about who gets turned away.
 *
 * @group ajax
 */
class Test_WP_Sweep_Ajax extends WP_Ajax_UnitTestCase {

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
		self::$admin      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Clears request state between tests.
	 */
	public function tear_down() {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/**
	 * Returns the plugin singleton.
	 *
	 * @return WPSweep The plugin instance.
	 */
	protected function sweep() {
		return WPSweep::get_instance();
	}

	/**
	 * Creates revisions to sweep.
	 *
	 * @param int $how_many Number of revisions.
	 * @return array Revision IDs.
	 */
	protected function make_revisions( $how_many = 2 ) {
		$parent = self::factory()->post->create();
		$ids    = array();

		for ( $i = 0; $i < $how_many; $i++ ) {
			$ids[] = wp_insert_post(
				array(
					'post_type'   => 'revision',
					'post_status' => 'inherit',
					'post_parent' => $parent,
					'post_title'  => "sweep-revision-{$i}",
					'post_name'   => "{$parent}-revision-{$i}",
				)
			);
		}

		return $ids;
	}

	/**
	 * Populates the request the handler will read.
	 *
	 * @param string $action     AJAX action.
	 * @param string $sweep_name Sweep name.
	 * @param string $sweep_type Sweep type.
	 * @param string $nonce      Nonce, or null to generate a valid one.
	 * @return void
	 */
	protected function set_request( $action, $sweep_name, $sweep_type = 'posts', $nonce = null ) {
		$nonce_action = 'sweep_details' === $action
			? 'wp_sweep_details_' . $sweep_name
			: 'wp_sweep_' . $sweep_name;

		$_GET = array(
			'action'     => $action,
			'sweep_name' => $sweep_name,
			'sweep_type' => $sweep_type,
			'_wpnonce'   => null === $nonce ? wp_create_nonce( $nonce_action ) : $nonce,
		);

		$_REQUEST = $_GET;
	}

	/**
	 * Dispatches an AJAX action the way admin-ajax.php would and returns the
	 * decoded JSON it emitted.
	 *
	 * Going through _handleAjax() rather than calling the handler directly
	 * matters: wp_send_json_* always dies, and the test case's die handler is
	 * what closes the output buffer _handleAjax() opened. Calling the handler
	 * on its own leaves that handler closing PHPUnit's buffer instead.
	 *
	 * @param string $action AJAX action name.
	 * @return array|null Decoded response, or null if nothing was emitted.
	 */
	protected function run_ajax( $action ) {
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		}

		return json_decode( $this->_last_response, true );
	}

	/**
	 * An administrator with a valid nonce can sweep.
	 */
	public function test_admin_can_sweep() {
		wp_set_current_user( self::$admin );
		$revisions = $this->make_revisions( 2 );

		$this->set_request( 'sweep', 'revisions', 'posts' );
		$response = $this->run_ajax( 'sweep' );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 0, (int) $response['data']['count'] );
		$this->assertStringContainsString( 'Revisions Processed', $response['data']['sweep'] );
		$this->assertNull( get_post( $revisions[0] ) );
	}

	/**
	 * The sweep response carries the numbers the screen writes back.
	 */
	public function test_sweep_response_carries_the_stats() {
		wp_set_current_user( self::$admin );
		$this->make_revisions( 1 );

		$this->set_request( 'sweep', 'revisions', 'posts' );
		$response = $this->run_ajax( 'sweep' );

		$this->assertArrayHasKey( 'total', $response['data'] );
		$this->assertArrayHasKey( 'percentage', $response['data'] );
		$this->assertArrayHasKey( 'posts', $response['data']['stats'] );
		$this->assertArrayHasKey( 'postmeta', $response['data']['stats'] );
		$this->assertStringEndsWith( '%', $response['data']['percentage'] );
	}

	/**
	 * An administrator can read the details list.
	 */
	public function test_admin_can_read_details() {
		wp_set_current_user( self::$admin );
		$this->make_revisions( 2 );

		$this->set_request( 'sweep_details', 'revisions' );
		$response = $this->run_ajax( 'sweep_details' );

		$this->assertTrue( $response['success'] );
		$this->assertContains( 'sweep-revision-0', $response['data'] );
	}

	/**
	 * A user without activate_plugins is refused, even holding a good nonce.
	 *
	 * @dataProvider data_endpoints
	 *
	 * @param string $action AJAX action.
	 */
	public function test_subscriber_is_refused( $action ) {
		wp_set_current_user( self::$subscriber );
		$revisions = $this->make_revisions( 1 );

		$this->set_request( $action, 'revisions' );
		$response = $this->run_ajax( $action );

		$this->assertFalse( $response['success'] );
		$this->assertInstanceOf( WP_Post::class, get_post( $revisions[0] ) );
	}

	/**
	 * A missing sweep name is refused before anything is read or deleted.
	 *
	 * @dataProvider data_endpoints
	 *
	 * @param string $action AJAX action.
	 */
	public function test_missing_sweep_name_is_refused( $action ) {
		wp_set_current_user( self::$admin );

		$_GET     = array( 'action' => $action );
		$_REQUEST = $_GET;

		$response = $this->run_ajax( $action );

		$this->assertFalse( $response['success'] );
	}

	/**
	 * A bad nonce stops the request.
	 *
	 * @dataProvider data_endpoints
	 *
	 * @param string $action AJAX action.
	 */
	public function test_bad_nonce_is_refused( $action ) {
		wp_set_current_user( self::$admin );
		$revisions = $this->make_revisions( 1 );

		$this->set_request( $action, 'revisions', 'posts', 'not-a-real-nonce' );
		$this->run_ajax( $action );

		$this->assertInstanceOf( WP_Post::class, get_post( $revisions[0] ) );
	}

	/**
	 * A nonce issued for a different sweep does not work for this one.
	 */
	public function test_nonce_is_scoped_to_the_sweep() {
		wp_set_current_user( self::$admin );
		$revisions = $this->make_revisions( 1 );

		$this->set_request( 'sweep', 'revisions', 'posts', wp_create_nonce( 'wp_sweep_auto_drafts' ) );
		$this->run_ajax( 'sweep' );

		$this->assertInstanceOf( WP_Post::class, get_post( $revisions[0] ) );
	}

	/**
	 * The details nonce does not unlock the sweep endpoint. Reading what
	 * would be deleted and actually deleting it are separate permissions.
	 */
	public function test_details_nonce_does_not_authorise_a_sweep() {
		wp_set_current_user( self::$admin );
		$revisions = $this->make_revisions( 1 );

		$this->set_request( 'sweep', 'revisions', 'posts', wp_create_nonce( 'wp_sweep_details_revisions' ) );
		$this->run_ajax( 'sweep' );

		$this->assertInstanceOf( WP_Post::class, get_post( $revisions[0] ) );
	}

	/**
	 * Both AJAX actions the admin screen posts to.
	 *
	 * @return array
	 */
	public function data_endpoints() {
		return array(
			'sweep'   => array( 'sweep' ),
			'details' => array( 'sweep_details' ),
		);
	}

	/**
	 * Both endpoints are wired up under the actions the script posts to.
	 */
	public function test_endpoints_are_registered() {
		$this->assertNotFalse( has_action( 'wp_ajax_sweep' ) );
		$this->assertNotFalse( has_action( 'wp_ajax_sweep_details' ) );
	}

	/**
	 * Neither endpoint is exposed to logged out visitors.
	 */
	public function test_endpoints_are_not_public() {
		$this->assertFalse( has_action( 'wp_ajax_nopriv_sweep' ) );
		$this->assertFalse( has_action( 'wp_ajax_nopriv_sweep_details' ) );
	}
}
