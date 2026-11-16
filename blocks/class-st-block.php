<?php
/**
 * Dynamic Gutenberg Block `simple-testimonials/list`.
 *
 * @package SimpleTestimonials
 */

namespace SimpleTestimonials;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `simple-testimonials/list` server-rendered block.
 */
class ST_Block {

	/**
	 * Shared shortcode and card renderer instance.
	 *
	 * @var ST_Shortcode
	 */
	private ST_Shortcode $shortcode;

	/**
	 * Constructor.
	 *
	 * @param ST_Shortcode $shortcode Shortcode renderer instance.
	 */
	public function __construct( ST_Shortcode $shortcode ) {
		$this->shortcode = $shortcode;
	}

	/**
	 * Initialize block hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_block' ), 10 );
	}

	/**
	 * Register editor script, script translations, and dynamic block type.
	 *
	 * @return void
	 */
	public function register_block(): void {
		$this->shortcode->register_styles();

		wp_register_script(
			'st-block-editor',
			SIMPLE_TESTIMONIALS_URL . 'blocks/testimonials-list/editor.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-i18n',
				'wp-components',
				'wp-block-editor',
				'wp-server-side-render',
				'wp-data',
				'wp-core-data',
			),
			SIMPLE_TESTIMONIALS_VERSION,
			true
		);

		$settings      = ST_Plugin::get_settings();
		$default_count = isset( $settings['default_count'] ) ? absint( $settings['default_count'] ) : 5;

		wp_add_inline_script(
			'st-block-editor',
			'window.stBlockConfig = ' . wp_json_encode( array( 'defaultCount' => $default_count ) ) . ';',
			'before'
		);

		wp_set_script_translations(
			'st-block-editor',
			'simple-testimonials',
			SIMPLE_TESTIMONIALS_DIR . 'languages'
		);

		register_block_type(
			SIMPLE_TESTIMONIALS_DIR . 'blocks/testimonials-list',
			array(
				'render_callback' => array( $this, 'render_block_callback' ),
			)
		);
	}

	/**
	 * Render dynamic block output via shared `ST_Shortcode::render_testimonials()`.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block_callback( array $attributes ): string {
		return $this->shortcode->render_testimonials( $attributes );
	}
}
