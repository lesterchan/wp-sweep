<?php
/**
 * Stand-in for the WP_CLI facade, recording what the command reports.
 *
 * Kept out of the test files themselves so each of those declares exactly one
 * class, and loaded from bootstrap.php rather than from a test, so the order
 * does not depend on which test runs first. The base class it extends lives in
 * helper-wp-cli-command.php, because the coding standard allows one class per
 * file and that rule is not relaxed for the suite.
 *
 * @package wp-sweep
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	/**
	 * Stand-in for the WP_CLI facade, recording what the command reports.
	 */
	class WP_CLI {
		/**
		 * Messages passed to WP_CLI::success().
		 *
		 * @var array
		 */
		public static $successes = array();

		/**
		 * Commands registered with WP_CLI::add_command().
		 *
		 * @var array
		 */
		public static $commands = array();

		/**
		 * Records a success message.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function success( $message ) {
			self::$successes[] = $message;
		}

		/**
		 * Records a command registration.
		 *
		 * @param string $name    Command name.
		 * @param string $handler Handler class name.
		 * @return void
		 */
		public static function add_command( $name, $handler ) {
			self::$commands[ $name ] = $handler;
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/class-wp-sweep-command.php';
