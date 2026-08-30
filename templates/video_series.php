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
		$series_permalink = get_permalink( $post );

		$requested_vid = isset( $_GET['vid'] ) ? sanitize_text_field( wp_unslash( $_GET['vid'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_video  = $video_data[0];
		foreach ( $video_data as $candidate ) {
			$code = isset( $candidate->youtube_video_code ) ? (string) $candidate->youtube_video_code : '';
			if ( '' !== $requested_vid && $code === $requested_vid ) {
				$active_video = $candidate;
				break;
			}
		}
		$active_code = isset( $active_video->youtube_video_code ) ? (string) $active_video->youtube_video_code : '';

		$episodes_payload = array();
		foreach ( $video_data as $video ) {
			$code = isset( $video->youtube_video_code ) ? (string) $video->youtube_video_code : '';
			if ( '' === $code ) {
				continue;
			}
			$episodes_payload[] = array(
				'id'          => $code,
				'title'       => isset( $video->title ) ? (string) $video->title : '',
				'description' => isset( $video->description ) ? (string) $video->description : '',
				'url'         => add_query_arg( 'vid', $code, $series_permalink ),
			);
		}

		echo '<article class="video-entry first" data-eluminate-series-player>';
		printf(
			'<h3 class="title visually-hidden roboto-bold" data-eluminate-series-title>%s</h3>',
			esc_html( isset( $active_video->title ) ? (string) $active_video->title : '' )
		);
		printf(
			'<iframe class="video-iframe" data-eluminate-series-iframe src="https://www.youtube.com/embed/%s" title="%s" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>',
			esc_attr( $active_code ),
			esc_attr( isset( $active_video->title ) ? (string) $active_video->title : '' )
		);
		printf(
			'<div class="description line-clamp-5" data-eluminate-series-description>%s</div>',
			wp_kses_post( isset( $active_video->description ) ? (string) $active_video->description : '' )
		);
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
			printf(
				'<script type="application/json" id="eluminate-series-episodes-data">%s</script>',
				wp_json_encode( $episodes_payload )
			);

			echo '<section class="shows-page-videos" data-eluminate-series-episodes>';
			foreach ( $video_data as $video ) {
				$code = isset( $video->youtube_video_code ) ? (string) $video->youtube_video_code : '';
				if ( '' === $code ) {
					continue;
				}
				$is_playing = ( $code === $active_code );
				$thumb_url  = $video->thumbnail_maxres_url ?? $video->thumbnail_standard_url ?? $video->thumbnail_default_url ?? $path_generic;
				$episode_url = add_query_arg( 'vid', $code, $series_permalink );
				$classes     = 'video-series-entry';
				if ( $is_playing ) {
					$classes .= ' is-playing';
				}

				printf(
					'<article class="%s" data-eluminate-series-episode data-vid="%s"%s>',
					esc_attr( $classes ),
					esc_attr( $code ),
					$is_playing ? ' hidden' : ''
				);
				get_template_part(
					'template-parts/video_series',
					'poop',
					array(
						'video'     => $video,
						'shortlink' => $series_permalink,
						'card_href' => $episode_url,
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
