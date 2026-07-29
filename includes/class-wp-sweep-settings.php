<?php
/**
 * The settings screen.
 *
 * @package WP-Sweep
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds Sweep -> Settings with the WordPress Settings API.
 *
 * There is one field, and it is registered rather than hand-written into a
 * form table, because the point of the Settings API here is not the saving of
 * markup -- it is that do_settings_sections() and add_settings_error() behave
 * the same way in all nineteen plugins.
 *
 * The settings screen takes manage_options while the sweep screen takes
 * activate_plugins. Both go through the same wp_sweep_capability filter.
 */
class WP_Sweep_Settings {

	/**
	 * The settings group passed to register_setting() and settings_fields().
	 *
	 * @var string
	 */
	const GROUP = 'wp_sweep_options';

	/**
	 * The settings page slug.
	 *
	 * @var string
	 */
	const PAGE = 'wp-sweep-settings';

	/**
	 * The capability required to see and save the settings.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * The section holding how much the screen shows.
	 *
	 * @var string
	 */
	const SECTION_DETAILS = 'wp_sweep_details';

	/**
	 * Hook the settings screen into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WP_SWEEP_MAIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * The capability required for a given context.
	 *
	 * @param string $context What the capability is being checked for.
	 * @return string The required capability.
	 */
	public static function capability( $context = 'settings' ) {
		/** This filter is documented in includes/class-wp-sweep.php */
		return apply_filters( 'wp_sweep_capability', self::CAPABILITY, $context );
	}

	/**
	 * Register the setting, its section and its fields.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			self::GROUP,
			WP_Sweep_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'WP_Sweep_Options', 'sanitize' ),
				'default'           => WP_Sweep_Options::get_defaults(),
			)
		);

		add_settings_section(
			self::SECTION_DETAILS,
			__( 'Details', 'wp-sweep' ),
			array( __CLASS__, 'section_details' ),
			self::PAGE
		);

		add_settings_field(
			'limit_details',
			__( 'Items to list', 'wp-sweep' ),
			array( __CLASS__, 'field_limit_details' ),
			self::PAGE,
			self::SECTION_DETAILS,
			array( 'label_for' => 'wp_sweep_limit_details' )
		);
	}

	/**
	 * The blurb above the section.
	 *
	 * @return void
	 */
	public static function section_details() {
		echo '<p>' . esc_html__( 'Clicking Details on a sweep lists a sample of what it would remove. The whole sample is held in memory and written into the page, which is why there is a limit on it at all.', 'wp-sweep' ) . '</p>';
	}

	/**
	 * The details cap field.
	 *
	 * @return void
	 */
	public static function field_limit_details() {
		printf(
			'<input type="number" min="1" max="10000" step="1" class="small-text" id="wp_sweep_limit_details" name="%1$s[limit_details]" value="%2$s" />',
			esc_attr( WP_Sweep_Options::OPTION ),
			esc_attr( WP_Sweep_Options::get( 'limit_details' ) )
		);

		echo '<p class="description">' . esc_html__( 'Between 1 and 10,000. The default is 500.', 'wp-sweep' ) . '</p>';
	}

	/**
	 * Render the settings screen.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::capability( 'settings' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'wp-sweep' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sweep Settings', 'wp-sweep' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add a Settings link to the plugin's row on the Plugins screen.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public static function action_links( $links ) {
		if ( ! current_user_can( self::capability( 'settings' ) ) ) {
			return $links;
		}

		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ),
				esc_html__( 'Settings', 'wp-sweep' )
			)
		);

		return $links;
	}
}
