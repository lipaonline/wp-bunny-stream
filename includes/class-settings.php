<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPBS_Settings {

	const OPTION = 'wpbs_settings';

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
	}

	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=bunny_video',
			__( 'Bunny Stream Settings', 'wp-bunny-stream' ),
			__( 'Settings', 'wp-bunny-stream' ),
			'manage_options',
			'wpbs-settings',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function register_settings() {
		register_setting( 'wpbs_settings_group', self::OPTION, [
			'sanitize_callback' => [ __CLASS__, 'sanitize' ],
			'default'           => [],
		] );
	}

	public static function sanitize( $input ) {
		$out                       = [];
		$out['library_id']         = isset( $input['library_id'] ) ? preg_replace( '/[^0-9]/', '', $input['library_id'] ) : '';
		$out['api_key']            = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
		$out['cdn_hostname']       = isset( $input['cdn_hostname'] ) ? sanitize_text_field( $input['cdn_hostname'] ) : '';
		$out['webhook_secret']     = isset( $input['webhook_secret'] ) ? sanitize_text_field( $input['webhook_secret'] ) : '';
		$out['player_autoplay']    = ! empty( $input['player_autoplay'] ) ? 1 : 0;
		$out['player_loop']        = ! empty( $input['player_loop'] ) ? 1 : 0;
		$out['player_muted']       = ! empty( $input['player_muted'] ) ? 1 : 0;
		$out['player_preload']     = ! empty( $input['player_preload'] ) ? 1 : 0;
		$out['player_responsive']  = ! empty( $input['player_responsive'] ) ? 1 : 0;
		$out['player_color']       = isset( $input['player_color'] ) ? sanitize_hex_color( $input['player_color'] ) : '';
		$out['auto_thumbnail']     = ! empty( $input['auto_thumbnail'] ) ? 1 : 0;
		return $out;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$opts          = get_option( self::OPTION, [] );
		$webhook_url   = rest_url( 'wp-bunny-stream/v1/webhook' );
		$secret        = isset( $opts['webhook_secret'] ) ? $opts['webhook_secret'] : '';
		$library_id    = isset( $opts['library_id'] ) ? $opts['library_id'] : '';
		$api_key       = isset( $opts['api_key'] ) ? $opts['api_key'] : '';
		$cdn_hostname  = isset( $opts['cdn_hostname'] ) ? $opts['cdn_hostname'] : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bunny Stream Settings', 'wp-bunny-stream' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'wpbs_settings_group' ); ?>
				<h2 class="title"><?php esc_html_e( 'API credentials', 'wp-bunny-stream' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="wpbs_library_id"><?php esc_html_e( 'Library ID', 'wp-bunny-stream' ); ?></label></th>
						<td><input name="wpbs_settings[library_id]" id="wpbs_library_id" type="text" class="regular-text" value="<?php echo esc_attr( $library_id ); ?>"></td>
					</tr>
					<tr>
						<th><label for="wpbs_api_key"><?php esc_html_e( 'API key', 'wp-bunny-stream' ); ?></label></th>
						<td><input name="wpbs_settings[api_key]" id="wpbs_api_key" type="password" class="regular-text" value="<?php echo esc_attr( $api_key ); ?>" autocomplete="off">
							<p class="description"><?php esc_html_e( 'Bunny Stream library API key (Stream → Library → API).', 'wp-bunny-stream' ); ?></p></td>
					</tr>
					<tr>
						<th><label for="wpbs_cdn_hostname"><?php esc_html_e( 'CDN hostname (optional)', 'wp-bunny-stream' ); ?></label></th>
						<td><input name="wpbs_settings[cdn_hostname]" id="wpbs_cdn_hostname" type="text" class="regular-text" placeholder="vz-xxxxx.b-cdn.net" value="<?php echo esc_attr( $cdn_hostname ); ?>">
							<p class="description"><?php esc_html_e( 'Used to fetch thumbnails & direct file URLs.', 'wp-bunny-stream' ); ?></p></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Default player options', 'wp-bunny-stream' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'Autoplay', 'wp-bunny-stream' ); ?></th>
						<td><label><input type="checkbox" name="wpbs_settings[player_autoplay]" value="1" <?php checked( 1, (int) self::get( 'player_autoplay' ) ); ?>> <?php esc_html_e( 'Start playing automatically', 'wp-bunny-stream' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Loop', 'wp-bunny-stream' ); ?></th>
						<td><label><input type="checkbox" name="wpbs_settings[player_loop]" value="1" <?php checked( 1, (int) self::get( 'player_loop' ) ); ?>></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Muted', 'wp-bunny-stream' ); ?></th>
						<td><label><input type="checkbox" name="wpbs_settings[player_muted]" value="1" <?php checked( 1, (int) self::get( 'player_muted' ) ); ?>></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Preload', 'wp-bunny-stream' ); ?></th>
						<td><label><input type="checkbox" name="wpbs_settings[player_preload]" value="1" <?php checked( 1, (int) self::get( 'player_preload' ) ); ?>></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Responsive embed', 'wp-bunny-stream' ); ?></th>
						<td><label><input type="checkbox" name="wpbs_settings[player_responsive]" value="1" <?php checked( 1, (int) self::get( 'player_responsive', 1 ) ); ?>> <?php esc_html_e( '16:9 fluid wrapper', 'wp-bunny-stream' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Player accent color', 'wp-bunny-stream' ); ?></th>
						<td><input type="text" name="wpbs_settings[player_color]" value="<?php echo esc_attr( self::get( 'player_color' ) ); ?>" placeholder="#ff8800" class="wpbs-color"></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Automation', 'wp-bunny-stream' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'Auto featured image', 'wp-bunny-stream' ); ?></th>
						<td><label><input type="checkbox" name="wpbs_settings[auto_thumbnail]" value="1" <?php checked( 1, (int) self::get( 'auto_thumbnail', 1 ) ); ?>> <?php esc_html_e( 'Pull Bunny thumbnail as post featured image', 'wp-bunny-stream' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="wpbs_webhook_secret"><?php esc_html_e( 'Webhook shared secret', 'wp-bunny-stream' ); ?></label></th>
						<td><input id="wpbs_webhook_secret" type="text" name="wpbs_settings[webhook_secret]" value="<?php echo esc_attr( $secret ); ?>" class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Append ?secret=YOUR_SECRET to the webhook URL below in Bunny dashboard, or leave empty to validate with HMAC using the library Read-only API key.', 'wp-bunny-stream' ); ?>
							</p></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Webhook URL', 'wp-bunny-stream' ); ?></th>
						<td><code><?php echo esc_html( $webhook_url ); ?></code></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public static function get( $key, $default = '' ) {
		return WPBS_Plugin::get_option( $key, $default );
	}
}
