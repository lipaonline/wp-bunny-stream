<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPBS_Shortcode {

	public static function register() {
		add_shortcode( 'bunny_video', [ __CLASS__, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
	}

	public static function register_assets() {
		wp_register_style(
			'wpbs-frontend',
			WPBS_URL . 'assets/css/frontend.css',
			[],
			WPBS_VERSION
		);
		wp_register_script(
			'wpbs-chapters',
			WPBS_URL . 'assets/js/chapters.js',
			[],
			WPBS_VERSION,
			true
		);
	}

	public static function render( $atts ) {
		$atts = shortcode_atts( [
			'id'       => 0,
			'guid'     => '',
			'autoplay' => null,
			'loop'     => null,
			'muted'    => null,
			'preload'  => null,
			'color'    => '',
			't'        => '',
			'width'    => '',
			'ratio'    => '',
			'chapters' => 'auto',
		], $atts, 'bunny_video' );

		$guid       = $atts['guid'];
		$library_id = WPBS_Plugin::get_option( 'library_id' );
		$override   = [];
		$ratio      = self::parse_ratio( $atts['ratio'] );

		if ( $atts['id'] ) {
			$post_id = (int) $atts['id'];
			$guid    = $guid ?: get_post_meta( $post_id, '_wpbs_video_guid', true );
			$lib     = get_post_meta( $post_id, '_wpbs_library_id', true );
			if ( $lib ) {
				$library_id = $lib;
			}
			$json = get_post_meta( $post_id, '_wpbs_player_override', true );
			if ( $json ) {
				$decoded = json_decode( $json, true );
				if ( is_array( $decoded ) ) {
					$override = $decoded;
				}
			}
			if ( ! $ratio ) {
				$w = (int) get_post_meta( $post_id, '_wpbs_width', true );
				$h = (int) get_post_meta( $post_id, '_wpbs_height', true );
				if ( $w > 0 && $h > 0 ) {
					$ratio = $h / $w;
				}
			}
		}

		if ( ! $guid || ! $library_id ) {
			return self::render_missing( $atts, $guid, $library_id );
		}

		foreach ( [ 'autoplay', 'loop', 'muted', 'preload', 'color', 't' ] as $k ) {
			if ( null !== $atts[ $k ] && '' !== $atts[ $k ] ) {
				$override[ $k ] = $atts[ $k ];
			}
		}

		$html = self::build_iframe( $library_id, $guid, $override, $atts['width'], $ratio );

		if ( $atts['id'] && 'off' !== $atts['chapters'] ) {
			$html .= self::render_chapter_list( (int) $atts['id'] );
		}

		return $html;
	}

	private static function render_chapter_list( $post_id ) {
		$chapters = json_decode( get_post_meta( $post_id, '_wpbs_chapters', true ) ?: '[]', true );
		if ( ! is_array( $chapters ) || ! $chapters ) {
			return '';
		}

		wp_enqueue_style( 'wpbs-frontend' );
		wp_enqueue_script( 'wpbs-chapters' );

		$out  = '<ul class="wpbs-chapters">';
		foreach ( $chapters as $ch ) {
			$start = isset( $ch['start'] ) ? (int) $ch['start'] : 0;
			$title = isset( $ch['title'] ) ? $ch['title'] : '';
			$out  .= sprintf(
				'<li><a href="#" data-wpbs-seek="%d"><span class="wpbs-chapters__time">%s</span><span class="wpbs-chapters__title">%s</span></a></li>',
				$start,
				esc_html( self::format_time( $start ) ),
				esc_html( $title )
			);
		}
		$out .= '</ul>';
		return $out;
	}

	private static function format_time( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		$h = intdiv( $seconds, 3600 );
		$m = intdiv( $seconds % 3600, 60 );
		$s = $seconds % 60;
		return $h ? sprintf( '%d:%02d:%02d', $h, $m, $s ) : sprintf( '%d:%02d', $m, $s );
	}

	/**
	 * Accept ratios as "9:16", "16:9", "1.78", "0.5625", or a decimal height/width.
	 * Returns the height/width ratio as a float, or 0 to fall back.
	 */
	private static function parse_ratio( $ratio ) {
		if ( ! $ratio ) {
			return 0;
		}
		$ratio = trim( (string) $ratio );
		if ( strpos( $ratio, ':' ) !== false ) {
			list( $w, $h ) = array_pad( array_map( 'floatval', explode( ':', $ratio ) ), 2, 0 );
			return ( $w > 0 && $h > 0 ) ? $h / $w : 0;
		}
		$f = (float) $ratio;
		return $f > 0 ? $f : 0;
	}

	private static function render_missing( $atts, $guid, $library_id ) {
		$reasons = [];
		if ( ! $atts['id'] && ! $guid ) {
			$reasons[] = __( 'No video selected.', 'wp-bunny-stream' );
		}
		if ( $atts['id'] && ! $guid ) {
			$reasons[] = sprintf(
				/* translators: %d post id */
				__( 'Post #%d has no Bunny video uploaded yet (missing _wpbs_video_guid).', 'wp-bunny-stream' ),
				(int) $atts['id']
			);
		}
		if ( ! $library_id ) {
			$reasons[] = __( 'Library ID is empty — configure it in Bunny Videos → Settings.', 'wp-bunny-stream' );
		}

		$comment = '<!-- WP Bunny Stream: ' . esc_html( implode( ' / ', $reasons ) ) . ' -->';

		$show_notice = is_user_logged_in() && current_user_can( 'edit_posts' );
		if ( class_exists( 'FLBuilderModel' ) && method_exists( 'FLBuilderModel', 'is_builder_active' ) ) {
			$show_notice = $show_notice || FLBuilderModel::is_builder_active();
		}
		if ( ! $show_notice ) {
			return $comment;
		}

		return $comment . sprintf(
			'<div style="padding:14px;border:1px dashed #d63638;background:#fcf0f1;color:#8a1f23;font-family:sans-serif;font-size:13px;border-radius:4px"><strong>WP Bunny Stream:</strong> %s</div>',
			esc_html( implode( ' ', $reasons ) )
		);
	}

	public static function build_iframe( $library_id, $guid, $override = [], $width = '', $ratio = 0 ) {
		$defaults = [
			'autoplay' => WPBS_Plugin::get_option( 'player_autoplay', 0 ),
			'loop'     => WPBS_Plugin::get_option( 'player_loop', 0 ),
			'muted'    => WPBS_Plugin::get_option( 'player_muted', 0 ),
			'preload'  => WPBS_Plugin::get_option( 'player_preload', 0 ),
			'color'    => WPBS_Plugin::get_option( 'player_color', '' ),
			't'        => '',
		];
		$opts = array_merge( $defaults, array_filter( $override, function ( $v ) { return $v !== '' && $v !== null; } ) );

		$params = [];
		foreach ( [ 'autoplay', 'loop', 'muted', 'preload' ] as $k ) {
			$params[ $k ] = ! empty( $opts[ $k ] ) ? 'true' : 'false';
		}
		if ( ! empty( $opts['color'] ) ) {
			$params['playerColor'] = ltrim( $opts['color'], '#' );
		}
		if ( ! empty( $opts['t'] ) ) {
			$params['t'] = (int) $opts['t'];
		}

		$src = sprintf(
			'https://iframe.mediadelivery.net/embed/%s/%s?%s',
			rawurlencode( $library_id ),
			rawurlencode( $guid ),
			http_build_query( $params )
		);

		$responsive = (int) WPBS_Plugin::get_option( 'player_responsive', 1 );

		if ( $responsive ) {
			$ratio_float = $ratio > 0 ? (float) $ratio : 9 / 16;
			$padding     = round( $ratio_float * 100, 4 );

			$is_portrait = $ratio_float > 1;
			$max_width   = $width;
			if ( ! $max_width && $is_portrait ) {
				// Cap vertical videos so they don't take the full content width.
				$max_width = '420px';
			}

			$style_wrap  = 'position:relative;padding-top:' . $padding . '%;';
			$style_wrap .= $max_width ? 'max-width:' . esc_attr( $max_width ) . ';margin-left:auto;margin-right:auto;' : '';
			$style_if    = 'border:0;position:absolute;top:0;left:0;height:100%;width:100%;';
			return sprintf(
				'<div class="wpbs-embed wpbs-embed--%s" style="%s"><iframe src="%s" style="%s" loading="lazy" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;" allowfullscreen></iframe></div>',
				$is_portrait ? 'portrait' : 'landscape',
				esc_attr( $style_wrap ),
				esc_url( $src ),
				esc_attr( $style_if )
			);
		}

		$w = $width ?: '100%';
		return sprintf(
			'<iframe class="wpbs-embed" src="%s" width="%s" height="450" style="border:0" loading="lazy" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;" allowfullscreen></iframe>',
			esc_url( $src ),
			esc_attr( $w )
		);
	}
}
