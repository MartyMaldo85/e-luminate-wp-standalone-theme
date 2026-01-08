<?php
/**
 * Template Name: Video Series Template Full Width
 * Template Post Type: video_series
 *
 * The template for the full-width page.
 *
 * @package Hestia
 * @since   Hestia 1.0
 */

if ( class_exists( 'Niztech_Youtube' ) ) {
	$path_to_plugins = join( DIRECTORY_SEPARATOR, array( WP_PLUGIN_DIR, 'niztech-youtube', 'class-niztech-youtube-client.php' ) );
	include_once $path_to_plugins;
}


$path_generic = implode(
	DIRECTORY_SEPARATOR,
	array( get_template_directory_uri(), 'assets', 'generic-16-9.svg' )
);


get_template_part( 'template-parts/layout', 'start' );
get_template_part( 'template-parts/header' );
get_template_part( 'template-parts/layout', 'nav' );
get_template_part( 'template-parts/main', 'start' );
?>
	<h2 class="main-title"><?php the_title(); ?></h2>
<?php

if ( have_posts() ) :
	while ( have_posts() ) :
		global $post;
		the_post();
		the_content();
	endwhile;
else :
	get_template_part( 'template-parts/404' );
endif;

if ( class_exists( 'Niztech_Youtube_Client' ) ) {
	$video_data = Niztech_Youtube_Client::video_content( $post->ID );
	if ( ! empty( $video_data ) ) {
		$terms = get_the_terms( $post->ID, 'list_in' );
		foreach ( $video_data as $key => $video ) {
			printf( '<article class="video-entry %s">', ( $key === 0 ) ? 'first' : '' );
			printf( '<h3 class="title visually-hidden roboto-bold">%s</h3>', $video->title );
			$img_url = $video->thumbnail_maxres_url ?? $video->thumbnail_standard_url ?? $video->thumbnail_default_url ?? $path_generic;
			if ( $key === 0 ) {
				printf( '<iframe class="video-iframe" src="https://www.youtube.com/embed/%s" frameborder="0" allowfullscreen></iframe>', $video->youtube_video_code );
			} elseif ( ! empty( $img_url ) ) {
				printf( '<a href="https://www.youtube.com/watch?v=%s"><img src="%s" alt="" class="video-entry-thumbnail" /></a>', $video->youtube_video_code, $img_url );
			}
			$line_clamp = ( $key === 0 ) ? 'line-clamp-5' : 'line-clamp-2';
			printf( '<div class="description %s">%s</div>', $line_clamp, $video->description );

			if ( $key != 0 ) {
				printf( '<a href="https://www.youtube.com/watch?v=%s">%s</a>', $video->youtube_video_code, _( 'more on YouTube' ) );
			}
			printf( '</article>' );
			if ( $terms && $key === 0 && count( $terms ) > 0 ) {
				$term_links = array_map(
					function ( $term ) {
						return sprintf( '<a href="/listing/%s">%s</a>', $term->slug, $term->name );
					},
					$terms
				);
				print( '<aside class="video-series-tags">' );
				print( __( 'Related topics: ', THEME_TEXT_DOMAIN ) );
				print( implode( ', ', $term_links ) );
				print( '</aside>' );
			}
		}
	}
}

get_template_part( 'template-parts/main', 'end' );
get_template_part( 'template-parts/footer' );
get_template_part( 'template-parts/layout', 'end' );
