<?php
/**
 * Drag-and-drop ordering for video series cards (per page + tags term) and episodes (per series).
 *
 * @package eluminate-standalone
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ELUMINATE_SERIES_ORDER_META = '_eluminate_tags_videos_order';
const ELUMINATE_EPISODE_ORDER_META = '_eluminate_episode_order';

if ( ! function_exists( 'eluminate_standalone_register_video_order_meta' ) ) {
	/**
	 * Registers post meta for REST/block-editor saves.
	 *
	 * @return void
	 */
	function eluminate_standalone_register_video_order_meta(): void {
		register_post_meta(
			'page',
			ELUMINATE_SERIES_ORDER_META,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_pages' );
				},
				'sanitize_callback' => 'eluminate_standalone_sanitize_series_order_meta',
			)
		);

		register_post_meta(
			'videos',
			ELUMINATE_EPISODE_ORDER_META,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => static function ( bool $allowed, string $meta_key, int $post_id ): bool {
					unset( $allowed, $meta_key );
					return current_user_can( 'edit_post', $post_id );
				},
				'sanitize_callback' => 'eluminate_standalone_sanitize_episode_order_meta',
			)
		);
	}
}
add_action( 'init', 'eluminate_standalone_register_video_order_meta' );

if ( ! function_exists( 'eluminate_standalone_sanitize_series_order_meta' ) ) {
	/**
	 * @param mixed $value Raw meta.
	 *
	 * @return string JSON map term_id => post_id[].
	 */
	function eluminate_standalone_sanitize_series_order_meta( $value ): string {
		$decoded = is_string( $value ) ? json_decode( $value, true ) : $value;
		if ( ! is_array( $decoded ) ) {
			return '{}';
		}

		$clean = array();
		foreach ( $decoded as $term_id => $post_ids ) {
			$tid = (int) $term_id;
			if ( $tid <= 0 || ! is_array( $post_ids ) ) {
				continue;
			}
			$ids = array_values(
				array_unique(
					array_filter(
						array_map( 'intval', $post_ids ),
						static function ( int $id ): bool {
							return $id > 0;
						}
					)
				)
			);
			if ( ! empty( $ids ) ) {
				$clean[ (string) $tid ] = $ids;
			}
		}

		return wp_json_encode( $clean );
	}
}

if ( ! function_exists( 'eluminate_standalone_sanitize_episode_order_meta' ) ) {
	/**
	 * @param mixed $value Raw meta.
	 *
	 * @return string JSON string[] of YouTube video codes.
	 */
	function eluminate_standalone_sanitize_episode_order_meta( $value ): string {
		$decoded = is_string( $value ) ? json_decode( $value, true ) : $value;
		if ( ! is_array( $decoded ) ) {
			return '[]';
		}

		$codes = array();
		foreach ( $decoded as $item ) {
			$code = sanitize_text_field( (string) $item );
			if ( '' !== $code && preg_match( '/^[\w-]+$/', $code ) ) {
				$codes[] = $code;
			}
		}

		return wp_json_encode( array_values( array_unique( $codes ) ) );
	}
}

if ( ! function_exists( 'eluminate_standalone_get_series_order_map' ) ) {
	/**
	 * @param int $page_id Page ID.
	 *
	 * @return array<string, int[]>
	 */
	function eluminate_standalone_get_series_order_map( int $page_id ): array {
		if ( $page_id <= 0 ) {
			return array();
		}

		$raw = get_post_meta( $page_id, ELUMINATE_SERIES_ORDER_META, true );
		if ( is_array( $raw ) ) {
			$decoded = $raw;
		} elseif ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
		} else {
			$decoded = array();
		}

		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$map = array();
		foreach ( $decoded as $term_id => $post_ids ) {
			$tid = (int) $term_id;
			if ( $tid <= 0 || ! is_array( $post_ids ) ) {
				continue;
			}
			$map[ (string) $tid ] = array_values(
				array_filter(
					array_map( 'intval', $post_ids ),
					static function ( int $id ): bool {
						return $id > 0;
					}
				)
			);
		}

		return $map;
	}
}

