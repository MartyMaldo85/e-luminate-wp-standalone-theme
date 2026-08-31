<?php
/**
 * One-time migration from legacy video_series / list_in slugs to videos / tags.
 *
 * @package eluminate-standalone
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'eluminate_standalone_migrate_legacy_content_slugs' ) ) {
	/**
	 * @return void
	 */
	function eluminate_standalone_migrate_legacy_content_slugs(): void {
		if ( get_option( 'eluminate_content_slugs_videos_tags_v2' ) ) {
			return;
		}

		global $wpdb;

		$wpdb->update(
			$wpdb->posts,
			array( 'post_type' => 'videos' ),
			array( 'post_type' => 'video_series' )
		);

		$wpdb->update(
			$wpdb->term_taxonomy,
			array( 'taxonomy' => 'tags' ),
			array( 'taxonomy' => 'list_in' )
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
				'_eluminate_tags_videos_order',
				'_eluminate_list_in_series_order'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_key = %s AND meta_value = %s",
				'tags',
				'_menu_item_object',
				'list_in'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_key = %s AND meta_value = %s",
				'videos',
				'_menu_item_object',
				'video_series'
			)
		);

		$wpdb->query(
			"UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, 'video_series', 'videos') WHERE meta_key = '_wp_page_template'"
		);

		$wpdb->query(
			"UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, 'templates/video_series.php', 'templates/videos.php') WHERE meta_key = '_wp_page_template'"
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
				'_eluminate_tags_term_id',
				'_eluminate_list_in_term_id'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
				'_eluminate_tags_term_ids',
				'_eluminate_list_in_term_ids'
			)
		);

		update_option( 'eluminate_content_slugs_videos_tags_v2', '1', false );
		delete_option( THEME_KEY . '_rewrite_build' );
	}
}
add_action( 'init', 'eluminate_standalone_migrate_legacy_content_slugs', 1 );
