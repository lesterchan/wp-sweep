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
	 * The option row holding every setting, as a nested array.
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
	 * The default option values.
	 *
	 * Until 2.0.0 the details cap was a public property on the sweep class set
	 * to 500, which meant the only way to change it was to reach into the
	 * object from another plugin.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'limit_details' => 500,
		);
	}

	/**
	 * Get the stored options, merged over the defaults.
	 *
	 * Merging on read is what lets an install upgraded from an older version
	 * pick up keys that did not exist when its row was written.
	 *
	 * @param string|null $key Optional single key to return.
	 * @return mixed The full option array, or one value, or null for an unknown key.
	 */
	public static function get( $key = null ) {
		$stored  = get_option( self::OPTION, array() );
		$options = wp_parse_args( is_array( $stored ) ? $stored : array(), self::get_defaults() );

		if ( null === $key ) {
			return $options;
		}

		return isset( $options[ $key ] ) ? $options[ $key ] : null;
	}

	/**
	 * Replace the stored options.
	 *
	 * @param array $options Option values.
	 * @return bool Whether the option row changed.
	 */
	public static function update( array $options ) {
		return update_option( self::OPTION, $options );
	}

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
	 * Clean a full set of submitted options.
	 *
	 * Also used as register_setting()'s sanitize_callback, which receives the
	 * whole nested array in one go. It reads nothing back out of the database:
	 * the version markers live in their own row, so there is nothing here to
	 * rescue and nothing this can corrupt.
	 *
	 * @param mixed $input Raw submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::get_defaults();

		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		$limit = isset( $input['limit_details'] ) ? (int) $input['limit_details'] : 0;

		/*
		 * A cap of zero would render a Details button that opens an empty list,
		 * which reads as a broken screen rather than as a setting. The upper
		 * bound is the point of the cap: the whole list is held in memory and
		 * written into the page.
		 */
		return array(
			'limit_details' => max( 1, min( 10000, $limit ) ),
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
		if ( '' === $versions['plugin'] && '' === $versions['db'] && false === get_option( self::OPTION, false ) ) {
			// A fresh install. Write the defaults once rather than leaving the
			// row absent and merging them on every read forever.
			add_option( self::OPTION, self::get_defaults() );

			return;
		}

		self::update( self::sanitize( get_option( self::OPTION, array() ) ) );
	}
}