if ( ! function_exists( 'eluminate_standalone_get_episode_order_codes' ) ) {
	/**
	 * @param int $post_id videos post ID.
	 *
	 * @return string[] YouTube video codes in saved order.
	 */
	function eluminate_standalone_get_episode_order_codes( int $post_id ): array {
		if ( $post_id <= 0 ) {
			return array();
		}

		$raw = get_post_meta( $post_id, ELUMINATE_EPISODE_ORDER_META, true );
		if ( is_array( $raw ) ) {
			$decoded = $raw;
		} elseif ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
		} else {
			$decoded = array();
		}

		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$codes = array();
		foreach ( $decoded as $item ) {
			$code = sanitize_text_field( (string) $item );
			if ( '' !== $code && preg_match( '/^[\w-]+$/', $code ) ) {
				$codes[] = $code;
			}
		}

		return array_values( array_unique( $codes ) );
	}
}

if ( ! function_exists( 'eluminate_standalone_resolve_episode_order_codes' ) ) {
	/**
	 * Normalizes stored order to YouTube codes (supports legacy Niztech row ids).
	 *
	 * @param int   $post_id videos post ID.
	 * @param array $videos  Current Niztech rows.
	 *
	 * @return string[]
	 */
	function eluminate_standalone_resolve_episode_order_codes( int $post_id, array $videos ): array {
		$stored = eluminate_standalone_get_episode_order_codes( $post_id );
		if ( empty( $stored ) ) {
			return array();
		}

		$by_id   = array();
		$by_code = array();
		foreach ( $videos as $video ) {
			if ( isset( $video->id ) ) {
				$by_id[ (int) $video->id ] = $video;
			}
			if ( ! empty( $video->youtube_video_code ) ) {
				$by_code[ (string) $video->youtube_video_code ] = $video;
			}
		}

		$codes = array();
		foreach ( $stored as $item ) {
			$item = (string) $item;
			if ( isset( $by_code[ $item ] ) ) {
				$codes[] = $item;
				continue;
			}
			if ( ctype_digit( $item ) && isset( $by_id[ (int) $item ] ) && ! empty( $by_id[ (int) $item ]->youtube_video_code ) ) {
				$codes[] = (string) $by_id[ (int) $item ]->youtube_video_code;
			}
		}

		return array_values( array_unique( $codes ) );
	}
}

if ( ! function_exists( 'eluminate_standalone_get_host_page_id' ) ) {
	/**
	 * Page hosting the current shortcode render (when in the loop).
	 *
	 * @return int
	 */
	function eluminate_standalone_get_host_page_id(): int {
		global $post;
		if ( $post instanceof WP_Post && 'page' === $post->post_type ) {
			return (int) $post->ID;
		}

		$queried = get_queried_object();
		if ( $queried instanceof WP_Post && 'page' === $queried->post_type ) {
			return (int) $queried->ID;
		}

		return 0;
	}
}

if ( ! function_exists( 'eluminate_standalone_apply_episode_order' ) ) {
	/**
	 * Reorders Niztech video rows using saved episode order meta.
	 *
	 * @param int        $post_id videos post ID.
	 * @param array|null $videos  Niztech video rows.
	 *
	 * @return array|null
	 */
	function eluminate_standalone_apply_episode_order( int $post_id, ?array $videos ): ?array {
		if ( empty( $videos ) ) {
			return $videos;
		}

		$order = eluminate_standalone_resolve_episode_order_codes( $post_id, $videos );
		if ( empty( $order ) ) {
			return $videos;
		}

		$by_code = array();
		foreach ( $videos as $video ) {
			if ( ! empty( $video->youtube_video_code ) ) {
				$by_code[ (string) $video->youtube_video_code ] = $video;
			}
		}

		$sorted = array();
		foreach ( $order as $code ) {
			if ( isset( $by_code[ $code ] ) ) {
				$sorted[] = $by_code[ $code ];
				unset( $by_code[ $code ] );
			}
		}
		foreach ( $by_code as $video ) {
			$sorted[] = $video;
		}

		return $sorted;
	}
}

if ( ! function_exists( 'eluminate_standalone_video_content' ) ) {
	/**
	 * Niztech video rows with theme episode order applied.
	 *
	 * @param int $post_id videos post ID.
	 *
	 * @return array|null
	 */
	function eluminate_standalone_video_content( int $post_id ): ?array {
		if ( ! class_exists( 'Niztech_Youtube_Client' ) ) {
			return null;
		}

		$videos = Niztech_Youtube_Client::video_content( $post_id );
		if ( empty( $videos ) ) {
			return $videos;
		}

		return eluminate_standalone_apply_episode_order( $post_id, $videos );
	}
}

