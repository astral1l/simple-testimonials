<?php
/**
 * Custom Post Type and Taxonomy Registration.
 *
 * @package SimpleTestimonials
 */

namespace SimpleTestimonials;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `testimonial` post type and `testimonial_category` taxonomy.
 */
class ST_CPT {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'after_setup_theme', array( $this, 'ensure_thumbnail_support' ) );
		add_action( 'init', array( $this, 'register_post_type' ), 5 );
		add_action( 'init', array( $this, 'register_taxonomy' ), 5 );
		add_filter( 'enter_title_here', array( $this, 'filter_title_placeholder' ), 10, 2 );
	}

	/**
	 * Ensure featured image support is enabled for testimonials regardless of theme.
	 *
	 * @return void
	 */
	public function ensure_thumbnail_support(): void {
		if ( ! current_theme_supports( 'post-thumbnails' ) ) {
			add_theme_support( 'post-thumbnails', array( 'testimonial' ) );
		} else {
			add_post_type_support( 'testimonial', 'thumbnail' );
		}
	}

	/**
	 * Register custom post type `testimonial`.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'                  => _x( 'Testimonials', 'Post type general name', 'simple-testimonials' ),
			'singular_name'         => _x( 'Testimonial', 'Post type singular name', 'simple-testimonials' ),
			'menu_name'             => _x( 'Testimonials', 'Admin Menu text', 'simple-testimonials' ),
			'name_admin_bar'        => _x( 'Testimonial', 'Add New on Toolbar', 'simple-testimonials' ),
			'add_new'               => __( 'Add New', 'simple-testimonials' ),
			'add_new_item'          => __( 'Add New Testimonial', 'simple-testimonials' ),
			'new_item'              => __( 'New Testimonial', 'simple-testimonials' ),
			'edit_item'             => __( 'Edit Testimonial', 'simple-testimonials' ),
			'view_item'             => __( 'View Testimonial', 'simple-testimonials' ),
			'all_items'             => __( 'All Testimonials', 'simple-testimonials' ),
			'search_items'          => __( 'Search Testimonials', 'simple-testimonials' ),
			'not_found'             => __( 'No testimonials found.', 'simple-testimonials' ),
			'not_found_in_trash'    => __( 'No testimonials found in Trash.', 'simple-testimonials' ),
			'featured_image'        => __( 'Author Photo', 'simple-testimonials' ),
			'set_featured_image'    => __( 'Set author photo', 'simple-testimonials' ),
			'remove_featured_image' => __( 'Remove author photo', 'simple-testimonials' ),
			'use_featured_image'    => __( 'Use as author photo', 'simple-testimonials' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 25,
			'menu_icon'          => 'dashicons-testimonial',
			'supports'           => array( 'title', 'editor', 'thumbnail' ),
			'show_in_rest'       => true,
		);

		register_post_type( 'testimonial', $args );
	}

	/**
	 * Register hierarchical taxonomy `testimonial_category`.
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		$labels = array(
			'name'              => _x( 'Testimonial Categories', 'Taxonomy general name', 'simple-testimonials' ),
			'singular_name'     => _x( 'Testimonial Category', 'Taxonomy singular name', 'simple-testimonials' ),
			'search_items'      => __( 'Search Categories', 'simple-testimonials' ),
			'all_items'         => __( 'All Categories', 'simple-testimonials' ),
			'parent_item'       => __( 'Parent Category', 'simple-testimonials' ),
			'parent_item_colon' => __( 'Parent Category:', 'simple-testimonials' ),
			'edit_item'         => __( 'Edit Category', 'simple-testimonials' ),
			'update_item'       => __( 'Update Category', 'simple-testimonials' ),
			'add_new_item'      => __( 'Add New Category', 'simple-testimonials' ),
			'new_item_name'     => __( 'New Category Name', 'simple-testimonials' ),
			'menu_name'         => __( 'Categories', 'simple-testimonials' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => false,
			'rewrite'           => false,
			'show_in_rest'      => true,
		);

		register_taxonomy( 'testimonial_category', array( 'testimonial' ), $args );
	}

	/**
	 * Customize editor title placeholder for `testimonial` posts.
	 *
	 * @param string   $placeholder Default placeholder text.
	 * @param \WP_Post $post        Current post object.
	 * @return string
	 */
	public function filter_title_placeholder( string $placeholder, \WP_Post $post ): string {
		if ( 'testimonial' === $post->post_type ) {
			return __( 'Enter author name', 'simple-testimonials' );
		}

		return $placeholder;
	}
}
