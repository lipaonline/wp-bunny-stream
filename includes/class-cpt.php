<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPBS_CPT {

	const POST_TYPE = 'bunny_video';
	const TAX_CAT   = 'bunny_video_category';
	const TAX_TAG   = 'bunny_video_tag';

	public static function register() {
		register_post_type( self::POST_TYPE, [
			'labels'        => [
				'name'               => __( 'Bunny Videos', 'wp-bunny-stream' ),
				'singular_name'      => __( 'Bunny Video', 'wp-bunny-stream' ),
				'add_new'            => __( 'Add New', 'wp-bunny-stream' ),
				'add_new_item'       => __( 'Add New Video', 'wp-bunny-stream' ),
				'edit_item'          => __( 'Edit Video', 'wp-bunny-stream' ),
				'new_item'           => __( 'New Video', 'wp-bunny-stream' ),
				'view_item'          => __( 'View Video', 'wp-bunny-stream' ),
				'search_items'       => __( 'Search Videos', 'wp-bunny-stream' ),
				'menu_name'          => __( 'Bunny Videos', 'wp-bunny-stream' ),
			],
			'public'        => true,
			'show_in_rest'  => true,
			'has_archive'   => true,
			'menu_icon'     => 'dashicons-video-alt3',
			'rewrite'       => [ 'slug' => 'video' ],
			'supports'      => [ 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'custom-fields' ],
			'taxonomies'    => [ self::TAX_CAT, self::TAX_TAG ],
		] );

		register_taxonomy( self::TAX_CAT, self::POST_TYPE, [
			'labels'            => [
				'name'          => __( 'Video Categories', 'wp-bunny-stream' ),
				'singular_name' => __( 'Video Category', 'wp-bunny-stream' ),
			],
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => [ 'slug' => 'video-category' ],
		] );

		register_taxonomy( self::TAX_TAG, self::POST_TYPE, [
			'labels'            => [
				'name'          => __( 'Video Tags', 'wp-bunny-stream' ),
				'singular_name' => __( 'Video Tag', 'wp-bunny-stream' ),
			],
			'hierarchical'      => false,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => [ 'slug' => 'video-tag' ],
		] );

		self::register_meta();
	}

	private static function register_meta() {
		$keys = [
			'_wpbs_video_guid'      => 'string',
			'_wpbs_library_id'      => 'string',
			'_wpbs_status'          => 'integer',
			'_wpbs_duration'        => 'integer',
			'_wpbs_width'           => 'integer',
			'_wpbs_height'          => 'integer',
			'_wpbs_thumbnail_url'   => 'string',
			'_wpbs_player_override' => 'string',
			'_wpbs_chapters'        => 'string',
			'_wpbs_moments'         => 'string',
			'_wpbs_captions'        => 'string',
			'_wpbs_description'     => 'string',
			'_wpbs_smart_status'    => 'string',
		];
		foreach ( $keys as $key => $type ) {
			register_post_meta( self::POST_TYPE, $key, [
				'show_in_rest' => true,
				'single'       => true,
				'type'         => $type,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			] );
		}
	}
}