if ( ! function_exists( 'eluminate_standalone_youtube_featured_image_enabled' ) ) {
	/**
	 * Whether Niztech "Use YouTube thumbnail image." is enabled for a series.
	 *
	 * @param int $post_id videos post ID.
	 *
	 * @return bool
	 */
	function eluminate_standalone_youtube_featured_image_enabled( int $post_id ): bool {
		$value = get_post_meta( $post_id, 'niztech_youtube_use_yt_thumbnail', true );
		return in_array( $value, array( 'on', '1', 1, true ), true );
	}
}

if ( ! function_exists( 'eluminate_standalone_thumbnail_url_matches_code' ) ) {
	/**
	 * Whether a YouTube thumbnail URL belongs to the given video code.
	 *
	 * @param string $url  Thumbnail URL.
	 * @param string $code YouTube video code.
	 *
	 * @return bool
	 */
	function eluminate_standalone_thumbnail_url_matches_code( string $url, string $code ): bool {
		if ( '' === $url || '' === $code ) {
			return true;
		}
		if ( preg_match( '#(?:/vi/|vi%3D|embed/)([^/&?%]+)#i', $url, $matches ) ) {
			return (string) $matches[1] === $code;
		}

		return true;
	}
}

if ( ! function_exists( 'eluminate_standalone_get_video_thumbnail_url' ) ) {
	/**
	 * Best thumbnail URL for a Niztech video row, preferring stored URLs that match the video code.
	 *
	 * @param object $video Niztech video row.
	 *
	 * @return string
	 */
	function eluminate_standalone_get_video_thumbnail_url( object $video ): string {
		$code = isset( $video->youtube_video_code ) ? (string) $video->youtube_video_code : '';
		$candidates = array(
			$video->thumbnail_maxres_url ?? '',
			$video->thumbnail_standard_url ?? '',
			$video->thumbnail_high_url ?? '',
			$video->thumbnail_default_url ?? '',
		);

		foreach ( $candidates as $candidate ) {
			$url = (string) $candidate;
			if ( '' === $url ) {
				continue;
			}
			if ( '' !== $code && ! eluminate_standalone_thumbnail_url_matches_code( $url, $code ) ) {
				continue;
			}
			return $url;
		}

		if ( '' !== $code ) {
			return 'https://i.ytimg.com/vi/' . rawurlencode( $code ) . '/maxresdefault.jpg';
		}

		return '';
	}
}

if ( ! function_exists( 'eluminate_standalone_sync_youtube_featured_image_from_episode_order' ) ) {
	/**
	 * Sets the WordPress featured image from the first episode after custom order.
	 *
	 * Runs after Niztech import + episode-order save on Update.
	 *
	 * @param int $post_id videos post ID.
	 *
	 * @return void
	 */
	function eluminate_standalone_sync_youtube_featured_image_from_episode_order( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'videos' !== $post->post_type ) {
			return;
		}

		if ( ! eluminate_standalone_youtube_featured_image_enabled( $post_id ) ) {
			return;
		}

		if ( ! class_exists( 'Niztech_Youtube_Admin' ) ) {
			return;
		}

		$videos = eluminate_standalone_video_content( $post_id );
		if ( empty( $videos[0] ) ) {
			return;
		}

		$first = $videos[0];
		$url   = eluminate_standalone_get_video_thumbnail_url( $first );
		if ( '' === $url ) {
			return;
		}

		$desc = isset( $first->description ) ? (string) $first->description : '';
		Niztech_Youtube_Admin::generate_featured_image( $url, $post_id, $desc );
	}
}
add_action( 'save_post_videos', 'eluminate_standalone_sync_youtube_featured_image_from_episode_order', 30 );

