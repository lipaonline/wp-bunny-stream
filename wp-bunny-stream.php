<?php
/**
 * Plugin Name: WP Bunny Stream
 * Description: Upload large video files to Bunny Stream with a dedicated CPT, taxonomies, customizable player, shortcode, Gutenberg block and Beaver Builder module.
 * Version: 1.0.0
 * Author: lipa
 * License: GPL-2.0-or-later
 * Text Domain: wp-bunny-stream
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPBS_VERSION', '1.0.0' );
define( 'WPBS_FILE', __FILE__ );
define( 'WPBS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPBS_URL', plugin_dir_url( __FILE__ ) );
define( 'WPBS_BASENAME', plugin_basename( __FILE__ ) );

require_once WPBS_DIR . 'includes/class-plugin.php';
require_once WPBS_DIR . 'includes/class-settings.php';
require_once WPBS_DIR . 'includes/class-cpt.php';
require_once WPBS_DIR . 'includes/class-bunny-api.php';
require_once WPBS_DIR . 'includes/class-uploader.php';
require_once WPBS_DIR . 'includes/class-webhook.php';
require_once WPBS_DIR . 'includes/class-shortcode.php';
require_once WPBS_DIR . 'includes/class-gutenberg.php';
require_once WPBS_DIR . 'includes/class-beaver-builder.php';

add_action( 'plugins_loaded', [ 'WPBS_Plugin', 'instance' ] );

register_activation_hook( __FILE__, function () {
	WPBS_CPT::register();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
