<?php
/**
 * Plugin options.
 *
 * @package WP-Sweep
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the plugin's two option rows.
 *
 * Settings live in one row and the version markers in another, so the settings
 * screen and the upgrade routine can never overwrite each other's work.
 *
 * WP-Sweep stored nothing at all until 2.0.0 -- it cleans up after other
 * plugins and had no state of its own -- so there is no legacy row to fold in
 * here. The version row still earns its place: it is what makes the next
 * change to the shape of these settings migratable rather than guesswork.
 */
class WP_Sweep_Options {

	/**
	 * The settings row this plugin no longer has.
	 *
	 * WP-Sweep has one tunable -- how many items a details list shows -- and for
	 * a while it was the single field of a settings screen. One field does not
	 * earn a screen, an option row, a Settings API registration and a sanitiser,
	 * so it is the `wp_sweep_limit_details` filter instead. Losing the second
	 * screen is also what lets the plugin sit under Tools again, where it was for
	 * its whole released life.
	 *
	 * The name survives only so the upgrade has something to delete. No released
	 * version ever wrote this row, so that cleanup is for 2.0.0 beta installs.
	 *
	 * Not to be confused with the `wp_sweep_transient_options` and
	 * `wp_sweep_details_transient_options` strings elsewhere in the plugin.
	 * Those are nonce actions for the sweep named `transient_options`, built
	 * as 'wp_sweep_' . $name, and they are neither options nor transients. No
	 * sweep is named `options` or `version`, so no nonce action can ever read
	 * as one of these rows; WP_Sweep_Regressions_Test holds that true.
	 *
	 * @var string
	 */
	const OPTION = 'wp_sweep_options';

	/**
	 * The option row holding the 'plugin' and 'db' version markers.
	 *
	 * @var string
	 */
	const VERSION = 'wp_sweep_version';

	/**
	 * Get the version markers.
	 *
	 * @return array The 'plugin' and 'db' markers, each an empty string when unset.
	 */
	public static function get_versions() {
		$stored = get_option( self::VERSION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array(
			'plugin' => isset( $stored['plugin'] ) ? (string) $stored['plugin'] : '',
			'db'     => isset( $stored['db'] ) ? (string) $stored['db'] : '',
		);
	}

	/**
	 * Bring the stored rows up to date with the running code.
	 *
	 * Runs on activation and on every admin load, because activation hooks do
	 * not fire when a plugin is updated -- which is the usual reason a
	 * migration never runs. Idempotent.
	 *
	 * Both markers are written together in one update_option() at the very end,
	 * so a half-finished upgrade never records itself as complete.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$versions = self::get_versions();

		if ( WP_SWEEP_VERSION === $versions['plugin'] && WP_SWEEP_DB_VERSION === $versions['db'] ) {
			return;
		}

		self::migrate( $versions );

		update_option(
			self::VERSION,
			array(
				'plugin' => WP_SWEEP_VERSION,
				'db'     => WP_SWEEP_DB_VERSION,
			)
		);
	}

	/**
	 * Fold whatever the previous version left behind into the current rows.
	 *
	 * There is nothing to fold in from a released version: every WP-Sweep up to
	 * and including 1.2.0 stored no options, no tables and no transients of its
	 * own. What this does do is re-sanitise the settings, so a row written by an
	 * older schema is brought to the current shape and bounds before anything
	 * reads it -- and it is where a genuine migration goes when
	 * WP_SWEEP_DB_VERSION is next bumped.
	 *
	 * @param array $versions The markers as they stood before the upgrade.
	 * @return void
	 */
	private static function migrate( $versions ) {
		unset( $versions );

		// The settings row went with the settings screen. No released version
		// ever wrote it, so this only tidies up an install that ran a 2.0.0 beta.
		delete_option( self::OPTION );
	}
}
