<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPBS_BB_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( [
			'name'            => __( 'Bunny Video', 'wp-bunny-stream' ),
			'description'     => __( 'Embed a Bunny Stream video.', 'wp-bunny-stream' ),
			'category'        => __( 'Media', 'wp-bunny-stream' ),
			'dir'             => WPBS_DIR . 'modules/bunny-video/',
			'url'             => WPBS_URL . 'modules/bunny-video/',
			'icon'            => 'format-video.svg',
			'partial_refresh' => true,
		] );
	}

	/**
	 * Server-side video options for the picker. Includes any status so
	 * users can wire up a draft post before publishing.
	 */
	public static function get_video_options() {
		$options = [ '' => __( '— Select a video —', 'wp-bunny-stream' ) ];
		$posts   = get_posts( [
			'post_type'      => 'bunny_video',
			'post_status'    => [ 'publish', 'draft', 'pending', 'future', 'private' ],
			'posts_per_page' => 300,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		] );
		foreach ( $posts as $p ) {
			$label = $p->post_title ? $p->post_title : sprintf( '#%d (no title)', $p->ID );
			$guid  = get_post_meta( $p->ID, '_wpbs_video_guid', true );
			if ( ! $guid ) {
				$label .= ' ⚠ no upload';
			} elseif ( 'publish' !== $p->post_status ) {
				$label .= ' [' . $p->post_status . ']';
			}
			$options[ $p->ID ] = $label;
		}
		return $options;
	}
}

FLBuilder::register_module( 'WPBS_BB_Module', [
	'general' => [
		'title'    => __( 'Video', 'wp-bunny-stream' ),
		'sections' => [
			'video' => [
				'title'  => __( 'Source', 'wp-bunny-stream' ),
				'fields' => [
					'video_id' => [
						'type'    => 'select',
						'label'   => __( 'Bunny video', 'wp-bunny-stream' ),
						'options' => WPBS_BB_Module::get_video_options(),
						'help'    => __( 'Tip: click then start typing to jump to a video.', 'wp-bunny-stream' ),
					],
					'width'    => [
						'type'        => 'unit',
						'label'       => __( 'Max width', 'wp-bunny-stream' ),
						'units'       => [ 'px', '%' ],
						'default_unit' => '%',
						'description' => '%',
					],
					'ratio'    => [
						'type'    => 'select',
						'label'   => __( 'Aspect ratio', 'wp-bunny-stream' ),
						'default' => '',
						'help'    => __( 'Auto detects from the video dimensions.', 'wp-bunny-stream' ),
						'options' => [
							''      => __( 'Auto (from video)', 'wp-bunny-stream' ),
							'16:9'  => '16:9 (landscape)',
							'9:16'  => '9:16 (portrait / reels)',
							'1:1'   => '1:1 (square)',
							'4:3'   => '4:3',
							'21:9'  => '21:9 (cinematic)',
							'4:5'   => '4:5 (Instagram portrait)',
						],
					],
				],
			],
			'player' => [
				'title'  => __( 'Player options', 'wp-bunny-stream' ),
				'fields' => [
					'autoplay' => [
						'type'    => 'select',
						'label'   => __( 'Autoplay', 'wp-bunny-stream' ),
						'default' => '',
						'options' => [ '' => __( 'Default', 'wp-bunny-stream' ), '1' => __( 'On', 'wp-bunny-stream' ), '0' => __( 'Off', 'wp-bunny-stream' ) ],
					],
					'loop' => [
						'type'    => 'select',
						'label'   => __( 'Loop', 'wp-bunny-stream' ),
						'default' => '',
						'options' => [ '' => __( 'Default', 'wp-bunny-stream' ), '1' => __( 'On', 'wp-bunny-stream' ), '0' => __( 'Off', 'wp-bunny-stream' ) ],
					],
					'muted' => [
						'type'    => 'select',
						'label'   => __( 'Muted', 'wp-bunny-stream' ),
						'default' => '',
						'options' => [ '' => __( 'Default', 'wp-bunny-stream' ), '1' => __( 'On', 'wp-bunny-stream' ), '0' => __( 'Off', 'wp-bunny-stream' ) ],
					],
					'preload' => [
						'type'    => 'select',
						'label'   => __( 'Preload', 'wp-bunny-stream' ),
						'default' => '',
						'options' => [ '' => __( 'Default', 'wp-bunny-stream' ), '1' => __( 'On', 'wp-bunny-stream' ), '0' => __( 'Off', 'wp-bunny-stream' ) ],
					],
					'color' => [
						'type'  => 'color',
						'label' => __( 'Accent color', 'wp-bunny-stream' ),
						'show_reset' => true,
					],
					'start_at' => [
						'type'        => 'unit',
						'label'       => __( 'Start at', 'wp-bunny-stream' ),
						'default_unit' => 's',
						'units'       => [ 's' ],
					],
				],
			],
		],
	],
] );
