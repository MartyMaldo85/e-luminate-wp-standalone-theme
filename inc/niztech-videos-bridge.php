<?php
/**
 * Bridges Niztech YouTube plugin to the theme `videos` post type (renamed from video_series).
 *
 * @package eluminate-standalone
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'eluminate_standalone_register_niztech_playlist_metabox_for_videos' ) ) {
	/**
	 * Niztech hard-codes its metabox to video_series; re-register for videos.
	 *
	 * @return void
	 */
	function eluminate_standalone_register_niztech_playlist_metabox_for_videos(): void {
		if ( ! class_exists( 'Niztech_Youtube_Admin' ) || ! class_exists( 'Niztech_Youtube' ) ) {
			return;
		}

		add_meta_box(
			'metabox-source-playlist-code',
			esc_html__( 'YouTube Video Import', 'eluminate-standalone' ),
			'eluminate_standalone_render_niztech_playlist_metabox',
			'videos',
			'normal',
			'default'
		);
	}
}
add_action( 'add_meta_boxes_videos', 'eluminate_standalone_register_niztech_playlist_metabox_for_videos' );

if ( ! function_exists( 'eluminate_standalone_render_niztech_playlist_metabox' ) ) {
	/**
	 * Niztech playlist metabox for `videos`, with Single Video first and selected by default.
	 *
	 * @param \WP_Post $post Current post.
	 *
	 * @return void
	 */
	function eluminate_standalone_render_niztech_playlist_metabox( WP_Post $post ): void {
		if ( ! class_exists( 'Niztech_Youtube_Admin' ) || ! class_exists( 'Niztech_Youtube' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_add = $screen && 'add' === $screen->action;

		wp_nonce_field( Niztech_Youtube_Admin::NONCE_SAVE_PLAYLIST_DATA, Niztech_Youtube::PLUGIN_PREFIX . 'source_nonce' );
		$type                = Niztech_Youtube::video_source_get_meta( Niztech_Youtube::PLUGIN_PREFIX . 'type', $post->ID );
		$use_yt_as_thumbnail = Niztech_Youtube::video_source_get_meta( Niztech_Youtube::PLUGIN_PREFIX . 'use_yt_thumbnail', $post->ID );
		$youtube_data        = Niztech_Youtube::get_video_or_playlist_code_and_foreign_key( $type, $post->ID );
		$youtube_url         = Niztech_Youtube::video_source_get_meta( Niztech_Youtube::PLUGIN_PREFIX . 'use_yt_url', $post->ID );
		if ( isset( $_POST['niztech_youtube_url'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$youtube_url = (string) wp_unslash( $_POST['niztech_youtube_url'] );
		}
		if ( isset( $_POST['niztech_youtube_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$type = (string) wp_unslash( $_POST['niztech_youtube_type'] );
		}
		$is_single_video     = Niztech_Youtube::TYPE_OPTION_VIDEO === $type || empty( $type );
		$is_playlist         = Niztech_Youtube::TYPE_OPTION_PLAYLIST === $type;
		?>
		<p class="eluminate-niztech-url-field">
			<label class="screen-reader-text" for="niztech_youtube_url"><?php esc_html_e( 'YouTube URL', 'eluminate-standalone' ); ?></label>
			<input type="text"
					name="niztech_youtube_url"
					id="niztech_youtube_url"
					class="eluminate-niztech-url-input"
					placeholder="<?php echo esc_attr__( 'YouTube URL', 'eluminate-standalone' ); ?>"
					value="<?php echo esc_attr( $youtube_url ? (string) $youtube_url : '' ); ?>">
			<input type="hidden" name="niztech_youtube_foreign_key" id="niztech_youtube_foreign_key"
					value="<?php echo esc_attr( isset( $youtube_data->id ) ? (string) $youtube_data->id : '' ); ?>">
		</p>
		<p>
			<?php esc_html_e( 'Import single video or playlist?', 'eluminate-standalone' ); ?>
			<br />
			<div class="eluminate-niztech-type-options">
				<label><?php esc_html_e( 'Single Video', 'eluminate-standalone' ); ?>
				<input name="niztech_youtube_type" type="radio" value="Single Video" <?php checked( $is_single_video ); ?> id="niztech_youtube_type_single_video" />
				</label>
				<br />
				<label><?php esc_html_e( 'Playlist', 'eluminate-standalone' ); ?>
				<input name="niztech_youtube_type" type="radio" value="Playlist" <?php checked( $is_playlist ); ?> id="niztech_youtube_type_playlist"/>
				</label>
			</div>
		</p>
		<p>
			<label for="niztech_youtube_use_youtube_featured">
				<?php esc_html_e( 'Use YouTube thumbnail image.', 'eluminate-standalone' ); ?>
			</label><br>
			<input id="niztech_youtube_use_youtube_featured"
					name="niztech_youtube_use_youtube_featured"
				<?php checked( $is_add || $use_yt_as_thumbnail ); ?>
					type="checkbox">
		</p>
		<p>
			<?php Niztech_Youtube_Admin::video_content_admin_html( $post->ID ); ?>
		</p>
		<?php
	}
}

add_filter(
	'niztech_youtube_admin_page_hook_suffixes',
	static function ( array $hook_suffixes ): array {
		$hook_suffixes[] = 'post-new.php';
		return array_values( array_unique( $hook_suffixes ) );
	}
);

add_action(
	'admin_enqueue_scripts',
	static function (): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'videos' === $screen->post_type ) {
			return;
		}

		wp_dequeue_script( 'niztech_youtube_admin.js' );
		wp_dequeue_style( 'niztech_youtube_admin' );
	},
	100
);

add_filter(
	'gettext',
	static function ( string $translation, string $text, string $domain ): string {
		if ( 'niztech_youtube' !== $domain ) {
			return $translation;
		}

		if ( 'Use Youtube Featured Image' === $text ) {
			return __( 'Use YouTube thumbnail image.', 'eluminate-standalone' );
		}

		if ( 'Youtube URL' === $text ) {
			return __( 'YouTube URL', 'eluminate-standalone' );
		}

		return $translation;
	},
	10,
	3
);

if ( ! function_exists( 'eluminate_standalone_normalize_niztech_youtube_url' ) ) {
	/**
	 * Convert share-style YouTube URLs to www.youtube.com/watch?v= format for Niztech.
	 *
	 * @param string $url Raw URL from the Niztech metabox.
	 *
	 * @return string
	 */
	function eluminate_standalone_normalize_niztech_youtube_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return $url;
		}

		if ( preg_match( '#^https?://(?:www\.)?youtu\.be/([^?&/]+)#i', $url, $matches ) ) {
			return 'https://www.youtube.com/watch?v=' . $matches[1];
		}

		if ( preg_match( '#^https?://(?:www\.)?youtube\.com/shorts/([^?&/]+)#i', $url, $matches ) ) {
			return 'https://www.youtube.com/watch?v=' . $matches[1];
		}

		if ( preg_match( '#^https?://youtube\.com/#i', $url ) ) {
			return (string) preg_replace( '#^https?://youtube\.com/#i', 'https://www.youtube.com/', $url );
		}

		return $url;
	}
}

if ( ! function_exists( 'eluminate_standalone_normalize_niztech_youtube_post_url' ) ) {
	/**
	 * Normalize Niztech YouTube URL in POST before the plugin validates it.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return void
	 */
	function eluminate_standalone_normalize_niztech_youtube_post_url( int $post_id, WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['niztech_youtube_url'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( 'videos' !== $post->post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_url = (string) wp_unslash( $_POST['niztech_youtube_url'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_POST['niztech_youtube_url'] = eluminate_standalone_normalize_niztech_youtube_url( $raw_url );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$posted_type = isset( $_POST['niztech_youtube_type'] ) ? (string) wp_unslash( $_POST['niztech_youtube_type'] ) : '';
		if ( '' === $posted_type && class_exists( 'Niztech_Youtube' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_POST['niztech_youtube_type'] = Niztech_Youtube::TYPE_OPTION_VIDEO;
			$posted_type                   = Niztech_Youtube::TYPE_OPTION_VIDEO;
		}

		$normalized_url = (string) $_POST['niztech_youtube_url'];
		if (
			class_exists( 'Niztech_Youtube' )
			&& Niztech_Youtube::TYPE_OPTION_PLAYLIST === $posted_type
			&& ! preg_match( '/[?&]list=/i', $normalized_url )
		) {
			// youtu.be / shorts / watch?v= links have no playlist id — treat as single video.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_POST['niztech_youtube_type'] = Niztech_Youtube::TYPE_OPTION_VIDEO;
		}
	}
}
add_action( 'save_post', 'eluminate_standalone_normalize_niztech_youtube_post_url', 5, 2 );

if ( ! function_exists( 'eluminate_standalone_get_youtube_playlist_title' ) ) {
	/**
	 * @param string $playlist_code YouTube playlist ID.
	 *
	 * @return string|null
	 */
	function eluminate_standalone_get_youtube_playlist_title( string $playlist_code ): ?string {
		if ( '' === $playlist_code || ! class_exists( 'Niztech_Youtube' ) ) {
			return null;
		}

		try {
			if ( empty( Niztech_Youtube::$google_service ) ) {
				Niztech_Youtube::setup_youtube_google_client();
			}

			$response = Niztech_Youtube::$google_service->playlists->listPlaylists(
				'snippet',
				array(
					'id' => $playlist_code,
				)
			);
		} catch ( \Exception $e ) {
			return null;
		}

		$title = $response->items[0]->snippet->title ?? '';
		$title = is_string( $title ) ? trim( $title ) : '';

		return '' !== $title ? $title : null;
	}
}

if ( ! function_exists( 'eluminate_standalone_get_niztech_import_title' ) ) {
	/**
	 * Resolve a WordPress post title from Niztech data saved for a video post.
	 *
	 * @param int $post_id Video post ID.
	 *
	 * @return string|null
	 */
	function eluminate_standalone_get_niztech_import_title( int $post_id ): ?string {
		if ( ! class_exists( 'Niztech_Youtube' ) ) {
			return null;
		}

		$type = Niztech_Youtube::video_source_get_meta( Niztech_Youtube::PLUGIN_PREFIX . 'type', $post_id );
		if ( empty( $type ) ) {
			return null;
		}

		global $wpdb;

		if ( Niztech_Youtube::TYPE_OPTION_VIDEO === $type ) {
			$table = $wpdb->prefix . Niztech_Youtube::TBL_VIDEOS;
			$title = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT title FROM {$table} WHERE post_id = %d AND playlist_id = 0 ORDER BY id ASC LIMIT 1",
					$post_id
				)
			);
		} else {
			$playlist_table = $wpdb->prefix . Niztech_Youtube::TBL_PLAYLIST;
			$playlist_code  = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT youtube_playlist_code FROM {$playlist_table} WHERE post_id = %d ORDER BY id ASC LIMIT 1",
					$post_id
				)
			);
			$title          = is_string( $playlist_code ) && '' !== $playlist_code
				? eluminate_standalone_get_youtube_playlist_title( $playlist_code )
				: null;
		}

		$title = is_string( $title ) ? trim( wp_strip_all_tags( $title ) ) : '';

		return '' !== $title ? $title : null;
	}
}

if ( ! function_exists( 'eluminate_standalone_maybe_set_video_title_from_youtube' ) ) {
	/**
	 * Set an empty video post title from the imported YouTube video or playlist name.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return void
	 */
	function eluminate_standalone_maybe_set_video_title_from_youtube( int $post_id, WP_Post $post ): void {
		static $is_updating_title = false;

		if ( $is_updating_title || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		if ( 'videos' !== $post->post_type ) {
			return;
		}

		if ( ! class_exists( 'Niztech_Youtube' ) ) {
			return;
		}

		$video_saved    = get_transient( Niztech_Youtube::PLUGIN_PREFIX . 'video_source_save_video_saved' );
		$playlist_saved = get_transient( Niztech_Youtube::PLUGIN_PREFIX . 'video_source_save_playlist_saved' );
		if ( ! $video_saved && ! $playlist_saved ) {
			return;
		}

		$current_title = trim( (string) $post->post_title );
		if ( '' !== $current_title ) {
			return;
		}

		$import_title = eluminate_standalone_get_niztech_import_title( $post_id );
		if ( null === $import_title ) {
			return;
		}

		$is_updating_title = true;
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $import_title,
			)
		);
		$is_updating_title = false;
	}
}
add_action( 'save_post', 'eluminate_standalone_maybe_set_video_title_from_youtube', 15, 2 );

if ( ! function_exists( 'eluminate_standalone_should_preserve_niztech_hidden_on_save' ) ) {
	/**
	 * Whether this save will run Niztech YouTube re-import (same gates as the plugin save handler).
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return bool
	 */
	function eluminate_standalone_should_preserve_niztech_hidden_on_save( int $post_id, WP_Post $post ): bool {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( 'videos' !== $post->post_type ) {
			return false;
		}

		if ( ! class_exists( 'Niztech_Youtube_Admin' ) || ! class_exists( 'Niztech_Youtube' ) ) {
			return false;
		}

		if ( ! isset( $_POST['niztech_youtube_source_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$nonce = (string) wp_unslash( $_POST['niztech_youtube_source_nonce'] );
		if ( ! wp_verify_nonce( $nonce, Niztech_Youtube_Admin::NONCE_SAVE_PLAYLIST_DATA ) ) {
			return false;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$url = isset( $_POST['niztech_youtube_url'] ) ? trim( (string) wp_unslash( $_POST['niztech_youtube_url'] ) ) : '';
		if ( '' === $url ) {
			return false;
		}

		return true;
	}
}

if ( ! function_exists( 'eluminate_standalone_snapshot_niztech_hidden_videos' ) ) {
	/**
	 * Remember hidden episode flags keyed by YouTube video ID before Niztech re-import.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return void
	 */
	function eluminate_standalone_snapshot_niztech_hidden_videos( int $post_id, WP_Post $post ): void {
		if ( ! eluminate_standalone_should_preserve_niztech_hidden_on_save( $post_id, $post ) ) {
			return;
		}

		global $wpdb, $eluminate_niztech_hidden_snapshots;

		if ( ! isset( $eluminate_niztech_hidden_snapshots ) || ! is_array( $eluminate_niztech_hidden_snapshots ) ) {
			$eluminate_niztech_hidden_snapshots = array();
		}

		$table = $wpdb->prefix . Niztech_Youtube::TBL_VIDEOS;
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT youtube_video_code, hidden FROM {$table} WHERE post_id = %d",
				$post_id
			),
			ARRAY_A
		);

		$snapshot = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$code = isset( $row['youtube_video_code'] ) ? (string) $row['youtube_video_code'] : '';
				if ( '' === $code ) {
					continue;
				}
				if ( ! empty( $row['hidden'] ) ) {
					$snapshot[ $code ] = 1;
				}
			}
		}

		$eluminate_niztech_hidden_snapshots[ $post_id ] = $snapshot;
	}
}
add_action( 'save_post', 'eluminate_standalone_snapshot_niztech_hidden_videos', 9, 2 );

if ( ! function_exists( 'eluminate_standalone_restore_niztech_hidden_videos' ) ) {
	/**
	 * Re-apply hidden episode flags after Niztech re-import on Update.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return void
	 */
	function eluminate_standalone_restore_niztech_hidden_videos( int $post_id, WP_Post $post ): void {
		global $eluminate_niztech_hidden_snapshots;

		if ( ! eluminate_standalone_should_preserve_niztech_hidden_on_save( $post_id, $post ) ) {
			return;
		}

		if ( ! isset( $eluminate_niztech_hidden_snapshots[ $post_id ] ) || ! is_array( $eluminate_niztech_hidden_snapshots[ $post_id ] ) ) {
			return;
		}

		$snapshot = $eluminate_niztech_hidden_snapshots[ $post_id ];
		unset( $eluminate_niztech_hidden_snapshots[ $post_id ] );

		if ( empty( $snapshot ) || ! class_exists( 'Niztech_Youtube' ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . Niztech_Youtube::TBL_VIDEOS;

		foreach ( $snapshot as $youtube_code => $hidden ) {
			if ( ! $hidden ) {
				continue;
			}

			$wpdb->update(
				$table,
				array( 'hidden' => 1 ),
				array(
					'post_id'            => $post_id,
					'youtube_video_code' => (string) $youtube_code,
				),
				array( '%d' ),
				array( '%d', '%s' )
			);
		}
	}
}
add_action( 'save_post', 'eluminate_standalone_restore_niztech_hidden_videos', 11, 2 );
