<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPBS_Webhook {

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_route' ] );
	}

	public static function register_route() {
		register_rest_route( 'wp-bunny-stream/v1', '/webhook', [
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => [ __CLASS__, 'handle' ],
		] );
	}

	public static function handle( WP_REST_Request $request ) {
		$raw    = $request->get_body();
		$secret = WPBS_Plugin::get_option( 'webhook_secret' );

		if ( $secret ) {
			$got = $request->get_param( 'secret' );
			if ( ! hash_equals( (string) $secret, (string) $got ) ) {
				return new WP_REST_Response( [ 'error' => 'bad secret' ], 401 );
			}
		} else {
			$api_key = WPBS_Plugin::get_option( 'api_key' );
			$header  = $request->get_header( 'x-bunnystream-signature' );
			if ( $api_key && $header ) {
				$expected = hash_hmac( 'sha256', $raw, $api_key );
				if ( ! hash_equals( $expected, $header ) ) {
					return new WP_REST_Response( [ 'error' => 'bad signature' ], 401 );
				}
			}
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_REST_Response( [ 'error' => 'bad payload' ], 400 );
		}

		$guid   = isset( $data['VideoGuid'] ) ? sanitize_text_field( $data['VideoGuid'] ) : '';
		$status = isset( $data['Status'] ) ? (int) $data['Status'] : -1;

		if ( ! $guid ) {
			return new WP_REST_Response( [ 'error' => 'no guid' ], 400 );
		}

		$post = self::find_post_by_guid( $guid );
		if ( ! $post ) {
			return new WP_REST_Response( [ 'ok' => true, 'note' => 'no matching post' ], 200 );
		}

		update_post_meta( $post->ID, '_wpbs_status', $status );

		if ( in_array( $status, [ 3, 4 ], true ) ) {
			$video = WPBS_Bunny_API::get_video( $guid );
			if ( ! is_wp_error( $video ) ) {
				self::sync_video_to_post( $post->ID, $video );
			}
		}

		return new WP_REST_Response( [ 'ok' => true ], 200 );
	}

	public static function sync_video_to_post( $post_id, $video ) {
		if ( isset( $video['status'] ) ) {
			update_post_meta( $post_id, '_wpbs_status', (int) $video['status'] );
		}
		if ( isset( $video['length'] ) ) {
			update_post_meta( $post_id, '_wpbs_duration', (int) $video['length'] );
		}
		if ( isset( $video['width'] ) ) {
			update_post_meta( $post_id, '_wpbs_width', (int) $video['width'] );
		}
		if ( isset( $video['height'] ) ) {
			update_post_meta( $post_id, '_wpbs_height', (int) $video['height'] );
		}
		if ( isset( $video['description'] ) ) {
			update_post_meta( $post_id, '_wpbs_description', (string) $video['description'] );
		}
		if ( isset( $video['smartGenerateStatus'] ) ) {
			update_post_meta( $post_id, '_wpbs_smart_status', (string) $video['smartGenerateStatus'] );
		}
		foreach ( [ 'chapters', 'moments', 'captions' ] as $key ) {
			if ( array_key_exists( $key, $video ) ) {
				$value = is_array( $video[ $key ] ) ? $video[ $key ] : [];
				update_post_meta( $post_id, '_wpbs_' . $key, wp_json_encode( $value ) );
			}
		}

		$thumb_url = WPBS_Bunny_API::thumbnail_url( isset( $video['guid'] ) ? $video['guid'] : '' );
		if ( $thumb_url ) {
			update_post_meta( $post_id, '_wpbs_thumbnail_url', $thumb_url );
			if ( WPBS_Plugin::get_option( 'auto_thumbnail', 1 ) && ! has_post_thumbnail( $post_id ) ) {
				self::set_featured_from_url( $post_id, $thumb_url );
			}
		}
	}

	private static function set_featured_from_url( $post_id, $url ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			return;
		}
		$file_array = [
			'name'     => 'bunny-' . $post_id . '.jpg',
			'tmp_name' => $tmp,
		];
		$attach_id = media_handle_sideload( $file_array, $post_id );
		if ( is_wp_error( $attach_id ) ) {
			@unlink( $tmp );
			return;
		}
		set_post_thumbnail( $post_id, $attach_id );
	}

	private static function find_post_by_guid( $guid ) {
		$q = new WP_Query( [
			'post_type'      => WPBS_CPT::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => '_wpbs_video_guid',
			'meta_value'     => $guid,
			'no_found_rows'  => true,
			'fields'         => 'all',
		] );
		return $q->have_posts() ? $q->posts[0] : null;
	}
}
