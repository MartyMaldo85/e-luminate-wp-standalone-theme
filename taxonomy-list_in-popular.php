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
	<h2 class="main-title"><?php echo single_term_title( '', false ); ?></h2>
<?php


if ( have_posts() ) :
	while ( have_posts() ) :
		global $post;
		the_post();

		if ( class_exists( 'Niztech_Youtube_Client' ) ) {
			$video_data = Niztech_Youtube_Client::video_content( $post->ID );
			if ( ! empty( $video_data ) ) {
				$number_videos    = count( $video_data );
				$first_video_data = $video_data[0];
				echo '<article class="video-series-entry">';
				get_template_part(
					'template-parts/video_series',
					'poop',
					array(
						'video'     => $first_video_data,
						'shortlink' => wp_get_shortlink( $post->ID ),
					)
				);
				echo( '<p class="video-series-count">' );
				if ( $number_videos == 1 ) {
					echo( __( '1 video in series', THEME_TEXT_DOMAIN ) );
				} else {
					echo( __( $number_videos . ' videos in series', THEME_TEXT_DOMAIN ) );
				}
				echo( '</p>' );
				echo '</article>';
			}
		}
	endwhile;
else :
	get_template_part( 'template-parts/404' );
endif;

get_template_part( 'template-parts/main', 'end' );
get_template_part( 'template-parts/footer' );
get_template_part( 'template-parts/layout', 'end' );