if ( ! function_exists( 'eluminate_standalone_order_series_posts_for_page_term' ) ) {
	/**
	 * @param WP_Post[] $posts   Query results.
	 * @param int       $page_id Page ID.
	 * @param int       $term_id tags term ID.
	 *
	 * @return WP_Post[]
	 */
	function eluminate_standalone_order_series_posts_for_page_term( array $posts, int $page_id, int $term_id ): array {
		if ( empty( $posts ) || $page_id <= 0 || $term_id <= 0 ) {
			return $posts;
		}

		$map   = eluminate_standalone_get_series_order_map( $page_id );
		$order = $map[ (string) $term_id ] ?? array();
		if ( empty( $order ) ) {
			return $posts;
		}

		$by_id = array();
		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$by_id[ (int) $post->ID ] = $post;
			}
		}

		$sorted = array();
		foreach ( $order as $post_id ) {
			if ( isset( $by_id[ $post_id ] ) ) {
				$sorted[] = $by_id[ $post_id ];
				unset( $by_id[ $post_id ] );
			}
		}
		foreach ( $by_id as $post ) {
			$sorted[] = $post;
		}

		return $sorted;
	}
}

if ( ! function_exists( 'eluminate_standalone_get_series_card_admin_items' ) ) {
	/**
	 * Series rows for admin drag UI.
	 *
	 * @param int   $term_id tags term ID.
	 * @param int[] $order   Optional saved post ID order.
	 *
	 * @return array<int, array{id:int, title:string, thumb:string}>
	 */
	function eluminate_standalone_get_series_card_admin_items( int $term_id, array $order = array() ): array {
		if ( $term_id <= 0 ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'videos',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy' => 'tags',
						'field'    => 'term_id',
						'terms'    => array( $term_id ),
					),
				),
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$thumb = '';
			$videos = eluminate_standalone_video_content( (int) $post->ID );
			if ( ! empty( $videos[0] ) ) {
				$thumb = eluminate_standalone_get_video_thumbnail_url( $videos[0] );
			}
			$items[ (int) $post->ID ] = array(
				'id'    => (int) $post->ID,
				'title' => get_the_title( $post ),
				'thumb' => $thumb,
			);
		}
		wp_reset_postdata();

		if ( empty( $order ) ) {
			return array_values( $items );
		}

		$sorted = array();
		foreach ( $order as $post_id ) {
			if ( isset( $items[ $post_id ] ) ) {
				$sorted[] = $items[ $post_id ];
				unset( $items[ $post_id ] );
			}
		}
		foreach ( $items as $item ) {
			$sorted[] = $item;
		}

		return $sorted;
	}
}

if ( ! function_exists( 'eluminate_standalone_get_episode_admin_items' ) ) {
	/**
	 * Episode rows for admin drag UI.
	 *
	 * @param int $post_id videos post ID.
	 *
	 * @return array<int, array{id:string, title:string, thumb:string}>
	 */
	function eluminate_standalone_get_episode_admin_items( int $post_id ): array {
		if ( $post_id <= 0 || ! class_exists( 'Niztech_Youtube_Client' ) ) {
			return array();
		}

		$videos = Niztech_Youtube_Client::video_content( $post_id );
		if ( empty( $videos ) ) {
			return array();
		}

		$videos = eluminate_standalone_apply_episode_order( $post_id, $videos );
		$items  = array();
		foreach ( $videos as $video ) {
			if ( empty( $video->youtube_video_code ) ) {
				continue;
			}
			$items[] = array(
				'id'    => (string) $video->youtube_video_code,
				'title' => isset( $video->title ) ? (string) $video->title : '',
				'thumb' => eluminate_standalone_get_video_thumbnail_url( $video ),
			);
		}

		return $items;
	}
}

if ( ! function_exists( 'eluminate_standalone_add_page_series_order_meta_box' ) ) {
	/**
	 * @return void
	 */
	function eluminate_standalone_add_page_series_order_meta_box(): void {
		add_meta_box(
			'eluminate-video-series-order',
			__( 'Video Order', 'eluminate-standalone' ),
			'eluminate_standalone_render_page_series_order_meta_box',
			'page',
			'normal',
			'default'
		);
	}
}
add_action( 'add_meta_boxes_page', 'eluminate_standalone_add_page_series_order_meta_box' );

if ( ! function_exists( 'eluminate_standalone_add_episode_order_meta_box' ) ) {
	/**
	 * @return void
	 */
	function eluminate_standalone_add_episode_order_meta_box(): void {
		add_meta_box(
			'eluminate-episode-order',
			__( 'Video Order', 'eluminate-standalone' ),
			'eluminate_standalone_render_episode_order_meta_box',
			'videos',
			'normal',
			'default'
		);
	}
}
add_action( 'add_meta_boxes_videos', 'eluminate_standalone_add_episode_order_meta_box' );

