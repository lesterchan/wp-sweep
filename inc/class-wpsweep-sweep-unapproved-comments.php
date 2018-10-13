<?php
/**
 * WP-Sweep sweep: Unapproved Comments
 *
 * @package wp-sweep
 */

/**
 * Class WPSweep_Unapproved_Sweep_Comments
 */
class WPSweep_Sweep_Unapproved_Comments extends WPSweep_Sweep {

	const SLUG = 'unapproved_comments';

	const TOTAL = 'comments';

	public function count() {
		return (int) $this->wp_db->get_var( $this->wp_db->prepare( "SELECT COUNT(comment_ID) FROM {$this->wp_db->comments} WHERE comment_approved = %s", '0' ) );
	}

	public function details() {
		return $this->wp_db->get_col( $this->wp_db->prepare( "SELECT comment_author FROM {$this->wp_db->comments} WHERE comment_approved = %s LIMIT %d", '0', $this->wp_sweep->limit_details ) );
	}

	public function sweep() {
		$query = $this->wp_db->get_col( $this->wp_db->prepare( "SELECT comment_ID FROM {$this->wp_db->comments} WHERE comment_approved = %s", '0' ) );

		foreach ( $query as $id ) {
			wp_delete_comment( (int) $id, true );
		}

		// translators: %s is the Unapproved Comments count.
		return count( $query );
	}

	public function get_name() {
		return __( 'Unapproved Comments', 'wp-sweep' );
	}
}
