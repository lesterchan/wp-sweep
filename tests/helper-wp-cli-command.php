<?php
/**
 * Stand-in for the WP-CLI base class the command file extends.
 *
 * WP-CLI is not loaded in a PHPUnit run, so `class WP_Sweep_Command extends
 * WP_CLI_Command` is a fatal error without this. Defining it is safe: the
 * plugin gates on the WP_CLI *constant*, which is deliberately left undefined,
 * so nothing else in the suite changes behaviour because the class exists.
 *
 * @package wp-sweep
 */

if ( ! class_exists( 'WP_CLI_Command' ) ) {
	/**
	 * Stand-in for WP-CLI's base command class.
	 */
	class WP_CLI_Command {}
}
