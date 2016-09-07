<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables
$current_page = admin_url( 'tools.php?page=' . plugin_basename( 'wp-sweep/admin.php' ) );
$message = '';

// Sweeping
if ( ! empty( $_GET['sweep'] ) ) {
	if ( check_admin_referer( 'wp_sweep_' . $_GET['sweep'] ) ) {
		$message = WPSweep::get_instance()->sweep( $_GET['sweep'] );
	}
}

// Database Table Status
$total_posts                = WPSweep::get_instance()->total_count( 'posts' );
$total_postmeta             = WPSweep::get_instance()->total_count( 'postmeta' );
$total_comments             = WPSweep::get_instance()->total_count( 'comments' );
$total_commentmeta          = WPSweep::get_instance()->total_count( 'commentmeta' );
$total_users                = WPSweep::get_instance()->total_count( 'users' );
$total_usermeta             = WPSweep::get_instance()->total_count( 'usermeta' );
$total_term_relationships   = WPSweep::get_instance()->total_count( 'term_relationships' );
$total_term_taxonomy        = WPSweep::get_instance()->total_count( 'term_taxonomy' );
$total_terms                = WPSweep::get_instance()->total_count( 'terms' );
$total_termmeta             = WPSweep::get_instance()->total_count( 'termmeta' );
$total_options              = WPSweep::get_instance()->total_count( 'options' );
$total_tables               = WPSweep::get_instance()->total_count( 'tables' );

// Count
$revisions                  = WPSweep::get_instance()->count( 'revisions' );
$auto_drafts                = WPSweep::get_instance()->count( 'auto_drafts' );
$deleted_posts              = WPSweep::get_instance()->count( 'deleted_posts' );
$orphan_postmeta            = WPSweep::get_instance()->count( 'orphan_postmeta' );
$duplicated_postmeta        = WPSweep::get_instance()->count( 'duplicated_postmeta' );
$oembed_postmeta            = WPSweep::get_instance()->count( 'oembed_postmeta' );

$unapproved_comments        = WPSweep::get_instance()->count( 'unapproved_comments' );
$spam_comments              = WPSweep::get_instance()->count( 'spam_comments' );
$deleted_comments           = WPSweep::get_instance()->count( 'deleted_comments' );
$orphan_commentmeta         = WPSweep::get_instance()->count( 'orphan_commentmeta' );
$duplicated_commentmeta     = WPSweep::get_instance()->count( 'duplicated_commentmeta' );

$orphan_usermeta            = WPSweep::get_instance()->count( 'orphan_usermeta' );
$duplicated_usermeta        = WPSweep::get_instance()->count( 'duplicated_usermeta' );

$orphan_term_relationships  = WPSweep::get_instance()->count( 'orphan_term_relationships' );
$unused_terms               = WPSweep::get_instance()->count( 'unused_terms' );
$orphan_termmeta            = WPSweep::get_instance()->count( 'orphan_termmeta' );
$duplicated_termmeta        = WPSweep::get_instance()->count( 'duplicated_termmeta' );

$transient_options          = WPSweep::get_instance()->count( 'transient_options' );

?>
<style type="text/css">
	.table-sweep thead th {
		width: 12%;
	}
	.table-sweep thead th.col-sweep-details {
		width: 56%;
	}
	.table-sweep thead th.col-sweep-action {
		width: 20%;
	}
