<?php
/**
 * Plugin Settings Page via WordPress Settings API.
 *
 * @package SimpleTestimonials
 */

namespace SimpleTestimonials;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the "Testimonials -> Settings" admin page.
 */
class ST_Settings {

	/**
	 * Initialize settings hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_submenu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register "Settings" submenu under the "Testimonials" menu.
	 *
	 * @return void
	 */
	public function add_settings_submenu(): void {
		add_submenu_page(
			'edit.php?post_type=testimonial',
			__( 'Testimonials Settings', 'simple-testimonials' ),
			__( 'Settings', 'simple-testimonials' ),
			'manage_options',
			'st-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue WordPress Color Picker assets on the plugin settings page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'testimonial_page_st-settings' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script(
			'st-admin-settings',
			SIMPLE_TESTIMONIALS_URL . 'admin/js/admin-settings.js',
			array( 'wp-color-picker' ),
			SIMPLE_TESTIMONIALS_VERSION,
			true
		);
	}

	/**
	 * Register option group, sections, and fields via Settings API.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'st_settings_group',
			'st_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => ST_Plugin::get_default_settings(),
			)
		);

		add_settings_section(
			'st_general_section',
			__( 'General Settings', 'simple-testimonials' ),
			array( $this, 'render_section_description' ),
			'st-settings'
		);

		add_settings_field(
			'default_count',
			__( 'Default Testimonials Count', 'simple-testimonials' ),
			array( $this, 'render_default_count_field' ),
			'st-settings',
			'st_general_section',
			array( 'label_for' => 'st_default_count' )
		);

		add_settings_field(
			'enable_form',
			__( 'Visitor Submission Form', 'simple-testimonials' ),
			array( $this, 'render_enable_form_field' ),
			'st-settings',
			'st_general_section'
		);

		add_settings_field(
			'enable_email',
			__( 'Email Notifications', 'simple-testimonials' ),
			array( $this, 'render_enable_email_field' ),
			'st-settings',
			'st_general_section'
		);

		add_settings_field(
			'notification_email',
			__( 'Notification Email', 'simple-testimonials' ),
			array( $this, 'render_notification_email_field' ),
			'st-settings',
			'st_general_section',
			array( 'label_for' => 'st_notification_email' )
		);

		add_settings_field(
			'star_color',
			__( 'Star Rating Color', 'simple-testimonials' ),
			array( $this, 'render_star_color_field' ),
			'st-settings',
			'st_general_section',
			array( 'label_for' => 'st_star_color' )
		);

		add_settings_field(
			'delete_on_uninstall',
			__( 'Uninstall Behavior', 'simple-testimonials' ),
			array( $this, 'render_delete_on_uninstall_field' ),
			'st-settings',
			'st_general_section'
		);
	}

	/**
	 * Render introductory text for the settings section.
	 *
	 * @return void
	 */
	public function render_section_description(): void {
		echo '<p>' . esc_html__( 'Configure default display options, visitor submissions, and moderation alerts.', 'simple-testimonials' ) . '</p>';
	}

	/**
	 * Sanitize and validate plugin settings before saving to the database.
	 *
	 * @param mixed $input Raw submitted settings array.
	 * @return array
	 */
	public function sanitize_settings( $input ): array {
		$current = ST_Plugin::get_settings();
		if ( ! is_array( $input ) ) {
			return $current;
		}

		$output = array();

		$raw_count               = isset( $input['default_count'] ) ? absint( $input['default_count'] ) : 5;
		$output['default_count'] = $raw_count > 0 ? min( 100, $raw_count ) : 5;

		$output['enable_form']         = ! empty( $input['enable_form'] ) ? 1 : 0;
		$output['enable_email']        = ! empty( $input['enable_email'] ) ? 1 : 0;
		$output['delete_on_uninstall'] = ! empty( $input['delete_on_uninstall'] ) ? 1 : 0;

		$raw_email = isset( $input['notification_email'] )
			? trim( sanitize_text_field( wp_unslash( (string) $input['notification_email'] ) ) )
			: '';

		if ( '' === $raw_email ) {
			$output['notification_email'] = (string) get_option( 'admin_email' );
		} else {
			$clean_email = sanitize_email( $raw_email );
			if ( is_email( $clean_email ) ) {
				$output['notification_email'] = $clean_email;
			} else {
				if ( function_exists( 'add_settings_error' ) ) {
					add_settings_error(
						'st_settings',
						'st_invalid_notification_email',
						__( 'Please enter a valid notification email address. Reverted to previous valid email.', 'simple-testimonials' ),
						'error'
					);
				}
				$fallback_email               = sanitize_email( (string) $current['notification_email'] );
				$output['notification_email'] = is_email( $fallback_email ) ? $fallback_email : (string) get_option( 'admin_email' );
			}
		}

		$raw_color = isset( $input['star_color'] )
			? sanitize_hex_color( wp_unslash( (string) $input['star_color'] ) )
			: '';

		$output['star_color'] = ! empty( $raw_color ) ? $raw_color : '#f59e0b';

		return $output;
	}

