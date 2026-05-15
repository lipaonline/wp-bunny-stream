<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPBS_Gutenberg {

	public static function register() {
		register_block_type( WPBS_DIR . 'blocks/bunny-video', [
			'render_callback' => [ __CLASS__, 'render' ],
		] );
	}

	public static function render( $attrs ) {
		$atts = [
			'id'       => isset( $attrs['videoId'] ) ? (int) $attrs['videoId'] : 0,
			'guid'     => isset( $attrs['guid'] ) ? sanitize_text_field( $attrs['guid'] ) : '',
			'autoplay' => isset( $attrs['autoplay'] ) ? ( $attrs['autoplay'] ? '1' : '0' ) : null,
			'loop'     => isset( $attrs['loop'] ) ? ( $attrs['loop'] ? '1' : '0' ) : null,
			'muted'    => isset( $attrs['muted'] ) ? ( $attrs['muted'] ? '1' : '0' ) : null,
			'preload'  => isset( $attrs['preload'] ) ? ( $attrs['preload'] ? '1' : '0' ) : null,
			'color'    => isset( $attrs['color'] ) ? sanitize_hex_color( $attrs['color'] ) : '',
			't'        => isset( $attrs['startAt'] ) ? (int) $attrs['startAt'] : '',
			'ratio'    => isset( $attrs['ratio'] ) ? sanitize_text_field( $attrs['ratio'] ) : '',
		];
		return WPBS_Shortcode::render( $atts );
	}
}
