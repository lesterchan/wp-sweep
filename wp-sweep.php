<?php
/**
 * WP-Sweep wp-sweep.php
 *
 * @package wp-sweep
 */

/*
Plugin Name: WP-Sweep
Plugin URI: https://lesterchan.net/portfolio/programming/php/
Description: WP-Sweep allows you to clean up unused, orphaned and duplicated data in your WordPress. It cleans up revisions, auto drafts, unapproved comments, spam comments, trashed comments, orphan post meta, orphan comment meta, orphan user meta, orphan term relationships, unused terms, duplicated post meta, duplicated comment meta, duplicated user meta and transient options. It also optimizes your database tables.
Version: 1.1.0
Author: Lester 'GaMerZ' Chan
Author URI: https://lesterchan.net
Text Domain: wp-sweep
License: GPL2
*/

/*
	Copyright 2018  Lester Chan  (email : lesterchan@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * WP-Sweep version
 *
 * @since 1.0.0
 */
define( 'WP_SWEEP_VERSION', '1.1.0' );

/**
 * WP Rest API
 */
require __DIR__ . '/inc/class-wpsweep-api.php';
new WPSweep_Api();

/**
 * WP-Sweep class
 *
 * @since 1.0.0
 */
class WPSweep {
	/**
	 * Limit the number of items to show for sweep details
	 *
	 * @since 1.0.3
	 *
	 * @access public
	 * @var int
	 */
	public $limit_details = 500;

	/**
	 * Static instance
	 *
	 * @since 1.0.0
	 *
	 * @access private
	 * @var WPSweep $instance
	 */
	private static $instance;

	/**
	 * Constructor method
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function __construct() {
		// Add Plugin Hooks.
		add_action( 'plugins_loaded', array( $this, 'add_hooks' ) );

		// Load Translation.
		load_plugin_textdomain( 'wp-sweep' );

		// Plugin Activation/Deactivation.
		register_activation_hook( __FILE__, array( $this, 'plugin_activation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'plugin_deactivation' ) );
	}

	/**
	 * Initializes the plugin object and returns its instance
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @return object The plugin object instance
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Init this plugin
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @return void
	 */
	public function init() {
		// include class for WP CLI command.
		if ( defined( 'WP_CLI' ) ) {
			require __DIR__ . '/inc/class-wpsweep-command.php';
			WP_CLI::add_command( 'sweep', 'WPSweep_Command' );
		}
	}