if ( ! function_exists( 'eluminate_standalone_render_sortable_list' ) ) {
	/**
	 * @param string                             $list_class CSS class for ul.
	 * @param array<int, array{id:int,title:string,thumb:string}> $items Items.
	 *
	 * @return void
	 */
	function eluminate_standalone_render_sortable_list( string $list_class, array $items ): void {
		echo '<ul class="eluminate-sortable-list ' . esc_attr( $list_class ) . '">';
		foreach ( $items as $item ) {
			$thumb_html = '';
			if ( ! empty( $item['thumb'] ) ) {
				$thumb_html = '<img class="eluminate-sortable-thumb" src="' . esc_url( $item['thumb'] ) . '" alt="" loading="lazy" />';
			}
			echo '<li class="eluminate-sortable-item" data-id="' . esc_attr( (string) $item['id'] ) . '">';
			echo '<span class="eluminate-sortable-handle" aria-hidden="true">≡</span>';
			echo $thumb_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped url above.
			echo '<span class="eluminate-sortable-label">' . esc_html( $item['title'] ) . '</span>';
			echo '</li>';
		}
		echo '</ul>';
	}
}

if ( ! function_exists( 'eluminate_standalone_render_page_series_order_meta_box' ) ) {
	/**
	 * @param WP_Post $post Page post.
	 *
	 * @return void
	 */
	function eluminate_standalone_render_page_series_order_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'eluminate_video_order_save', 'eluminate_video_order_nonce' );

		$term_ids = eluminate_standalone_get_shows_videos_shortcode_term_ids( (string) $post->post_content );
		if ( empty( $term_ids ) ) {
			$term_ids = eluminate_standalone_get_page_tags_term_ids( (int) $post->ID );
		}

		$order_map = eluminate_standalone_get_series_order_map( (int) $post->ID );

		echo '<p class="description">';
		echo esc_html__( 'Drag videos to reorder. Click "Save" to apply changes.', 'eluminate-standalone' );
		echo '</p>';

		echo '<div id="eluminate-series-order-root" data-context="page">';
		if ( empty( $term_ids ) ) {
			echo '<p class="eluminate-order-empty"><em>' . esc_html__( 'Check a tag in the sidebar to add a video section, then arrange its videos here.', 'eluminate-standalone' ) . '</em></p>';
		} else {
			foreach ( $term_ids as $term_id ) {
				$term = get_term( (int) $term_id, 'tags' );
				if ( ! $term instanceof WP_Term ) {
					continue;
				}
				$saved_order = $order_map[ (string) $term->term_id ] ?? array();
				$items       = eluminate_standalone_get_series_card_admin_items( (int) $term->term_id, $saved_order );
				echo '<div class="eluminate-order-section" data-term-id="' . esc_attr( (string) $term->term_id ) . '" data-term-slug="' . esc_attr( $term->slug ) . '">';
				echo '<h4 class="eluminate-order-section-title">' . esc_html( $term->name ) . '</h4>';
				if ( empty( $items ) ) {
					echo '<p class="eluminate-order-empty"><em>' . esc_html__( 'No published videos with this tag yet.', 'eluminate-standalone' ) . '</em></p>';
				} else {
					eluminate_standalone_render_sortable_list( 'eluminate-series-sortable', $items );
				}
				echo '</div>';
			}
		}
		echo '</div>';

		echo '<input type="hidden" id="eluminate_tags_videos_order" name="eluminate_tags_videos_order" value="' . esc_attr( wp_json_encode( $order_map ) ) . '" />';
	}
}

