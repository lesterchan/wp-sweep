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
class WP_Sweep_Ajax_Test extends WP_Ajax_UnitTestCase {

	use WP_Sweep_Creates_Admins;

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
	 * Stops core's update checks from reaching out to the network.
	 *
	 * _handleAjax() fires admin_init as part of standing in for a real
	 * admin-ajax.php request, and core hangs _maybe_update_core,
	 * _maybe_update_plugins and _maybe_update_themes off that hook. Those call
	 * api.wordpress.org, which a test container cannot reach; core raises a
	 * warning about it, and convertWarningsToExceptions in phpunit.xml.dist
	 * turns that warning into an error.
	 *
	 * The result was an intermittent error in whichever AJAX test happened to
	 * run first after the update transients expired — nothing to do with the
	 * test that reported it, and nothing to do with the plugin, which is why
	 * it looked unreproducible until the suite was run in random order.
	 */
	public function set_up() {
		parent::set_up();

		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );
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
	 * @return Sweep The plugin instance.
	 */
	protected function sweep() {
		return WP_Sweep::get_instance();
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
	 * The exception class the last dispatch died with, for diagnostics.
	 *
	 * @var string
	 */
	protected $last_die_exception = '';

	/**
	 * Dispatches an AJAX action the way admin-ajax.php would and returns the
	 * decoded JSON it emitted.
	 *
	 * Going through _handleAjax() rather than calling the handler directly
	 * matters: wp_send_json_* always dies, and the test case's die handler is
	 * what closes the output buffer _handleAjax() opened. Calling the handler
	 * on its own leaves that handler closing PHPUnit's buffer instead.
	 *
	 * The catch is on WPDieException, the parent, rather than on the two AJAX
	 * subclasses. wp_die() only routes to the handler that throws
	 * WPAjaxDieStopException while wp_doing_ajax() is true; when it is not,
	 * the same failed referer check throws a plain WPDieException instead.
	 * Catching only the subclasses let that one escape and surface as a
	 * PHPUnit error rather than a handled death — which is what an
	 * unreproducible one-off error in this class looked like. Reproduced by
	 * filtering wp_doing_ajax to false, which turns
	 * WPAjaxDieStopException into WPDieException on the identical request.
	 *
	 * @param string $action AJAX action name.
	 * @return array|null Decoded response, or null if nothing was emitted.
	 */
	protected function run_ajax( $action ) {
		$this->last_die_exception = '';

		$buffer_depth = ob_get_level();

		try {
			$this->_handleAjax( $action );
		} catch ( WPDieException $e ) {
			// Covers WPAjaxDieContinueException and WPAjaxDieStopException too.
			$this->last_die_exception = get_class( $e );
		}

		/*
		 * _handleAjax() opens an output buffer. The AJAX die handler closes it
		 * on the way past; the plain one does not, and PHPUnit then reports the
		 * test as risky for not closing what it opened — or worse, a later
		 * ob_get_clean() elsewhere closes PHPUnit's buffer instead of ours.
		 * Unwind to exactly the depth we started at, whatever happened.
		 */
		while ( ob_get_level() > $buffer_depth ) {
			$this->_last_response .= ob_get_clean();
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

		$this->assertTrue( $response['success'], 'An administrator may sweep.' );
		$this->assertSame( 0, (int) $response['data']['count'], 'The response reports nothing left, since the sweep just emptied it.' );
		$this->assertStringContainsString( 'Revisions Processed', $response['data']['sweep'], 'And carries the message naming what was swept.' );
		$this->assertNull( get_post( $revisions[0] ), 'The sweep deleted the revision it reported on.' );
	}

	/**
	 * The sweep response carries the numbers the screen writes back.
	 */
	public function test_sweep_response_carries_the_stats() {
		wp_set_current_user( self::$admin );
		$this->make_revisions( 1 );

		$this->set_request( 'sweep', 'revisions', 'posts' );
		$response = $this->run_ajax( 'sweep' );

		$this->assertArrayHasKey( 'total', $response['data'], 'The response carries the total.' );
		$this->assertArrayHasKey( 'percentage', $response['data'], 'The response carries the percentage.' );
		$this->assertArrayHasKey( 'posts', $response['data']['stats'], 'The response stats cover the posts table.' );
		$this->assertArrayHasKey( 'postmeta', $response['data']['stats'], 'The response stats cover the postmeta table.' );
		$this->assertStringEndsWith( '%', $response['data']['percentage'], 'The percentage is formatted as a percentage, ready to render.' );
	}

	/**
	 * An administrator can read the details list.
	 */
	public function test_admin_can_read_details() {
		wp_set_current_user( self::$admin );
		$this->make_revisions( 2 );

		$this->set_request( 'sweep_details', 'revisions' );
		$response = $this->run_ajax( 'sweep_details' );

		$this->assertTrue( $response['success'], 'An administrator may read the details.' );
		$this->assertContains( 'sweep-revision-0', $response['data'], 'The details response carries the sample rows themselves.' );
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

		$this->assertFalse( $response['success'], 'A subscriber is refused.' );
		$this->assertInstanceOf( WP_Post::class, get_post( $revisions[0] ), 'The refused subscriber deleted nothing.' );
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

		$this->assertFalse( $response['success'], 'A request with no sweep name is refused.' );
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

		$this->assertInstanceOf( WP_Post::class, get_post( $revisions[0] ), 'A bad nonce deletes nothing.' );
	}

	/**
	 * A nonce issued for a different sweep does not work for this one.
	 */
	public function test_nonce_is_scoped_to_the_sweep() {
		wp_set_current_user( self::$admin );
		$revisions = $this->make_revisions( 1 );

		$this->set_request( 'sweep', 'revisions', 'posts', wp_create_nonce( 'wp_sweep_auto_drafts' ) );
		$this->run_ajax( 'sweep' );

		$this->assertInstanceOf( WP_Post::class, get_post( $revisions[0] ), 'A nonce for another sweep does not authorise this one.' );
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

		$this->assertInstanceOf( WP_Post::class, get_post( $revisions[0] ), 'The details nonce does not authorise a sweep.' );
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
		$this->assertNotFalse( has_action( 'wp_ajax_sweep' ), 'The sweep endpoint is registered.' );
		$this->assertNotFalse( has_action( 'wp_ajax_sweep_details' ), 'The details endpoint is registered.' );
	}

	/**
	 * Neither endpoint is exposed to logged out visitors.
	 */
	public function test_endpoints_are_not_public() {
		$this->assertFalse( has_action( 'wp_ajax_nopriv_sweep' ), 'The sweep endpoint has no nopriv twin.' );
		$this->assertFalse( has_action( 'wp_ajax_nopriv_sweep_details' ), 'The details endpoint has no nopriv twin.' );
	}

	/**
	 * Each sweep type reports the totals for its own group of tables.
	 *
	 * The screen writes these back into the "There are a total of ..." line
	 * above each table, so a missing key leaves a stale number on screen.
	 *
	 * @dataProvider data_sweep_types
	 *
	 * @param string $sweep_name Sweep name to run.
	 * @param string $sweep_type Sweep type to report on.
	 * @param array  $expected   Stat keys the response must carry.
	 */
	public function test_stats_cover_the_whole_group( $sweep_name, $sweep_type, $expected ) {
		wp_set_current_user( self::$admin );

		$this->set_request( 'sweep', $sweep_name, $sweep_type );
		$response = $this->run_ajax( 'sweep' );

		$this->assertTrue( $response['success'], 'The stats request succeeded, or the shape assertions below are vacuous.' );
		$this->assertSame( $expected, array_keys( $response['data']['stats'] ), 'The stats cover every table in the group, in order.' );

		foreach ( $response['data']['stats'] as $value ) {
			$this->assertIsNumeric( $value, 'Every stat in the group is a number the screen can format.' );
		}
	}

	/**
	 * Every sweep type, with a sweep that uses it and the stats it owes.
	 *
	 * @return array
	 */
	public function data_sweep_types() {
		$terms = array( 'term_relationships', 'term_taxonomy', 'terms', 'termmeta' );

		return array(
			'posts'              => array( 'revisions', 'posts', array( 'posts', 'postmeta' ) ),
			'postmeta'           => array( 'orphan_postmeta', 'postmeta', array( 'posts', 'postmeta' ) ),
			'comments'           => array( 'spam_comments', 'comments', array( 'comments', 'commentmeta' ) ),
			'commentmeta'        => array( 'orphan_commentmeta', 'commentmeta', array( 'comments', 'commentmeta' ) ),
			'usermeta'           => array( 'orphan_usermeta', 'usermeta', array( 'users', 'usermeta' ) ),
			'term_relationships' => array( 'orphan_term_relationships', 'term_relationships', $terms ),
			'terms'              => array( 'unused_terms', 'terms', $terms ),
			'termmeta'           => array( 'orphan_termmeta', 'termmeta', $terms ),
			'options'            => array( 'transient_options', 'options', array( 'options' ) ),
			'tables'             => array( 'optimize_database', 'tables', array( 'tables' ) ),
		);
	}

	/**
	 * A sweep type the plugin does not recognise is refused.
	 *
	 * The total_count() switch would fall through and report 0, which the
	 * screen would then render as a total of zero posts.
	 */
	public function test_unknown_sweep_type_is_refused() {
		wp_set_current_user( self::$admin );
		$revisions = $this->make_revisions( 1 );

		$this->set_request( 'sweep', 'revisions', 'no_such_table' );
		$response = $this->run_ajax( 'sweep' );

		$this->assertFalse( $response['success'], 'An unknown sweep type is refused.' );
		$this->assertInstanceOf( WP_Post::class, get_post( $revisions[0] ), 'The refused request deleted nothing.' );
	}

	/**
	 * A missing sweep type is refused too.
	 */
	public function test_missing_sweep_type_is_refused() {
		wp_set_current_user( self::$admin );

		$_GET     = array(
			'action'     => 'sweep',
			'sweep_name' => 'revisions',
			'_wpnonce'   => wp_create_nonce( 'wp_sweep_revisions' ),
		);
		$_REQUEST = $_GET;

		$this->assertFalse( $this->run_ajax( 'sweep' )['success'], 'A request with no sweep type is refused.' );
	}

	/**
	 * A sweep name carrying slashes or markup is rejected, not passed on.
	 *
	 * @dataProvider data_hostile_names
	 *
	 * @param string $name A name that must never reach the query builders.
	 */
	public function test_hostile_sweep_names_are_rejected( $name ) {
		wp_set_current_user( self::$admin );
		$revisions = $this->make_revisions( 1 );

		$_GET     = array(
			'action'     => 'sweep',
			'sweep_name' => $name,
			'sweep_type' => 'posts',
			'_wpnonce'   => wp_create_nonce( 'wp_sweep_' . $name ),
		);
		$_REQUEST = $_GET;

		$response = $this->run_ajax( 'sweep' );

		// Refused either by the name check, which answers with a JSON error,
		// or by the referer check, which dies and answers with nothing. What
		// matters is that neither one deleted anything.
		if ( null !== $response ) {
			$this->assertFalse( $response['success'], 'A hostile sweep name is refused.' );
		}

		$this->assertInstanceOf( WP_Post::class, get_post( $revisions[0] ), 'The hostile name deleted nothing.' );
	}

	/**
	 * Names that must never reach the query builders.
	 *
	 * 'revisions\\' is here because wp_unslash() reduces it to the valid
	 * name 'revisions' — so it gets past validation and is stopped by the
	 * nonce instead, which was generated for the slashed spelling.
	 *
	 * @return array
	 */
	public function data_hostile_names() {
		return array(
			'sql'     => array( "revisions'; DROP TABLE wp_posts; --" ),
			'markup'  => array( '<script>alert(1)</script>' ),
			'slashed' => array( 'revisions\\' ),
			'empty'   => array( '' ),
			'unknown' => array( 'no_such_sweep' ),
		);
	}

	/**
	 * A failed referer check is handled in either context.
	 *
	 * The wp_die() function picks its handler from wp_doing_ajax(). While that is true the
	 * test case throws WPAjaxDieStopException; while it is false the very
	 * same request throws a plain WPDieException. The helper has to survive
	 * both, because the difference is invisible from the test and shows up
	 * only as an intermittent error.
	 *
	 * @dataProvider data_ajax_contexts
	 *
	 * @param bool   $doing_ajax Whether to report an AJAX context.
	 * @param string $expected   Exception class wp_die() should throw.
	 */
	public function test_a_failed_referer_check_is_handled_in_either_context( $doing_ajax, $expected ) {
		wp_set_current_user( self::$admin );
		$revisions = $this->make_revisions( 1 );

		if ( ! $doing_ajax ) {
			add_filter( 'wp_doing_ajax', '__return_false' );
		}

		$this->set_request( 'sweep', 'revisions', 'posts', 'not-a-real-nonce' );

		// The assertion is that this does not escape as an error.
		$this->run_ajax( 'sweep' );

		remove_filter( 'wp_doing_ajax', '__return_false' );

		$this->assertSame( $expected, $this->last_die_exception, 'The failure dies the way the calling context expects, which differs between the two.' );
		$this->assertInstanceOf( WP_Post::class, get_post( $revisions[0] ), 'A failed referer check deletes nothing, in either context.' );
	}

	/**
	 * The two contexts wp_die() distinguishes, and what each throws.
	 *
	 * @return array
	 */
	public function data_ajax_contexts() {
		return array(
			'ajax'     => array( true, 'WPAjaxDieStopException' ),
			'not ajax' => array( false, 'WPDieException' ),
		);
	}

	/**
	 * Every exception the AJAX test case throws descends from WPDieException,
	 * so catching the parent really does cover all of them.
	 */
	public function test_die_exceptions_share_a_parent() {
		$this->assertTrue( is_subclass_of( 'WPAjaxDieStopException', 'WPDieException' ), 'The stop exception descends from WPDieException, so one catch covers both.' );
		$this->assertTrue( is_subclass_of( 'WPAjaxDieContinueException', 'WPDieException' ), 'The continue exception descends from WPDieException, so one catch covers both.' );
	}
}
