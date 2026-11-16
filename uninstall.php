<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Cleans up plugin options, temporary rate-limit transients, and optionally
 * deletes all testimonials and testimonial categories if configured in settings.
 *
 * @package SimpleTestimonials
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$simple_testimonials_settings = get_option( 'st_settings', array() );
$simple_testimonials_delete   = is_array( $simple_testimonials_settings ) && ! empty( $simple_testimonials_settings['delete_on_uninstall'] );

if ( $simple_testimonials_delete ) {
	if ( ! taxonomy_exists( 'testimonial_category' ) ) {
		register_taxonomy(
			'testimonial_category',
			'testimonial',
			array(
				'public'  => false,
				'rewrite' => false,
			)
		);
	}

	$simple_testimonials_posts = get_posts(
		array(
			'post_type'     => 'testimonial',
			'post_status'   => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ),
			'numberposts'   => -1,
			'fields'        => 'ids',
			'no_found_rows' => true,
		)
	);

	foreach ( $simple_testimonials_posts as $simple_testimonials_post_id ) {
		wp_delete_post( (int) $simple_testimonials_post_id, true );
	}

	$simple_testimonials_terms = get_terms(
		array(
			'taxonomy'   => 'testimonial_category',
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);

	if ( ! is_wp_error( $simple_testimonials_terms ) ) {
		foreach ( $simple_testimonials_terms as $simple_testimonials_term_id ) {
			wp_delete_term( (int) $simple_testimonials_term_id, 'testimonial_category' );
		}
	}
}

delete_option( 'st_settings' );

// Clean up rate-limit and validation transients created by the plugin.
$simple_testimonials_like_rl      = $wpdb->esc_like( '_transient_st_rl_' ) . '%';
$simple_testimonials_like_rl_time = $wpdb->esc_like( '_transient_timeout_st_rl_' ) . '%';
$simple_testimonials_like_err     = $wpdb->esc_like( '_transient_st_meta_err_' ) . '%';
$simple_testimonials_like_err_t   = $wpdb->esc_like( '_transient_timeout_st_meta_err_' ) . '%';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$simple_testimonials_like_rl,
		$simple_testimonials_like_rl_time,
		$simple_testimonials_like_err,
		$simple_testimonials_like_err_t
	)
);
