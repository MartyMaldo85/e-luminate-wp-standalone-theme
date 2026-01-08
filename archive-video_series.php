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
$paged        = max( 1, get_query_var( 'paged' ) );
$page         = max( 1, get_query_var( 'page' ) );

$args = array(
	'order'          => 'DESC',
	'orderby'        => 'date',
	'page'           => $page,
	'paged'          => $paged,
	'post_status'    => 'publish',
	'post_type'      => 'video_series',
	'posts_per_page' => 10,
);

$recent_video_series = new WP_Query( $args );

get_template_part( 'template-parts/layout', 'start', array( 'class' => array( 'recent' ) ) );
get_template_part( 'template-parts/header' );
get_template_part( 'template-parts/layout', 'nav' );
get_template_part( 'template-parts/main', 'start' );
?>
	<h2 class="main-title"><?php echo __( 'Recent videos', THEME_TEXT_DOMAIN ); ?></h2>
<?php

if ( $recent_video_series->have_posts() ) :
	while ( $recent_video_series->have_posts() ) :
		global $post;
		$recent_video_series->the_post();

		if ( class_exists( 'Niztech_Youtube_Client' ) ) {
			$video_data = Niztech_Youtube_Client::video_content( $post->ID );
			if ( ! empty( $video_data ) ) {
				$terms         = get_the_terms( $post->ID, 'list_in' );
				$video         = $video_data[0];
				$number_videos = count( $video_data );
				echo '<article class="video-series-entry">';
				get_template_part(
					'template-parts/video_series',
					'poop',
					array(
						'video'     => $video,
						'shortlink' => wp_get_shortlink( $post->ID ),
					)
				);
				echo '</article>';
			}
		}
	endwhile;
	echo get_the_posts_pagination(
		array(
			'total'   => $recent_video_series->max_num_pages,
			'current' => $paged,
		)
	);
	wp_reset_postdata();
else :
	get_template_part( 'template-parts/404' );
endif;

get_template_part( 'template-parts/main', 'end' );
get_template_part( 'template-parts/footer' );
get_template_part( 'template-parts/layout', 'end' );