if ( ! function_exists( 'eluminate_standalone_render_episode_order_meta_box' ) ) {
	/**
	 * @param WP_Post $post videos post.
	 *
	 * @return void
	 */
	function eluminate_standalone_render_episode_order_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'eluminate_video_order_save', 'eluminate_video_order_nonce' );

		$items = eluminate_standalone_get_episode_admin_items( (int) $post->ID );
		$order = eluminate_standalone_get_episode_order_codes( (int) $post->ID );

		echo '<p class="description">';
		echo esc_html__( 'Drag videos to reorder. Click "Update" to apply changes.', 'eluminate-standalone' );
		echo '</p>';

		echo '<div id="eluminate-episode-order-root" data-context="series" data-post-id="' . esc_attr( (string) $post->ID ) . '">';
		if ( empty( $items ) ) {
			echo '<p class="eluminate-order-empty"><em>' . esc_html__( 'Add YouTube videos to this video first (Niztech YouTube plugin).', 'eluminate-standalone' ) . '</em></p>';
		} else {
			eluminate_standalone_render_sortable_list( 'eluminate-episode-sortable', $items );
		}
		echo '</div>';

		echo '<input type="hidden" id="eluminate_episode_order" name="eluminate_episode_order" value="' . esc_attr( wp_json_encode( $order ) ) . '" />';
	}
}

