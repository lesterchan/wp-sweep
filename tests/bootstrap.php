<?php
/**
 * PHPUnit bootstrap for WP-Sweep.
 *
 * @package wp-sweep
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

require_once __DIR__ . '/helper-fixtures.php';
require_once __DIR__ . '/helper-wp-cli.php';
