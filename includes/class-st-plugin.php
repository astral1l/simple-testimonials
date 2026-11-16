<?php
/**
 * Main Plugin Orchestrator Class.
 *
 * @package SimpleTestimonials
 */

namespace SimpleTestimonials;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates all plugin components and lifecycle hooks.
 */
class ST_Plugin {

	/**
	 * Singleton plugin instance.
	 *
	 * @var ST_Plugin|null
	 */
	private static ?ST_Plugin $instance = null;

	/**
	 * Custom Post Type and Taxonomy component.
	 *
	 * @var ST_CPT
	 */
	private ST_CPT $cpt;

	/**
	 * Admin metabox and list table columns component.
	 *
	 * @var ST_Metabox
	 */
	private ST_Metabox $metabox;

	/**
	 * Settings page component.
	 *
	 * @var ST_Settings
	 */
	private ST_Settings $settings;

	/**
	 * Frontend shortcode and card renderer component.
	 *
	 * @var ST_Shortcode
	 */
	private ST_Shortcode $shortcode;

	/**
	 * Visitor submission form component.
	 *
	 * @var ST_Form
	 */
	private ST_Form $form;

	/**
	 * Dynamic Gutenberg block component.
	 *
	 * @var ST_Block
	 */
	private ST_Block $block;

	/**
	 * Boot and return the singleton instance.
	 *
	 * @return ST_Plugin
	 */
	public static function boot(): ST_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->cpt       = new ST_CPT();
		$this->metabox   = new ST_Metabox();
		$this->settings  = new ST_Settings();
		$this->shortcode = new ST_Shortcode();
		$this->form      = new ST_Form();
		$this->block     = new ST_Block( $this->shortcode );
	}

	/**
	 * Register hooks for all components.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );

		$this->cpt->init();
		$this->metabox->init();
		$this->settings->init();
		$this->shortcode->init();
		$this->form->init();
		$this->block->init();
	}

	/**
	 * Load plugin translations before CPT, taxonomy, and blocks are registered.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'simple-testimonials',
			false,
			dirname( SIMPLE_TESTIMONIALS_BASENAME ) . '/languages'
		);
	}

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		$cpt = new ST_CPT();
		$cpt->register_post_type();
		$cpt->register_taxonomy();

		if ( false === get_option( 'st_settings', false ) ) {
			add_option( 'st_settings', self::get_default_settings() );
		}

		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Return default plugin settings.
	 *
	 * @return array
	 */
	public static function get_default_settings(): array {
		return array(
			'default_count'       => 5,
			'enable_form'         => 1,
			'enable_email'        => 1,
			'notification_email'  => (string) get_option( 'admin_email' ),
			'star_color'          => '#f59e0b',
			'delete_on_uninstall' => 0,
		);
	}

	/**
	 * Retrieve merged plugin settings with guaranteed defaults.
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		$defaults = self::get_default_settings();
		$saved    = get_option( 'st_settings', array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Get CPT component instance.
	 *
	 * @return ST_CPT
	 */
	public function get_cpt(): ST_CPT {
		return $this->cpt;
	}

	/**
	 * Get Metabox component instance.
	 *
	 * @return ST_Metabox
	 */
	public function get_metabox(): ST_Metabox {
		return $this->metabox;
	}

	/**
	 * Get Settings component instance.
	 *
	 * @return ST_Settings
	 */
	public function get_settings_controller(): ST_Settings {
		return $this->settings;
	}

	/**
	 * Get Shortcode component instance.
	 *
	 * @return ST_Shortcode
	 */
	public function get_shortcode(): ST_Shortcode {
		return $this->shortcode;
	}

	/**
	 * Get Form component instance.
	 *
	 * @return ST_Form
	 */
	public function get_form(): ST_Form {
		return $this->form;
	}

	/**
	 * Get Block component instance.
	 *
	 * @return ST_Block
	 */
	public function get_block(): ST_Block {
		return $this->block;
	}
}
