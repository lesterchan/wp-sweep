<?php
/*
Plugin Name: WP-Sweep
Plugin URI: http://lesterchan.net/portfolio/programming/php/
Description: WP-Sweep allows you to clean up unused, orphaned and duplicated data in your WordPress. It cleans up revisions, auto drafts, unapproved comments, spam comments, trashed comments, orphan post meta, orphan comment meta, orphan user meta, orphan term relationships, unused terms, duplicated post meta, duplicated comment meta, duplicated user meta and transient options.
Version: 1.0.1
Author: Lester 'GaMerZ' Chan
Author URI: http://lesterchan.net
Text Domain: wp-sweep
*/

/*
    Copyright 2015  Lester Chan  (email : lesterchan@gmail.com)

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

/**
 * WP-Sweep version
 */
define( 'WP_SWEEP_VERSION', '1.0.1' );

/**
 * Class WPSweep
 */
class WPSweep {
	/**
	 * Variables
	 */
	private static $instance;

	/**
	 * Constructor method
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		// Add Plugin Hooks
		add_action( 'plugins_loaded', array( $this, 'add_hooks' ) );

		// Load Translation
		load_plugin_textdomain( 'wp-sweep', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		// Plugin Activation/Deactivation
		register_activation_hook( __FILE__, array( $this, 'plugin_activation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'plugin_deactivation' ) );
	}

	/**
	 * Initializes the plugin object and returns its instance
	 *
	 * @access public
	 * @return object the plugin object instance
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
	 * @access public
	 * @return void
	 */
	public function init() {}

