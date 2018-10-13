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

	public function admin_page() {
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