	/**
	 * Adds all the plugin hooks
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @return void
	 */
	public function add_hooks() {
		// Actions.
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'wp_ajax_sweep_details', array( $this, 'ajax_sweep_details' ) );
		add_action( 'wp_ajax_sweep', array( $this, 'ajax_sweep' ) );
	}

	/**
	 * Enqueue JS/CSS files used for admin
	 *
	 * @since 1.0.3
	 *
	 * @access public
	 * @param string $hook Page hook.
	 * @return void
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 'wp-sweep/admin.php' !== $hook ) {
			return;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			wp_enqueue_script( 'wp-sweep', plugins_url( 'wp-sweep/js/wp-sweep.js' ), array( 'jquery' ), WP_SWEEP_VERSION, true );
		} else {
			wp_enqueue_script( 'wp-sweep', plugins_url( 'wp-sweep/js/wp-sweep.min.js' ), array( 'jquery' ), WP_SWEEP_VERSION, true );
		}

		wp_localize_script(
			'wp-sweep', 'wp_sweep', array(
				'text_close_warning' => __( 'Sweeping is in progress. If you leave now, the process won\'t be completed.', 'wp-sweep' ),
				'text_sweep'         => __( 'Sweep', 'wp-sweep' ),
				'text_sweep_all'     => __( 'Sweep All', 'wp-sweep' ),
				'text_sweeping'      => __( 'Sweeping...', 'wp-sweep' ),
				'text_na'            => __( 'N/A', 'wp-sweep' ),
			)
		);
	}

	/**
	 * Admin menu
	 *
	 * @since 1.0.3
	 *
	 * @access public
	 * @return void
	 */
	public function admin_menu() {
		add_management_page( _x( 'Sweep', 'Page title', 'wp-sweep' ), _x( 'Sweep', 'Menu title', 'wp-sweep' ), 'manage_options', 'wp-sweep/admin.php' );
	}


	/**
	 * Sweep Details loaded via AJAX
	 *
	 * @since 1.0.3
	 *
	 * @access public
	 * @return void
	 */
	public function ajax_sweep_details() {
		if ( ! empty( $_GET['action'] )
			&& ! empty( $_GET['sweep_name'] )
			&& ! empty( $_GET['sweep_type'] )
		) {
			// Verify Referer.
			if ( ! check_admin_referer( 'wp_sweep_details_' . $_GET['sweep_name'] ) ) {
				wp_send_json_error(
					array(
						'error' => __( 'Failed to verify referrer.', 'wp-sweep' ),
					)
				);
			} elseif ( 'sweep_details' === $_GET['action'] ) {
				wp_send_json_success( $this->details( $_GET['sweep_name'] ) );
			}
		}
	}

	/**
	 * Sweep via AJAX
	 *
	 * @since 1.0.3
	 *
	 * @access public
	 * @return void
	 */
	public function ajax_sweep() {
		if ( ! empty( $_GET['action'] )
			&& ! empty( $_GET['sweep_name'] )
			&& ! empty( $_GET['sweep_type'] )
		) {
			// Verify Referer.
			if ( ! check_admin_referer( 'wp_sweep_' . $_GET['sweep_name'] ) ) {
				wp_send_json_error(
					array(
						'error' => __( 'Failed to verify referrer.', 'wp-sweep' ),
					)
				);
			} elseif ( 'sweep' === $_GET['action'] ) {
				$sweep       = $this->sweep( $_GET['sweep_name'] );
				$count       = $this->count( $_GET['sweep_name'] );
				$total_count = $this->total_count( $_GET['sweep_type'] );
				$total_stats = array();
				switch ( $_GET['sweep_type'] ) {
					case 'posts':
					case 'postmeta':
						$total_stats = array(
							'posts'    => $this->total_count( 'posts' ),
							'postmeta' => $this->total_count( 'postmeta' ),
						);
						break;
					case 'comments':
					case 'commentmeta':
						$total_stats = array(
							'comments'    => $this->total_count( 'comments' ),
							'commentmeta' => $this->total_count( 'commentmeta' ),
						);
						break;
					case 'users':
					case 'usermeta':
						$total_stats = array(
							'users'    => $this->total_count( 'users' ),
							'usermeta' => $this->total_count( 'usermeta' ),
						);
						break;
					case 'term_relationships':
					case 'term_taxonomy':
					case 'terms':
					case 'termmeta':
						$total_stats = array(
							'term_relationships' => $this->total_count( 'term_relationships' ),
							'term_taxonomy'      => $this->total_count( 'term_taxonomy' ),
							'terms'              => $this->total_count( 'terms' ),
							'termmeta'           => $this->total_count( 'termmeta' ),
						);
						break;
					case 'options':
						$total_stats = array( 'options' => $this->total_count( 'options' ) );
						break;
					case 'tables':
						$total_stats = array( 'tables' => $this->total_count( 'tables' ) );
						break;
				}

				wp_send_json_success(
					array(
						'sweep'      => $sweep,
						'count'      => $count,
						'total'      => $total_count,
						'percentage' => $this->format_percentage( $count, $total_count ),
						'stats'      => $total_stats,
					)
				);
			}
		}
	}

	/**
	 * Count the number of total items belonging to each sweep
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @param string $name Sweep name.
	 * @return int Number of items belonging to each sweep
	 */
	public function total_count( $name ) {
		global $wpdb;

		$count = 0;

		switch ( $name ) {
			case 'posts':
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts" );
				break;
			case 'postmeta':
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->postmeta" );
				break;
			case 'comments':
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->comments" );
				break;
			case 'commentmeta':
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->commentmeta" );
				break;
			case 'users':
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->users" );
				break;
			case 'usermeta':
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->usermeta" );
				break;
			case 'term_relationships':
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->term_relationships" );
				break;
			case 'term_taxonomy':
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->term_taxonomy" );
				break;
			case 'terms':
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->terms" );
				break;
			case 'termmeta':
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->termmeta" );
				break;
			case 'options':
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->options" );
				break;
			case 'tables':
				$count = count( $wpdb->get_col( 'SHOW TABLES' ) );
				break;
		}

		return apply_filters( 'wp_sweep_total_count', $count, $name );
	}

	/**
	 * Count the number of items belonging to each sweep
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @param string $name Sweep name.
	 * @return int Number of items belonging to each sweep
	 */
	public function count( $name ) {
		global $wpdb;

		$count = 0;

		switch ( $name ) {
			case 'revisions':
				$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = %s", 'revision' ) );
				break;
			case 'auto_drafts':
				$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_status = %s", 'auto-draft' ) );
				break;
			case 'deleted_posts':
				$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_status = %s", 'trash' ) );
				break;
			case 'unapproved_comments':
				$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(comment_ID) FROM $wpdb->comments WHERE comment_approved = %s", '0' ) );
				break;
			case 'spam_comments':
				$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(comment_ID) FROM $wpdb->comments WHERE comment_approved = %s", 'spam' ) );
				break;
			case 'deleted_comments':
				$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(comment_ID) FROM $wpdb->comments WHERE (comment_approved = %s OR comment_approved = %s)", 'trash', 'post-trashed' ) );
				break;
			case 'transient_options':
				$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(option_id) FROM $wpdb->options WHERE option_name LIKE(%s)", '%_transient_%' ) );
				break;
			case 'orphan_postmeta':
				$count = $wpdb->get_var( "SELECT COUNT(meta_id) FROM $wpdb->postmeta WHERE post_id NOT IN (SELECT ID FROM $wpdb->posts)" );
				break;
			case 'orphan_commentmeta':
				$count = $wpdb->get_var( "SELECT COUNT(meta_id) FROM $wpdb->commentmeta WHERE comment_id NOT IN (SELECT comment_ID FROM $wpdb->comments)" );
				break;
			case 'orphan_usermeta':
				$count = $wpdb->get_var( "SELECT COUNT(umeta_id) FROM $wpdb->usermeta WHERE user_id NOT IN (SELECT ID FROM $wpdb->users)" );
				break;
			case 'orphan_termmeta':
				$count = $wpdb->get_var( "SELECT COUNT(meta_id) FROM $wpdb->termmeta WHERE term_id NOT IN (SELECT term_id FROM $wpdb->terms)" );
				break;
			case 'orphan_term_relationships':
				$orphan_term_relationships_sql = implode( "','", array_map( 'esc_sql', $this->get_excluded_taxonomies() ) );
				$count                         = $wpdb->get_var( "SELECT COUNT(object_id) FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy NOT IN ('$orphan_term_relationships_sql') AND tr.object_id NOT IN (SELECT ID FROM $wpdb->posts)" ); // WPCS: unprepared SQL ok.
				break;
			case 'unused_terms':
				$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(t.term_id) FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.count = %d AND t.term_id NOT IN (" . implode( ',', $this->get_excluded_termids() ) . ')', 0 ) ); // WPCS: unprepared SQL ok.
				break;
			case 'duplicated_postmeta':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT COUNT(meta_id) AS count FROM $wpdb->postmeta GROUP BY post_id, meta_key, meta_value HAVING count > %d", 1 ) );
				if ( is_array( $query ) ) {
					$count = array_sum( array_map( 'intval', $query ) );
				}
				break;
			case 'duplicated_commentmeta':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT COUNT(meta_id) AS count FROM $wpdb->commentmeta GROUP BY comment_id, meta_key, meta_value HAVING count > %d", 1 ) );
				if ( is_array( $query ) ) {
					$count = array_sum( array_map( 'intval', $query ) );
				}
				break;
			case 'duplicated_usermeta':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT COUNT(umeta_id) AS count FROM $wpdb->usermeta GROUP BY user_id, meta_key, meta_value HAVING count > %d", 1 ) );
				if ( is_array( $query ) ) {
					$count = array_sum( array_map( 'intval', $query ) );
				}
				break;
			case 'duplicated_termmeta':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT COUNT(meta_id) AS count FROM $wpdb->termmeta GROUP BY term_id, meta_key, meta_value HAVING count > %d", 1 ) );
				if ( is_array( $query ) ) {
					$count = array_sum( array_map( 'intval', $query ) );
				}
				break;
			case 'optimize_database':
				$count = count( $wpdb->get_col( 'SHOW TABLES' ) );
				break;
			case 'oembed_postmeta':
				$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(meta_id) FROM $wpdb->postmeta WHERE meta_key LIKE(%s)", '%_oembed_%' ) );
				break;
		}

		return apply_filters( 'wp_sweep_count', $count, $name );
	}

	/**
	 * Return more details about a sweep
	 *
	 * @since 1.0.3
	 *
	 * @access public
	 * @param string $name Sweep name.
	 * @return array Details of items belonging to each sweep
	 */
	public function details( $name ) {
		global $wpdb;

		$details = array();

		switch ( $name ) {
			case 'revisions':
				$details = $wpdb->get_col( $wpdb->prepare( "SELECT post_title FROM $wpdb->posts WHERE post_type = %s LIMIT %d", 'revision', $this->limit_details ) );
				break;
			case 'auto_drafts':
				$details = $wpdb->get_col( $wpdb->prepare( "SELECT post_title FROM $wpdb->posts WHERE post_status = %s LIMIT %d", 'auto-draft', $this->limit_details ) );
				break;
			case 'deleted_posts':
				$details = $wpdb->get_col( $wpdb->prepare( "SELECT post_title FROM $wpdb->posts WHERE post_status = %s LIMIT %d", 'trash', $this->limit_details ) );
				break;
			case 'unapproved_comments':
				$details = $wpdb->get_col( $wpdb->prepare( "SELECT comment_author FROM $wpdb->comments WHERE comment_approved = %s LIMIT %d", '0', $this->limit_details ) );
				break;
			case 'spam_comments':
				$details = $wpdb->get_col( $wpdb->prepare( "SELECT comment_author FROM $wpdb->comments WHERE comment_approved = %s LIMIT %d", 'spam', $this->limit_details ) );
				break;
			case 'deleted_comments':
				$details = $wpdb->get_col( $wpdb->prepare( "SELECT comment_author FROM $wpdb->comments WHERE (comment_approved = %s OR comment_approved = %s) LIMIT %d", 'trash', 'post-trashed', $this->limit_details ) );
				break;
			case 'transient_options':
				$details = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE(%s) LIMIT %d", '%_transient_%', $this->limit_details ) );
				break;
			case 'orphan_postmeta':
				$details = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM $wpdb->postmeta WHERE post_id NOT IN (SELECT ID FROM $wpdb->posts) LIMIT %d", $this->limit_details ) );
				break;
			case 'orphan_commentmeta':
				$details = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM $wpdb->commentmeta WHERE comment_id NOT IN (SELECT comment_ID FROM $wpdb->comments) LIMIT %d", $this->limit_details ) );
				break;
			case 'orphan_usermeta':
				$details = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM $wpdb->usermeta WHERE user_id NOT IN (SELECT ID FROM $wpdb->users) LIMIT %d", $this->limit_details ) );
				break;
			case 'orphan_termmeta':
				$details = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM $wpdb->termmeta WHERE term_id NOT IN (SELECT term_id FROM $wpdb->terms) LIMIT %d", $this->limit_details ) );
				break;
			case 'orphan_term_relationships':
				$orphan_term_relationships_sql = implode( "','", array_map( 'esc_sql', $this->get_excluded_taxonomies() ) );
				$details                       = $wpdb->get_col( $wpdb->prepare( "SELECT tt.taxonomy FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy NOT IN ('$orphan_term_relationships_sql') AND tr.object_id NOT IN (SELECT ID FROM $wpdb->posts) LIMIT %d", $this->limit_details ) ); // WPCS: unprepared SQL ok.
				break;
			case 'unused_terms':
				$details = $wpdb->get_col( $wpdb->prepare( "SELECT t.name FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.count = %d AND t.term_id NOT IN (" . implode( ',', $this->get_excluded_termids() ) . ') LIMIT %d', 0, $this->limit_details ) ); // WPCS: unprepared SQL ok.
				break;
			case 'duplicated_postmeta':
				$query   = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(meta_id) AS count, meta_key FROM $wpdb->postmeta GROUP BY post_id, meta_key, meta_value HAVING count > %d LIMIT %d", 1, $this->limit_details ) );
				$details = array();
				if ( $query ) {
					foreach ( $query as $meta ) {
						$details[] = $meta->meta_key;
					}
				}
				break;
			case 'duplicated_commentmeta':
				$query   = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(meta_id) AS count, meta_key FROM $wpdb->commentmeta GROUP BY comment_id, meta_key, meta_value HAVING count > %d LIMIT %d", 1, $this->limit_details ) );
				$details = array();
				if ( $query ) {
					foreach ( $query as $meta ) {
						$details[] = $meta->meta_key;
					}
				}
				break;
			case 'duplicated_usermeta':
				$query   = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(umeta_id) AS count, meta_key FROM $wpdb->usermeta GROUP BY user_id, meta_key, meta_value HAVING count > %d LIMIT %d", 1, $this->limit_details ) );
				$details = array();
				if ( $query ) {
					foreach ( $query as $meta ) {
						$details[] = $meta->meta_key;
					}
				}
				break;
			case 'duplicated_termmeta':
				$query   = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(meta_id) AS count, meta_key FROM $wpdb->termmeta GROUP BY term_id, meta_key, meta_value HAVING count > %d LIMIT %d", 1, $this->limit_details ) );
				$details = array();
				if ( $query ) {
					foreach ( $query as $meta ) {
						$details[] = $meta->meta_key;
					}
				}
				break;
			case 'optimize_database':
				$details = $wpdb->get_col( 'SHOW TABLES' );
				break;
			case 'oembed_postmeta':
				$details = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM $wpdb->postmeta WHERE meta_key LIKE(%s) LIMIT %d", '%_oembed_%', $this->limit_details ) );
				break;
		}

		return apply_filters( 'wp_sweep_details', $details, $name );
	}

	/**
	 * Does the sweeping/cleaning up
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @param string $name Sweep name.
	 * @return string Processed message
	 */
	public function sweep( $name ) {
		global $wpdb;

		$message = '';

		switch ( $name ) {
			case 'revisions':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s", 'revision' ) );
				if ( $query ) {
					foreach ( $query as $id ) {
						wp_delete_post_revision( (int) $id );
					}

					// translators: %s is Revisions count.
					$message = sprintf( __( '%s Revisions Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'auto_drafts':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status = %s", 'auto-draft' ) );
				if ( $query ) {
					foreach ( $query as $id ) {
						wp_delete_post( (int) $id, true );
					}

					// translators: %s is the Auto Drafts count.
					$message = sprintf( __( '%s Auto Drafts Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'deleted_posts':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status = %s", 'trash' ) );
				if ( $query ) {
					foreach ( $query as $id ) {
						wp_delete_post( $id, true );
					}

					// translators: %s is the Deleted Posts count.
					$message = sprintf( __( '%s Deleted Posts Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'unapproved_comments':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_approved = %s", '0' ) );
				if ( $query ) {
					foreach ( $query as $id ) {
						wp_delete_comment( (int) $id, true );
					}

					// translators: %s is the Unapproved Comments count.
					$message = sprintf( __( '%s Unapproved Comments Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'spam_comments':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_approved = %s", 'spam' ) );
				if ( $query ) {
					foreach ( $query as $id ) {
						wp_delete_comment( (int) $id, true );
					}

					// translators: %s is the Spam Comments count.
					$message = sprintf( __( '%s Spam Comments Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'deleted_comments':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE (comment_approved = %s OR comment_approved = %s)", 'trash', 'post-trashed' ) );
				if ( $query ) {
					foreach ( $query as $id ) {
						wp_delete_comment( (int) $id, true );
					}

					// translators: %s is the Trash Comments count.
					$message = sprintf( __( '%s Trash Comments Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'transient_options':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE(%s)", '%_transient_%' ) );
				if ( $query ) {
					foreach ( $query as $option_name ) {
						if ( strpos( $option_name, '_site_transient_' ) !== false ) {
							delete_site_transient( str_replace( '_site_transient_', '', $option_name ) );
						} else {
							delete_transient( str_replace( '_transient_', '', $option_name ) );
						}
					}

					// translators: %s is the Transient Options count.
					$message = sprintf( __( '%s Transient Options Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'orphan_postmeta':
				$query = $wpdb->get_results( "SELECT post_id, meta_key FROM $wpdb->postmeta WHERE post_id NOT IN (SELECT ID FROM $wpdb->posts)" );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$post_id = (int) $meta->post_id;
						if ( 0 === $post_id ) {
							$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s", $post_id, $meta->meta_key ) );
						} else {
							delete_post_meta( $post_id, $meta->meta_key );
						}
					}

					// translators: %s is the Orphaned Post Meta count.
					$message = sprintf( __( '%s Orphaned Post Meta Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'orphan_commentmeta':
				$query = $wpdb->get_results( "SELECT comment_id, meta_key FROM $wpdb->commentmeta WHERE comment_id NOT IN (SELECT comment_ID FROM $wpdb->comments)" );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$comment_id = (int) $meta->comment_id;
						if ( 0 === $comment_id ) {
							$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->commentmeta WHERE comment_id = %d AND meta_key = %s", $comment_id, $meta->meta_key ) );
						} else {
							delete_comment_meta( $comment_id, $meta->meta_key );
						}
					}

					// translators: %s is the Orphaned Comment Meta count.
					$message = sprintf( __( '%s Orphaned Comment Meta Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'orphan_usermeta':
				$query = $wpdb->get_results( "SELECT user_id, meta_key FROM $wpdb->usermeta WHERE user_id NOT IN (SELECT ID FROM $wpdb->users)" );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$user_id = (int) $meta->user_id;
						if ( 0 === $user_id ) {
							$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE user_id = %d AND meta_key = %s", $user_id, $meta->meta_key ) );
						} else {
							delete_user_meta( $user_id, $meta->meta_key );
						}
					}

					// translators: %s is the Orphaned User Meta count.
					$message = sprintf( __( '%s Orphaned User Meta Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'orphan_termmeta':
				$query = $wpdb->get_results( "SELECT term_id, meta_key FROM $wpdb->termmeta WHERE term_id NOT IN (SELECT term_id FROM $wpdb->terms)" );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$term_id = (int) $meta->term_id;
						if ( 0 === $term_id ) {
							$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->termmeta WHERE term_id = %d AND meta_key = %s", $term_id, $meta->meta_key ) );
						} else {
							delete_term_meta( $term_id, $meta->meta_key );
						}
					}

					// translators: %s is the Orphaned Term Meta count.
					$message = sprintf( __( '%s Orphaned Term Meta Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'orphan_term_relationships':
				$query = $wpdb->get_results( "SELECT tr.object_id, tr.term_taxonomy_id, tt.term_id, tt.taxonomy FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy NOT IN ('" . implode( '\',\'', $this->get_excluded_taxonomies() ) . "') AND tr.object_id NOT IN (SELECT ID FROM $wpdb->posts)" ); // WPCS: unprepared SQL ok.
				if ( $query ) {
					foreach ( $query as $tax ) {
						$wp_remove_object_terms = wp_remove_object_terms( (int) $tax->object_id, (int) $tax->term_id, $tax->taxonomy );
						if ( true !== $wp_remove_object_terms ) {
							$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->term_relationships WHERE object_id = %d AND term_taxonomy_id = %d", $tax->object_id, $tax->term_taxonomy_id ) );
						}
					}

					// translators: %s is the Orphaned Term Relationships count.
					$message = sprintf( __( '%s Orphaned Term Relationships Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'unused_terms':
				$query = $wpdb->get_results( $wpdb->prepare( "SELECT tt.term_taxonomy_id, t.term_id, tt.taxonomy FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.count = %d AND t.term_id NOT IN (" . implode( ',', $this->get_excluded_termids() ) . ')', 0 ) ); // WPCS: unprepared SQL ok.
				if ( $query ) {
					$check_wp_terms = false;
					foreach ( $query as $tax ) {
						if ( taxonomy_exists( $tax->taxonomy ) ) {
							wp_delete_term( (int) $tax->term_id, $tax->taxonomy );
						} else {
							$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->term_taxonomy WHERE term_taxonomy_id = %d", (int) $tax->term_taxonomy_id ) );
							$check_wp_terms = true;
						}
					}
					// We need this for invalid taxonomies.
					if ( $check_wp_terms ) {
						$wpdb->get_results( "DELETE FROM $wpdb->terms WHERE term_id NOT IN (SELECT term_id FROM $wpdb->term_taxonomy)" );
					}

					// translators: %s is the Unused Terms count.
					$message = sprintf( __( '%s Unused Terms Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'duplicated_postmeta':
				$query = $wpdb->get_results( $wpdb->prepare( "SELECT GROUP_CONCAT(meta_id ORDER BY meta_id DESC) AS ids, post_id, COUNT(*) AS count FROM $wpdb->postmeta GROUP BY post_id, meta_key, meta_value HAVING count > %d", 1 ) );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$ids = array_map( 'intval', explode( ',', $meta->ids ) );
						array_pop( $ids );
						$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_id IN (" . implode( ',', $ids ) . ') AND post_id = %d', (int) $meta->post_id ) ); // WPCS: unprepared SQL ok.
					}

					// translators: %s is the Duplicated Post Meta count.
					$message = sprintf( __( '%s Duplicated Post Meta Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'duplicated_commentmeta':
				$query = $wpdb->get_results( $wpdb->prepare( "SELECT GROUP_CONCAT(meta_id ORDER BY meta_id DESC) AS ids, comment_id, COUNT(*) AS count FROM $wpdb->commentmeta GROUP BY comment_id, meta_key, meta_value HAVING count > %d", 1 ) );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$ids = array_map( 'intval', explode( ',', $meta->ids ) );
						array_pop( $ids );
						$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->commentmeta WHERE meta_id IN (" . implode( ',', $ids ) . ') AND comment_id = %d', (int) $meta->comment_id ) ); // WPCS: unprepared SQL ok.
					}

					// translators: %s is the Duplicated Comment Meta count.
					$message = sprintf( __( '%s Duplicated Comment Meta Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'duplicated_usermeta':
				$query = $wpdb->get_results( $wpdb->prepare( "SELECT GROUP_CONCAT(umeta_id ORDER BY umeta_id DESC) AS ids, user_id, COUNT(*) AS count FROM $wpdb->usermeta GROUP BY user_id, meta_key, meta_value HAVING count > %d", 1 ) );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$ids = array_map( 'intval', explode( ',', $meta->ids ) );
						array_pop( $ids );
						$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE umeta_id IN (" . implode( ',', $ids ) . ') AND user_id = %d', (int) $meta->user_id ) ); // WPCS: unprepared SQL ok.
					}

					// translators: %s is the Duplicated User Meta count.
					$message = sprintf( __( '%s Duplicated User Meta Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'duplicated_termmeta':
				$query = $wpdb->get_results( $wpdb->prepare( "SELECT GROUP_CONCAT(meta_id ORDER BY meta_id DESC) AS ids, term_id, COUNT(*) AS count FROM $wpdb->termmeta GROUP BY term_id, meta_key, meta_value HAVING count > %d", 1 ) );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$ids = array_map( 'intval', explode( ',', $meta->ids ) );
						array_pop( $ids );
						$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->termmeta WHERE meta_id IN (" . implode( ',', $ids ) . ') AND term_id = %d', (int) $meta->term_id ) ); // WPCS: unprepared SQL ok.
					}

					// translators: %s is the Duplicated Term Meta count.
					$message = sprintf( __( '%s Duplicated Term Meta Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'optimize_database':
				$query = $wpdb->get_col( 'SHOW TABLES' );
				if ( $query ) {
					$tables = implode( ',', $query );
					$wpdb->query( "OPTIMIZE TABLE $tables" ); // WPCS: unprepared SQL ok.

					// translators: %s is the Tables count.
					$message = sprintf( __( '%s Tables Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
			case 'oembed_postmeta':
				$query = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_key FROM $wpdb->postmeta WHERE meta_key LIKE(%s)", '%_oembed_%' ) );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$post_id = (int) $meta->post_id;
						if ( 0 === $post_id ) {
							$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s", $post_id, $meta->meta_key ) );
						} else {
							delete_post_meta( $post_id, $meta->meta_key );
						}
					}
					// translators: %s is the oEmbed Caches count.
					$message = sprintf( __( '%s oEmbed Caches In Post Meta Processed', 'wp-sweep' ), number_format_i18n( count( $query ) ) );
				}
				break;
		}

		return apply_filters( 'wp_sweep_sweep', $message, $name );
	}

	/**
	 * Format number to percentage, taking care of division by 0.
	 * Props @barisunver https://github.com/barisunver
	 *
	 * @since 1.0.2
	 *
	 * @access public
	 * @param int $current Current number.
	 * @param int $total Total number.
	 * @return string Number in percentage
	 */
	public function format_percentage( $current, $total ) {
		return ( $total > 0 ? round( ( $current / $total ) * 100, 2 ) : 0 ) . '%';
	}

	/**
	 * Get excluded taxonomies
	 *
	 * @since 1.0.8
	 *
	 * @access private
	 * @return array Excluded taxonomies
	 */
	private function get_excluded_taxonomies() {
		$excluded_taxonomies   = array();
		$excluded_taxonomies[] = 'link_category';

		return apply_filters( 'wp_sweep_excluded_taxonomies', $excluded_taxonomies );
	}

	/**
	 * Get excluded term IDs
	 *
	 * @since 1.0.3
	 *
	 * @access private
	 * @return array Excluded term IDs
	 */
	private function get_excluded_termids() {
		$default_term_ids = $this->get_default_taxonomy_termids();
		if ( ! is_array( $default_term_ids ) ) {
			$default_term_ids = array();
		}
		$parent_term_ids = $this->get_parent_termids();
		if ( ! is_array( $parent_term_ids ) ) {
			$parent_term_ids = array();
		}
		return array_merge( $default_term_ids, $parent_term_ids );
	}

	/**
	 * Get all default taxonomy term IDs
	 *
	 * @since 1.0.3
	 *
	 * @access private
	 * @return array Default taxonomy term IDs
	 */
	private function get_default_taxonomy_termids() {
		$taxonomies       = get_taxonomies();
		$default_term_ids = array();
		if ( $taxonomies ) {
			$tax = array_keys( $taxonomies );
			if ( $tax ) {
				foreach ( $tax as $t ) {
					$term_id = (int) get_option( 'default_' . $t );
					if ( $term_id > 0 ) {
						$default_term_ids[] = $term_id;
					}
				}
			}
		}
		return $default_term_ids;
	}

	/**
	 * Get terms that has a parent term
	 *
	 * @since 1.0.3
	 *
	 * @access private
	 * @return array Parent term IDs
	 */
	private function get_parent_termids() {
		global $wpdb;
		return $wpdb->get_col( $wpdb->prepare( "SELECT tt.parent FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.parent > %d", 0 ) );
	}

	/**
	 * What to do when the plugin is being deactivated
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @param boolean $network_wide Is network wide.
	 * @return void
	 */
	public function plugin_activation( $network_wide ) {
		if ( is_multisite() && $network_wide ) {
			$ms_sites = (array) get_sites();

			if ( 0 < count( $ms_sites ) ) {
				foreach ( $ms_sites as $ms_site ) {
					switch_to_blog( $ms_site->blog_id );
					$this->plugin_activated();
					restore_current_blog();
				}
			}
		} else {
			$this->plugin_activated();
		}
	}

	/**
	 * Perform plugin activation tasks
	 *
	 * @since 1.0.0
	 *
	 * @access private
	 * @return void
	 */
	private function plugin_activated() {
	}

	/**
	 * What to do when the plugin is being activated
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @param boolean $network_wide Is network wide.
	 * @return void
	 */
	public function plugin_deactivation( $network_wide ) {
		if ( is_multisite() && $network_wide ) {
			$ms_sites = (array) get_sites();

			if ( 0 < count( $ms_sites ) ) {
				foreach ( $ms_sites as $ms_site ) {
					switch_to_blog( $ms_site->blog_id );
					$this->plugin_deactivated();
					restore_current_blog();
				}
			}
		} else {
			$this->plugin_deactivated();
		}
	}

	/**
	 * Perform plugin deactivation tasks
	 *
	 * @since 1.0.0
	 *
	 * @access private
	 * @return void
	 */
	private function plugin_deactivated() {
	}
}

/**
 * Init WP-Sweep
 */
WPSweep::get_instance();
