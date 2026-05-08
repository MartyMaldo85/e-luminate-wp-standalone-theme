<?php //phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * Template Name: Video Series Component
 *
 * Template Post Type: video_series
 *
 * Renders the template for a singular video_series.
 *
 * @category   Theme
 * @package eluminate-standalone
 * @author     Nazario A. Ayala <nazario@niztech.com>
 * @license    opensource.org MIT License
 * @link       https://www.niztech.com
 * @since      0.0.1
 */

if ( ! empty( $args['video'] ) ) {
	$path_generic = implode(
		DIRECTORY_SEPARATOR,
		array( get_template_directory_uri(), 'assets', 'generic-16-9.svg' )
	);
	$video_title = isset( $args['video']->title ) ? (string) $args['video']->title : '';
	$fallback    = isset( $args['shortlink'] ) ? (string) $args['shortlink'] : '';
	$card_href   = ! empty( $args['card_href'] ) ? (string) $args['card_href'] : $fallback;
	if ( ! empty( $args['thumb_url'] ) ) {
		$thumb_src = (string) $args['thumb_url'];
	} elseif ( empty( $args['video']->thumbnail_standard_url ) ) {
		$thumb_src = $path_generic;
	} else {
		$thumb_src = (string) $args['video']->thumbnail_standard_url;
	}

	printf(
		'
	<a href="%s">
		<img
			class="video-series-thumbnail"
			src="%s"
			alt="" />
	</a>
	<h3 class="title roboto-bold"><a href="%s" title="%s">%s</a></h3>',
		esc_url( $card_href ),
		esc_url( $thumb_src ),
		esc_url( $card_href ),
		esc_attr( $video_title ),
		esc_html( $video_title )
	);
	if ( ! empty( $args['video']->description ) ) {
		printf( '<p class="line-clamp-5">%s</p>', wp_kses_post( $args['video']->description ) );
	}

	if ( isset( $args['video_count'] ) ) {
		$video_count = (int) $args['video_count'];
		echo '<p class="video-series-count">';
		printf(
			_n( '%s video in series', '%s videos in series', $video_count, 'eluminate-standalone' ),
			$video_count
		);
		echo '</p>';
	}
}
