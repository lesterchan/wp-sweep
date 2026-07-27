<?php
/**
 * WP-Sweep uninstall.php
 *
 * WP-Sweep has nothing to uninstall.
 *
 * The plugin cleans up after other plugins and persists nothing of its own:
 * no options, no database tables, no capabilities and no scheduled events.
 * Everything it does is a deletion performed at the moment the user asks for
 * it, so removing the plugin leaves nothing behind to tidy away.
 *
 * Before 2.0.0 this file looped over every site on a multisite network to call
 * an empty function. That loop carried three bugs at once — get_sites()
 * silently stops at its default of 100 sites, full WP_Site objects were
 * hydrated to read one column, and restore_current_blog() sat after the loop
 * instead of inside it, leaving the switch stack unwound by exactly one. None
 * of it did anything, so it was deleted rather than repaired.
 *
 * If a future version starts storing something, the deletion belongs here, and
 * the loop it needs is the one documented in the modernize-wp-plugin skill:
 * get_sites( array( 'fields' => 'ids', 'number' => 0 ) ), with
 * restore_current_blog() inside the loop body. Test_WP_Sweep_Regressions
 * fails the moment the plugin writes an option, as a reminder.
 *
 * @package WP-Sweep
 */

// Exit if WordPress did not initiate this uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
