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
$sweep_number = 1;

// Sweeping.
$wp_sweep = WPSweep::get_instance();
if ( ! empty( $_GET['sweep'] ) ) {
	if ( check_admin_referer( 'wp_sweep_' . $_GET['sweep'] ) ) {
		$message = $wp_sweep->sweep( $_GET['sweep'] );
	}
}

require __DIR__ . '/inc/class-wpsweep-admin.php';
$sweep_admin = new WPSweep_Admin( $wp_sweep );

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
		<?php echo esc_html( sprintf( __( 'For performance reasons, only %s items will be shown if you click Details.', 'wp-sweep' ), number_format_i18n( $wp_sweep->limit_details ) ) ); ?>
	</p>
	<!-- Sweeps -->
	<?php foreach ( $wp_sweep->types as $type => $type_instance ) : ?>
		<h3><?php echo esc_html( $type_instance->get_name() ); ?></h3>
		<p><?php echo wp_kses_post( $type_instance->get_description() ); ?></p>
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
			<?php foreach ( $wp_sweep->sweeps[ $type ] as $sweep ) :
				$sweep_admin->print_row( $sweep, $sweep_number++ );
			endforeach; ?>
			</tbody>
		</table>
		<?php do_action( "wp_sweep_admin_{$type}_sweep" ); ?>
		<p>&nbsp;</p>
	<?php endforeach; ?>
	<h3><?php esc_html_e( 'Sweep All', 'wp-sweep' ); ?></h3>
	<p><?php esc_html_e( 'Note that some unused terms might belong to draft posts that have not been published yet. Only sweep all when you do not have any draft posts.', 'wp-sweep' ); ?></p>
	<div class="sweep-all">
		<p style="text-align: center;">
			<button class="button button-primary btn-sweep-all"><?php esc_html_e( 'Sweep All', 'wp-sweep' ); ?></button>
		</p>
	</div>
</div>
