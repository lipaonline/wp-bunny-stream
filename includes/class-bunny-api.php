<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPBS_Bunny_API {

	const BASE = 'https://video.bunnycdn.com';

	public static function credentials() {
		return [
			'library_id'   => WPBS_Plugin::get_option( 'library_id' ),
			'api_key'      => WPBS_Plugin::get_option( 'api_key' ),
			'cdn_hostname' => WPBS_Plugin::get_option( 'cdn_hostname' ),
		];
	}

	public static function is_configured() {
		$c = self::credentials();
		return ! empty( $c['library_id'] ) && ! empty( $c['api_key'] );
	}

	/**
	 * Create a video object on Bunny Stream.
	 *
	 * @return array|WP_Error { guid: string, ... }
	 */
	public static function create_video( $title, $collection_id = '' ) {
		$c = self::credentials();
		if ( ! self::is_configured() ) {
			return new WP_Error( 'wpbs_not_configured', __( 'Bunny credentials missing.', 'wp-bunny-stream' ) );
		}

		$body = [ 'title' => $title ];
		if ( $collection_id ) {
			$body['collectionId'] = $collection_id;
		}

		$res = wp_remote_post( self::BASE . '/library/' . rawurlencode( $c['library_id'] ) . '/videos', [
			'headers' => [
				'AccessKey'    => $c['api_key'],
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
			'body'    => wp_json_encode( $body ),
			'timeout' => 20,
		] );

		return self::parse( $res );
	}

	/**
	 * Update a video's metadata. Accepts title, collectionId, chapters,
	 * moments, metaTags. Any omitted key is left untouched server-side.
	 */
	public static function update_video( $guid, $args ) {
		$c = self::credentials();
		if ( ! self::is_configured() ) {
			return new WP_Error( 'wpbs_not_configured', 'Bunny credentials missing' );
		}
		$res = wp_remote_post( self::BASE . '/library/' . rawurlencode( $c['library_id'] ) . '/videos/' . rawurlencode( $guid ), [
			'headers' => [
				'AccessKey'    => $c['api_key'],
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
			'body'    => wp_json_encode( $args ),
			'timeout' => 30,
		] );
		return self::parse( $res );
	}

	/**
	 * Add or replace a caption on a video.
	 *
	 * @param string $guid    Video GUID
	 * @param string $srclang ISO 639-1 language code
	 * @param string $label   Display label
	 * @param string $content Raw caption file content (VTT or SRT)
	 */
	public static function add_caption( $guid, $srclang, $label, $content ) {
		$c = self::credentials();
		if ( ! self::is_configured() ) {
			return new WP_Error( 'wpbs_not_configured', 'Bunny credentials missing' );
		}
		$res = wp_remote_post( self::BASE . '/library/' . rawurlencode( $c['library_id'] ) . '/videos/' . rawurlencode( $guid ) . '/captions/' . rawurlencode( $srclang ), [
			'headers' => [
				'AccessKey'    => $c['api_key'],
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
			'body'    => wp_json_encode( [
				'srclang'      => $srclang,
				'label'        => $label,
				'captionsFile' => base64_encode( $content ),
			] ),
			'timeout' => 30,
		] );
		return self::parse( $res );
	}

	public static function delete_caption( $guid, $srclang ) {
		$c = self::credentials();
		if ( ! self::is_configured() ) {
			return new WP_Error( 'wpbs_not_configured', 'Bunny credentials missing' );
		}
		$res = wp_remote_request( self::BASE . '/library/' . rawurlencode( $c['library_id'] ) . '/videos/' . rawurlencode( $guid ) . '/captions/' . rawurlencode( $srclang ), [
			'method'  => 'DELETE',
			'headers' => [ 'AccessKey' => $c['api_key'] ],
			'timeout' => 20,
		] );
		return self::parse( $res );
	}

	/**
	 * Fetch the raw VTT caption file from the Bunny CDN.
	 */
	public static function fetch_caption_content( $guid, $srclang ) {
		$c = self::credentials();
		if ( empty( $c['cdn_hostname'] ) ) {
			return new WP_Error( 'wpbs_no_cdn', 'CDN hostname not configured' );
		}
		$url = 'https://' . untrailingslashit( $c['cdn_hostname'] ) . '/' . rawurlencode( $guid ) . '/captions/' . rawurlencode( $srclang ) . '.vtt';
		$res = wp_remote_get( $url, [ 'timeout' => 15 ] );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'wpbs_fetch_caption', 'Caption fetch failed (' . $code . ')' );
		}
		return wp_remote_retrieve_body( $res );
	}

	public static function get_video( $guid ) {
		$c = self::credentials();
		if ( ! self::is_configured() ) {
			return new WP_Error( 'wpbs_not_configured', 'Bunny credentials missing' );
		}
		$res = wp_remote_get( self::BASE . '/library/' . rawurlencode( $c['library_id'] ) . '/videos/' . rawurlencode( $guid ), [
			'headers' => [
				'AccessKey' => $c['api_key'],
				'Accept'    => 'application/json',
			],
			'timeout' => 20,
		] );
		return self::parse( $res );
	}

	public static function delete_video( $guid ) {
		$c = self::credentials();
		if ( ! self::is_configured() ) {
			return new WP_Error( 'wpbs_not_configured', 'Bunny credentials missing' );
		}
		$res = wp_remote_request( self::BASE . '/library/' . rawurlencode( $c['library_id'] ) . '/videos/' . rawurlencode( $guid ), [
			'method'  => 'DELETE',
			'headers' => [ 'AccessKey' => $c['api_key'] ],
			'timeout' => 20,
		] );
		return self::parse( $res );
	}

	/**
	 * Trigger transcription + optional AI generation on a video.
	 * Charges $0.10/minute per language.
	 *
	 * @param string $guid Video GUID
	 * @param array  $args {
	 *     @type string $sourceLanguage   ISO 639-1 (e.g. "fr")
	 *     @type array  $targetLanguages  Array of ISO 639-1 codes for translations
	 *     @type bool   $generateTitle
	 *     @type bool   $generateDescription
	 *     @type bool   $generateChapters
	 *     @type bool   $generateMoments
	 *     @type bool   $force            Re-transcribe even if already done
	 * }
	 */
	public static function transcribe( $guid, $args = [] ) {
		$c = self::credentials();
		if ( ! self::is_configured() ) {
			return new WP_Error( 'wpbs_not_configured', 'Bunny credentials missing' );
		}
		$force = ! empty( $args['force'] );
		unset( $args['force'] );

		$url = self::BASE . '/library/' . rawurlencode( $c['library_id'] ) . '/videos/' . rawurlencode( $guid ) . '/transcribe';
		if ( $force ) {
			$url .= '?force=true';
		}

		$res = wp_remote_post( $url, [
			'headers' => [
				'AccessKey'    => $c['api_key'],
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
			'body'    => wp_json_encode( $args ),
			'timeout' => 30,
		] );
		return self::parse( $res );
	}

	/**
	 * Trigger AI smart actions (re-generate title/description/chapters/moments)
	 * on a video that already has a transcription. Free.
	 */
	public static function smart_actions( $guid, $args = [] ) {
		$c = self::credentials();
		if ( ! self::is_configured() ) {
			return new WP_Error( 'wpbs_not_configured', 'Bunny credentials missing' );
		}

		$res = wp_remote_post( self::BASE . '/library/' . rawurlencode( $c['library_id'] ) . '/videos/' . rawurlencode( $guid ) . '/smart', [
			'headers' => [
				'AccessKey'    => $c['api_key'],
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
			'body'    => wp_json_encode( $args ),
			'timeout' => 30,
		] );
		return self::parse( $res );
	}

	/**
	 * Build TUS signature payload for a freshly created video.
	 *
	 * Algorithm: SHA256(library_id + api_key + expiration + video_id)
	 */
	public static function tus_signature( $video_guid, $ttl = HOUR_IN_SECONDS * 12 ) {
		$c = self::credentials();
		if ( ! self::is_configured() ) {
			return new WP_Error( 'wpbs_not_configured', 'Bunny credentials missing' );
		}
		$expire    = time() + (int) $ttl;
		$signature = hash( 'sha256', $c['library_id'] . $c['api_key'] . $expire . $video_guid );

		return [
			'endpoint'  => self::BASE . '/tusupload',
			'libraryId' => (string) $c['library_id'],
			'videoId'   => $video_guid,
			'expire'    => $expire,
			'signature' => $signature,
		];
	}

	public static function thumbnail_url( $guid ) {
		$c = self::credentials();
		if ( empty( $c['cdn_hostname'] ) || empty( $guid ) ) {
			return '';
		}
		return 'https://' . untrailingslashit( $c['cdn_hostname'] ) . '/' . $guid . '/thumbnail.jpg';
	}

	private static function parse( $res ) {
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'wpbs_api_error', sprintf( 'Bunny API error %d', $code ), $body );
		}
		return is_array( $body ) ? $body : [];
	}
}
