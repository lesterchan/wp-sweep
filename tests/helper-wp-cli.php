<?php
/**
 * Stand-ins for the WP-CLI classes the command file extends and calls.
 *
 * WP-CLI is not loaded in a PHPUnit run, so `class Sweep_Command extends
 * WP_CLI_Command` is a fatal error without these. Defining them is safe: the
 * plugin gates on the WP_CLI *constant*, which is deliberately left undefined,
 * so nothing else in the suite changes behaviour because these classes exist.
 *
 * Kept out of the test files themselves so each of those declares exactly one
 * class, and loaded from bootstrap.php rather than from a test, so the order
 * does not depend on which test runs first.
 *
 * @package wp-sweep
 */

if ( ! class_exists( 'WP_CLI_Command' ) ) {
	/**
	 * Stand-in for WP-CLI's base command class.
	 */
	class WP_CLI_Command {}
}

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

require_once dirname( __DIR__ ) . '/includes/class-sweep-command.php';
