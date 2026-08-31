<?php //phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * Template Name: Taxonomy List In Popular
 *
 * Template Post Type: videos
 *
 * @category   Theme
 * @package eluminate-standalone
 * @author     Nazario A. Ayala <nazario@niztech.com>
 * @license    opensource.org MIT License
 * @link       https://www.niztech.com
 * @since      0.0.1
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
	echo '<div class="entry-content">';
	echo '<section class="shows-page-videos">';
	while ( have_posts() ) :
		global $post;
		the_post();

		if ( class_exists( 'Niztech_Youtube_Client' ) ) {
			$video_data = function_exists( 'eluminate_standalone_video_content' )
				? eluminate_standalone_video_content( $post->ID )
				: Niztech_Youtube_Client::video_content( $post->ID );
			if ( ! empty( $video_data ) ) {
				$number_videos    = count( $video_data );
				$first_video_data = $video_data[0];
				$thumb_url        = function_exists( 'eluminate_standalone_get_video_thumbnail_url' )
					? eluminate_standalone_get_video_thumbnail_url( $first_video_data )
					: '';
				echo '<article class="video-series-entry">';
				get_template_part(
					'template-parts/videos',
					'card',
					array(
						'video'     => $first_video_data,
						'shortlink' => wp_get_shortlink( $post->ID ),
						'thumb_url' => $thumb_url,
					)
				);
			echo '<p class="video-series-count">';
			printf( _n( '%s video in series', '%s videos in series', $number_videos, 'eluminate-standalone' ), $number_videos );
			echo '</p>';
				echo '</article>';
			}
		}
	endwhile;
	echo '</section>';
	echo '</div>';
else :
	get_template_part( 'template-parts/404' );
endif;

get_template_part( 'template-parts/main', 'end' );
get_template_part( 'template-parts/footer' );
get_template_part( 'template-parts/layout', 'end' );
