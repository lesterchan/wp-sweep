<?php
/**
 * A privileged user, on a single site and on a network alike.
 *
 * @package WP-Sweep
 */

/**
 * Creates users privileged enough to reach the sweep surface.
 *
 * A trait rather than a method on WP_Sweep_TestCase because WP_Sweep_Ajax_Test
 * has to extend WP_Ajax_UnitTestCase — only its die handler closes the buffer
 * that _handleAjax() opens — so it cannot inherit from the plugin's own base
 * class and would otherwise need its own copy of this.
 */
trait WP_Sweep_Creates_Admins {

	/**
	 * Creates a user who may actually reach the sweep surface.
	 *
	 * A site administrator is enough on a single site and is *not* enough on a
	 * network. The sweep surface takes `activate_plugins`, and core's
	 * map_meta_cap() adds `manage_network_plugins` to that capability under
	 * multisite unless the network admin has delegated the Plugins menu — which
	 * is not delegated by default. So a plain administrator is refused, which is
	 * the correct behaviour for something that deletes data, and the test needs
	 * the privilege the real operator would have.
	 *
	 * Every test class goes through this rather than calling the factory
	 * directly, so the network case is answered once.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 * @return int The new user's ID.
	 */
	protected static function create_admin( $factory ) {
		$user_id = $factory->user->create( array( 'role' => 'administrator' ) );

		if ( is_multisite() ) {
			grant_super_admin( $user_id );
		}

		return $user_id;
	}
}
