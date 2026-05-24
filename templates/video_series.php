<?php //phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * Template Name: Video Series
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
		?>
		<div class="entry-content">
			<?php the_content(); ?>
		</div>
		<?php
	endwhile;
else :
	get_template_part( 'template-parts/404' );
endif;

if ( class_exists( 'Niztech_Youtube_Client' ) ) {
	$video_data = Niztech_Youtube_Client::video_content( $post->ID );
	if ( ! empty( $video_data ) ) {
		$terms            = get_the_terms( $post->ID, 'list_in' );
		$series_shortlink = wp_get_shortlink( $post->ID );
		$first_video      = $video_data[0];

		echo '<article class="video-entry first">';
		printf( '<h3 class="title visually-hidden roboto-bold">%s</h3>', esc_html( $first_video->title ) );
		printf(
			'<iframe class="video-iframe" src="https://www.youtube.com/embed/%s" frameborder="0" allowfullscreen></iframe>',
			esc_attr( $first_video->youtube_video_code )
		);
		printf( '<div class="description line-clamp-5">%s</div>', wp_kses_post( $first_video->description ) );
		echo '</article>';

		if ( $terms && count( $terms ) > 0 ) {
			$term_links = array_map(
				function ( $term ) {
					$link = get_term_link( $term );
					if ( is_wp_error( $link ) ) {
						return esc_html( $term->name );
					}

					return sprintf(
						'<a href="%s">%s</a>',
						esc_url( $link ),
						esc_html( $term->name )
					);
				},
				$terms
			);
		echo '<aside class="video-series-tags">';
		echo esc_html__( 'Related topics: ', 'eluminate-standalone' );
		echo implode( ', ', $term_links );
			echo '</aside>';
		}

		if ( count( $video_data ) > 1 ) {
			echo '<section class="shows-page-videos">';
			foreach ( $video_data as $key => $video ) {
				if ( 0 === $key ) {
					continue;
				}
				$thumb_url = $video->thumbnail_maxres_url ?? $video->thumbnail_standard_url ?? $video->thumbnail_default_url ?? $path_generic;
				$watch_url = sprintf( 'https://www.youtube.com/watch?v=%s', $video->youtube_video_code );
				echo '<article class="video-series-entry">';
				get_template_part(
					'template-parts/video_series',
					'poop',
					array(
						'video'     => $video,
						'shortlink' => $series_shortlink,
						'card_href' => $watch_url,
						'thumb_url' => $thumb_url,
					)
				);
				echo '</article>';
			}
			echo '</section>';
		}
	}
}

get_template_part( 'template-parts/main', 'end' );
get_template_part( 'template-parts/footer' );
get_template_part( 'template-parts/layout', 'end' );
