<?php

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
					echo( '<p class="video-series-count">' );
					if ( $number_videos == 1 ) {
						echo( __( '1 video in series', THEME_TEXT_DOMAIN ) );
					} else {
						echo( __( $number_videos . ' videos in series', THEME_TEXT_DOMAIN ) );
					}
				}
				echo( '</p>' );
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
else :
	get_template_part( 'template-parts/404' );
endif;

get_template_part( 'template-parts/main', 'end' );
get_template_part( 'template-parts/footer' );
get_template_part( 'template-parts/layout', 'end' );
