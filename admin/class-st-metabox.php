<?php
/**
 * Metabox and Admin List Table Columns for Testimonials.
 *
 * @package SimpleTestimonials
 */

namespace SimpleTestimonials;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the testimonial details metabox, field validation, and admin list columns.
 */
class ST_Metabox {

	/**
	 * Initialize admin hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'add_meta_boxes_testimonial', array( $this, 'register_meta_box' ) );
		add_action( 'save_post_testimonial', array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'render_validation_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );

		add_filter( 'manage_testimonial_posts_columns', array( $this, 'add_custom_columns' ) );
		add_action( 'manage_testimonial_posts_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );
		add_filter( 'manage_edit-testimonial_sortable_columns', array( $this, 'register_sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'handle_column_sorting' ) );
	}

	/**
	 * Enqueue admin stylesheet on `testimonial` edit and list screens.
	 *
	 * @return void
	 */
	public function enqueue_admin_styles(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'testimonial' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'st-admin-style',
			SIMPLE_TESTIMONIALS_URL . 'admin/css/admin.css',
			array(),
			SIMPLE_TESTIMONIALS_VERSION
		);

		$settings   = ST_Plugin::get_settings();
		$star_color = sanitize_hex_color( (string) $settings['star_color'] );
		if ( empty( $star_color ) ) {
			$star_color = '#f59e0b';
		}

