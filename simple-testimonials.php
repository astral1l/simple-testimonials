<?php
/**
 * Plugin Name:       Simple Testimonials
 * Plugin URI:        https://example.com/simple-testimonials
 * Description:       Manage, moderate, and display client testimonials via shortcode, Gutenberg block, and AJAX submission form.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            astrall
 * Author URI:        https://example.com
 * Text Domain:       simple-testimonials
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SimpleTestimonials
 */

namespace SimpleTestimonials;

defined( 'ABSPATH' ) || exit;

define( 'SIMPLE_TESTIMONIALS_VERSION', '1.0.0' );
define( 'SIMPLE_TESTIMONIALS_FILE', __FILE__ );
define( 'SIMPLE_TESTIMONIALS_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIMPLE_TESTIMONIALS_URL', plugin_dir_url( __FILE__ ) );
define( 'SIMPLE_TESTIMONIALS_BASENAME', plugin_basename( __FILE__ ) );

require_once SIMPLE_TESTIMONIALS_DIR . 'includes/class-st-cpt.php';
require_once SIMPLE_TESTIMONIALS_DIR . 'admin/class-st-metabox.php';
require_once SIMPLE_TESTIMONIALS_DIR . 'admin/class-st-settings.php';
require_once SIMPLE_TESTIMONIALS_DIR . 'public/class-st-shortcode.php';
require_once SIMPLE_TESTIMONIALS_DIR . 'public/class-st-form.php';
require_once SIMPLE_TESTIMONIALS_DIR . 'blocks/class-st-block.php';
require_once SIMPLE_TESTIMONIALS_DIR . 'includes/class-st-plugin.php';

register_activation_hook( __FILE__, array( ST_Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( ST_Plugin::class, 'deactivate' ) );
add_action( 'plugins_loaded', array( ST_Plugin::class, 'boot' ) );
