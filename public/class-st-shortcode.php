<?php
/**
 * Shortcode [testimonials] and Shared Server-Side Card Renderer.
 *
 * @package SimpleTestimonials
 */

namespace SimpleTestimonials;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `[testimonials]` and renders testimonial cards for both shortcode and Gutenberg block.
 */
class ST_Shortcode {

	/**
	 * Initialize hooks.
	 *
	 * Style registration runs on `init` (priority 5) so styles are already registered
	 * before block themes (Twenty Twenty-Four / Twenty Twenty-Five) render templates.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_styles' ), 5 );
		add_shortcode( 'testimonials', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Register frontend stylesheet and attach dynamic `--st-star-color` CSS variable.
	 *
	 * @return void
	 */
	public function register_styles(): void {
		if ( ! wp_style_is( 'st-frontend-style', 'registered' ) ) {
			wp_register_style(
				'st-frontend-style',
				SIMPLE_TESTIMONIALS_URL . 'public/css/frontend.css',
				array(),
				SIMPLE_TESTIMONIALS_VERSION
			);
		}

		self::attach_star_color_inline_style();
	}

	/**
	 * Attach `--st-star-color` inline CSS variable to `st-frontend-style` once.
	 *
	 * @return void
	 */
	public static function attach_star_color_inline_style(): void {
		$wp_styles = wp_styles();
		$existing  = $wp_styles->get_data( 'st-frontend-style', 'after' );
		if ( ! empty( $existing ) ) {
			return;
		}

		$settings   = ST_Plugin::get_settings();
		$star_color = sanitize_hex_color( (string) $settings['star_color'] );
		if ( empty( $star_color ) ) {
			$star_color = '#f59e0b';
		}

		wp_add_inline_style(
			'st-frontend-style',
			':root { --st-star-color: ' . esc_attr( $star_color ) . '; }'
		);
	}

	/**
	 * Enqueue frontend stylesheet on demand (only when shortcode, block, or form is rendered).
	 *
	 * @return void
	 */
	public static function enqueue_frontend_style(): void {
		if ( ! wp_style_is( 'st-frontend-style', 'registered' ) ) {
			wp_register_style(
				'st-frontend-style',
				SIMPLE_TESTIMONIALS_URL . 'public/css/frontend.css',
				array(),
				SIMPLE_TESTIMONIALS_VERSION
			);
		}

		self::attach_star_color_inline_style();
		wp_enqueue_style( 'st-frontend-style' );
	}