if ( ! function_exists( 'eluminate_standalone_save_video_order_meta' ) ) {
	/**
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	function eluminate_standalone_save_video_order_meta( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( 'page' === $post->post_type && isset( $_POST['eluminate_tags_videos_order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! eluminate_standalone_verify_video_order_nonce() ) {
				return;
			}
			$raw = wp_unslash( $_POST['eluminate_tags_videos_order'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $post_id, ELUMINATE_SERIES_ORDER_META, eluminate_standalone_sanitize_series_order_meta( $raw ) );
		}

		if ( 'videos' === $post->post_type && isset( $_POST['eluminate_episode_order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! eluminate_standalone_verify_video_order_nonce() ) {
				return;
			}
			$raw = wp_unslash( $_POST['eluminate_episode_order'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $post_id, ELUMINATE_EPISODE_ORDER_META, eluminate_standalone_sanitize_episode_order_meta( $raw ) );
		}
	}
}
add_action( 'save_post_videos', 'eluminate_standalone_save_video_order_meta', 20 );
add_action( 'save_post_page', 'eluminate_standalone_save_video_order_meta', 10 );

if ( ! function_exists( 'eluminate_standalone_verify_video_order_nonce' ) ) {
	/**
	 * @return bool
	 */
	function eluminate_standalone_verify_video_order_nonce(): bool {
		if ( ! isset( $_POST['eluminate_video_order_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return false;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['eluminate_video_order_nonce'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return (bool) wp_verify_nonce( $nonce, 'eluminate_video_order_save' );
	}
}

if ( ! function_exists( 'eluminate_standalone_ajax_series_for_term' ) ) {
	/**
	 * AJAX: series card list for a tags term.
	 *
	 * @return void
	 */
	function eluminate_standalone_ajax_series_for_term(): void {
		check_ajax_referer( 'eluminate_video_order', 'nonce' );

		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}

		$term_id = isset( $_POST['term_id'] ) ? (int) wp_unslash( $_POST['term_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$term    = get_term( $term_id, 'tags' );
		if ( ! $term instanceof WP_Term ) {
			wp_send_json_error( array( 'message' => 'Invalid term' ), 400 );
		}

		$order_raw = isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$order     = is_array( $order_raw ) ? array_map( 'intval', $order_raw ) : array();

		wp_send_json_success(
			array(
				'term_id'   => (int) $term->term_id,
				'term_name' => $term->name,
				'term_slug' => $term->slug,
				'items'     => eluminate_standalone_get_series_card_admin_items( (int) $term->term_id, $order ),
			)
		);
	}
}
add_action( 'wp_ajax_eluminate_series_for_term', 'eluminate_standalone_ajax_series_for_term' );

if ( ! function_exists( 'eluminate_standalone_query_series_for_term' ) ) {
	/**
	 * Fetches videos posts for a tags term, honoring per-page custom card order.
	 *
	 * @param WP_Term $term     tags term.
	 * @param int     $page_id  Host page ID (0 = default date order + WP pagination).
	 * @param int     $per_page Posts per page.
	 * @param int     $paged    Current page.
	 * @param int     $limit    Optional hard cap (0 = paginate).
	 *
	 * @return array{posts: WP_Post[], max_pages: int, paged: int}
	 */
	function eluminate_standalone_query_series_for_term( WP_Term $term, int $page_id, int $per_page, int $paged, int $limit = 0 ): array {
		$term_id   = (int) $term->term_id;
		$tax_query = array(
			array(
				'taxonomy' => 'tags',
				'field'    => 'term_id',
				'terms'    => array( $term_id ),
			),
		);

		$order_map    = eluminate_standalone_get_series_order_map( $page_id );
		$custom_order = $order_map[ (string) $term_id ] ?? array();

		if ( empty( $custom_order ) ) {
			$query = new WP_Query(
				array(
					'post_type'      => 'videos',
					'post_status'    => 'publish',
					'posts_per_page' => $limit > 0 ? $limit : $per_page,
					'paged'          => $limit > 0 ? 1 : $paged,
					'tax_query'      => $tax_query,
				)
			);

			return array(
				'posts'     => $query->posts,
				'max_pages' => $limit > 0 ? 1 : (int) $query->max_num_pages,
				'paged'     => $limit > 0 ? 1 : $paged,
			);
		}

		$all_query = new WP_Query(
			array(
				'post_type'              => 'videos',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'tax_query'              => $tax_query,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
			)
		);

		$ordered = eluminate_standalone_order_series_posts_for_page_term(
			$all_query->posts,
			$page_id,
			$term_id
		);
		wp_reset_postdata();

		$total = count( $ordered );
		if ( $limit > 0 ) {
			return array(
				'posts'     => array_slice( $ordered, 0, $limit ),
				'max_pages' => 1,
				'paged'     => 1,
			);
		}

		$max_pages = max( 1, (int) ceil( $total / max( 1, $per_page ) ) );
		$paged     = max( 1, min( $paged, $max_pages ) );
		$offset    = ( $paged - 1 ) * $per_page;

		return array(
			'posts'     => array_slice( $ordered, $offset, $per_page ),
			'max_pages' => $max_pages,
			'paged'     => $paged,
		);
	}
}

if ( ! function_exists( 'eluminate_standalone_enqueue_video_order_admin_assets' ) ) {
	/**
	 * @param string $hook_suffix Admin hook.
	 *
	 * @return void
	 */
	function eluminate_standalone_enqueue_video_order_admin_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->post_type, array( 'page', 'videos' ), true ) ) {
			return;
		}

		wp_enqueue_script( 'jquery-ui-sortable' );

		$style_deps = array();
		if ( 'videos' === $screen->post_type && wp_style_is( 'niztech_youtube_admin', 'registered' ) ) {
			$style_deps[] = 'niztech_youtube_admin';
		}

		$style_path = trailingslashit( get_template_directory() ) . 'styles/video-order-admin.css';
		if ( is_readable( $style_path ) ) {
			wp_enqueue_style(
				'eluminate-video-order-admin',
				get_template_directory_uri() . '/styles/video-order-admin.css',
				$style_deps,
				(string) filemtime( $style_path )
			);
		}

		$script_path = trailingslashit( get_template_directory() ) . 'assets/js/video-order-admin.js';
		if ( ! is_readable( $script_path ) ) {
			return;
		}

		wp_enqueue_script(
			'eluminate-video-order-admin',
			get_template_directory_uri() . '/assets/js/video-order-admin.js',
			array( 'jquery', 'jquery-ui-sortable', 'wp-data', 'wp-api-fetch' ),
			(string) filemtime( $script_path ),
			true
		);

		$post_id = 0;
		if ( 'post' === $screen->base && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = (int) $_GET['post'];
		}

		wp_localize_script(
			'eluminate-video-order-admin',
			'eluminateVideoOrder',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'eluminate_video_order' ),
				'seriesOrderMeta'   => ELUMINATE_SERIES_ORDER_META,
				'episodeOrderMeta'  => ELUMINATE_EPISODE_ORDER_META,
				'postId'            => 'videos' === $screen->post_type ? $post_id : 0,
				'strings'           => array(
					'noSeries'        => __( 'No published videos with this tag yet.', 'eluminate-standalone' ),
					'loading'         => __( 'Loading…', 'eluminate-standalone' ),
					'sectionFor'      => __( 'Tag', 'eluminate-standalone' ),
					'noTagSections'   => __( 'Check a tag in the sidebar to add a video section, then arrange its videos here.', 'eluminate-standalone' ),
					'noSeriesDefault' => __( 'No published videos with this tag yet.', 'eluminate-standalone' ),
				),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'eluminate_standalone_enqueue_video_order_admin_assets', 20 );
