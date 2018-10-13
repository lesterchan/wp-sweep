<?php
/**
 * WP-Sweep admin.php
 *
 * @package wp-sweep
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables.
$current_page = admin_url( 'tools.php?page=' . plugin_basename( 'wp-sweep/admin.php' ) );
$message      = '';

// Sweeping.
if ( ! empty( $_GET['sweep'] ) ) {
	if ( check_admin_referer( 'wp_sweep_' . $_GET['sweep'] ) ) {
		$message = WPSweep::get_instance()->sweep( $_GET['sweep'] );
	}
}

require __DIR__ . '/inc/class-wpsweep-admin.php';
$sweep_admin = new WPSweep_Admin( WPSweep::get_instance() );

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
	<h2><?php esc_html_e( 'WP-Sweep', 'wp-sweep' ); ?></h2>
	<div class="notice notice-warning">
		<p>
			<?php /* translators: %1 WP-DBManager Plugin URL, %2 _blank to open new window */ ?>
			<?php echo wp_kses_post( sprintf( __( 'Before you do any sweep, please <a href="%1$s" target="%2$s">backup your database</a> first because any sweep done is irreversible.', 'wp-sweep' ), 'https://wordpress.org/plugins/wp-dbmanager/', '_blank' ) ); ?>
		</p>
	</div>
	<p>
		<?php /* translators: %s maximum number of results */ ?>
		<?php echo esc_html( sprintf( __( 'For performance reasons, only %s items will be shown if you click Details.', 'wp-sweep' ), number_format_i18n( WPSweep::get_instance()->limit_details ) ) ); ?>
	</p>
	<h3><?php esc_html_e( 'Post Sweep', 'wp-sweep' ); ?></h3>
	<?php /* translators: %1 is the number of posts, %2 is the number of post meta */ ?>
	<p><?php echo wp_kses_post( sprintf( __( 'There are a total of <strong class="attention"><span class="sweep-count-type-posts">%1$s</span> Posts</strong> and <strong class="attention"><span class="sweep-count-type-postmeta">%2$s</span> Post Meta</strong>.', 'wp-sweep' ), number_format_i18n( $total_posts ), number_format_i18n( $total_postmeta ) ) ); ?></p>
	<div class="sweep-message"></div>
	<table class="widefat table-sweep">
		<thead>
			<tr>
				<th class="col-sweep-details"><?php esc_html_e( 'Details', 'wp-sweep' ); ?></th>
				<th class="col-sweep-count"><?php esc_html_e( 'Count', 'wp-sweep' ); ?></th>
				<th class="col-sweep-percent"><?php esc_html_e( '% Of', 'wp-sweep' ); ?></th>
				<th class="col-sweep-action"><?php esc_html_e( 'Action', 'wp-sweep' ); ?></th>
			</tr>
		</thead>
		<tbody>




		</tbody>
	</table>
	<?php do_action( 'wp_sweep_admin_post_sweep' ); ?>
	<p>&nbsp;</p>
	<h3><?php esc_html_e( 'Comment Sweep', 'wp-sweep' ); ?></h3>
	<?php /* translators: %1 is the number of comments, %2 is the number of comment meta */ ?>
	<p><?php echo wp_kses_post( sprintf( __( 'There are a total of <strong class="attention"><span class="sweep-count-type-comments">%1$s</span> Comments</strong> and <strong class="attention"><span class="sweep-count-type-commentmeta">%2$s</span> Comment Meta</strong>.', 'wp-sweep' ), number_format_i18n( $total_comments ), number_format_i18n( $total_commentmeta ) ) ); ?></p>
	<div class="sweep-message"></div>
	<table class="widefat table-sweep">
		<thead>
			<tr>
				<th class="col-sweep-details"><?php esc_html_e( 'Details', 'wp-sweep' ); ?></th>
				<th class="col-sweep-count"><?php esc_html_e( 'Count', 'wp-sweep' ); ?></th>
				<th class="col-sweep-percent"><?php esc_html_e( '% Of', 'wp-sweep' ); ?></th>
				<th class="col-sweep-action"><?php esc_html_e( 'Action', 'wp-sweep' ); ?></th>
			</tr>
		</thead>
		<tbody>