	/**
	 * Render default testimonials count input.
	 *
	 * @return void
	 */
	public function render_default_count_field(): void {
		$settings = ST_Plugin::get_settings();
		?>
		<input
			type="number"
			id="st_default_count"
			name="st_settings[default_count]"
			value="<?php echo esc_attr( (string) $settings['default_count'] ); ?>"
			min="1"
			max="100"
			step="1"
			class="small-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Used by [testimonials] shortcode and Gutenberg block when count is not explicitly set.', 'simple-testimonials' ); ?>
		</p>
		<?php
	}

	/**
	 * Render visitor form toggle checkbox.
	 *
	 * @return void
	 */
	public function render_enable_form_field(): void {
		$settings = ST_Plugin::get_settings();
		?>
		<label for="st_enable_form">
			<input
				type="checkbox"
				id="st_enable_form"
				name="st_settings[enable_form]"
				value="1"
				<?php checked( 1, (int) $settings['enable_form'] ); ?>
			/>
			<?php esc_html_e( 'Enable public submission form via [testimonial_form] shortcode', 'simple-testimonials' ); ?>
		</label>
		<?php
	}

	/**
	 * Render email notifications toggle checkbox.
	 *
	 * @return void
	 */
	public function render_enable_email_field(): void {
		$settings = ST_Plugin::get_settings();
		?>
		<label for="st_enable_email">
			<input
				type="checkbox"
				id="st_enable_email"
				name="st_settings[enable_email]"
				value="1"
				<?php checked( 1, (int) $settings['enable_email'] ); ?>
			/>
			<?php esc_html_e( 'Send an email notification when a new testimonial awaits moderation', 'simple-testimonials' ); ?>
		</label>
		<?php
	}

	/**
	 * Render notification recipient email input.
	 *
	 * @return void
	 */
	public function render_notification_email_field(): void {
		$settings = ST_Plugin::get_settings();
		?>
		<input
			type="email"
			id="st_notification_email"
			name="st_settings[notification_email]"
			value="<?php echo esc_attr( (string) $settings['notification_email'] ); ?>"
			class="regular-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Defaults to the site administrator email if left blank.', 'simple-testimonials' ); ?>
		</p>
		<?php
	}

	/**
	 * Render star color picker input.
	 *
	 * @return void
	 */
	public function render_star_color_field(): void {
		$settings = ST_Plugin::get_settings();
		?>
		<input
			type="text"
			id="st_star_color"
			name="st_settings[star_color]"
			value="<?php echo esc_attr( (string) $settings['star_color'] ); ?>"
			class="st-color-picker"
			data-default-color="#f59e0b"
		/>
		<?php
	}

	/**
	 * Render delete-on-uninstall checkbox.
	 *
	 * @return void
	 */
	public function render_delete_on_uninstall_field(): void {
		$settings = ST_Plugin::get_settings();
		?>
		<label for="st_delete_on_uninstall">
			<input
				type="checkbox"
				id="st_delete_on_uninstall"
				name="st_settings[delete_on_uninstall]"
				value="1"
				<?php checked( 1, (int) $settings['delete_on_uninstall'] ); ?>
			/>
			<?php esc_html_e( 'Delete all testimonials and categories when uninstalling the plugin', 'simple-testimonials' ); ?>
		</label>
		<?php
	}

	/**
	 * Render settings page HTML.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors(); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'st_settings_group' );
				do_settings_sections( 'st-settings' );
				submit_button( __( 'Save Settings', 'simple-testimonials' ) );
				?>
			</form>
		</div>
		<?php
	}
}