		wp_add_inline_style(
			'st-admin-style',
			':root { --st-admin-star-color: ' . esc_attr( $star_color ) . '; }'
		);
	}

	/**
	 * Register custom fields metabox on the `testimonial` edit screen.
	 *
	 * @return void
	 */
	public function register_meta_box(): void {
		add_meta_box(
			'st_testimonial_details',
			__( 'Testimonial Details', 'simple-testimonials' ),
			array( $this, 'render_meta_box' ),
			'testimonial',
			'normal',
			'high'
		);
	}

	/**
	 * Render metabox fields.
	 *
	 * @param \WP_Post $post Current post object.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'st_save_testimonial_meta', 'st_testimonial_meta_nonce' );

		$company = (string) get_post_meta( $post->ID, '_st_company', true );
		$rating  = (int) get_post_meta( $post->ID, '_st_rating', true );
		$email   = (string) get_post_meta( $post->ID, '_st_email', true );
		$url     = (string) get_post_meta( $post->ID, '_st_url', true );

		if ( $rating < 1 || $rating > 5 ) {
			$rating = 5;
		}
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="st_company"><?php esc_html_e( 'Position / Company', 'simple-testimonials' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							id="st_company"
							name="st_company"
							value="<?php echo esc_attr( $company ); ?>"
							maxlength="100"
							class="regular-text"
						/>
						<p class="description"><?php esc_html_e( 'Optional. Up to 100 characters.', 'simple-testimonials' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="st_rating">
							<?php esc_html_e( 'Rating', 'simple-testimonials' ); ?>
							<span class="st-required-mark" aria-hidden="true">*</span>
						</label>
					</th>
					<td>
						<select id="st_rating" name="st_rating" required>
							<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
								<option value="<?php echo esc_attr( (string) $i ); ?>" <?php selected( $rating, $i ); ?>>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: rating value from 1 to 5 */
											__( '%d out of 5', 'simple-testimonials' ),
											$i
										) . ' (' . str_repeat( '★', $i ) . str_repeat( '☆', 5 - $i ) . ')'
									);
									?>
								</option>
							<?php endfor; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="st_email"><?php esc_html_e( 'Author Email', 'simple-testimonials' ); ?></label>
					</th>
					<td>
						<input
							type="email"
							id="st_email"
							name="st_email"
							value="<?php echo esc_attr( $email ); ?>"
							class="regular-text"
						/>
						<p class="description"><?php esc_html_e( 'Admin only. Never displayed on the public site.', 'simple-testimonials' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="st_url"><?php esc_html_e( 'Author Website URL', 'simple-testimonials' ); ?></label>
					</th>
					<td>
						<input
							type="url"
							id="st_url"
							name="st_url"
							value="<?php echo esc_url( $url ); ?>"
							placeholder="https://example.com"
							class="regular-text"
						/>
						<p class="description"><?php esc_html_e( 'Optional. Must start with http:// or https://.', 'simple-testimonials' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Validate and save metabox fields to post meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta_box( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['st_testimonial_meta_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['st_testimonial_meta_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'st_save_testimonial_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || 'testimonial' !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$errors = array();

		if ( isset( $_POST['st_company'] ) ) {
			$raw_company = sanitize_text_field( wp_unslash( $_POST['st_company'] ) );
			if ( mb_strlen( $raw_company ) > 100 ) {
				$errors[]    = __( 'Position / Company was truncated to 100 characters.', 'simple-testimonials' );
				$raw_company = mb_substr( $raw_company, 0, 100 );
			}
			update_post_meta( $post_id, '_st_company', $raw_company );
		}

		$rating = isset( $_POST['st_rating'] ) ? absint( $_POST['st_rating'] ) : 0;
		if ( $rating < 1 || $rating > 5 ) {
			$errors[] = __( 'Rating must be a number between 1 and 5. Default rating (5) was applied.', 'simple-testimonials' );
			$rating   = 5;
		}
		update_post_meta( $post_id, '_st_rating', $rating );

		if ( isset( $_POST['st_email'] ) ) {
			$raw_email = trim( sanitize_text_field( wp_unslash( $_POST['st_email'] ) ) );
			if ( '' === $raw_email ) {
				update_post_meta( $post_id, '_st_email', '' );
			} else {
				$clean_email = sanitize_email( $raw_email );
				if ( is_email( $clean_email ) ) {
					update_post_meta( $post_id, '_st_email', $clean_email );
				} else {
					$errors[] = __( 'Invalid Author Email format. The email field was not saved.', 'simple-testimonials' );
					update_post_meta( $post_id, '_st_email', '' );
				}
			}
		}

		if ( isset( $_POST['st_url'] ) ) {
			$raw_url = trim( sanitize_text_field( wp_unslash( $_POST['st_url'] ) ) );
			if ( '' === $raw_url ) {
				update_post_meta( $post_id, '_st_url', '' );
			} else {
				$validated_url = esc_url_raw( $raw_url, array( 'http', 'https' ) );
				if ( '' !== $validated_url && false !== filter_var( $validated_url, FILTER_VALIDATE_URL ) ) {
					update_post_meta( $post_id, '_st_url', $validated_url );
				} else {
					$errors[] = __( 'Invalid Author Website URL. Please enter a valid http:// or https:// address.', 'simple-testimonials' );
					update_post_meta( $post_id, '_st_url', '' );
				}
			}
		}

		$transient_key = $this->get_error_transient_key( $post_id );
		if ( ! empty( $errors ) ) {
			set_transient( $transient_key, $errors, 60 );
		} else {
			delete_transient( $transient_key );
		}
	}

	/**
	 * Display validation warning notices on the testimonial edit screen.
	 *
	 * @return void
	 */
	public function render_validation_notices(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base || 'testimonial' !== $screen->post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( 0 === $post_id ) {
			return;
		}

		$transient_key = $this->get_error_transient_key( $post_id );
		$errors        = get_transient( $transient_key );
		if ( ! is_array( $errors ) || empty( $errors ) ) {
			return;
		}

		delete_transient( $transient_key );

		foreach ( $errors as $error_msg ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html( (string) $error_msg )
			);
		}
	}

	/**
	 * Build transient key for storing per-user metabox validation notices.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_error_transient_key( int $post_id ): string {
		return 'st_meta_err_' . get_current_user_id() . '_' . $post_id;
	}

	/**
	 * Add custom columns (`Company` and `Rating`) to the testimonial admin list table.
	 *
	 * @param array $columns Existing list table columns.
	 * @return array
	 */
	public function add_custom_columns( array $columns ): array {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				$new_columns['st_company'] = __( 'Company', 'simple-testimonials' );
				$new_columns['st_rating']  = __( 'Rating', 'simple-testimonials' );
			}
			$new_columns[ $key ] = $label;
		}

		if ( ! isset( $new_columns['st_rating'] ) ) {
			$new_columns['st_company'] = __( 'Company', 'simple-testimonials' );
			$new_columns['st_rating']  = __( 'Rating', 'simple-testimonials' );
		}

		return $new_columns;
	}

	/**
	 * Render custom column output in the testimonial list table.
	 *
	 * @param string $column  Column slug.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_custom_columns( string $column, int $post_id ): void {
		if ( 'st_company' === $column ) {
			$company = (string) get_post_meta( $post_id, '_st_company', true );
			echo '' !== $company ? esc_html( $company ) : '—';
			return;
		}

		if ( 'st_rating' === $column ) {
			$rating = (int) get_post_meta( $post_id, '_st_rating', true );
			if ( $rating >= 1 && $rating <= 5 ) {
				$filled = str_repeat( '★', $rating );
				$empty  = str_repeat( '★', 5 - $rating );
				$label  = sprintf(
					/* translators: %d: rating value from 1 to 5 */
					__( '%d out of 5', 'simple-testimonials' ),
					$rating
				);

				echo '<span class="st-admin-stars" role="img" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '">';
				echo esc_html( $filled );
				if ( '' !== $empty ) {
					echo '<span class="st-admin-stars__empty" aria-hidden="true">' . esc_html( $empty ) . '</span>';
				}
				echo '</span>';
				echo '<span class="st-admin-rating-num">(' . esc_html( (string) $rating ) . ')</span>';
			} else {
				echo '—';
			}
		}
	}

	/**
	 * Register the `Rating` column as sortable.
	 *
	 * @param array $columns Sortable columns map.
	 * @return array
	 */
	public function register_sortable_columns( array $columns ): array {
		$columns['st_rating'] = 'st_rating';
		return $columns;
	}

	/**
	 * Modify admin list query when sorting by `st_rating` without hiding drafts lacking meta.
	 *
	 * @param \WP_Query $query Current query instance.
	 * @return void
	 */
	public function handle_column_sorting( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'testimonial' !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( 'st_rating' !== $query->get( 'orderby' ) ) {
			return;
		}

		$order      = strtoupper( (string) $query->get( 'order' ) );
		$order      = 'ASC' === $order ? 'ASC' : 'DESC';
		$meta_query = $query->get( 'meta_query' );

		if ( ! is_array( $meta_query ) ) {
			$meta_query = array();
		}

		$meta_query['st_rating_sort'] = array(
			'relation'         => 'OR',
			'st_rating_clause' => array(
				'key'     => '_st_rating',
				'compare' => 'NOT EXISTS',
				'type'    => 'NUMERIC',
			),
			array(
				'key'     => '_st_rating',
				'value'   => 0,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			),
		);

		$query->set( 'meta_query', $meta_query );
		$query->set(
			'orderby',
			array(
				'st_rating_clause' => $order,
				'date'             => 'DESC',
			)
		);
	}
}
