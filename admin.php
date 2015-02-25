<?php
### Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

### Variables
$current_page = admin_url( 'tools.php?page=' . plugin_basename( 'wp-sweep/admin.php' ) );
$message = '';

### Sweeping
if( ! empty( $_GET['sweep'] ) ) {
	if ( check_admin_referer( 'wp_sweep_' . $_GET['sweep'] ) ) {
		$message = WPSweep::get_instance()->sweep( $_GET['sweep'] );
	}
}

### Database Table Status
$total_posts = WPSweep::get_instance()->table_count( 'posts' );
$total_postmeta = WPSweep::get_instance()->table_count( 'postmeta' );
$total_comments = WPSweep::get_instance()->table_count( 'comments' );
$total_commentmeta = WPSweep::get_instance()->table_count( 'commentmeta' );
$total_users = WPSweep::get_instance()->table_count( 'users' );
$total_usermeta = WPSweep::get_instance()->table_count( 'usermeta' );
$total_term_relationships = WPSweep::get_instance()->table_count( 'term_relationships' );
$total_term_taxonomy = WPSweep::get_instance()->table_count( 'term_taxonomy' );
$total_terms = WPSweep::get_instance()->table_count( 'terms' );
$total_options = WPSweep::get_instance()->table_count( 'options' );

### Count
$revisions                  = WPSweep::get_instance()->count( 'revisions' );
$auto_drafts                = WPSweep::get_instance()->count( 'auto_drafts' );
$orphan_postmeta            = WPSweep::get_instance()->count( 'orphan_postmeta' );
$duplicated_postmeta        = WPSweep::get_instance()->count( 'duplicated_postmeta' );

$unapproved_comments        = WPSweep::get_instance()->count( 'unapproved_comments' );
$spam_comments              = WPSweep::get_instance()->count( 'spam_comments' );
$deleted_comments           = WPSweep::get_instance()->count( 'deleted_comments' ) ;
$orphan_commentmeta         = WPSweep::get_instance()->count( 'orphan_commentmeta' );
$duplicated_commentmeta     = WPSweep::get_instance()->count( 'duplicated_commentmeta' );

$orphan_usermeta            = WPSweep::get_instance()->count( 'orphan_usermeta' );
$duplicated_usermeta        = WPSweep::get_instance()->count( 'duplicated_usermeta' );

$orphan_term_relationships  = WPSweep::get_instance()->count( 'orphan_term_relationships' );
$unused_terms               = WPSweep::get_instance()->count( 'unused_terms' );

