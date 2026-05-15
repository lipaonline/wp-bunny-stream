<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPBS_Uploader {

	public static function init() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
		add_action( 'wp_ajax_wpbs_create_video', [ __CLASS__, 'ajax_create_video' ] );
		add_action( 'wp_ajax_wpbs_save_video', [ __CLASS__, 'ajax_save_video' ] );
		add_action( 'wp_ajax_wpbs_refresh_video', [ __CLASS__, 'ajax_refresh_video' ] );
		add_action( 'wp_ajax_wpbs_search_videos', [ __CLASS__, 'ajax_search_videos' ] );
		add_action( 'wp_ajax_wpbs_link_video', [ __CLASS__, 'ajax_link_video' ] );
		add_action( 'wp_ajax_wpbs_transcribe', [ __CLASS__, 'ajax_transcribe' ] );
		add_action( 'wp_ajax_wpbs_smart_actions', [ __CLASS__, 'ajax_smart_actions' ] );
		add_action( 'wp_ajax_wpbs_save_chapters', [ __CLASS__, 'ajax_save_chapters' ] );
		add_action( 'wp_ajax_wpbs_save_moments', [ __CLASS__, 'ajax_save_moments' ] );
		add_action( 'wp_ajax_wpbs_save_caption', [ __CLASS__, 'ajax_save_caption' ] );
		add_action( 'wp_ajax_wpbs_delete_caption', [ __CLASS__, 'ajax_delete_caption' ] );
		add_action( 'wp_ajax_wpbs_fetch_caption', [ __CLASS__, 'ajax_fetch_caption' ] );
		add_action( 'before_delete_post', [ __CLASS__, 'on_delete_post' ] );
		add_action( 'save_post_' . WPBS_CPT::POST_TYPE, [ __CLASS__, 'save_player_box' ] );
	}

	public static function enqueue( $hook ) {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || WPBS_CPT::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'wpbs-tus',
			'https://cdn.jsdelivr.net/npm/tus-js-client@4.1.0/dist/tus.min.js',
			[],
			'4.1.0',
			true
		);

		wp_enqueue_script(
			'wpbs-admin-uploader',
			WPBS_URL . 'assets/js/admin-uploader.js',
			[ 'wpbs-tus', 'jquery' ],
			WPBS_VERSION,
			true
		);

		wp_enqueue_style(
			'wpbs-admin',
			WPBS_URL . 'assets/css/admin.css',
			[],
			WPBS_VERSION
		);

		global $post;
		wp_localize_script( 'wpbs-admin-uploader', 'WPBS', [
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'wpbs_uploader' ),
			'postId'     => $post ? $post->ID : 0,
			'configured' => WPBS_Bunny_API::is_configured(),
			'i18n'       => [
				'configure'   => __( 'Configure Bunny credentials first.', 'wp-bunny-stream' ),
				'creating'    => __( 'Creating video on Bunny…', 'wp-bunny-stream' ),
				'uploading'   => __( 'Uploading', 'wp-bunny-stream' ),
				'done'        => __( 'Upload finished. Encoding in progress on Bunny.', 'wp-bunny-stream' ),
				'saveReminder'=> __( 'Click Save / Publish to keep your title.', 'wp-bunny-stream' ),
				'failed'      => __( 'Upload failed', 'wp-bunny-stream' ),
				'replace'     => __( 'Replace video', 'wp-bunny-stream' ),
				'refresh'     => __( 'Refresh status', 'wp-bunny-stream' ),
				'chooseFile'  => __( 'Choose a video file', 'wp-bunny-stream' ),
			],
		] );
	}

	public static function add_meta_boxes() {
		add_meta_box(
			'wpbs_uploader',
			__( 'Bunny Stream video', 'wp-bunny-stream' ),
			[ __CLASS__, 'render_uploader' ],
			WPBS_CPT::POST_TYPE,
			'normal',
			'high'
		);
		add_meta_box(
			'wpbs_player',
			__( 'Player options (override)', 'wp-bunny-stream' ),
			[ __CLASS__, 'render_player_box' ],
			WPBS_CPT::POST_TYPE,
			'side',
			'default'
		);
	}

	public static function render_uploader( $post ) {
		$guid       = get_post_meta( $post->ID, '_wpbs_video_guid', true );
		$library    = get_post_meta( $post->ID, '_wpbs_library_id', true );
		$status     = (int) get_post_meta( $post->ID, '_wpbs_status', true );
		$duration   = (int) get_post_meta( $post->ID, '_wpbs_duration', true );
		$thumb      = get_post_meta( $post->ID, '_wpbs_thumbnail_url', true );
		$configured = WPBS_Bunny_API::is_configured();
		?>
		<div class="wpbs-uploader" data-guid="<?php echo esc_attr( $guid ); ?>" data-library="<?php echo esc_attr( $library ); ?>">
			<?php if ( ! $configured ) : ?>
				<div class="notice notice-warning inline"><p><?php
					printf(
						/* translators: %s settings page link */
						wp_kses_post( __( 'Add your Bunny Stream credentials in the <a href="%s">settings page</a> first.', 'wp-bunny-stream' ) ),
						esc_url( admin_url( 'edit.php?post_type=bunny_video&page=wpbs-settings' ) )
					);
				?></p></div>
			<?php endif; ?>

			<?php if ( $guid ) :
				$languages       = self::available_languages();
				$current_post_lang = '';
				if ( function_exists( 'pll_get_post_language' ) ) {
					$current_post_lang = pll_get_post_language( $post->ID, 'slug' );
				}
				if ( ! $current_post_lang && $languages ) {
					$current_post_lang = key( $languages );
				}
				?>
				<div class="wpbs-current">
					<?php if ( $thumb ) : ?>
						<img src="<?php echo esc_url( $thumb ); ?>" alt="" class="wpbs-thumb">
					<?php endif; ?>
					<p>
						<strong><?php esc_html_e( 'Video GUID:', 'wp-bunny-stream' ); ?></strong>
						<code><?php echo esc_html( $guid ); ?></code>
					</p>
					<p>
						<strong><?php esc_html_e( 'Status:', 'wp-bunny-stream' ); ?></strong>
						<span class="wpbs-status-label" data-status="<?php echo esc_attr( $status ); ?>"><?php echo esc_html( self::status_label( $status ) ); ?></span>
						&nbsp;|&nbsp;
						<strong><?php esc_html_e( 'Duration:', 'wp-bunny-stream' ); ?></strong>
						<span class="wpbs-duration"><?php echo esc_html( gmdate( 'i:s', $duration ) ); ?></span>
					</p>
					<p>
						<button type="button" class="button wpbs-refresh"><?php esc_html_e( 'Refresh status', 'wp-bunny-stream' ); ?></button>
						<button type="button" class="button wpbs-replace"><?php esc_html_e( 'Replace video', 'wp-bunny-stream' ); ?></button>
					</p>
				</div>

				<?php
				$chapters = json_decode( get_post_meta( $post->ID, '_wpbs_chapters', true ) ?: '[]', true ) ?: [];
				$moments  = json_decode( get_post_meta( $post->ID, '_wpbs_moments', true ) ?: '[]', true ) ?: [];
				$captions = json_decode( get_post_meta( $post->ID, '_wpbs_captions', true ) ?: '[]', true ) ?: [];
				?>
				<details <?php echo $moments ? 'open' : ''; ?> class="wpbs-moments-editor">
					<summary><strong><?php
						echo esc_html( $moments
							? sprintf( _n( '%d moment', '%d moments', count( $moments ), 'wp-bunny-stream' ), count( $moments ) )
							: __( 'Moments', 'wp-bunny-stream' )
						);
					?></strong> — <?php esc_html_e( 'click to edit', 'wp-bunny-stream' ); ?></summary>
					<div class="wpbs-moments-rows" data-moments="<?php echo esc_attr( wp_json_encode( $moments ) ); ?>">
						<!-- rows rendered by JS -->
					</div>
					<p>
						<button type="button" class="button wpbs-moment-add">+ <?php esc_html_e( 'Add moment', 'wp-bunny-stream' ); ?></button>
						<button type="button" class="button button-primary wpbs-moments-save"><?php esc_html_e( 'Save moments to Bunny', 'wp-bunny-stream' ); ?></button>
					</p>
					<p class="description"><?php esc_html_e( 'Each moment is a single point in time (label + timestamp). Shown as markers on the Bunny player timeline.', 'wp-bunny-stream' ); ?></p>
				</details>

				<details <?php echo $captions ? 'open' : ''; ?> class="wpbs-captions-editor" data-captions="<?php echo esc_attr( wp_json_encode( $captions ) ); ?>">
					<summary><strong><?php
						echo esc_html( $captions
							? sprintf( _n( '%d caption track', '%d caption tracks', count( $captions ), 'wp-bunny-stream' ), count( $captions ) )
							: __( 'Captions', 'wp-bunny-stream' )
						);
					?></strong> — <?php esc_html_e( 'manage subtitles', 'wp-bunny-stream' ); ?></summary>

					<div class="wpbs-captions-list">
						<!-- rendered by JS -->
					</div>

					<div class="wpbs-caption-form" style="display:none">
						<h5 class="wpbs-caption-form-title"></h5>
						<p>
							<label><?php esc_html_e( 'Language', 'wp-bunny-stream' ); ?>
								<select class="wpbs-cap-lang">
									<?php
									$langs = self::available_languages();
									foreach ( $langs as $slug => $name ) :
										?>
										<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name . ' (' . $slug . ')' ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label style="margin-left:14px"><?php esc_html_e( 'Label', 'wp-bunny-stream' ); ?>
								<input type="text" class="wpbs-cap-label" placeholder="Français">
							</label>
						</p>
						<p>
							<label><?php esc_html_e( 'Upload .vtt or .srt file', 'wp-bunny-stream' ); ?>
								<input type="file" class="wpbs-cap-file" accept=".vtt,.srt,text/vtt,application/x-subrip,text/plain">
							</label>
						</p>
						<p><strong><?php esc_html_e( 'or edit content directly:', 'wp-bunny-stream' ); ?></strong></p>
						<textarea class="wpbs-cap-content" rows="12" placeholder="WEBVTT&#10;&#10;00:00:00.000 --> 00:00:03.000&#10;Bonjour"></textarea>
						<p>
							<button type="button" class="button button-primary wpbs-cap-save"><?php esc_html_e( 'Save caption', 'wp-bunny-stream' ); ?></button>
							<button type="button" class="button wpbs-cap-cancel"><?php esc_html_e( 'Cancel', 'wp-bunny-stream' ); ?></button>
						</p>
					</div>

					<p class="wpbs-caption-actions">
						<button type="button" class="button wpbs-cap-add">+ <?php esc_html_e( 'Add caption', 'wp-bunny-stream' ); ?></button>
					</p>
				</details>

				<details <?php echo $chapters ? 'open' : ''; ?> class="wpbs-chapters-editor">
					<summary><strong><?php
						echo esc_html( $chapters
							? sprintf( _n( '%d chapter', '%d chapters', count( $chapters ), 'wp-bunny-stream' ), count( $chapters ) )
							: __( 'Chapters', 'wp-bunny-stream' )
						);
					?></strong> — <?php esc_html_e( 'click to edit', 'wp-bunny-stream' ); ?></summary>
					<div class="wpbs-chapters-rows" data-chapters="<?php echo esc_attr( wp_json_encode( $chapters ) ); ?>">
						<!-- rows rendered by JS -->
					</div>
					<p>
						<button type="button" class="button wpbs-chapter-add">+ <?php esc_html_e( 'Add chapter', 'wp-bunny-stream' ); ?></button>
						<button type="button" class="button button-primary wpbs-chapters-save"><?php esc_html_e( 'Save chapters to Bunny', 'wp-bunny-stream' ); ?></button>
					</p>
					<p class="description"><?php esc_html_e( 'Time format: mm:ss or hh:mm:ss (e.g. 1:23 or 0:01:23).', 'wp-bunny-stream' ); ?></p>
				</details>

				<div class="wpbs-ai">
					<h4><?php esc_html_e( 'AI: chapters, moments & captions', 'wp-bunny-stream' ); ?></h4>
					<p>
						<label><?php esc_html_e( 'Source language', 'wp-bunny-stream' ); ?>
							<select class="wpbs-ai-source">
								<?php foreach ( $languages as $slug => $name ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $current_post_lang ); ?>><?php echo esc_html( $name ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</p>
					<?php if ( count( $languages ) > 1 ) : ?>
					<p>
						<strong><?php esc_html_e( 'Translate captions to:', 'wp-bunny-stream' ); ?></strong><br>
						<?php foreach ( $languages as $slug => $name ) : ?>
							<label style="margin-right:14px">
								<input type="checkbox" class="wpbs-ai-target" value="<?php echo esc_attr( $slug ); ?>" <?php disabled( $slug, $current_post_lang ); ?>>
								<?php echo esc_html( $name ); ?>
							</label>
						<?php endforeach; ?>
					</p>
					<?php endif; ?>
					<p>
						<button type="button" class="button wpbs-smart">
							<?php esc_html_e( 'Generate chapters + moments', 'wp-bunny-stream' ); ?>
						</button>
						<button type="button" class="button button-primary wpbs-transcribe">
							<?php esc_html_e( 'Transcribe + generate all (paid)', 'wp-bunny-stream' ); ?>
						</button>
					</p>
					<p class="description">
						<?php esc_html_e( '"Generate chapters + moments" is free but requires a transcription to already exist. "Transcribe + generate all" charges $0.10 per minute per language.', 'wp-bunny-stream' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<div class="wpbs-dropzone" <?php echo $guid ? 'style="display:none"' : ''; ?>>
				<input type="file" id="wpbs-file" accept="video/*">
				<label for="wpbs-file" class="wpbs-dropzone-label">
					<span class="dashicons dashicons-upload"></span>
					<?php esc_html_e( 'Drop a video file here or click to select', 'wp-bunny-stream' ); ?>
					<small><?php esc_html_e( 'Resumable upload — large files supported.', 'wp-bunny-stream' ); ?></small>
				</label>
			</div>

			<div class="wpbs-progress" style="display:none">
				<div class="wpbs-progress-bar"><span></span></div>
				<p class="wpbs-progress-text">0%</p>
				<button type="button" class="button wpbs-cancel"><?php esc_html_e( 'Cancel', 'wp-bunny-stream' ); ?></button>
			</div>

			<div class="wpbs-message" style="display:none"></div>

			<details class="wpbs-link-existing" <?php echo $guid ? '' : 'open'; ?>>
				<summary><?php esc_html_e( 'Link an existing Bunny video by GUID', 'wp-bunny-stream' ); ?></summary>
				<p class="description"><?php esc_html_e( 'Already uploaded the video to Bunny directly? Paste its GUID to attach it to this post.', 'wp-bunny-stream' ); ?></p>
				<p>
					<label><?php esc_html_e( 'Video GUID', 'wp-bunny-stream' ); ?>
						<input type="text" id="wpbs-link-guid" class="regular-text" placeholder="8308cc8c-c5d4-43f7-87da-c24560cce953">
					</label>
				</p>
				<p>
					<button type="button" class="button button-primary wpbs-link-btn"><?php esc_html_e( 'Link video', 'wp-bunny-stream' ); ?></button>
				</p>
			</details>
		</div>
		<?php
	}

	public static function render_player_box( $post ) {
		$json     = get_post_meta( $post->ID, '_wpbs_player_override', true );
		$override = $json ? json_decode( $json, true ) : [];
		$value    = function ( $k, $d = '' ) use ( $override ) {
			return isset( $override[ $k ] ) ? $override[ $k ] : $d;
		};
		wp_nonce_field( 'wpbs_player_save', 'wpbs_player_nonce' );
		?>
		<p>
			<label><input type="checkbox" name="wpbs_player[autoplay]" value="1" <?php checked( '1', $value( 'autoplay' ) ); ?>> <?php esc_html_e( 'Autoplay', 'wp-bunny-stream' ); ?></label><br>
			<label><input type="checkbox" name="wpbs_player[loop]" value="1" <?php checked( '1', $value( 'loop' ) ); ?>> <?php esc_html_e( 'Loop', 'wp-bunny-stream' ); ?></label><br>
			<label><input type="checkbox" name="wpbs_player[muted]" value="1" <?php checked( '1', $value( 'muted' ) ); ?>> <?php esc_html_e( 'Muted', 'wp-bunny-stream' ); ?></label><br>
			<label><input type="checkbox" name="wpbs_player[preload]" value="1" <?php checked( '1', $value( 'preload' ) ); ?>> <?php esc_html_e( 'Preload', 'wp-bunny-stream' ); ?></label>
		</p>
		<p>
			<label><?php esc_html_e( 'Accent color', 'wp-bunny-stream' ); ?>
				<input type="text" name="wpbs_player[color]" value="<?php echo esc_attr( $value( 'color' ) ); ?>" placeholder="#ff8800" class="widefat">
			</label>
		</p>
		<p>
			<label><?php esc_html_e( 'Start at (seconds)', 'wp-bunny-stream' ); ?>
				<input type="number" min="0" name="wpbs_player[t]" value="<?php echo esc_attr( $value( 't' ) ); ?>" class="widefat">
			</label>
		</p>
		<p class="description"><?php esc_html_e( 'Leave empty to use the global settings defaults.', 'wp-bunny-stream' ); ?></p>
		<?php
	}

	public static function save_player_box( $post_id ) {
		if ( ! isset( $_POST['wpbs_player_nonce'] ) || ! wp_verify_nonce( $_POST['wpbs_player_nonce'], 'wpbs_player_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw   = isset( $_POST['wpbs_player'] ) && is_array( $_POST['wpbs_player'] ) ? $_POST['wpbs_player'] : [];
		$clean = [];
		foreach ( [ 'autoplay', 'loop', 'muted', 'preload' ] as $k ) {
			if ( ! empty( $raw[ $k ] ) ) {
				$clean[ $k ] = '1';
			}
		}
		if ( ! empty( $raw['color'] ) ) {
			$clean['color'] = sanitize_hex_color( $raw['color'] );
		}
		if ( isset( $raw['t'] ) && '' !== $raw['t'] ) {
			$clean['t'] = max( 0, (int) $raw['t'] );
		}
		update_post_meta( $post_id, '_wpbs_player_override', wp_json_encode( $clean ) );
	}

	public static function ajax_create_video() {
		check_ajax_referer( 'wpbs_uploader', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : 'Untitled';

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'invalid post' ], 400 );
		}

		$result = WPBS_Bunny_API::create_video( $title );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
		}

		$guid = isset( $result['guid'] ) ? $result['guid'] : '';
		if ( ! $guid ) {
			wp_send_json_error( [ 'message' => 'No GUID returned' ], 500 );
		}

		$creds = WPBS_Bunny_API::credentials();
		update_post_meta( $post_id, '_wpbs_video_guid', $guid );
		update_post_meta( $post_id, '_wpbs_library_id', $creds['library_id'] );
		update_post_meta( $post_id, '_wpbs_status', 0 );

		$sig = WPBS_Bunny_API::tus_signature( $guid );

		wp_send_json_success( [
			'guid'      => $guid,
			'signature' => $sig,
		] );
	}

	public static function ajax_save_video() {
		check_ajax_referer( 'wpbs_uploader', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}
		update_post_meta( $post_id, '_wpbs_status', 1 );
		wp_send_json_success();
	}

	/**
	 * Attach an existing Bunny video to the post by validating the GUID with
	 * the Bunny API and copying its metadata over.
	 */
	public static function ajax_link_video() {
		check_ajax_referer( 'wpbs_uploader', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$guid    = isset( $_POST['guid'] ) ? sanitize_text_field( wp_unslash( $_POST['guid'] ) ) : '';

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}
		if ( ! $guid || ! preg_match( '/^[a-f0-9-]{36}$/i', $guid ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid GUID format', 'wp-bunny-stream' ) ], 400 );
		}

		$video = WPBS_Bunny_API::get_video( $guid );
		if ( is_wp_error( $video ) ) {
			wp_send_json_error( [ 'message' => $video->get_error_message() ], 404 );
		}

		$creds = WPBS_Bunny_API::credentials();
		update_post_meta( $post_id, '_wpbs_video_guid', $guid );
		update_post_meta( $post_id, '_wpbs_library_id', $creds['library_id'] );
		WPBS_Webhook::sync_video_to_post( $post_id, $video );

		wp_send_json_success( [
			'guid'   => $guid,
			'status' => isset( $video['status'] ) ? (int) $video['status'] : 0,
			'title'  => isset( $video['title'] ) ? $video['title'] : '',
		] );
	}

	/**
	 * List available Polylang languages as ISO 639-1 slugs, with WP locale fallback.
	 */
	public static function available_languages() {
		$languages = [];
		if ( function_exists( 'pll_languages_list' ) ) {
			$slugs = pll_languages_list( [ 'fields' => 'slug' ] );
			$names = function_exists( 'pll_languages_list' ) ? pll_languages_list( [ 'fields' => 'name' ] ) : [];
			foreach ( $slugs as $i => $slug ) {
				$languages[ $slug ] = isset( $names[ $i ] ) ? $names[ $i ] : strtoupper( $slug );
			}
		}
		if ( ! $languages ) {
			$locale = get_locale();
			$slug   = strtolower( substr( $locale, 0, 2 ) );
			$languages[ $slug ] = strtoupper( $slug );
		}
		return $languages;
	}

	public static function ajax_transcribe() {
		check_ajax_referer( 'wpbs_uploader', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}
		$guid = get_post_meta( $post_id, '_wpbs_video_guid', true );
		if ( ! $guid ) {
			wp_send_json_error( [ 'message' => __( 'No video on this post', 'wp-bunny-stream' ) ], 400 );
		}

		$source  = isset( $_POST['source_language'] ) ? sanitize_text_field( wp_unslash( $_POST['source_language'] ) ) : '';
		$targets = isset( $_POST['target_languages'] ) && is_array( $_POST['target_languages'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['target_languages'] ) )
			: [];
		$force   = ! empty( $_POST['force'] );

		$args = [
			'generateTitle'       => false,
			'generateDescription' => false,
			'generateChapters'    => true,
			'generateMoments'     => true,
			'force'               => $force,
		];
		if ( $source ) {
			$args['sourceLanguage'] = $source;
		}
		if ( $targets ) {
			$args['targetLanguages'] = array_values( array_filter( $targets ) );
		}

		$res = WPBS_Bunny_API::transcribe( $guid, $args );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( [ 'message' => $res->get_error_message() ], 500 );
		}
		wp_send_json_success( $res );
	}

	public static function ajax_save_moments() {
		check_ajax_referer( 'wpbs_uploader', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}
		$guid = get_post_meta( $post_id, '_wpbs_video_guid', true );
		if ( ! $guid ) {
			wp_send_json_error( [ 'message' => __( 'No video on this post', 'wp-bunny-stream' ) ], 400 );
		}

		$raw = isset( $_POST['moments'] ) && is_array( $_POST['moments'] ) ? wp_unslash( $_POST['moments'] ) : [];
		$clean = [];
		foreach ( $raw as $row ) {
			$label     = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			$timestamp = isset( $row['timestamp'] ) ? max( 0, (int) $row['timestamp'] ) : 0;
			if ( '' === $label && 0 === $timestamp ) {
				continue;
			}
			$clean[] = [
				'label'     => $label,
				'timestamp' => $timestamp,
			];
		}

		usort( $clean, function ( $a, $b ) { return $a['timestamp'] - $b['timestamp']; } );

		$res = WPBS_Bunny_API::update_video( $guid, [ 'moments' => $clean ] );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( [ 'message' => $res->get_error_message() ], 500 );
		}

		update_post_meta( $post_id, '_wpbs_moments', wp_json_encode( $clean ) );
		wp_send_json_success( [ 'moments' => $clean ] );
	}

	public static function ajax_save_caption() {
		check_ajax_referer( 'wpbs_uploader', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}
		$guid = get_post_meta( $post_id, '_wpbs_video_guid', true );
		if ( ! $guid ) {
			wp_send_json_error( [ 'message' => 'No video' ], 400 );
		}

		$srclang = isset( $_POST['srclang'] ) ? strtolower( preg_replace( '/[^a-z0-9-]/i', '', wp_unslash( $_POST['srclang'] ) ) ) : '';
		$label   = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';

		if ( ! $srclang ) {
			wp_send_json_error( [ 'message' => __( 'Language code required', 'wp-bunny-stream' ) ], 400 );
		}
		if ( '' === trim( $content ) ) {
			wp_send_json_error( [ 'message' => __( 'Caption content is empty', 'wp-bunny-stream' ) ], 400 );
		}
		if ( ! $label ) {
			$label = strtoupper( $srclang );
		}

		$res = WPBS_Bunny_API::add_caption( $guid, $srclang, $label, $content );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( [ 'message' => $res->get_error_message() ], 500 );
		}

		$video = WPBS_Bunny_API::get_video( $guid );
		if ( ! is_wp_error( $video ) ) {
			WPBS_Webhook::sync_video_to_post( $post_id, $video );
		}

		wp_send_json_success( [
			'captions' => is_wp_error( $video ) || empty( $video['captions'] ) ? [] : $video['captions'],
		] );
	}

	public static function ajax_delete_caption() {
		check_ajax_referer( 'wpbs_uploader', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}
		$guid    = get_post_meta( $post_id, '_wpbs_video_guid', true );
		$srclang = isset( $_POST['srclang'] ) ? strtolower( preg_replace( '/[^a-z0-9-]/i', '', wp_unslash( $_POST['srclang'] ) ) ) : '';
		if ( ! $guid || ! $srclang ) {
			wp_send_json_error( [ 'message' => 'Missing data' ], 400 );
		}

		$res = WPBS_Bunny_API::delete_caption( $guid, $srclang );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( [ 'message' => $res->get_error_message() ], 500 );
		}

		$video = WPBS_Bunny_API::get_video( $guid );
		if ( ! is_wp_error( $video ) ) {
			WPBS_Webhook::sync_video_to_post( $post_id, $video );
		}

		wp_send_json_success( [
			'captions' => is_wp_error( $video ) || empty( $video['captions'] ) ? [] : $video['captions'],
		] );
	}

	public static function ajax_fetch_caption() {
		check_ajax_referer( 'wpbs_uploader', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}
		$guid    = get_post_meta( $post_id, '_wpbs_video_guid', true );
		$srclang = isset( $_POST['srclang'] ) ? strtolower( preg_replace( '/[^a-z0-9-]/i', '', wp_unslash( $_POST['srclang'] ) ) ) : '';
		if ( ! $guid || ! $srclang ) {
			wp_send_json_error( [ 'message' => 'Missing data' ], 400 );
		}

		$content = WPBS_Bunny_API::fetch_caption_content( $guid, $srclang );
		if ( is_wp_error( $content ) ) {
			wp_send_json_error( [ 'message' => $content->get_error_message() ], 500 );
		}
		wp_send_json_success( [ 'content' => $content ] );
	}

	public static function ajax_save_chapters() {
		check_ajax_referer( 'wpbs_uploader', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}
		$guid = get_post_meta( $post_id, '_wpbs_video_guid', true );
		if ( ! $guid ) {
			wp_send_json_error( [ 'message' => __( 'No video on this post', 'wp-bunny-stream' ) ], 400 );
		}

		$raw = isset( $_POST['chapters'] ) && is_array( $_POST['chapters'] ) ? wp_unslash( $_POST['chapters'] ) : [];
		$clean = [];
		foreach ( $raw as $row ) {
			$title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
			$start = isset( $row['start'] ) ? max( 0, (int) $row['start'] ) : 0;
			$end   = isset( $row['end'] ) ? max( 0, (int) $row['end'] ) : 0;
			if ( '' === $title && 0 === $start && 0 === $end ) {
				continue;
			}
			if ( $end < $start ) {
				$end = $start;
			}
			$clean[] = [
				'title' => $title,
				'start' => $start,
				'end'   => $end,
			];
		}

		usort( $clean, function ( $a, $b ) { return $a['start'] - $b['start']; } );

		$res = WPBS_Bunny_API::update_video( $guid, [ 'chapters' => $clean ] );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( [ 'message' => $res->get_error_message() ], 500 );
		}

		update_post_meta( $post_id, '_wpbs_chapters', wp_json_encode( $clean ) );
		wp_send_json_success( [ 'chapters' => $clean ] );
	}

	public static function ajax_smart_actions() {
		check_ajax_referer( 'wpbs_uploader', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}
		$guid = get_post_meta( $post_id, '_wpbs_video_guid', true );
		if ( ! $guid ) {
			wp_send_json_error( [ 'message' => __( 'No video on this post', 'wp-bunny-stream' ) ], 400 );
		}

		$source = isset( $_POST['source_language'] ) ? sanitize_text_field( wp_unslash( $_POST['source_language'] ) ) : '';

		$args = [
			'generateTitle'       => false,
			'generateDescription' => false,
			'generateChapters'    => true,
			'generateMoments'     => true,
		];
		if ( $source ) {
			$args['sourceLanguage'] = $source;
		}

		$res = WPBS_Bunny_API::smart_actions( $guid, $args );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( [ 'message' => $res->get_error_message() ], 500 );
		}
		wp_send_json_success( $res );
	}

	public static function ajax_refresh_video() {
		check_ajax_referer( 'wpbs_uploader', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}
		$guid = get_post_meta( $post_id, '_wpbs_video_guid', true );
		if ( ! $guid ) {
			wp_send_json_error( [ 'message' => 'no guid' ], 400 );
		}
		$video = WPBS_Bunny_API::get_video( $guid );
		if ( is_wp_error( $video ) ) {
			wp_send_json_error( [ 'message' => $video->get_error_message() ], 500 );
		}
		WPBS_Webhook::sync_video_to_post( $post_id, $video );
		wp_send_json_success( [
			'status'   => isset( $video['status'] ) ? (int) $video['status'] : 0,
			'label'    => self::status_label( isset( $video['status'] ) ? (int) $video['status'] : 0 ),
			'duration' => isset( $video['length'] ) ? (int) $video['length'] : 0,
			'chapters' => isset( $video['chapters'] ) && is_array( $video['chapters'] ) ? $video['chapters'] : [],
			'moments'  => isset( $video['moments'] ) && is_array( $video['moments'] ) ? $video['moments'] : [],
			'captions' => isset( $video['captions'] ) && is_array( $video['captions'] ) ? $video['captions'] : [],
		] );
	}

	/**
	 * Search handler used by Beaver Builder suggest field, Gutenberg block,
	 * and the shortcode picker. Returns an array of { value, label, name } objects.
	 *
	 * Reads search term from `fl_as_query` (Beaver Builder), `q`, or `search`.
	 */
	public static function ajax_search_videos() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json( [] );
		}

		// Beaver Builder asks for labels of already-saved IDs on form load.
		if ( ! empty( $_REQUEST['fl_as_value'] ) ) {
			$ids = array_filter( array_map( 'intval', explode( ',', wp_unslash( $_REQUEST['fl_as_value'] ) ) ) );
			$out = [];
			foreach ( $ids as $id ) {
				$post = get_post( $id );
				if ( $post && WPBS_CPT::POST_TYPE === $post->post_type ) {
					$out[] = [
						'value' => (string) $post->ID,
						'name'  => $post->post_title ?: sprintf( '(no title #%d)', $post->ID ),
					];
				}
			}
			wp_send_json( $out );
		}

		$term = '';
		foreach ( [ 'fl_as_query', 'q', 'search', 'term' ] as $k ) {
			if ( isset( $_REQUEST[ $k ] ) && '' !== $_REQUEST[ $k ] ) {
				$term = sanitize_text_field( wp_unslash( $_REQUEST[ $k ] ) );
				break;
			}
		}

		$query = new WP_Query( [
			'post_type'      => WPBS_CPT::POST_TYPE,
			'post_status'    => [ 'publish', 'draft', 'private', 'pending', 'future' ],
			'posts_per_page' => 20,
			's'              => $term,
			'orderby'        => $term ? 'relevance' : 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		] );

		$out = [];
		foreach ( $query->posts as $p ) {
			$title = $p->post_title ? $p->post_title : sprintf( '(no title #%d)', $p->ID );
			$out[] = [
				'value' => (string) $p->ID,
				'label' => $title,
				'name'  => $title,
			];
		}
		wp_send_json( $out );
	}

	public static function on_delete_post( $post_id ) {
		if ( WPBS_CPT::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		$guid = get_post_meta( $post_id, '_wpbs_video_guid', true );
		if ( $guid ) {
			WPBS_Bunny_API::delete_video( $guid );
		}
	}

	public static function format_time( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		$h = intdiv( $seconds, 3600 );
		$m = intdiv( $seconds % 3600, 60 );
		$s = $seconds % 60;
		return $h ? sprintf( '%d:%02d:%02d', $h, $m, $s ) : sprintf( '%d:%02d', $m, $s );
	}

	public static function status_label( $status ) {
		$map = [
			0  => __( 'Queued', 'wp-bunny-stream' ),
			1  => __( 'Processing', 'wp-bunny-stream' ),
			2  => __( 'Encoding', 'wp-bunny-stream' ),
			3  => __( 'Finished', 'wp-bunny-stream' ),
			4  => __( 'Resolution finished', 'wp-bunny-stream' ),
			5  => __( 'Failed', 'wp-bunny-stream' ),
			6  => __( 'Pre-signed upload created', 'wp-bunny-stream' ),
			7  => __( 'Pre-signed upload completed', 'wp-bunny-stream' ),
			8  => __( 'Pre-signed upload failed', 'wp-bunny-stream' ),
			9  => __( 'Captions generated', 'wp-bunny-stream' ),
			10 => __( 'Title/description generated', 'wp-bunny-stream' ),
		];
		return isset( $map[ $status ] ) ? $map[ $status ] : (string) $status;
	}
}
