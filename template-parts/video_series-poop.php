<?php
if ( ! empty( $args['video'] ) ) {
	$path_generic = implode(
		DIRECTORY_SEPARATOR,
		array( get_template_directory_uri(), 'assets', 'generic-16-9.svg' )
	);

	printf(
		'
	<a href="%s">
		<img
			src="%s"
			alt="" />
	</a>
	<h3 class="title roboto-bold"><a href="%s">%s</a></h3>',
		$args['shortlink'],
		( empty( $args['video']->thumbnail_standard_url ) ? $path_generic : $args['video']->thumbnail_standard_url ),
		$args['shortlink'],
		$args['video']->title
	);
	if ( ! empty( $args['video']->description ) ) {
		printf( '<p class="line-clamp-5">%s</p>', $args['video']->description );
	}

	if ( isset( $args['video_count'] ) ) {
		echo( '<p class="video-series-count">' );
		if ( $args['video_count'] == 1 ) {
			echo( __( '1 video in series', THEME_TEXT_DOMAIN ) );
		} else {
			echo( __( $args['video_count'] . ' videos in series', THEME_TEXT_DOMAIN ) );
		}
		echo( '</p>' );
	}
}
