<?php
/**
 * PHPUnit bootstrap for WP-Sweep.
 *
 * @package WP-Sweep
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	// Where wp-env mounts the WordPress test library.
	$_tests_dir = '/wordpress-phpunit';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php." . PHP_EOL;
	echo 'Run the suite through bin/test.sh, which starts wp-env for you.' . PHP_EOL;
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load WP-Sweep once the test suite has a WordPress to load it into.
 */
function _wp_sweep_manually_load_plugin() {
	require dirname( __DIR__ ) . '/wp-sweep.php';
}
tests_add_filter( 'muplugins_loaded', '_wp_sweep_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/helper-creates-admins.php';
require_once __DIR__ . '/helper-ajax-testcase.php';
require_once __DIR__ . '/helper-testcase.php';
require_once __DIR__ . '/helper-wp-cli-command.php';
require_once __DIR__ . '/helper-wp-cli-halt.php';
require_once __DIR__ . '/helper-wp-cli.php';

// The shared metadata contract, a byte-identical copy of
// _standards/templates/helper-metadata-testcase.php. It extends Plugin_TestCase
// because the nineteen copies have to be identical; the alias is the one line
// per plugin the mechanism needs.
class_alias( 'WP_Sweep_TestCase', 'Plugin_TestCase' );
require_once __DIR__ . '/helper-metadata-testcase.php';