<?php
$wp_sweep = WPSweep::get_instance();
foreach ( $wp_sweep->sweeps as $category ) {
	foreach ( $category as $sweep ) {
echo $sweep->get_name() . 'N';
		$sweep_admin->print_row( $sweep, 1 );
	}
}
?>

		</tbody>
	</table>
	<?php do_action( 'wp_sweep_admin_comment_sweep' ); ?>
	<p>&nbsp;</p>
	<h3><?php esc_html_e( 'User Sweep', 'wp-sweep' ); ?></h3>
	<?php /* translators: %1 is the number of users, %2 is the number of user meta */ ?>
	<p><?php echo wp_kses_post( sprintf( __( 'There are a total of <strong class="attention"><span class="sweep-count-type-users">%1$s</span> Users</strong> and <strong class="attention"><span class="sweep-count-type-usermeta">%2$s</span> User Meta</strong>.', 'wp-sweep' ), number_format_i18n( $total_users ), number_format_i18n( $total_usermeta ) ) ); ?></p>
	<div class="sweep-message"></div>
	<table class="widefat table-sweep">
		<thead>
			<tr>
				<th class="col-sweep-details"><?php esc_html_e( 'Details', 'wp-sweep' ); ?></th>
				<th class="col-sweep-count"><?php esc_html_e( 'Count', 'wp-sweep' ); ?></th>
				<th class="col-sweep-percent"><?php esc_html_e( '% Of', 'wp-sweep' ); ?></th>
				<th class="col-sweep-action"><?php esc_html_e( 'Action', 'wp-sweep' ); ?></th>
			</tr>
		</thead>
		<tbody>



		</tbody>
	</table>
	<?php do_action( 'wp_sweep_admin_user_sweep' ); ?>
	<p>&nbsp;</p>
	<h3><?php esc_html_e( 'Term Sweep', 'wp-sweep' ); ?></h3>
	<?php /* translators: %1 is the number of terms, %2 is the number of term meta */ ?>
	<p><?php echo wp_kses_post( sprintf( __( 'There are a total of <strong class="attention "><span class="sweep-count-type-terms">%1$s</span> Terms</strong>, <strong class="attention "><span class="sweep-count-type-termmeta">%2$s</span> Term Meta</strong>, <strong class="attention"><span class="sweep-count-type-term_taxonomy">%3$s</span> Term Taxonomy</strong> and <strong class="attention"><span class="sweep-count-type-term_relationships">%4$s</span> Term Relationships</strong>.', 'wp-sweep' ), number_format_i18n( $total_terms ), number_format_i18n( $total_termmeta ), number_format_i18n( $total_term_taxonomy ), number_format_i18n( $total_term_relationships ) ) ); ?></p>
	<div class="sweep-message"></div>
	<table class="widefat table-sweep">
		<thead>
			<tr>
				<th class="col-sweep-details"><?php esc_html_e( 'Details', 'wp-sweep' ); ?></th>
				<th class="col-sweep-count"><?php esc_html_e( 'Count', 'wp-sweep' ); ?></th>
				<th class="col-sweep-percent"><?php esc_html_e( '% Of', 'wp-sweep' ); ?></th>
				<th class="col-sweep-action"><?php esc_html_e( 'Action', 'wp-sweep' ); ?></th>
			</tr>
		</thead>
		<tbody>



		</tbody>
	</table>
	<?php do_action( 'wp_sweep_admin_term_sweep' ); ?>
	<p>&nbsp;</p>
	<h3><?php esc_html_e( 'Option Sweep', 'wp-sweep' ); ?></h3>
	<?php /* translators: %1 is the number of options */ ?>
	<p><?php echo wp_kses_post( sprintf( __( 'There are a total of <strong class="attention"><span class="sweep-count-type-options">%s</span> Options</strong>.', 'wp-sweep' ), number_format_i18n( $total_options ) ) ); ?></p>
	<div class="sweep-message"></div>
	<table class="widefat table-sweep">
		<thead>
			<tr>
				<th class="col-sweep-details"><?php esc_html_e( 'Details', 'wp-sweep' ); ?></th>
				<th class="col-sweep-count"><?php esc_html_e( 'Count', 'wp-sweep' ); ?></th>
				<th class="col-sweep-percent"><?php esc_html_e( '% Of', 'wp-sweep' ); ?></th>
				<th class="col-sweep-action"><?php esc_html_e( 'Action', 'wp-sweep' ); ?></th>
			</tr>
		</thead>
		<tbody>



		</tbody>
	</table>
	<?php do_action( 'wp_sweep_admin_option_sweep' ); ?>
	<p>&nbsp;</p>
	<h3><?php esc_html_e( 'Database Sweep', 'wp-sweep' ); ?></h3>
	<?php /* translators: %1 is the number of database tables */ ?>
	<p><?php echo wp_kses_post( sprintf( __( 'There are a total of <strong class="attention"><span class="sweep-count-type-tables">%s</span> Tables</strong>.', 'wp-sweep' ), number_format_i18n( $total_tables ) ) ); ?></p>
	<div class="sweep-message"></div>
	<table class="widefat table-sweep">
		<thead>
			<tr>
				<th class="col-sweep-details"><?php esc_html_e( 'Details', 'wp-sweep' ); ?></th>
				<th class="col-sweep-count"><?php esc_html_e( 'Count', 'wp-sweep' ); ?></th>
				<th class="col-sweep-percent"><?php esc_html_e( '% Of', 'wp-sweep' ); ?></th>
				<th class="col-sweep-action"><?php esc_html_e( 'Action', 'wp-sweep' ); ?></th>
			</tr>
		</thead>
		<tbody>



		</tbody>
	</table>
	<?php do_action( 'wp_sweep_admin_database_sweep' ); ?>
	<p>&nbsp;</p>
	<h3><?php esc_html_e( 'Sweep All', 'wp-sweep' ); ?></h3>
	<p><?php esc_html_e( 'Note that some unused terms might belong to draft posts that have not been published yet. Only sweep all when you do not have any draft posts.', 'wp-sweep' ); ?></p>
	<div class="sweep-all">
		<p style="text-align: center;">
			<button class="button button-primary btn-sweep-all"><?php esc_html_e( 'Sweep All', 'wp-sweep' ); ?></button>
		</p>
	</div>
</div>
