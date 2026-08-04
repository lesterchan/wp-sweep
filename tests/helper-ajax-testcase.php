<?php
/**
 * Base class for the tests that drive the plugin's admin-ajax.php endpoints.
 *
 * Rooted in WP_Ajax_UnitTestCase rather than in WP_Sweep_TestCase, because only
 * that base class installs the handler which turns the wp_send_json_*() call at
 * the end of every endpoint into a catchable exception instead of a die() that
 * takes the test runner with it. PHP has no multiple inheritance, so the one
 * piece of shared surface the AJAX tests need is pulled in as a trait here.
 *
 * @package WP-Sweep
 */

/**
 * Gives the AJAX tests the same administrator helper every other test uses.
 */
abstract class WP_Sweep_Ajax_TestCase extends WP_Ajax_UnitTestCase {

	use WP_Sweep_Creates_Admins;
}
