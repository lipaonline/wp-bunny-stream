<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPBS_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', [ 'WPBS_CPT', 'register' ] );
		add_action( 'init', [ 'WPBS_Shortcode', 'register' ] );
		add_action( 'init', [ 'WPBS_Gutenberg', 'register' ] );

		WPBS_Settings::init();
		WPBS_Uploader::init();
		WPBS_Webhook::init();
		WPBS_Beaver_Builder::init();

		load_plugin_textdomain( 'wp-bunny-stream', false, dirname( WPBS_BASENAME ) . '/languages' );
	}

	public static function get_option( $key, $default = '' ) {
		$opts = get_option( 'wpbs_settings', [] );
		return isset( $opts[ $key ] ) ? $opts[ $key ] : $default;
	}
}
