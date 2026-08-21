<?php
/**
 * Removes everything WP-Sweep ever stored.
 *
 * The plugin stores nothing: no settings row, no version row, no database
 * tables, no capabilities and no scheduled events. Two rows are deleted anyway,
 * because 2.0.0 beta builds wrote them before it settled on storing nothing --
 * `wp_sweep_options` held settings and `wp_sweep_version` held the two markers
 * -- and a site that ran one of those builds is the only place either will ever
 * be found.
 *
 * 1.2.0 stored nothing either, so uninstalling one of those installs finds
 * nothing to remove -- and this file still looped over every site on a network
 * to call an empty function, a loop that carried three bugs at once, none of
 * which mattered, because there was nothing to delete.
 *
 * The loop is the correct one now: get_sites() with
 * 'fields' => 'ids' so full WP_Site objects are not hydrated to read one
 * column, 'number' => 0 so it does not silently stop at the default of 100
 * sites, and restore_current_blog() inside the loop body so the switch stack
 * does not end up unwound by exactly one.
 *
 * @package WP-Sweep
 */

// Exit if WordPress did not initiate this uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete the plugin's option rows on the current site.
 *
 * The names are written out literally: uninstall.php runs without the plugin
 * loaded, and there is no options class to read them from in any case.
 *
 * @return void
 */
function wp_sweep_uninstall_site() {
	delete_option( 'wp_sweep_options' );
	delete_option( 'wp_sweep_version' );
}

if ( is_multisite() ) {
	$wp_sweep_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $wp_sweep_site_ids as $wp_sweep_site_id ) {
		switch_to_blog( (int) $wp_sweep_site_id );
		wp_sweep_uninstall_site();
		restore_current_blog();
	}
} else {
	wp_sweep_uninstall_site();
}
