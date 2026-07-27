<?php
/**
 * Plugin Name: WP-Sweep
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: WP-Sweep allows you to clean up unused, orphaned and duplicated data in your WordPress. It cleans up revisions, auto drafts, unapproved comments, spam comments, trashed comments, orphan post meta, orphan comment meta, orphan user meta, orphan term relationships, unused terms, duplicated post meta, duplicated comment meta, duplicated user meta and transient options. It also optimizes your database tables.
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Lester 'GaMerZ' Chan
 * Author URI: https://lesterchan.net
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-sweep
 * Domain Path: /languages
 *
 * @package WP-Sweep
 */

/*
	Copyright 2026 Lester Chan  (email : lesterchan@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

defined( 'ABSPATH' ) || exit;

/**
 * WP-Sweep version.
 *
 * @since 1.0.0
 */
define( 'WP_SWEEP_VERSION', '2.0.0' );

/**
 * WP-Sweep main file.
 *
 * @since 1.0.0
 */
define( 'WP_SWEEP_MAIN_FILE', __FILE__ );

/**
 * Absolute path to the plugin directory, with a trailing slash.
 *
 * @since 2.0.0
 */
define( 'WP_SWEEP_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL of the plugin directory, with a trailing slash.
 *
 * Derived from the main file rather than assembled from a hardcoded slug, so
 * the script still resolves when the plugin is installed under a directory
 * name other than wp-sweep.
 *
 * @since 2.0.0
 */
define( 'WP_SWEEP_URL', plugin_dir_url( __FILE__ ) );

/**
 * The directory name the plugin is installed under.
 *
 * @since 2.0.0
 */
define( 'WP_SWEEP_SLUG', dirname( plugin_basename( __FILE__ ) ) );

require WP_SWEEP_DIR . 'includes/class-sweep.php';
require WP_SWEEP_DIR . 'includes/class-sweep-api.php';

/**
 * WP REST API.
 */
new Sweep_Api();

/**
 * Init WP-Sweep.
 */
Sweep::get_instance();