$transient_options          = WPSweep::get_instance()->count( 'transient_options' );
?>
<div class="wrap">
	<h2><?php _e( 'WP-Sweep', 'wp-sweep' ); ?></h2>
	<?php if( ! empty( $message ) ): ?>
		<div class="updated">
			<p><?php echo $message; ?></p>
		</div>
	<?php endif; ?>
	<div class="update-nag">
		<?php printf( __( 'Before you do any sweep, please <a href="%s" target="%s">backup your database</a> first because any sweep done is irreversible.', 'wp-sweep' ), 'https://wordpress.org/plugins/wp-dbmanager/', '_blank' ); ?>
	</div>
	<h3><?php _e( 'Post Sweep', 'wp-sweep' ); ?></h3>
	<p><?php printf( __( 'There are a total of <strong class="attention">%s Posts</strong> and <strong class="attention">%s Post Meta</strong>.', 'wp-sweep' ), number_format_i18n( $total_posts ), number_format_i18n( $total_postmeta ) ); ?></p>
	<table class="widefat">
		<thead>
			<tr>
				<th style="width: 64%;"><?php _e( 'Details', 'wp-sweep' ); ?></th>
				<th style="width: 12%;"><?php _e( 'Count', 'wp-sweep' ); ?></th>
				<th style="width: 12%;"><?php _e( '% Of', 'wp-sweep' ); ?></th>
				<th style="width: 12%;"><?php _e( 'Action', 'wp-sweep' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<strong><?php _e( 'Revisions', 'wp-sweep' ); ?></strong>
				</td>
				<td>
					<?php echo number_format_i18n( $revisions ); ?>
				</td>
				<td>
					<?php echo round( ( $revisions/$total_posts ) * 100, 2 ); ?>%
				</td>
				<td>
					<?php if( ! empty( $revisions ) ): ?>
						<a href="<?php echo wp_nonce_url( $current_page . '&sweep=revisions', 'wp_sweep_revisions' ); ?>" class="button button-primary"><?php _e( 'Sweep', 'wp-sweep' ); ?></a>
					<?php else: ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="alternate">
				<td>
					<strong><?php _e( 'Auto Drafts', 'wp-sweep' ); ?></strong>
				</td>
				<td>
					<?php echo number_format_i18n( $auto_drafts ); ?>
				</td>
				<td>
					<?php echo round( ( $auto_drafts/$total_posts ) * 100, 2 ); ?>%
				</td>
				<td>
					<?php if( ! empty( $auto_drafts ) ): ?>
						<a href="<?php echo wp_nonce_url( $current_page . '&sweep=auto_drafts', 'wp_sweep_auto_drafts' ); ?>" class="button button-primary"><?php _e( 'Sweep', 'wp-sweep' ); ?></a>
					<?php else: ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td>
					<strong><?php _e( 'Orphaned Post Meta', 'wp-sweep' ); ?></strong>
				</td>
				<td>
					<?php echo number_format_i18n( $orphan_postmeta ); ?>
				</td>
				<td>
					<?php echo round( ( $orphan_postmeta/$total_postmeta ) * 100, 2 ); ?>%
				</td>
				<td>
					<?php if( ! empty( $orphan_postmeta ) ): ?>
						<a href="<?php echo wp_nonce_url( $current_page . '&sweep=orphan_postmeta', 'wp_sweep_orphan_postmeta' ); ?>" class="button button-primary"><?php _e( 'Sweep', 'wp-sweep' ); ?></a>
					<?php else: ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="alternate">
				<td>
					<strong><?php _e( 'Duplicated Post Meta', 'wp-sweep' ); ?></strong>
				</td>
				<td>
					<?php echo number_format_i18n( $duplicated_postmeta ); ?>
				</td>
				<td>
					<?php echo round( ( $duplicated_postmeta/$total_postmeta ) * 100, 2 ); ?>%
				</td>
				<td>
					<?php if( ! empty( $duplicated_postmeta ) ): ?>
						<a href="<?php echo wp_nonce_url( $current_page . '&sweep=duplicated_postmeta', 'wp_sweep_duplicated_postmeta' ); ?>" class="button button-primary"><?php _e( 'Sweep', 'wp-sweep' ); ?></a>
					<?php else: ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
	<p>&nbsp;</p>
	<h3><?php _e( 'Comment Sweep', 'wp-sweep' ); ?></h3>
	<p><?php printf( __( 'There are a total of <strong class="attention">%s Comments</strong> and <strong class="attention">%s Comment Meta</strong>.', 'wp-sweep' ), number_format_i18n( $total_comments ), number_format_i18n( $total_commentmeta ) ); ?></p>
	<table class="widefat">
		<thead>
			<tr>
				<th style="width: 64%;"><?php _e( 'Details', 'wp-sweep' ); ?></th>
				<th style="width: 12%;"><?php _e( 'Count', 'wp-sweep' ); ?></th>
				<th style="width: 12%;"><?php _e( '% Of', 'wp-sweep' ); ?></th>
				<th style="width: 12%;"><?php _e( 'Action', 'wp-sweep' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<strong><?php _e( 'Unapproved Comments', 'wp-sweep' ); ?></strong>
				</td>
				<td>
					<?php echo number_format_i18n( $unapproved_comments ); ?>
				</td>
				<td>
					<?php echo round( ( $unapproved_comments/$total_comments ) * 100, 2 ); ?>%
				</td>
				<td>
					<?php if( ! empty( $unapproved_comments ) ): ?>
						<a href="<?php echo wp_nonce_url( $current_page . '&sweep=unapproved_comments', 'wp_sweep_unapproved_comments' ); ?>" class="button button-primary"><?php _e( 'Sweep', 'wp-sweep' ); ?></a>
					<?php else: ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="alternate">
				<td>
					<strong><?php _e( 'Spam Comments', 'wp-sweep' ); ?></strong>
				</td>
				<td>
					<?php echo number_format_i18n( $spam_comments ); ?>
				</td>
				<td>
					<?php echo round( ( $spam_comments/$total_comments ) * 100, 2 ); ?>%
				</td>
				<td>
					<?php if( ! empty( $spam_comments ) ): ?>
						<a href="<?php echo wp_nonce_url( $current_page . '&sweep=spam_comments', 'wp_sweep_spam_comments' ); ?>" class="button button-primary"><?php _e( 'Sweep', 'wp-sweep' ); ?></a>
					<?php else: ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td>
					<strong><?php _e( 'Delete Comments', 'wp-sweep' ); ?></strong>
				</td>
				<td>
					<?php echo number_format_i18n( $deleted_comments ); ?>
				</td>
				<td>
					<?php echo round( ( $deleted_comments/$total_comments ) * 100, 2 ); ?>%
				</td>
				<td>
					<?php if( ! empty( $deleted_comments ) ): ?>
						<a href="<?php echo wp_nonce_url( $current_page . '&sweep=deleted_comments', 'wp_sweep_deleted_comments' ); ?>" class="button button-primary"><?php _e( 'Sweep', 'wp-sweep' ); ?></a>
					<?php else: ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="alternate">
				<td>
					<strong><?php _e( 'Orphaned Comment Meta', 'wp-sweep' ); ?></strong>
				</td>
				<td>
					<?php echo number_format_i18n( $orphan_commentmeta ); ?>
				</td>
				<td>
					<?php echo round( ( $orphan_commentmeta/$total_commentmeta ) * 100, 2 ); ?>%
				</td>
				<td>
					<?php if( ! empty( $orphan_commentmeta ) ): ?>
						<a href="<?php echo wp_nonce_url( $current_page . '&sweep=orphan_commentmeta', 'wp_sweep_orphan_commentmeta' ); ?>" class="button button-primary"><?php _e( 'Sweep', 'wp-sweep' ); ?></a>
					<?php else: ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td>
					<strong><?php _e( 'Duplicated Comment Meta', 'wp-sweep' ); ?></strong>
				</td>
				<td>
					<?php echo number_format_i18n( $duplicated_commentmeta ); ?>
				</td>
				<td>
					<?php echo round( ( $duplicated_commentmeta/$total_commentmeta ) * 100, 2 ); ?>%
				</td>
				<td>
					<?php if( ! empty( $duplicated_commentmeta ) ): ?>
						<a href="<?php echo wp_nonce_url( $current_page . '&sweep=duplicated_commentmeta', 'wp_sweep_duplicated_commentmeta' ); ?>" class="button button-primary"><?php _e( 'Sweep', 'wp-sweep' ); ?></a>
					<?php else: ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
	<p>&nbsp;</p>
	<h3><?php _e( 'User Sweep', 'wp-sweep' ); ?></h3>
	<p><?php printf( __( 'There are a total of <strong class="attention">%s Users</strong> and <strong class="attention">%s User Meta</strong>.', 'wp-sweep' ), number_format_i18n( $total_users ), number_format_i18n( $total_usermeta ) ); ?></p>
	<table class="widefat">
		<thead>
			<tr>
				<th style="width: 64%;"><?php _e( 'Details', 'wp-sweep' ); ?></th>
				<th style="width: 12%;"><?php _e( 'Count', 'wp-sweep' ); ?></th>
				<th style="width: 12%;"><?php _e( '% Of', 'wp-sweep' ); ?></th>
				<th style="width: 12%;"><?php _e( 'Action', 'wp-sweep' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<strong><?php _e( 'Orphaned User Meta', 'wp-sweep' ); ?></strong>
				</td>
				<td>
					<?php echo number_format_i18n( $orphan_usermeta ); ?>
				</td>
				<td>
					<?php echo round( ( $orphan_usermeta/$total_usermeta ) * 100, 2 ); ?>%
				</td>
				<td>
					<?php if( ! empty( $orphan_usermeta ) ): ?>
						<a href="<?php echo wp_nonce_url( $current_page . '&sweep=orphan_usermeta', 'wp_sweep_orphan_usermeta' ); ?>" class="button button-primary"><?php _e( 'Sweep', 'wp-sweep' ); ?></a>
					<?php else: ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="alternate">
				<td>
					<strong><?php _e( 'Duplicated User Meta', 'wp-sweep' ); ?></strong>
				</td>
				<td>
					<?php echo number_format_i18n( $duplicated_usermeta ); ?>
				</td>
				<td>
					<?php echo round( ( $duplicated_usermeta/$total_usermeta ) * 100, 2 ); ?>%
				</td>
				<td>
					<?php if( ! empty( $duplicated_usermeta ) ): ?>
						<a href="<?php echo wp_nonce_url( $current_page . '&sweep=duplicated_usermeta', 'wp_sweep_duplicated_usermeta' ); ?>" class="button button-primary"><?php _e( 'Sweep', 'wp-sweep' ); ?></a>
					<?php else: ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
	<p>&nbsp;</p>
	<h3><?php _e( 'Term Sweep', 'wp-sweep' ); ?></h3>
	<p><?php printf( __( 'There are a total of <strong class="attention">%s Terms</strong>, <strong class="attention">%s Term Taxonomy</strong> and <strong class="attention">%s Term Relationships</strong>.', 'wp-sweep' ), number_format_i18n( $total_terms ), number_format_i18n( $total_term_taxonomy ), number_format_i18n( $total_term_relationships ) ); ?></p>
	<table class="widefat">
		<thead>
			<tr>
				<th style="width: 64%;"><?php _e( 'Details', 'wp-sweep' ); ?></th>
				<th style="width: 12%;"><?php _e( 'Count', 'wp-sweep' ); ?></th>
				<th style="width: 12%;"><?php _e( '% Of', 'wp-sweep' ); ?></th>
				<th style="width: 12%;"><?php _e( 'Action', 'wp-sweep' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<strong><?php _e( 'Orphaned Term Relationship', 'wp-sweep' ); ?></strong>
				</td>
				<td>
					<?php echo number_format_i18n( $orphan_term_relationships ); ?>
				</td>
				<td>
					<?php echo round( ( $orphan_term_relationships/$total_term_relationships ) * 100, 2 ); ?>%
				</td>
				<td>
					<?php if( ! empty( $orphan_term_relationships ) ): ?>
						<a href="<?php echo wp_nonce_url( $current_page . '&sweep=orphan_term_relationships', 'wp_sweep_orphan_term_relationships' ); ?>" class="button button-primary"><?php _e( 'Sweep', 'wp-sweep' ); ?></a>
					<?php else: ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="alternate">
				<td>
					<strong><?php _e( 'Unused Terms', 'wp-sweep' ); ?></strong>
					<p><?php _e( 'Note that some unused terms might belong to draft posts that have not been published yet. Only sweep this when you do not have any draft posts.', 'wp-sweep' ); ?></p>
				</td>
				<td>
					<?php echo number_format_i18n( $unused_terms ); ?>
				</td>
				<td>
					<?php echo round( ( $unused_terms/$total_terms ) * 100, 2 ); ?>%
				</td>
				<td>
					<?php if( ! empty( $unused_terms ) ): ?>
						<a href="<?php echo wp_nonce_url( $current_page . '&sweep=unused_terms', 'wp_sweep_unused_terms' ); ?>" class="button button-primary"><?php _e( 'Sweep', 'wp-sweep' ); ?></a>
					<?php else: ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
	<p>&nbsp;</p>
	<h3><?php _e( 'Option Sweep', 'wp-sweep' ); ?></h3>
	<p><?php printf( __( 'There are a total of <strong class="attention">%s Options</strong>.', 'wp-sweep' ), number_format_i18n( $total_options ) ); ?></p>
	<table class="widefat">
		<thead>
			<tr>
				<th style="width: 64%;"><?php _e( 'Details', 'wp-sweep' ); ?></th>
				<th style="width: 12%;"><?php _e( 'Count', 'wp-sweep' ); ?></th>
				<th style="width: 12%;"><?php _e( '% Of', 'wp-sweep' ); ?></th>
				<th style="width: 12%;"><?php _e( 'Action', 'wp-sweep' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<strong><?php _e( 'Transient Options', 'wp-sweep' ); ?></strong>
				</td>
				<td>
					<?php echo number_format_i18n( $transient_options ); ?>
				</td>
				<td>
					<?php echo round( ( $transient_options/$total_options ) * 100, 2 ); ?>%
				</td>
				<td>
					<?php if( ! empty( $transient_options ) ): ?>
						<a href="<?php echo wp_nonce_url( $current_page . '&sweep=transient_options', 'wp_sweep_transient_options' ); ?>" class="button button-primary"><?php _e( 'Sweep', 'wp-sweep' ); ?></a>
					<?php else: ?>
						<?php _e( 'N/A', 'wp-sweep' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
</div>