<?php

/**
 * WP-Sweep
 *
 * @package wp-sweep
 *
 * @wordpress-plugin
 * Plugin Name: WP-Sweep
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: WP-Sweep allows you to clean up unused, orphaned and duplicated data in your WordPress. It cleans up revisions, auto drafts, unapproved comments, spam comments, trashed comments, orphan post meta, orphan comment meta, orphan user meta, orphan term relationships, unused terms, duplicated post meta, duplicated comment meta, duplicated user meta and transient options. It also optimizes your database tables.
 * Version: 1.1.8
 * Author: Lester 'GaMerZ' Chan
 * Author URI: https://lesterchan.net
 * Text Domain: wp-sweep
 * License: GPL2
 *
 *     Copyright 2023  Lester Chan  (email : lesterchan@gmail.com)
 *
 *     This program is free software; you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License, version 2, as
 *     published by the Free Software Foundation.
 *
 *     This program is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *     GNU General Public License for more details.
 *
 *     You should have received a copy of the GNU General Public License
 *     along with this program; if not, write to the Free Software
 *     Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * WP-Sweep version
 *
 * @since 1.0.0
 */
define( 'WP_SWEEP_VERSION', '1.1.8' );

/**
 * WP-Sweep main file
 */
define( 'WP_SWEEP_MAIN_FILE', __FILE__ );

require __DIR__ . '/inc/class-wpsweep.php';
require __DIR__ . '/inc/class-wpsweep-api.php';

/**
 * WP Rest API
 */
new WPSweep_Api();

/**
 * Init WP-Sweep
 */
WPSweep::get_instance();
