<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$atts = [
	'id'       => isset( $settings->video_id ) ? (int) $settings->video_id : 0,
	'autoplay' => isset( $settings->autoplay ) && '' !== $settings->autoplay ? $settings->autoplay : null,
	'loop'     => isset( $settings->loop ) && '' !== $settings->loop ? $settings->loop : null,
	'muted'    => isset( $settings->muted ) && '' !== $settings->muted ? $settings->muted : null,
	'preload'  => isset( $settings->preload ) && '' !== $settings->preload ? $settings->preload : null,
	'color'    => isset( $settings->color ) ? $settings->color : '',
	't'        => isset( $settings->start_at ) ? (int) $settings->start_at : '',
	'width'    => isset( $settings->width ) ? $settings->width . ( isset( $settings->width_unit ) ? $settings->width_unit : '%' ) : '',
	'ratio'    => isset( $settings->ratio ) ? $settings->ratio : '',
];

echo WPBS_Shortcode::render( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
