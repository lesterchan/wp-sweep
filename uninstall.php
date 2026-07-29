<?php
/**
 * Removes everything WP-Sweep stored.
 *
 * Two option rows and nothing else: no database tables, no capabilities and no
 * scheduled events. Before 2.0.0 the plugin stored nothing at all, and this
 * file looped over every site on a network to call an empty function -- a loop
 * that carried three bugs at once, none of which mattered, because there was
 * nothing to delete.
 *
 * Now that there is, the loop is the correct one: get_sites() with
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
 * The names are written out rather than read from WP_Sweep_Options, because
 * uninstall.php runs without the plugin loaded.
 *
 * @return void
 */
function wp_sweep_delete_options() {
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
		wp_sweep_delete_options();
		restore_current_blog();
	}
} else {
	wp_sweep_delete_options();
}
