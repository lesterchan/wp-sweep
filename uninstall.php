<?php
/**
 * WP-Sweep uninstall.php
 *
 * @package wp-sweep
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	$ms_sites = get_sites();

	if ( 0 < count( $ms_sites ) ) {
		foreach ( $ms_sites as $ms_site ) {
			switch_to_blog( $ms_site->blog_id );
			plugin_uninstalled();
		}
	}

	restore_current_blog();
} else {
	plugin_uninstalled();
}

/**
 * Delete plugin table when uninstalled
 *
 * @access public
 * @return void
 */
function plugin_uninstalled() {
}