	/**
	 * Handle `[testimonials]` shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts = array() ): string {
		$settings      = ST_Plugin::get_settings();
		$default_count = isset( $settings['default_count'] ) ? absint( $settings['default_count'] ) : 5;

		$parsed_atts = shortcode_atts(
			array(
				'count'      => $default_count,
				'category'   => '',
				'min_rating' => 1,
				'layout'     => 'grid',
				'orderby'    => 'date',
			),
			is_array( $atts ) ? $atts : array(),
			'testimonials'
		);

		return $this->render_testimonials( $parsed_atts );
	}

	/**
	 * Shared rendering method used by both `[testimonials]` shortcode and `simple-testimonials/list` block.
	 *
	 * @param array $args Display arguments (`count`, `category`, `min_rating`, `layout`, `orderby`, `align`).
	 * @return string
	 */
	public function render_testimonials( array $args ): string {
		self::enqueue_frontend_style();

		$settings      = ST_Plugin::get_settings();
		$default_count = isset( $settings['default_count'] ) ? absint( $settings['default_count'] ) : 5;
		if ( $default_count < 1 ) {
			$default_count = 5;
		}

		$raw_count  = isset( $args['count'] ) ? absint( $args['count'] ) : 0;
		$count      = $raw_count > 0 ? min( 100, $raw_count ) : $default_count;
		$category   = isset( $args['category'] ) ? sanitize_text_field( (string) $args['category'] ) : '';
		$min_rating = isset( $args['min_rating'] ) ? absint( $args['min_rating'] ) : 1;
		$min_rating = max( 1, min( 5, $min_rating ) );

		$layout = isset( $args['layout'] ) ? sanitize_key( (string) $args['layout'] ) : 'grid';
		if ( ! in_array( $layout, array( 'grid', 'list' ), true ) ) {
			$layout = 'grid';
		}

		$orderby = isset( $args['orderby'] ) ? sanitize_key( (string) $args['orderby'] ) : 'date';
		if ( ! in_array( $orderby, array( 'date', 'rating', 'rand' ), true ) ) {
			$orderby = 'date';
		}

		$align_class = '';
		if ( ! empty( $args['align'] ) ) {
			$align = sanitize_key( (string) $args['align'] );
			if ( in_array( $align, array( 'wide', 'full' ), true ) ) {
				$align_class = ' align' . $align;
			}
		}

		$query_args = array(
			'post_type'      => 'testimonial',
			'post_status'    => 'publish',
			'posts_per_page' => $count,
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				'rating_clause' => array(
					'key'     => '_st_rating',
					'value'   => $min_rating,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			),
		);

		if ( '' !== $category ) {
			$slugs = array_filter(
				array_map(
					'sanitize_title',
					array_map( 'trim', explode( ',', $category ) )
				)
			);

			if ( ! empty( $slugs ) ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				$query_args['tax_query'] = array(
					array(
						'taxonomy' => 'testimonial_category',
						'field'    => 'slug',
						'terms'    => array_values( $slugs ),
					),
				);
			}
		}

		if ( 'rating' === $orderby ) {
			$query_args['orderby'] = array(
				'rating_clause' => 'DESC',
				'date'          => 'DESC',
			);
		} elseif ( 'rand' === $orderby ) {
			$query_args['orderby'] = 'rand';
		} else {
			$query_args['orderby'] = 'date';
			$query_args['order']   = 'DESC';
		}

		$query = new \WP_Query( $query_args );

		if ( ! $query->have_posts() ) {
			return '<p class="st-empty">' . esc_html__( 'No testimonials found.', 'simple-testimonials' ) . '</p>';
		}

		ob_start();
		?>
		<div class="st-testimonials st-layout-<?php echo esc_attr( $layout . $align_class ); ?>">
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				$post_id  = (int) get_the_ID();
				$name     = get_the_title( $post_id );
				$company  = (string) get_post_meta( $post_id, '_st_company', true );
				$rating   = (int) get_post_meta( $post_id, '_st_rating', true );
				$raw_url  = (string) get_post_meta( $post_id, '_st_url', true );
				$safe_url = '' !== $raw_url ? esc_url( $raw_url, array( 'http', 'https' ) ) : '';

				$rating   = max( 1, min( 5, $rating ) );
				$initials = $this->get_initials( $name );

				$raw_content = get_the_content( null, false, $post_id );
				$formatted   = has_blocks( $raw_content ) ? do_blocks( $raw_content ) : wpautop( $raw_content );

				$rating_label = sprintf(
					/* translators: %d: rating value from 1 to 5 */
					__( 'Rating: %d out of 5', 'simple-testimonials' ),
					$rating
				);
				?>
				<article class="st-card">
					<header class="st-card__header">
						<div class="st-card__avatar">
							<?php if ( has_post_thumbnail( $post_id ) ) : ?>
								<?php
								echo wp_kses_post(
									get_the_post_thumbnail(
										$post_id,
										'thumbnail',
										array(
											'class'   => 'st-card__photo',
											'loading' => 'lazy',
											'alt'     => $name,
										)
									)
								);
								?>
							<?php else : ?>
								<span class="st-card__initials" aria-hidden="true"><?php echo esc_html( $initials ); ?></span>
							<?php endif; ?>
						</div>
						<div class="st-card__meta">
							<h3 class="st-card__author">
								<?php if ( '' !== $safe_url ) : ?>
									<a href="<?php echo esc_url( $safe_url ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( $name ); ?>
									</a>
								<?php else : ?>
									<?php echo esc_html( $name ); ?>
								<?php endif; ?>
							</h3>
							<?php if ( '' !== $company ) : ?>
								<p class="st-card__company"><?php echo esc_html( $company ); ?></p>
							<?php endif; ?>
							<div class="st-card__rating" role="img" aria-label="<?php echo esc_attr( $rating_label ); ?>">
								<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
									<span class="st-star <?php echo $i <= $rating ? 'st-star--filled' : 'st-star--empty'; ?>" aria-hidden="true">★</span>
								<?php endfor; ?>
							</div>
						</div>
					</header>
					<div class="st-card__content">
						<?php echo wp_kses_post( $formatted ); ?>
					</div>
				</article>
			<?php endwhile; ?>
		</div>
		<?php
		wp_reset_postdata();

		return (string) ob_get_clean();
	}

	/**
	 * Extract up to two uppercase initials from an author name.
	 *
	 * @param string $name Author name.
	 * @return string
	 */
	private function get_initials( string $name ): string {
		$clean = trim( wp_strip_all_tags( $name ) );
		if ( '' === $clean ) {
			return '?';
		}

		$words    = preg_split( '/\s+/u', $clean );
		$initials = '';

		if ( is_array( $words ) ) {
			foreach ( array_slice( $words, 0, 2 ) as $word ) {
				if ( '' !== $word ) {
					$initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
				}
			}
		}

		return '' !== $initials ? $initials : '?';
	}
}
