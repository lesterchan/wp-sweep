<?php
/**
 * WP-Sweep UI
 *
 * @package wp-sweep
 */

/**
 * Class WPSweep_Admin
 */
class WPSweep_Admin {

	private $wp_sweep;

	public function __construct( $wp_sweep ) {
		$this->wp_sweep = $wp_sweep;
	}

	public function print_page() {
/* TODO What is it?? Unused?
		$current_page = admin_url( 'tools.php?page=' . plugin_basename( 'wp-sweep/admin.php' ) );
		$message      = '';
*/

// TODO What is it? The non-AJAX sweep???
		// Sweeping.
		if ( ! empty( $_GET['sweep'] ) ) {
			if ( check_admin_referer( 'wp_sweep_' . $_GET['sweep'] ) ) {
				$message = $this->wp_sweep->sweep( $_GET['sweep'] );
			}
		}

		// Number for alternating background color
		$sweep_number = 1;
		?>
		<style type="text/css">
			.table-sweep thead th { width: 12%; }
			.table-sweep thead th.col-sweep-details { width: 56%; }
			.table-sweep thead th.col-sweep-action { width: 20%; }
		</style>
		<div class="wrap">
			<h2><?php esc_html_e( 'WP-Sweep', 'wp-sweep' ); ?></h2>
			<div class="notice notice-warning">
				<p>
					<?php /* translators: %1 WP-DBManager Plugin URL, %2 _blank to open new window */
					echo wp_kses_post( sprintf( __( 'Before you do any sweep, please <a href="%1$s" target="%2$s">backup your database</a> first because any sweep done is irreversible.', 'wp-sweep' ), 'https://wordpress.org/plugins/wp-dbmanager/', '_blank' ) ); ?>
				</p>
			</div>
			<p>
				<?php /* translators: %s maximum number of results */
				echo esc_html( sprintf( __( 'For performance reasons, only %s items will be shown if you click Details.', 'wp-sweep' ), number_format_i18n( $this->wp_sweep->limit_details ) ) ); ?>
			</p>
			<!-- Sweeps -->
			<?php foreach ( $this->wp_sweep->types as $type => $type_instance ) : ?>
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
					<?php foreach ( $this->wp_sweep->sweeps[ $type ] as $sweep ) :
						$this->print_row( $sweep, $sweep_number++ );
					endforeach; ?>
					</tbody>
				</table>
				<?php do_action( "wp_sweep_admin_{$type}_sweep" ); ?>
				<p>&nbsp;</p>
			<?php endforeach; ?>
			<!-- Sweep All -->
			<h3><?php esc_html_e( 'Sweep All', 'wp-sweep' ); ?></h3>
			<p><?php esc_html_e( 'Note that some unused terms might belong to draft posts that have not been published yet. Only sweep all when you do not have any draft posts.', 'wp-sweep' ); ?></p>
			<div class="sweep-all">
				<p style="text-align: center;">
					<button class="button button-primary btn-sweep-all"><?php esc_html_e( 'Sweep All', 'wp-sweep' ); ?></button>
				</p>
			</div>
		</div>
		<?php
	}

	public function print_row( $sweep, $number ) {
		$name = $sweep->get_name();
		$count = $sweep->count();
		$count_formatted = number_format_i18n( $count );
		$percentage = $this->wp_sweep->format_percentage( $count, $this->wp_sweep->totals[ $sweep::TOTAL ] );
		$nonce = wp_create_nonce( 'wp_sweep_' . $sweep::SLUG );
		$nonce_details = wp_create_nonce( 'wp_sweep_details_' . $sweep::SLUG );
		$alternate_class = ( 0 === $number % 2 ) ? ' class="alternate"' : '';
		?>
		<tr<?php echo $alternate_class; ?>>
			<td>
				<strong><?php echo esc_html( $name ); ?></strong>
				<p class="sweep-details" style="display: none;"></p>
			</td>
			<td>
				<span class="sweep-count"><?php echo esc_html( $count ); ?></span>
			</td>
			<?php if ( false !== $percentage ) : ?>
				<td>
					<span class="sweep-percentage"><?php echo esc_html( $percentage ); ?></span>
				</td>
			<?php endif; ?>
			<td>
			<?php if ( 0 === $count ) :
				esc_html_e( 'N/A', 'wp-sweep' );
			else : ?>
				<button data-action="sweep"
					data-sweep_name="<?php echo esc_attr( $sweep::SLUG ); ?>"
					data-nonce="<?php echo esc_attr( $nonce ); ?>"
					class="button button-primary btn-sweep"><?php esc_html_e( 'Sweep', 'wp-sweep' ); ?></button>
				<button data-action="sweep_details"
					data-sweep_name="<?php echo esc_attr( $sweep::SLUG ); ?>"
					data-nonce="<?php echo esc_attr( $nonce_details ); ?>"
					class="button btn-sweep-details"><?php esc_html_e( 'Details', 'wp-sweep' ); ?></button>
			<?php endif; ?>
			</td>
		</tr>
		<?php
	}
}
