<?php
/**
 * WP-Sweep Comment sweep type
 *
 * @package wp-sweep
 */

/**
 * Abstract class WPSweep_Sweep_Type_Comment
 */
class WPSweep_Sweep_Type_Comment extends WPSweep_Sweep_Type {
	const SLUG = 'comment';

	const ORDER = 20;

	public $total_dependency = array(
		'comments'    => array( 'comments', 'commentmeta' ),
		'commentmeta' => array( 'comments', 'commentmeta' ),
	);

	public function register_total() {
		add_filter( 'wp_sweep_total_comments', array( $this, 'total_comments' ) );
		add_filter( 'wp_sweep_total_commentmeta', array( $this, 'total_commentmeta' ) );
	}

	public function total_comments() {
		return (int) $this->wp_db->get_var( "SELECT COUNT(*) FROM {$this->wp_db->comments}" );
	}

	public function total_commentmeta() {
		return (int) $this->wp_db->get_var( "SELECT COUNT(*) FROM {$this->wp_db->commentmeta}" );
	}

	public function get_name() {
		return __('Comment Sweep', 'wp-sweep' );
	}

	public function get_description() {
		return sprintf(
			/* translators: %1 is the number of comments, %2 is the number of comment meta */
			__( 'There are a total of <strong class="attention"><span class="sweep-count-type-comments">%1$s</span> Comments</strong> and <strong class="attention"><span class="sweep-count-type-commentmeta">%2$s</span> Comment Meta</strong>.', 'wp-sweep' ),
			number_format_i18n( $this->total_comments() ),
			number_format_i18n( $this->total_commentmeta() )
		);
	}
}
