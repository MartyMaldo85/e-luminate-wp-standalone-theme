<?php //phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * Template Name: Taxonomy List In
 *
 * Template Post Type: video_series
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

$paged = max( 1, get_query_var( 'paged' ) );
$page  = max( 1, get_query_var( 'page' ) );

get_template_part( 'template-parts/layout', 'start', array( 'class' => array( 'list_in' ) ) );
get_template_part( 'template-parts/header' );
get_template_part( 'template-parts/layout', 'nav' );
get_template_part( 'template-parts/main', 'start' );
?>
	<h2 class="main-title"><?php single_term_title( '' ); ?></h2>
<?php
global $wp_query;

if ( have_posts() ) :
	echo '<div class="entry-content">';
	echo '<section class="shows-page-videos">';
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
			if ( ! empty( $number_videos ) ) {
				echo '<p class="video-series-count">';
				printf( _n( '%s video in series', '%s videos in series', $number_videos, 'eluminate-standalone' ), $number_videos );
				echo '</p>';
			}
				echo '</article>';
			}
		}
	endwhile;
	echo get_the_posts_pagination(
		array(
			'total'   => $wp_query->max_num_pages,
			'current' => $paged,
		)
	);
	echo '</section>';
	echo '</div>';
else :
	get_template_part( 'template-parts/404' );
endif;

get_template_part( 'template-parts/main', 'end' );
get_template_part( 'template-parts/footer' );
get_template_part( 'template-parts/layout', 'end' );
