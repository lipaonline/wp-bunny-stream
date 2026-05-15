<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPBS_Beaver_Builder {

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_module' ], 20 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_picker' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_picker' ] );
	}

	public static function register_module() {
		if ( ! class_exists( 'FLBuilder' ) ) {
			return;
		}
		require_once WPBS_DIR . 'modules/bunny-video/bunny-video.php';
	}

	/**
	 * Enqueue the autocomplete enhancer wherever the Beaver Builder UI runs.
	 * BB loads its settings forms both in the frontend builder and the admin
	 * preview iframe, so we hook both contexts.
	 */
	public static function enqueue_picker() {
		if ( ! class_exists( 'FLBuilderModel' ) ) {
			return;
		}
		$is_builder = method_exists( 'FLBuilderModel', 'is_builder_active' ) && FLBuilderModel::is_builder_active();
		if ( ! $is_builder ) {
			return;
		}
		wp_enqueue_script(
			'wpbs-bb-picker',
			WPBS_URL . 'modules/bunny-video/js/bb-picker.js',
			[],
			WPBS_VERSION,
			true
		);
	}
}
