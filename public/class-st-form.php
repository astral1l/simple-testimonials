<?php
/**
 * Visitor Submission Form `[testimonial_form]` and AJAX Handler.
 *
 * @package SimpleTestimonials
 */

namespace SimpleTestimonials;

defined( 'ABSPATH' ) || exit;

/**
 * Renders `[testimonial_form]`, validates anti-spam rules, saves pending testimonials, and emails moderators.
 */
class ST_Form {

	/**
	 * Counter for generating unique form element IDs if rendered multiple times on a page.
	 *
	 * @var int
	 */
	private static int $instance_count = 0;

	/**
	 * Initialize hooks.
	 *
	 * Script registration runs on `init` (priority 5) so `wp_enqueue_script()` works reliably
	 * inside block theme templates rendered prior to `wp_head()`.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_scripts' ), 5 );
		add_shortcode( 'testimonial_form', array( $this, 'render_form_shortcode' ) );

		add_action( 'wp_ajax_st_submit_testimonial', array( $this, 'handle_ajax_submission' ) );
		add_action( 'wp_ajax_nopriv_st_submit_testimonial', array( $this, 'handle_ajax_submission' ) );
	}

	/**
	 * Register vanilla JS form handler and localize translatable UI strings.
	 *
	 * @return void
	 */
	public function register_scripts(): void {
		if ( ! wp_script_is( 'st-form-script', 'registered' ) ) {
			wp_register_script(
				'st-form-script',
				SIMPLE_TESTIMONIALS_URL . 'public/js/form.js',
				array(),
				SIMPLE_TESTIMONIALS_VERSION,
				true
			);
		}

		wp_localize_script(
			'st-form-script',
			'stFormData',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'submitting'    => __( 'Submitting...', 'simple-testimonials' ),
				'requiredError' => __( 'Please fill in your name and testimonial text.', 'simple-testimonials' ),
				'emailError'    => __( 'Please enter a valid email address.', 'simple-testimonials' ),
				'genericError'  => __( 'Something went wrong while submitting your testimonial. Please try again.', 'simple-testimonials' ),
			)
		);
	}

	/**
	 * Render `[testimonial_form]` shortcode output.
	 *
	 * @return string
	 */
	public function render_form_shortcode(): string {
		$settings = ST_Plugin::get_settings();
		if ( empty( $settings['enable_form'] ) ) {
			return '';
		}

		if ( ! wp_script_is( 'st-form-script', 'registered' ) ) {
			$this->register_scripts();
		}

		ST_Shortcode::enqueue_frontend_style();
		wp_enqueue_script( 'st-form-script' );

		++self::$instance_count;
		$suffix  = self::$instance_count > 1 ? '-' . self::$instance_count : '';
		$form_id = 'st-testimonial-form' . $suffix;

		ob_start();
		?>
		<div class="st-form-wrapper">
			<form id="<?php echo esc_attr( $form_id ); ?>" class="st-form" method="post" novalidate>
				<?php wp_nonce_field( 'st_submit_testimonial_action', 'st_form_nonce' ); ?>
				<input type="hidden" name="action" value="st_submit_testimonial" />

				<div class="st-form__honeypot" aria-hidden="true">
					<label for="<?php echo esc_attr( 'st_hp_field' . $suffix ); ?>">
						<?php esc_html_e( 'Leave this field empty', 'simple-testimonials' ); ?>
					</label>
					<input
						type="text"
						id="<?php echo esc_attr( 'st_hp_field' . $suffix ); ?>"
						name="st_hp_field"
						value=""
						tabindex="-1"
						autocomplete="new-password"
					/>
				</div>

				<div class="st-form__field">
					<label for="<?php echo esc_attr( 'st_author_name' . $suffix ); ?>">
						<?php esc_html_e( 'Your Name', 'simple-testimonials' ); ?>
						<span class="st-required" aria-hidden="true">*</span>
					</label>
					<input
						type="text"
						id="<?php echo esc_attr( 'st_author_name' . $suffix ); ?>"
						name="st_author_name"
						required
						maxlength="120"
						autocomplete="name"
					/>
				</div>

				<div class="st-form__row">
					<div class="st-form__field">
						<label for="<?php echo esc_attr( 'st_author_email' . $suffix ); ?>">
							<?php esc_html_e( 'Your Email', 'simple-testimonials' ); ?>
						</label>
						<input
							type="email"
							id="<?php echo esc_attr( 'st_author_email' . $suffix ); ?>"
							name="st_author_email"
							maxlength="120"
							autocomplete="email"
						/>
					</div>

					<div class="st-form__field">
						<label for="<?php echo esc_attr( 'st_author_company' . $suffix ); ?>">
							<?php esc_html_e( 'Position / Company', 'simple-testimonials' ); ?>
						</label>
						<input
							type="text"
							id="<?php echo esc_attr( 'st_author_company' . $suffix ); ?>"
							name="st_author_company"
							maxlength="100"
							autocomplete="organization"
						/>
					</div>
				</div>

				<fieldset class="st-form__field">
					<legend class="st-form__legend">
						<?php esc_html_e( 'Rating', 'simple-testimonials' ); ?>
						<span class="st-required" aria-hidden="true">*</span>
					</legend>
					<div class="st-rating-picker">
						<?php for ( $star = 5; $star >= 1; $star-- ) : ?>
							<?php
							$star_id    = 'st_rating_' . $star . $suffix;
							$star_label = sprintf(
								/* translators: %d: rating value from 1 to 5 */
								__( '%d out of 5 stars', 'simple-testimonials' ),
								$star
							);
							?>
							<input
								type="radio"
								id="<?php echo esc_attr( $star_id ); ?>"
								name="st_author_rating"
								value="<?php echo esc_attr( (string) $star ); ?>"
								<?php checked( 5, $star ); ?>
								required
							/>
							<label
								for="<?php echo esc_attr( $star_id ); ?>"
								title="<?php echo esc_attr( $star_label ); ?>"
								aria-label="<?php echo esc_attr( $star_label ); ?>"
							>★</label>
						<?php endfor; ?>
					</div>
				</fieldset>

				<div class="st-form__field">
					<label for="<?php echo esc_attr( 'st_testimonial_text' . $suffix ); ?>">
						<?php esc_html_e( 'Your Testimonial', 'simple-testimonials' ); ?>
						<span class="st-required" aria-hidden="true">*</span>
					</label>
					<textarea
						id="<?php echo esc_attr( 'st_testimonial_text' . $suffix ); ?>"
						name="st_testimonial_text"
						rows="5"
						maxlength="3000"
						required
					></textarea>
				</div>

				<div class="st-form__actions">
					<button type="submit" class="st-form__submit">
						<?php esc_html_e( 'Submit Testimonial', 'simple-testimonials' ); ?>
					</button>
				</div>

				<div class="st-form__message" role="status" aria-live="polite" hidden></div>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Process AJAX testimonial submission (`wp_ajax` / `wp_ajax_nopriv`).
	 *
	 * @return void
	 */
	public function handle_ajax_submission(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified immediately in process_submission().
		$result = $this->process_submission( $_POST );

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status     = is_array( $error_data ) && isset( $error_data['status'] ) ? (int) $error_data['status'] : 400;

			wp_send_json_error(
				array( 'message' => $result->get_error_message() ),
				$status
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Validate, rate-limit, insert pending testimonial, and trigger notification email.
	 *
	 * Separated from `wp_send_json_*` termination so automated integration tests can also invoke it directly.
	 *
	 * @param array $raw_data Submitted POST payload.
	 * @return array|\WP_Error
	 */
	public function process_submission( array $raw_data ) {
		$settings = ST_Plugin::get_settings();
		if ( empty( $settings['enable_form'] ) ) {
			return new \WP_Error(
				'st_form_disabled',
				__( 'Testimonial submissions are currently disabled.', 'simple-testimonials' ),
				array( 'status' => 403 )
			);
		}

		$nonce = isset( $raw_data['st_form_nonce'] )
			? sanitize_text_field( wp_unslash( (string) $raw_data['st_form_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'st_submit_testimonial_action' ) ) {
			return new \WP_Error(
				'st_invalid_nonce',
				__( 'Security verification failed. Please refresh the page and try again.', 'simple-testimonials' ),
				array( 'status' => 403 )
			);
		}

		// Anti-spam 1: Honeypot trap field (also accept legacy `st_hp_website` if posted).
		$honeypot = '';
		if ( isset( $raw_data['st_hp_field'] ) ) {
			$honeypot = sanitize_text_field( wp_unslash( (string) $raw_data['st_hp_field'] ) );
		} elseif ( isset( $raw_data['st_hp_website'] ) ) {
			$honeypot = sanitize_text_field( wp_unslash( (string) $raw_data['st_hp_website'] ) );
		}

		if ( '' !== $honeypot ) {
			return new \WP_Error(
				'st_honeypot_triggered',
				__( 'Spam submission blocked.', 'simple-testimonials' ),
				array( 'status' => 400 )
			);
		}

		// Anti-spam 2: Rate limit — maximum 1 testimonial per 60 seconds per IP.
		$ip_address    = $this->get_client_ip();
		$transient_key = $this->get_rate_limit_key( $ip_address );
		if ( false !== get_transient( $transient_key ) ) {
			return new \WP_Error(
				'st_rate_limited',
				__( 'You have recently submitted a testimonial. Please wait 60 seconds before submitting another.', 'simple-testimonials' ),
				array( 'status' => 429 )
			);
		}

		$name    = isset( $raw_data['st_author_name'] ) ? trim( sanitize_text_field( wp_unslash( (string) $raw_data['st_author_name'] ) ) ) : '';
		$raw_em  = isset( $raw_data['st_author_email'] ) ? trim( sanitize_text_field( wp_unslash( (string) $raw_data['st_author_email'] ) ) ) : '';
		$company = isset( $raw_data['st_author_company'] ) ? trim( sanitize_text_field( wp_unslash( (string) $raw_data['st_author_company'] ) ) ) : '';
		$rating  = isset( $raw_data['st_author_rating'] ) ? absint( $raw_data['st_author_rating'] ) : 0;
		$text    = isset( $raw_data['st_testimonial_text'] ) ? trim( sanitize_textarea_field( wp_unslash( (string) $raw_data['st_testimonial_text'] ) ) ) : '';

		$name    = mb_substr( $name, 0, 120 );
		$company = mb_substr( $company, 0, 100 );
		$text    = mb_substr( $text, 0, 3000 );

		if ( '' === $name || '' === $text ) {
			return new \WP_Error(
				'st_missing_required',
				__( 'Please fill in your name and testimonial text.', 'simple-testimonials' ),
				array( 'status' => 400 )
			);
		}

		if ( $rating < 1 || $rating > 5 ) {
			return new \WP_Error(
				'st_invalid_rating',
				__( 'Please select a rating between 1 and 5.', 'simple-testimonials' ),
				array( 'status' => 400 )
			);
		}

		$email = '';
		if ( '' !== $raw_em ) {
			$clean_email = sanitize_email( $raw_em );
			if ( ! is_email( $clean_email ) ) {
				return new \WP_Error(
					'st_invalid_email',
					__( 'Please enter a valid email address.', 'simple-testimonials' ),
					array( 'status' => 400 )
				);
			}
			$email = $clean_email;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'testimonial',
				'post_title'   => $name,
				'post_content' => $text,
				'post_status'  => 'pending',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return new \WP_Error(
				'st_insert_failed',
				__( 'Could not save your testimonial. Please try again later.', 'simple-testimonials' ),
				array( 'status' => 500 )
			);
		}

		update_post_meta( $post_id, '_st_company', $company );
		update_post_meta( $post_id, '_st_rating', $rating );
		update_post_meta( $post_id, '_st_email', $email );

		set_transient( $transient_key, time(), MINUTE_IN_SECONDS );

		$this->maybe_send_notification( $post_id, $name, $company, $email, $rating, $text );

		return array(
			'post_id' => $post_id,
			'message' => __( 'Thank you! Your testimonial has been submitted and will appear once approved by a moderator.', 'simple-testimonials' ),
		);
	}

	/**
	 * Build rate-limit transient key for a client IP address.
	 *
	 * @param string $ip_address Sanitized client IP.
	 * @return string
	 */
	public function get_rate_limit_key( string $ip_address ): string {
		return 'st_rl_' . md5( $ip_address );
	}

	/**
	 * Send moderation notification email to administrator if enabled in plugin settings.
	 *
	 * @param int    $post_id Created post ID.
	 * @param string $name    Author name.
	 * @param string $company Author company/position.
	 * @param string $email   Author email.
	 * @param int    $rating  Rating 1-5.
	 * @param string $text    Testimonial text.
	 * @return void
	 */
	private function maybe_send_notification( int $post_id, string $name, string $company, string $email, int $rating, string $text ): void {
		$settings = ST_Plugin::get_settings();
		if ( empty( $settings['enable_email'] ) ) {
			return;
		}

		$recipient = sanitize_email( (string) $settings['notification_email'] );
		if ( ! is_email( $recipient ) ) {
			$recipient = sanitize_email( (string) get_option( 'admin_email' ) );
		}

		if ( ! is_email( $recipient ) ) {
			return;
		}

		$site_name = wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
		$subject   = sprintf(
			/* translators: %s: website title */
			__( '[%s] New testimonial awaiting moderation', 'simple-testimonials' ),
			$site_name
		);

		$edit_link  = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
		$body_lines = array(
			__( 'A new client testimonial has been submitted and is awaiting moderation:', 'simple-testimonials' ),
			'',
			/* translators: %s: author name */
			sprintf( __( 'Author: %s', 'simple-testimonials' ), $name ),
			/* translators: %s: author position or company */
			sprintf( __( 'Position / Company: %s', 'simple-testimonials' ), '' !== $company ? $company : '—' ),
			/* translators: %s: author email */
			sprintf( __( 'Email: %s', 'simple-testimonials' ), '' !== $email ? $email : '—' ),
			/* translators: %d: rating value from 1 to 5 */
			sprintf( __( 'Rating: %d out of 5', 'simple-testimonials' ), $rating ),
			'',
			__( 'Testimonial:', 'simple-testimonials' ),
			$text,
			'',
			/* translators: %s: admin edit URL */
			sprintf( __( 'Review and moderate: %s', 'simple-testimonials' ), $edit_link ),
		);

		wp_mail( $recipient, $subject, implode( "\n", $body_lines ) );
	}

	/**
	 * Retrieve sanitized client IP address from `REMOTE_ADDR`.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$raw_ip = sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) );
			if ( false !== filter_var( $raw_ip, FILTER_VALIDATE_IP ) ) {
				return $raw_ip;
			}
		}

		return '0.0.0.0';
	}
}