</style>
<div class="wrap">
	<h2><?php _e( 'WP-Sweep', 'wp-sweep' ); ?></h2>
	<div class="notice notice-warning">
		<p>
			<?php printf( __( 'Before you do any sweep, please <a href="%1$s" target="%2$s">backup your database</a> first because any sweep done is irreversible.', 'wp-sweep' ), 'https://wordpress.org/plugins/wp-dbmanager/', '_blank' ); ?>
		</p>
	</div>
	<p>
		<?php printf( __( 'For performance reasons, only %s items will be shown if you click Details', 'wp-sweep' ), number_format_i18n( WPSweep::get_instance()->limit_details ) ); ?>
	</p>
	<h3><?php _e( 'Post Sweep', 'wp-sweep' ); ?></h3>
	<p><?php printf( __( 'There are a total of <strong class="attention"><span class="sweep-count-type-posts">%1$s</span> Posts</strong> and <strong class="attention"><span class="sweep-count-type-postmeta">%2$s</span> Post Meta</strong>.', 'wp-sweep' ), number_format_i18n( $total_posts ), number_format_i18n( $total_postmeta ) ); ?></p>
	<div class="sweep-message"></div>
	<table class="widefat table-sweep">
		<thead>
			<tr>
				<th class="col-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></th>
				<th class="col-sweep-count"><?php _e( 'Count', 'wp-sweep' ); ?></th>
				<th class="col-sweep-percent"><?php _e( '% Of', 'wp-sweep' ); ?></th>
				<th class="col-sweep-action"><?php _e( 'Action', 'wp-sweep' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<strong><?php _e( 'Revisions', 'wp-sweep' ); ?></strong>
					<p class="sweep-details" style="display: none;"></p>
				</td>
				<td>
					<span class="sweep-count"><?php echo number_format_i18n( $revisions ); ?></span>
				</td>
				<td>
					<span class="sweep-percentage"><?php echo WPSweep::get_instance()->format_percentage( $revisions, $total_posts ); ?></span>
				</td>
				<td>
					<?php if ( ! empty( $revisions ) ) :  ?>
						<button data-action="sweep" data-sweep_name="revisions" data-sweep_type="posts" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_revisions' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
						<button data-action="sweep_details" data-sweep_name="revisions" data-sweep_type="posts" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_revisions' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
					<?php else : ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="alternate">
				<td>
					<strong><?php _e( 'Auto Drafts', 'wp-sweep' ); ?></strong>
					<p class="sweep-details" style="display: none;"></p>
				</td>
				<td>
					<span class="sweep-count"><?php echo number_format_i18n( $auto_drafts ); ?></span>
				</td>
				<td>
					<span class="sweep-percentage"><?php echo WPSweep::get_instance()->format_percentage( $auto_drafts, $total_posts ); ?></span>
				</td>
				<td>
					<?php if ( ! empty( $auto_drafts ) ) :  ?>
						<button data-action="sweep" data-sweep_name="auto_drafts" data-sweep_type="posts" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_auto_drafts' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
						<button data-action="sweep_details" data-sweep_name="auto_drafts" data-sweep_type="posts" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_auto_drafts' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
					<?php else : ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td>
					<strong><?php _e( 'Deleted Posts', 'wp-sweep' ); ?></strong>
					<p class="sweep-details" style="display: none;"></p>
				</td>
				<td>
					<span class="sweep-count"><?php echo number_format_i18n( $deleted_posts ); ?></span>
				</td>
				<td>
					<span class="sweep-percentage"><?php echo WPSweep::get_instance()->format_percentage( $deleted_posts, $total_posts ); ?></span>
				</td>
				<td>
					<?php if ( ! empty( $deleted_posts ) ) :  ?>
						<button data-action="sweep" data-sweep_name="deleted_posts" data-sweep_type="posts" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_deleted_posts' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
						<button data-action="sweep_details" data-sweep_name="deleted_posts" data-sweep_type="posts" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_deleted_posts' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
					<?php else : ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="alternate">
				<td>
					<strong><?php _e( 'Orphaned Post Meta', 'wp-sweep' ); ?></strong>
					<p class="sweep-details" style="display: none;"></p>
				</td>
				<td>
					<span class="sweep-count"><?php echo number_format_i18n( $orphan_postmeta ); ?></span>
				</td>
				<td>
					<span class="sweep-percentage"><?php echo WPSweep::get_instance()->format_percentage( $orphan_postmeta, $total_postmeta ); ?></span>
				</td>
				<td>
					<?php if ( ! empty( $orphan_postmeta ) ) :  ?>
						<button data-action="sweep" data-sweep_name="orphan_postmeta" data-sweep_type="postmeta" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_orphan_postmeta' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
						<button data-action="sweep_details" data-sweep_name="orphan_postmeta" data-sweep_type="postmeta" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_orphan_postmeta' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
					<?php else : ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td>
					<strong><?php _e( 'Duplicated Post Meta', 'wp-sweep' ); ?></strong>
					<p class="sweep-details" style="display: none;"></p>
				</td>
				<td>
					<span class="sweep-count"><?php echo number_format_i18n( $duplicated_postmeta ); ?></span>
				</td>
				<td>
					<span class="sweep-percentage"><?php echo WPSweep::get_instance()->format_percentage( $duplicated_postmeta, $total_postmeta ); ?></span>
				</td>
				<td>
					<?php if ( ! empty( $duplicated_postmeta ) ) :  ?>
						<button data-action="sweep" data-sweep_name="duplicated_postmeta" data-sweep_type="postmeta" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_duplicated_postmeta' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
						<button data-action="sweep_details" data-sweep_name="duplicated_postmeta" data-sweep_type="postmeta" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_duplicated_postmeta' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
					<?php else : ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="alternate">
				<td>
					<strong><?php _e( 'oEmbed Caches In Post Meta', 'wp-sweep' ); ?></strong>
					<p class="sweep-details" style="display: none;"></p>
				</td>
				<td>
					<span class="sweep-count"><?php echo number_format_i18n( $oembed_postmeta ); ?></span>
				</td>
				<td>
					<span class="sweep-percentage"><?php echo WPSweep::get_instance()->format_percentage( $oembed_postmeta, $total_postmeta ); ?></span>
				</td>
				<td>
					<?php if ( ! empty( $oembed_postmeta ) ) :  ?>
						<button data-action="sweep" data-sweep_name="oembed_postmeta" data-sweep_type="postmeta" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_oembed_postmeta' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
						<button data-action="sweep_details" data-sweep_name="oembed_postmeta" data-sweep_type="postmeta" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_oembed_postmeta' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
					<?php else : ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
	<?php do_action( 'wp_sweep_admin_post_sweep' ); ?>
	<p>&nbsp;</p>
	<h3><?php _e( 'Comment Sweep', 'wp-sweep' ); ?></h3>
	<p><?php printf( __( 'There are a total of <strong class="attention"><span class="sweep-count-type-comments">%1$s</span> Comments</strong> and <strong class="attention"><span class="sweep-count-type-commentmeta">%2$s</span> Comment Meta</strong>.', 'wp-sweep' ), number_format_i18n( $total_comments ), number_format_i18n( $total_commentmeta ) ); ?></p>
	<div class="sweep-message"></div>
	<table class="widefat table-sweep">
		<thead>
			<tr>
				<th class="col-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></th>
				<th class="col-sweep-count"><?php _e( 'Count', 'wp-sweep' ); ?></th>
				<th class="col-sweep-percent"><?php _e( '% Of', 'wp-sweep' ); ?></th>
				<th class="col-sweep-action"><?php _e( 'Action', 'wp-sweep' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<strong><?php _e( 'Unapproved Comments', 'wp-sweep' ); ?></strong>
					<p class="sweep-details" style="display: none;"></p>
				</td>
				<td>
					<span class="sweep-count"><?php echo number_format_i18n( $unapproved_comments ); ?></span>
				</td>
				<td>
					<span class="sweep-percentage"><?php echo WPSweep::get_instance()->format_percentage( $unapproved_comments, $total_comments ); ?></span>
				</td>
				<td>
					<?php if ( ! empty( $unapproved_comments ) ) :  ?>
						<button data-action="sweep" data-sweep_name="unapproved_comments" data-sweep_type="comments" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_unapproved_comments' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
						<button data-action="sweep_details" data-sweep_name="unapproved_comments" data-sweep_type="comments" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_unapproved_comments' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
					<?php else : ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="alternate">
				<td>
					<strong><?php _e( 'Spammed Comments', 'wp-sweep' ); ?></strong>
					<p class="sweep-details" style="display: none;"></p>
				</td>
				<td>
					<span class="sweep-count"><?php echo number_format_i18n( $spam_comments ); ?></span>
				</td>
				<td>
					<span class="sweep-percentage"><?php echo WPSweep::get_instance()->format_percentage( $spam_comments, $total_comments ); ?></span>
				</td>
				<td>
					<?php if ( ! empty( $spam_comments ) ) :  ?>
						<button data-action="sweep" data-sweep_name="spam_comments" data-sweep_type="comments" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_spam_comments' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
						<button data-action="sweep_details" data-sweep_name="spam_comments" data-sweep_type="comments" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_spam_comments' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
					<?php else : ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td>
					<strong><?php _e( 'Deleted Comments', 'wp-sweep' ); ?></strong>
					<p class="sweep-details" style="display: none;"></p>
				</td>
				<td>
					<span class="sweep-count"><?php echo number_format_i18n( $deleted_comments ); ?></span>
				</td>
				<td>
					<span class="sweep-percentage"><?php echo WPSweep::get_instance()->format_percentage( $deleted_comments, $total_comments ); ?></span>
				</td>
				<td>
					<?php if ( ! empty( $deleted_comments ) ) :  ?>
						<button data-action="sweep" data-sweep_name="deleted_comments" data-sweep_type="comments" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_deleted_comments' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
						<button data-action="sweep_details" data-sweep_name="deleted_comments" data-sweep_type="comments" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_deleted_comments' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
					<?php else : ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="alternate">
				<td>
					<strong><?php _e( 'Orphaned Comment Meta', 'wp-sweep' ); ?></strong>
					<p class="sweep-details" style="display: none;"></p>
				</td>
				<td>
					<span class="sweep-count"><?php echo number_format_i18n( $orphan_commentmeta ); ?></span>
				</td>
				<td>
					<span class="sweep-percentage"><?php echo WPSweep::get_instance()->format_percentage( $orphan_commentmeta, $total_commentmeta ); ?></span>
				</td>
				<td>
					<?php if ( ! empty( $orphan_commentmeta ) ) :  ?>
						<button data-action="sweep" data-sweep_name="orphan_commentmeta" data-sweep_type="commentmeta" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_orphan_commentmeta' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
						<button data-action="sweep_details" data-sweep_name="orphan_commentmeta" data-sweep_type="commentmeta" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_orphan_commentmeta' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
					<?php else : ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td>
					<strong><?php _e( 'Duplicated Comment Meta', 'wp-sweep' ); ?></strong>
					<p class="sweep-details" style="display: none;"></p>
				</td>
				<td>
					<span class="sweep-count"><?php echo number_format_i18n( $duplicated_commentmeta ); ?></span>
				</td>
				<td>
					<span class="sweep-percentage"><?php echo WPSweep::get_instance()->format_percentage( $duplicated_commentmeta, $total_commentmeta ); ?></span>
				</td>
				<td>
					<?php if ( ! empty( $duplicated_commentmeta ) ) :  ?>
						<button data-action="sweep" data-sweep_name="duplicated_commentmeta" data-sweep_type="commentmeta" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_duplicated_commentmeta' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
						<button data-action="sweep_details" data-sweep_name="duplicated_commentmeta" data-sweep_type="commentmeta" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_duplicated_commentmeta' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
					<?php else : ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
	<?php do_action( 'wp_sweep_admin_comment_sweep' ); ?>
	<p>&nbsp;</p>
	<h3><?php _e( 'User Sweep', 'wp-sweep' ); ?></h3>
	<p><?php printf( __( 'There are a total of <strong class="attention"><span class="sweep-count-type-users">%1$s</span> Users</strong> and <strong class="attention"><span class="sweep-count-type-usermeta">%2$s</span> User Meta</strong>.', 'wp-sweep' ), number_format_i18n( $total_users ), number_format_i18n( $total_usermeta ) ); ?></p>
	<div class="sweep-message"></div>
	<table class="widefat table-sweep">
		<thead>
			<tr>
				<th class="col-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></th>
				<th class="col-sweep-count"><?php _e( 'Count', 'wp-sweep' ); ?></th>
				<th class="col-sweep-percent"><?php _e( '% Of', 'wp-sweep' ); ?></th>
				<th class="col-sweep-action"><?php _e( 'Action', 'wp-sweep' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<strong><?php _e( 'Orphaned User Meta', 'wp-sweep' ); ?></strong>
					<p class="sweep-details" style="display: none;"></p>
				</td>
				<td>
					<span class="sweep-count"><?php echo number_format_i18n( $orphan_usermeta ); ?></span>
				</td>
				<td>
					<span class="sweep-percentage"><?php echo WPSweep::get_instance()->format_percentage( $orphan_usermeta, $total_usermeta ); ?></span>
				</td>
				<td>
					<?php if ( ! empty( $orphan_usermeta ) ) :  ?>
						<button data-action="sweep" data-sweep_name="orphan_usermeta" data-sweep_type="usermeta" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_orphan_usermeta' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
						<button data-action="sweep_details" data-sweep_name="orphan_usermeta" data-sweep_type="usermeta" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_orphan_usermeta' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
					<?php else : ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="alternate">
				<td>
					<strong><?php _e( 'Duplicated User Meta', 'wp-sweep' ); ?></strong>
					<p class="sweep-details" style="display: none;"></p>
				</td>
				<td>
					<span class="sweep-count"><?php echo number_format_i18n( $duplicated_usermeta ); ?></span>
				</td>
				<td>
					<span class="sweep-percentage"><?php echo WPSweep::get_instance()->format_percentage( $duplicated_usermeta, $total_usermeta ); ?></span>
				</td>
				<td>
					<?php if ( ! empty( $duplicated_usermeta ) ) :  ?>
						<button data-action="sweep" data-sweep_name="duplicated_usermeta" data-sweep_type="usermeta" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_duplicated_usermeta' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
						<button data-action="sweep_details" data-sweep_name="duplicated_usermeta" data-sweep_type="usermeta" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_duplicated_usermeta' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
					<?php else : ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
	<?php do_action( 'wp_sweep_admin_user_sweep' ); ?>
	<p>&nbsp;</p>
	<h3><?php _e( 'Term Sweep', 'wp-sweep' ); ?></h3>
	<p><?php printf( __( 'There are a total of <strong class="attention "><span class="sweep-count-type-terms">%1$s</span> Terms</strong>, <strong class="attention "><span class="sweep-count-type-termmeta">%2$s</span> Term Meta</strong>, <strong class="attention"><span class="sweep-count-type-term_taxonomy">%3$s</span> Term Taxonomy</strong> and <strong class="attention"><span class="sweep-count-type-term_relationships">%4$s</span> Term Relationships</strong>.', 'wp-sweep' ), number_format_i18n( $total_terms ), number_format_i18n( $total_termmeta ), number_format_i18n( $total_term_taxonomy ), number_format_i18n( $total_term_relationships ) ); ?></p>
	<div class="sweep-message"></div>
	<table class="widefat table-sweep">
		<thead>
			<tr>
				<th class="col-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></th>
				<th class="col-sweep-count"><?php _e( 'Count', 'wp-sweep' ); ?></th>
				<th class="col-sweep-percent"><?php _e( '% Of', 'wp-sweep' ); ?></th>
				<th class="col-sweep-action"><?php _e( 'Action', 'wp-sweep' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<tr>
			<td>
				<strong><?php _e( 'Orphaned Term Meta', 'wp-sweep' ); ?></strong>
				<p class="sweep-details" style="display: none;"></p>
			</td>
			<td>
				<span class="sweep-count"><?php echo number_format_i18n( $orphan_termmeta ); ?></span>
			</td>
			<td>
				<span class="sweep-percentage"><?php echo WPSweep::get_instance()->format_percentage( $orphan_termmeta, $total_termmeta ); ?></span>
			</td>
			<td>
				<?php if ( ! empty( $orphan_termmeta ) ) :  ?>
					<button data-action="sweep" data-sweep_name="orphan_termmeta" data-sweep_type="termmeta" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_orphan_termmeta' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
					<button data-action="sweep_details" data-sweep_name="orphan_termmeta" data-sweep_type="termmeta" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_orphan_termmeta' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
				<?php else : ?>
					<?php _e( 'N/A', 'wp-sweep' ); ?>
				<?php endif; ?>
			</td>
		</tr>
		<tr class="alternate">
			<td>
				<strong><?php _e( 'Duplicated Term Meta', 'wp-sweep' ); ?></strong>
				<p class="sweep-details" style="display: none;"></p>
			</td>
			<td>
				<span class="sweep-count"><?php echo number_format_i18n( $duplicated_termmeta ); ?></span>
			</td>
			<td>
				<span class="sweep-percentage"><?php echo WPSweep::get_instance()->format_percentage( $duplicated_termmeta, $total_termmeta ); ?></span>
			</td>
			<td>
				<?php if ( ! empty( $duplicated_termmeta ) ) :  ?>
					<button data-action="sweep" data-sweep_name="duplicated_termmeta" data-sweep_type="termmeta" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_duplicated_termmeta' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
					<button data-action="sweep_details" data-sweep_name="duplicated_termmeta" data-sweep_type="termmeta" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_duplicated_termmeta' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
				<?php else : ?>
					<?php _e( 'N/A', 'wp-sweep' ); ?>
				<?php endif; ?>
			</td>
		</tr>
			<tr>
				<td>
					<strong><?php _e( 'Orphaned Term Relationship', 'wp-sweep' ); ?></strong>
					<p class="sweep-details" style="display: none;"></p>
				</td>
				<td>
					<span class="sweep-count"><?php echo number_format_i18n( $orphan_term_relationships ); ?></span>
				</td>
				<td>
					<span class="sweep-percentage"><?php echo WPSweep::get_instance()->format_percentage( $orphan_term_relationships, $total_term_relationships ); ?></span>
				</td>
				<td>
					<?php if ( ! empty( $orphan_term_relationships ) ) :  ?>
						<button data-action="sweep" data-sweep_name="orphan_term_relationships" data-sweep_type="term_relationships" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_orphan_term_relationships' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
						<button data-action="sweep_details" data-sweep_name="orphan_term_relationships" data-sweep_type="term_relationships" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_orphan_term_relationships' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
					<?php else : ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="alternate">
				<td>
					<strong><?php _e( 'Unused Terms', 'wp-sweep' ); ?></strong>
					<p><?php _e( 'Note that some unused terms might belong to draft posts that have not been published yet. Only sweep this when you do not have any draft posts.', 'wp-sweep' ); ?></p>
					<p class="sweep-details" style="display: none;"></p>
				</td>
				<td>
					<span class="sweep-count"><?php echo number_format_i18n( $unused_terms ); ?></span>
				</td>
				<td>
					<span class="sweep-percentage"><?php echo WPSweep::get_instance()->format_percentage( $unused_terms, $total_terms ); ?></span>
				</td>
				<td>
					<?php if ( ! empty( $unused_terms ) ) :  ?>
						<button data-action="sweep" data-sweep_name="unused_terms" data-sweep_type="terms" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_unused_terms' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
						<button data-action="sweep_details" data-sweep_name="unused_terms" data-sweep_type="terms" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_unused_terms' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
					<?php else : ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
	<?php do_action( 'wp_sweep_admin_term_sweep' ); ?>
	<p>&nbsp;</p>
	<h3><?php _e( 'Option Sweep', 'wp-sweep' ); ?></h3>
	<p><?php printf( __( 'There are a total of <strong class="attention"><span class="sweep-count-type-options">%s</span> Options</strong>.', 'wp-sweep' ), number_format_i18n( $total_options ) ); ?></p>
	<div class="sweep-message"></div>
	<table class="widefat table-sweep">
		<thead>
			<tr>
				<th class="col-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></th>
				<th class="col-sweep-count"><?php _e( 'Count', 'wp-sweep' ); ?></th>
				<th class="col-sweep-percent"><?php _e( '% Of', 'wp-sweep' ); ?></th>
				<th class="col-sweep-action"><?php _e( 'Action', 'wp-sweep' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<strong><?php _e( 'Transient Options', 'wp-sweep' ); ?></strong>
					<p class="sweep-details" style="display: none;"></p>
				</td>
				<td>
					<span class="sweep-count"><?php echo number_format_i18n( $transient_options ); ?></span>
				</td>
				<td>
					<span class="sweep-percentage"><?php echo WPSweep::get_instance()->format_percentage( $transient_options, $total_options ); ?></span>
				</td>
				<td>
					<?php if ( ! empty( $transient_options ) ) :  ?>
						<button data-action="sweep" data-sweep_name="transient_options" data-sweep_type="options" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_transient_options' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
						<button data-action="sweep_details" data-sweep_name="transient_options" data-sweep_type="options" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_transient_options' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
					<?php else : ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
	<?php do_action( 'wp_sweep_admin_option_sweep' ); ?>
	<p>&nbsp;</p>
	<h3><?php _e( 'Database Sweep', 'wp-sweep' ); ?></h3>
	<p><?php printf( __( 'There are a total of <strong class="attention"><span class="sweep-count-type-tables">%s</span> Tables</strong>.', 'wp-sweep' ), number_format_i18n( $total_tables ) ); ?></p>
	<div class="sweep-message"></div>
	<table class="widefat table-sweep">
		<thead>
			<tr>
				<th class="col-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></th>
				<th class="col-sweep-count"><?php _e( 'Count', 'wp-sweep' ); ?></th>
				<th class="col-sweep-percent"><?php _e( '% Of', 'wp-sweep' ); ?></th>
				<th class="col-sweep-action"><?php _e( 'Action', 'wp-sweep' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<strong><?php _e( 'Optimize Tables', 'wp-sweep' ); ?></strong>
					<p class="sweep-details" style="display: none;"></p>
				</td>
				<td>
					<span class="sweep-count"><?php echo number_format_i18n( $total_tables ); ?></span>
				</td>
				<td>
					<?php _e( 'N/A', 'wp-sweep' ); ?>
				</td>
				<td>
					<?php if ( ! empty( $total_tables ) ) :  ?>
						<button data-action="sweep" data-sweep_name="optimize_database" data-sweep_type="tables" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_optimize_database' ); ?>" class="button button-primary btn-sweep"><?php _e( 'Sweep', 'wp-sweep' ); ?></button>
						<button data-action="sweep_details" data-sweep_name="optimize_database" data-sweep_type="tables" data-nonce="<?php echo wp_create_nonce( 'wp_sweep_details_optimize_database' ); ?>" class="button btn-sweep-details"><?php _e( 'Details', 'wp-sweep' ); ?></button>
					<?php else : ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
	<?php do_action( 'wp_sweep_admin_database_sweep' ); ?>
	<p>&nbsp;</p>
	<h3><?php _e( 'Sweep All', 'wp-sweep' ); ?></h3>
	<p><?php _e( 'Note that some unused terms might belong to draft posts that have not been published yet. Only sweep all when you do not have any draft posts.', 'wp-sweep' ); ?></p>
	<div class="sweep-all">
		<p style="text-align: center;">
			<button class="button button-primary btn-sweep-all"><?php _e( 'Sweep All', 'wp-sweep' ); ?></button>
		</p>
	</div>
</div>