	/**
	 * Adds all the plugin hooks
	 *
	 * @access public
	 * @return void
	 */
	public function add_hooks() {
		// Actions
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	/**
	 * Admin menu
	 *
	 * @access public
	 * @return void
	 */
	public function admin_menu() {
		add_management_page( __( 'Sweep', 'wp-sweep' ), __( 'Sweep', 'wp-sweep' ), 'activate_plugins', 'wp-sweep/admin.php' );
	}

	/**
	 * Count the number of items belonging to each table
	 *
	 * @access public
	 * @param string Table name
	 * @return integer Number of items belonging to each table
	 */
	public function table_count( $name ) {
		global $wpdb;

		$count = 0;

		switch( $name ) {
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
			case 'options':
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->options" );
				break;
		}

		return $count;
	}

	/**
	 * Count the number of items belonging to each sweep type
	 *
	 * @access public
	 * @param string Sweep type
	 * @return integer Number of items belonging to each sweep type
	 */
	public function count( $type ) {
		global $wpdb;

		$count = 0;

		switch( $type ) {
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
			case 'orphan_term_relationships':
				$count = $wpdb->get_var( "SELECT COUNT(object_id) FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy != 'link_category' AND tr.object_id NOT IN (SELECT ID FROM $wpdb->posts)" );
				break;
			case 'unused_terms':
				$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(t.term_id) FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.count = %d", 0 ) );
				break;
			case 'duplicated_postmeta':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT COUNT(meta_id) AS count FROM $wpdb->postmeta GROUP BY post_id, meta_key, meta_value HAVING count > %d", 1 ) );
				if( is_array( $query ) ) {
					$count = array_sum( array_map( 'intval', $query ) );
				}
				break;
			case 'duplicated_commentmeta':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT COUNT(meta_id) AS count FROM $wpdb->commentmeta GROUP BY comment_id, meta_key, meta_value HAVING count > %d", 1 ) );
				if( is_array( $query ) ) {
					$count = array_sum( array_map( 'intval', $query ) );
				}
				break;
			case 'duplicated_usermeta':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT COUNT(umeta_id) AS count FROM $wpdb->usermeta GROUP BY user_id, meta_key, meta_value HAVING count > %d", 1 ) );
				if( is_array( $query ) ) {
					$count = array_sum( array_map( 'intval', $query ) );
				}
				break;
		}

		return $count;
	}

	/**
	 * Does the sweeping/cleaning up
	 *
	 * @access public
	 * @param string Sweep type
	 * @return string Processed message
	 */
	public function sweep( $type ) {
		global $wpdb;

		$message = '';

		switch( $type ) {
			case 'revisions':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s", 'revision' ) );
				if( $query ) {
					foreach ( $query as $id ) {
						wp_delete_post_revision( $id );
					}

					$message = sprintf( __( '%d Revisions Processed', 'wp-sweep' ), number_format_i18n( sizeof( $query ) ) );
				}
				break;
			case 'auto_drafts':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status = %s", 'auto-draft' ) );
				if( $query ) {
					foreach ( $query as $id ) {
						wp_delete_post( $id, true );
					}

					$message = sprintf( __( '%d Auto Drafts Processed', 'wp-sweep' ), number_format_i18n( sizeof( $query ) ) );
				}
				break;
			case 'deleted_posts':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status = %s", 'trash' ) );
				if( $query ) {
					foreach ( $query as $id ) {
						wp_delete_post( $id, true );
					}

					$message = sprintf( __( '%d Deleted Posts Processed', 'wp-sweep' ), number_format_i18n( sizeof( $query ) ) );
				}
				break;
			case 'unapproved_comments':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_approved = %s", '0' ) );
				if( $query ) {
					foreach ( $query as $id ) {
						wp_delete_comment( $id, true );
					}

					$message = sprintf( __( '%d Unapproved Comments Processed', 'wp-sweep' ), number_format_i18n( sizeof( $query ) ) );
				}
				break;
			case 'spam_comments':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_approved = %s", 'spam' ) );
				if( $query ) {
					foreach ( $query as $id ) {
						wp_delete_comment( $id, true );
					}

					$message = sprintf( __( '%d Spam Comments Processed', 'wp-sweep' ), number_format_i18n( sizeof( $query ) ) );
				}
				break;
			case 'deleted_comments':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE (comment_approved = %s OR comment_approved = %s)", 'trash', 'post-trashed' ) );
				if( $query ) {
					foreach ( $query as $id ) {
						wp_delete_comment( $id, true );
					}

					$message = sprintf( __( '%d Trash Comments Processed', 'wp-sweep' ), number_format_i18n( sizeof( $query ) ) );
				}
				break;
			case 'transient_options':
				$query = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE(%s)", '%_transient_%' ) );
				if( $query ) {
					foreach ( $query as $option_name ) {
						if( strpos( $option_name, '_site_transient_' ) !== false ) {
							delete_site_transient( str_replace( '_site_transient_', '', $option_name ) );
						} else {
							delete_transient( str_replace( '_transient_', '', $option_name ) );
						}
					}

					$message = sprintf( __( '%d Transient Options Processed', 'wp-sweep' ), number_format_i18n( sizeof( $query ) ) );
				}
				break;
			case 'orphan_postmeta':
				$query = $wpdb->get_results( "SELECT post_id, meta_key FROM $wpdb->postmeta WHERE post_id NOT IN (SELECT ID FROM $wpdb->posts)" );
				if( $query ) {
					foreach ( $query as $meta ) {
						$post_id = intval( $meta->post_id );
						if( $post_id === 0 ) {
							$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s", $post_id, $meta->meta_key ) );
						} else {
							delete_post_meta( $post_id, $meta->meta_key );
						}
					}

					$message = sprintf( __( '%d Orphaned Post Meta Processed', 'wp-sweep' ), number_format_i18n( sizeof( $query ) ) );
				}
				break;
			case 'orphan_commentmeta':
				$query = $wpdb->get_results( "SELECT comment_id, meta_key FROM $wpdb->commentmeta WHERE comment_id NOT IN (SELECT comment_ID FROM $wpdb->comments)" );
				if( $query ) {
					foreach ( $query as $meta ) {
						$comment_id = intval( $meta->comment_id );
						if( $comment_id === 0 ) {
							$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->commentmeta WHERE comment_id = %d AND meta_key = %s", $comment_id, $meta->meta_key ) );
						} else {
							delete_comment_meta( $comment_id, $meta->meta_key );
						}
					}

					$message = sprintf( __( '%d Orphaned Comment Meta Processed', 'wp-sweep' ), number_format_i18n( sizeof( $query ) ) );
				}
				break;
			case 'orphan_usermeta':
				$query = $wpdb->get_results( "SELECT user_id, meta_key FROM $wpdb->usermeta WHERE user_id NOT IN (SELECT ID FROM $wpdb->users)" );
				if( $query ) {
					foreach ( $query as $meta ) {
						$user_id = intval( $meta->user_id );
						if( $user_id === 0 ) {
							$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE user_id = %d AND meta_key = %s", $user_id, $meta->meta_key ) );
						} else {
							delete_user_meta( $user_id, $meta->meta_key );
						}
					}

					$message = sprintf( __( '%d Orphaned User Meta Processed', 'wp-sweep' ), number_format_i18n( sizeof( $query ) ) );
				}
				break;
			case 'orphan_term_relationships':
				$query = $wpdb->get_results( "SELECT tr.object_id, tr.term_taxonomy_id, tt.taxonomy FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy != 'link_category' AND tr.object_id NOT IN (SELECT ID FROM $wpdb->posts)" );
				if( $query ) {
					foreach ( $query as $tax ) {
						wp_remove_object_terms( $tax->object_id, $tax->term_taxonomy_id, $tax->taxonomy );
					}

					$message = sprintf( __( '%d Orphaned Term Relationships Processed', 'wp-sweep' ), number_format_i18n( sizeof( $query ) ) );
				}
				break;
			case 'unused_terms':
				$query = $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, tt.taxonomy FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.count = %d", 0 ) );
				if( $query ) {
					foreach ( $query as $tax ) {
						wp_delete_term( $tax->term_id, $tax->taxonomy );
					}

					$message = sprintf( __( '%d Unused Terms Processed', 'wp-sweep' ), number_format_i18n( sizeof( $query ) ) );
				}
				break;
			case 'duplicated_postmeta':
				$query = $wpdb->get_results( $wpdb->prepare( "SELECT GROUP_CONCAT(meta_id ORDER BY meta_id DESC) AS ids, post_id, COUNT(*) AS count FROM $wpdb->postmeta GROUP BY post_id, meta_key, meta_value HAVING count > %d", 1 ) );
				if( $query ) {
					foreach ( $query as $meta ) {
						$ids = array_map( 'intval', explode( ',', $meta->ids ) );
						array_pop( $ids );
						$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_id IN (" . implode( ',', $ids ) . ") AND post_id = %d", intval( $meta->post_id ) ) );
					}

					$message = sprintf( __( '%d Duplicated Post Meta Processed', 'wp-sweep' ), number_format_i18n( sizeof( $query ) ) );
				}
				break;
			case 'duplicated_commentmeta':
				$query = $wpdb->get_results( $wpdb->prepare( "SELECT GROUP_CONCAT(meta_id ORDER BY meta_id DESC) AS ids, comment_id, COUNT(*) AS count FROM $wpdb->commentmeta GROUP BY comment_id, meta_key, meta_value HAVING count > %d", 1 ) );
				if( $query ) {
					foreach ( $query as $meta ) {
						$ids = array_map( 'intval', explode( ',', $meta->ids ) );
						array_pop( $ids );
						$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->commentmeta WHERE meta_id IN (" . implode( ',', $ids ) . ") AND comment_id = %d", intval( $meta->comment_id ) ) );
					}

					$message = sprintf( __( '%d Duplicated Comment Meta Processed', 'wp-sweep' ), number_format_i18n( sizeof( $query ) ) );
				}
				break;
			case 'duplicated_usermeta':
				$query = $wpdb->get_results( $wpdb->prepare( "SELECT GROUP_CONCAT(umeta_id ORDER BY umeta_id DESC) AS ids, user_id, COUNT(*) AS count FROM $wpdb->usermeta GROUP BY user_id, meta_key, meta_value HAVING count > %d", 1 ) );
				if( $query ) {
					foreach ( $query as $meta ) {
						$ids = array_map( 'intval', explode( ',', $meta->ids ) );
						array_pop( $ids );
						$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE umeta_id IN (" . implode( ',', $ids ) . ") AND user_id = %d", intval( $meta->user_id ) ) );
					}

					$message = sprintf( __( '%d Duplicated User Meta Processed', 'wp-sweep' ), number_format_i18n( sizeof( $query ) ) );
				}
				break;
		}

		return $message;
	}

	/**
	 * What to do when the plugin is being deactivated
	 *
	 * @access public
	 * @param boolean Is the plugin being network activated?
	 * @return void
	 */
	public function plugin_activation( $network_wide ) {
		if ( is_multisite() && $network_wide ) {
			$ms_sites = wp_get_sites();

			if( 0 < sizeof( $ms_sites ) ) {
				foreach ( $ms_sites as $ms_site ) {
					switch_to_blog( $ms_site['blog_id'] );
					$this->plugin_activated();
				}
			}

			restore_current_blog();
		} else {
			$this->plugin_activated();
		}
	}

	/**
	 * Perform plugin activation tasks
	 *
	 * @access private
	 * @return void
	 */
	private function plugin_activated() {}

	/**
	 * What to do when the plugin is being activated
	 *
	 * @access public
	 * @param boolean Is the plugin being network activated?
	 * @return void
	 */
	public function plugin_deactivation( $network_wide ) {
		if ( is_multisite() && $network_wide ) {
			$ms_sites = wp_get_sites();

			if( 0 < sizeof( $ms_sites ) ) {
				foreach ( $ms_sites as $ms_site ) {
					switch_to_blog( $ms_site['blog_id'] );
					$this->plugin_deactivated();
				}
			}

			restore_current_blog();
		} else {
			$this->plugin_deactivated();
		}
	}

	/**
	 * Perform plugin deactivation tasks
	 *
	 * @access private
	 * @return void
	 */
	private function plugin_deactivated() {}
}

/**
 * Init WP-Sweep
 *
 */
WPSweep::get_instance();