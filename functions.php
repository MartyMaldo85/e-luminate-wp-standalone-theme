<?php
/**
 * File Name: functions.php
 * Requires 'niztech-youtube' plugin. Registers the `videos` post type and `tags` taxonomy.
 *
 * @category   Theme
 * @package eluminate-standalone
 * @author     Nazario A. Ayala <nazario@niztech.com>
 * @license    opensource.org MIT License
 * @link       https://www.niztech.com
 * @since      0.0.1
 */

const THEME_KEY     = 'eluminate-standalone';
const THEME_VERSION = 8;

/** Admin nav menu whose top-level rows define order for "List in By Topic menu" tags terms (auto-sync). */
const ELUMINATE_BY_TOPIC_SYNC_MENU_NAME = 'By Topic';

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$eluminate_video_order_inc = trailingslashit( __DIR__ ) . 'inc/video-order.php';
$eluminate_migrate_slugs_inc = trailingslashit( __DIR__ ) . 'inc/migrate-slugs.php';
if ( is_readable( $eluminate_migrate_slugs_inc ) ) {
	require_once $eluminate_migrate_slugs_inc;
}
$eluminate_niztech_bridge_inc = trailingslashit( __DIR__ ) . 'inc/niztech-videos-bridge.php';
if ( is_readable( $eluminate_niztech_bridge_inc ) ) {
	require_once $eluminate_niztech_bridge_inc;
}
if ( is_readable( $eluminate_video_order_inc ) ) {
	require_once $eluminate_video_order_inc;
}

/*
 * Block editor loads pages via the REST API; `content.rendered` runs `do_shortcode()`. Heavy shortcodes must
 * not run there or JSON responses can fail (editor shows "item doesn't exist"). This flag is set for the
 * whole REST request before any route runs — more reliable than REST_REQUEST / URI heuristics alone.
 */
add_filter(
	'rest_pre_dispatch',
	static function ( $result, $server, $request ) {
		$GLOBALS['eluminate_standalone_rest_dispatch'] = true;
		return $result;
	},
	0,
	3
);

if ( ! function_exists( 'eluminate_standalone_get_browser_sync_proxy_host' ) ) {
	/**
	 * Browser-sync client host when MAMP is reached through the dev proxy.
	 *
	 * browser-sync sets changeOrigin, so PHP often sees localhost:8888 as HTTP_HOST even
	 * when the browser is on localhost:3000. bs-config.js forwards X-Forwarded-Host instead.
	 *
	 * @return string|null Host with port, e.g. localhost:3000.
	 */
	function eluminate_standalone_get_browser_sync_proxy_host(): ?string {
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_HOST'] ) ) {
			$forwarded = trim( explode( ',', (string) wp_unslash( $_SERVER['HTTP_X_FORWARDED_HOST'] ) )[0] );
			if ( str_starts_with( $forwarded, 'localhost:3000' ) ) {
				return $forwarded;
			}
		}

		if ( isset( $_SERVER['HTTP_HOST'] ) && 'localhost:3000' === $_SERVER['HTTP_HOST'] ) {
			return 'localhost:3000';
		}

		return null;
	}
}

if ( ! function_exists( 'eluminate_standalone_bootstrap_browser_sync_proxy_request' ) ) {
	/**
	 * Align PHP request host with the browser-sync client host.
	 *
	 * Without this, overriding home/siteurl to :3000 while HTTP_HOST stays :8888 makes
	 * redirect_canonical bounce forever (frontend "too many redirects").
	 *
	 * @return void
	 */
	function eluminate_standalone_bootstrap_browser_sync_proxy_request(): void {
		$host = eluminate_standalone_get_browser_sync_proxy_host();
		if ( null === $host ) {
			return;
		}

		$_SERVER['HTTP_HOST']   = $host;
		$_SERVER['SERVER_NAME'] = explode( ':', $host )[0];
		if ( str_contains( $host, ':' ) ) {
			$_SERVER['SERVER_PORT'] = explode( ':', $host )[1];
		}
	}
}
eluminate_standalone_bootstrap_browser_sync_proxy_request();

if ( ! function_exists( 'eluminate_standalone_is_browser_sync_proxy_request' ) ) {
	/**
	 * Whether the request is served through browser-sync (npm run dev → localhost:3000).
	 *
	 * @return bool
	 */
	function eluminate_standalone_is_browser_sync_proxy_request(): bool {
		return null !== eluminate_standalone_get_browser_sync_proxy_host();
	}
}

if ( ! function_exists( 'eluminate_standalone_browser_sync_proxy_base_url' ) ) {
	/**
	 * WordPress base URL when proxied through browser-sync.
	 *
	 * @return string
	 */
	function eluminate_standalone_browser_sync_proxy_base_url(): string {
		$host = eluminate_standalone_get_browser_sync_proxy_host();

		return 'http://' . ( $host ?? 'localhost:3000' ) . '/wordpress';
	}
}

if ( ! function_exists( 'eluminate_standalone_filter_browser_sync_proxy_option' ) ) {
	/**
	 * Overrides siteurl/home for proxied dev requests without changing the database.
	 *
	 * @param mixed $pre_option Current pre-option value.
	 *
	 * @return mixed
	 */
	function eluminate_standalone_filter_browser_sync_proxy_option( $pre_option ) {
		if ( ! eluminate_standalone_is_browser_sync_proxy_request() ) {
			return $pre_option;
		}

		return eluminate_standalone_browser_sync_proxy_base_url();
	}
}
add_filter( 'pre_option_siteurl', 'eluminate_standalone_filter_browser_sync_proxy_option' );
add_filter( 'pre_option_home', 'eluminate_standalone_filter_browser_sync_proxy_option' );

if ( ! function_exists( 'eluminate_standalone_fix_browser_sync_proxy_url' ) ) {
	/**
	 * Rewrites any lingering MAMP URLs to the browser-sync proxy origin.
	 *
	 * @param string $url Generated URL.
	 *
	 * @return string
	 */
	function eluminate_standalone_fix_browser_sync_proxy_url( $url ) {
		if ( ! is_string( $url ) || ! eluminate_standalone_is_browser_sync_proxy_request() ) {
			return $url;
		}

		$proxy_host = eluminate_standalone_get_browser_sync_proxy_host();
		if ( null === $proxy_host ) {
			return $url;
		}

		return str_replace( 'http://localhost:8888', 'http://' . $proxy_host, $url );
	}
}
add_filter( 'home_url', 'eluminate_standalone_fix_browser_sync_proxy_url', 20 );
add_filter( 'site_url', 'eluminate_standalone_fix_browser_sync_proxy_url', 20 );
add_filter( 'admin_url', 'eluminate_standalone_fix_browser_sync_proxy_url', 20 );
add_filter( 'rest_url', 'eluminate_standalone_fix_browser_sync_proxy_url', 20 );
add_filter( 'content_url', 'eluminate_standalone_fix_browser_sync_proxy_url', 20 );
add_filter( 'plugins_url', 'eluminate_standalone_fix_browser_sync_proxy_url', 20 );
add_filter( 'theme_root_uri', 'eluminate_standalone_fix_browser_sync_proxy_url', 20 );
add_filter( 'stylesheet_directory_uri', 'eluminate_standalone_fix_browser_sync_proxy_url', 20 );
add_filter( 'template_directory_uri', 'eluminate_standalone_fix_browser_sync_proxy_url', 20 );
add_filter( 'script_loader_src', 'eluminate_standalone_fix_browser_sync_proxy_url', 20 );
add_filter( 'style_loader_src', 'eluminate_standalone_fix_browser_sync_proxy_url', 20 );

if ( class_exists( 'Niztech_Youtube' ) ) {
	$path_to_plugins = join( DIRECTORY_SEPARATOR, array( WP_PLUGIN_DIR, 'niztech-youtube', 'class-niztech-youtube-client.php' ) );
	include_once $path_to_plugins;
}

/**
 * Enqueue theme styles with cache-busting version derived from the latest CSS file mtime.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		$style_path    = trailingslashit( get_stylesheet_directory() ) . 'style.css';
		$style_version = THEME_VERSION;
		// Bust cache when style.css or any imported styles/*.css changes (not only on local).
		$latest_mtime = file_exists( $style_path ) ? filemtime( $style_path ) : 0;
		$styles_dir   = trailingslashit( get_stylesheet_directory() ) . 'styles';
		if ( is_dir( $styles_dir ) ) {
			$style_files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $styles_dir )
			);
			foreach ( $style_files as $file ) {
				if ( $file->isFile() && 'css' === $file->getExtension() ) {
					$file_mtime = $file->getMTime();
					if ( $file_mtime > $latest_mtime ) {
						$latest_mtime = $file_mtime;
					}
				}
			}
		}

		if ( $latest_mtime > 0 ) {
			$style_version = (string) $latest_mtime;
		}

		wp_enqueue_style( 'style', get_stylesheet_uri(), array(), $style_version );
	}
);

/**
 * Contact Form 7 default CSS loads after the main theme bundle; enqueue overrides last (after CF7).
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$contact_css = trailingslashit( get_template_directory() ) . 'styles/page-contact.css';
		if ( ! is_readable( $contact_css ) ) {
			return;
		}
		$deps = array( 'style' );
		if ( wp_style_is( 'contact-form-7', 'registered' ) ) {
			$deps[] = 'contact-form-7';
		}
		wp_enqueue_style(
			'eluminate-page-contact',
			get_template_directory_uri() . '/styles/page-contact.css',
			$deps,
			(string) filemtime( $contact_css )
		);

		$contact_float_js = trailingslashit( get_template_directory() ) . 'assets/js/contact-float-labels.js';
		if ( is_readable( $contact_float_js ) ) {
			$js_deps = array();
			if ( wp_script_is( 'contact-form-7', 'registered' ) ) {
				$js_deps[] = 'contact-form-7';
			}
			wp_enqueue_script(
				'eluminate-contact-float-labels',
				get_template_directory_uri() . '/assets/js/contact-float-labels.js',
				$js_deps,
				(string) filemtime( $contact_float_js ),
				true
			);
		}
	},
	25
);

/**
 * Favicons from /assets (favicon.svg, favicon-32.png, favicon.ico, apple-touch-icon.png).
 * Removes Customizer site icon meta when those files exist to avoid duplicate link tags.
 */
add_action(
	'after_setup_theme',
	static function (): void {
		$dir = trailingslashit( get_template_directory() ) . 'assets/';
		if (
			is_readable( $dir . 'favicon.svg' )
			|| is_readable( $dir . 'favicon-32.png' )
			|| is_readable( $dir . 'favicon.ico' )
		) {
			remove_action( 'wp_head', 'wp_site_icon', 99 );
		}
	},
	20
);

add_action(
	'wp_head',
	static function (): void {
		$base = trailingslashit( get_template_directory_uri() ) . 'assets/';
		$dir  = trailingslashit( get_template_directory() ) . 'assets/';

		if ( is_readable( $dir . 'favicon.svg' ) ) {
			echo '<link rel="icon" href="' . esc_url( $base . 'favicon.svg' ) . '" type="image/svg+xml">' . "\n";
		}
		if ( is_readable( $dir . 'favicon-32.png' ) ) {
			echo '<link rel="icon" href="' . esc_url( $base . 'favicon-32.png' ) . '" sizes="32x32" type="image/png">' . "\n";
		}
		if ( is_readable( $dir . 'favicon.ico' ) ) {
			echo '<link rel="icon" href="' . esc_url( $base . 'favicon.ico' ) . '" sizes="48x48">' . "\n";
		}
		if ( is_readable( $dir . 'apple-touch-icon.png' ) ) {
			echo '<link rel="apple-touch-icon" href="' . esc_url( $base . 'apple-touch-icon.png' ) . '">' . "\n";
		}
	},
	2
);

/**
 * Register Post types used by this theme.
 * Register videos Post Type
 */
add_action(
	'init',
	function () {
		eluminate_standalone_register_post_type_init();

		eluminate_standalone_menu_init();
		// Prefill taxonomy terms before building the "By Topic" menu so get_terms() is not empty.
		eluminate_standalone_prefill_taxonomies_init();
		eluminate_standalone_menu_tags_init();

		update_option( THEME_KEY . '_init_version_run', THEME_VERSION );
	},
	0
);


/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
add_action(
	'after_setup_theme',
	function () {
		/*
		* Make theme available for translation.
		* Translations can be filed at WordPress.org. See: https://translate.wordpress.org/projects/wp-themes/twentyfifteen
		* If you're building a theme based on twentyfifteen, use a find and replace
		* to change 'twentyfifteen' to the name of your theme in all the template files
		*/

		/*
		* Let WordPress manage the document title.
		* By adding theme support, we declare that this theme does not use a
		* hard-coded <title> tag in the document head, and expect WordPress to
		* provide it for us.
		*/

		add_theme_support( 'title-tag' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'html5', array( 'style', 'script' ) );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'content-width', 1024 );
		add_editor_style( 'styles/editor.css' );
		load_theme_textdomain( 'eluminate-standalone', implode( DIRECTORY_SEPARATOR, array( get_template_directory(), 'languages' ) ) );
	}
);

/**
 * Load theme webfonts in the block editor so editor.css faces resolve.
 */
add_action(
	'enqueue_block_editor_assets',
	static function (): void {
		wp_enqueue_style(
			'eluminate-editor-fonts',
			'https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap',
			array(),
			null
		);
	}
);

/**
 * This forces `videos` pages to use the videos.php
 *
 * @param $single_template
 *
 * @return string
 */
add_filter(
	'single_template',
	function ( $single_template ) {
		global $post;
		// Use a custom template only when needed.
		if ( 'videos' === $post->post_type ) {
			return join( DIRECTORY_SEPARATOR, array( __DIR__, 'templates', 'videos.php' ) );
		}

		return $single_template;
	}
);

/**
 * Taxonomy archives for `tags` must query `videos`; WordPress defaults the main query to `post`.
 */
add_action(
	'pre_get_posts',
	function ( $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->is_tax( 'tags' ) ) {
			$query->set( 'post_type', 'videos' );
			$query->set( 'posts_per_page', 10 );
		}
	}
);

if ( ! function_exists( 'eluminate_recent_videos_data' ) ) {
	/**
	 * Fetches published videos data by descending date order.
	 *
	 * @param int $post_count number of posts to include. Default 20.
	 *
	 * @return array
	 */
	function eluminate_recent_videos_data( int $post_count = 20 ): array {
		$videos_data = wp_get_recent_posts(
			array(
				'numberposts' => $post_count,
				'orderby'     => 'post_date',
				'order'       => 'DESC',
				'post_type'   => 'videos',
				'post_status' => 'publish',
			)
		);

		if ( class_exists( 'Niztech_Youtube_Client' ) ) {
			foreach ( $videos_data as &$video ) {
				$video['video_data'] = function_exists( 'eluminate_standalone_video_content' )
					? eluminate_standalone_video_content( (int) $video['ID'] )
					: Niztech_Youtube_Client::video_content( $video['ID'] );
			}
		}

		return $videos_data;
	}
}

if ( ! function_exists( 'eluminate_featured_videos_data' ) ) {
	/**
	 * Fetches published videos data by descending date order.
	 *
	 * @param int $post_count number of posts to include. Default 20.
	 *
	 * @return array
	 */
	function eluminate_featured_videos_data( int $post_count = 3 ): array {
		$videos_data = wp_get_recent_posts(
			array(
				'numberposts' => $post_count,
				'orderby'     => 'post_date',
				'order'       => 'DESC',
				'post_type'   => 'videos',
				'post_status' => 'publish',
				'tax_query'   => array(
					array(
						'taxonomy' => 'tags',
						'field'    => 'slug',
						'terms'    => 'featured',
						'operator' => 'IN',
					),
				),
			)
		);

		if ( class_exists( 'Niztech_Youtube_Client' ) ) {
			foreach ( $videos_data as &$video ) {
				$video['video_data'] = function_exists( 'eluminate_standalone_video_content' )
					? eluminate_standalone_video_content( (int) $video['ID'] )
					: Niztech_Youtube_Client::video_content( $video['ID'] );
			}
		}

		return $videos_data;
	}
}

if ( ! function_exists( 'eluminate_videos_shows_page_grid_html' ) ) {
	/**
	 * Renders videos cards in the same grid markup as tags taxonomy archives (`.shows-page-videos`).
	 *
	 * Each `$series` row must include `ID` and `video_data` (from Niztech), same shape as
	 * {@see eluminate_featured_videos_data()} / {@see eluminate_recent_videos_data()}.
	 *
	 * @param array $videos_data Series rows from wp_get_recent_posts plus video_data.
	 * @param array $options Optional id, extra section class name(s).
	 *
	 * @return string HTML or empty string.
	 */
	function eluminate_videos_shows_page_grid_html( array $videos_data, array $options = array() ): string {
		if ( empty( $videos_data ) || ! class_exists( 'Niztech_Youtube_Client' ) ) {
			return '';
		}

		$classes   = array( 'shows-page-videos' );
		$extra     = isset( $options['class'] ) ? trim( (string) $options['class'] ) : '';
		if ( '' !== $extra ) {
			$classes[] = $extra;
		}
		$section_id = ! empty( $options['id'] ) ? ' id="' . esc_attr( (string) $options['id'] ) . '"' : '';

		ob_start();
		echo '<section' . $section_id . ' class="' . esc_attr( implode( ' ', array_filter( $classes ) ) ) . '">';
		foreach ( $videos_data as $series ) {
			$video_data = $series['video_data'] ?? array();
			if ( empty( $video_data ) ) {
				continue;
			}
			$post_id = isset( $series['ID'] ) ? (int) $series['ID'] : 0;
			if ( $post_id <= 0 ) {
				continue;
			}
			$first_video_data = $video_data[0];
			if ( ! is_object( $first_video_data ) ) {
				continue;
			}
			$number_videos = count( $video_data );
			$thumb_url     = function_exists( 'eluminate_standalone_get_video_thumbnail_url' )
				? eluminate_standalone_get_video_thumbnail_url( $first_video_data )
				: '';
			echo '<article class="video-series-entry">';
			get_template_part(
				'template-parts/videos',
				'card',
				array(
					'video'     => $first_video_data,
					'shortlink' => wp_get_shortlink( $post_id ),
					'thumb_url' => $thumb_url,
				)
			);
			if ( $number_videos > 0 ) {
				echo '<p class="video-series-count">';
				echo esc_html(
					sprintf(
						/* translators: %s is the number of videos in the series. */
						_n( '%s video in series', '%s videos in series', $number_videos, 'eluminate-standalone' ),
						number_format_i18n( $number_videos )
					)
				);
				echo '</p>';
			}
			echo '</article>';
		}
		echo '</section>';

		$html = (string) ob_get_clean();

		return ( strpos( $html, 'video-series-entry' ) !== false ) ? $html : '';
	}
}


if ( ! function_exists( 'eluminate_videos_html' ) ) {
	/**
	 * Generates the html to display.
	 *
	 * @param array $videos_data array of videos objects.
	 * @param array $options extra parameters: id, class, hide_others, title_position, show_desc
	 *
	 * @return string Html string.
	 */
	function eluminate_videos_html( array $videos_data, array $options = array() ): string {
		$show_desc = false;
		if ( isset( $options['show_desc'] ) && ( '' === $options['show_desc'] || 'true' === $options['show_desc'] ) ) {
			$show_desc = true;
		}

		$section_attribute_html[] = isset( $options['id'] ) ? 'id="' . $options['id'] . '"' : '';
		$section_attribute_html[] = isset( $options['class'] ) ? 'class="' . $options['class'] . '"' : '';
		$html                     = '<section ' . join( ' ', $section_attribute_html ) . '>';
		$title_position           = $options['title_position'] ?? 'hide';
		foreach ( $videos_data as $series ) {
			$videos                 = $series['video_data'] ?? array();
			$class                  = $options['class'] ?? null;
			$article_attribute_html = $class ? ' class="' . $class . '-series" ' : 'class="series"';
			$html                  .= '<article ' . $article_attribute_html . '>';

			if ( 'top' === $title_position && isset( $series['post_title'] ) ) {
				$html .= '<h2 class="title">' . $series['post_title'] . '</h2 >';
			}

			if ( sizeof( $videos ) > 0 ) {
				$img_url = $videos[0]->thumbnail_maxres_url ?? $videos[0]->thumbnail_standard_url ?? $videos[0]->thumbnail_default_url ?? null;
				if ( $img_url ) {
					$img_attribute_html            = array();
					$class ? $img_attribute_html[] = 'class="' . $class . '-series-image series-image"' : $img_attribute_html[] = 'class="series-img"';
					$img_attribute_html[]          = 'alt="' . $series['post_title'] . '"';
					$html                         .= '<a href="' . $series['guid'] . '"><img ' . implode(
						' ',
						$img_attribute_html
					) . ' src="' . $img_url . '"></a>';
				}
			}

			if ( 'bottom' === $title_position && isset( $series['post_title'] ) ) {
				$html .= '<h2 class="title">' . $series['post_title'] . '</h2 >';
			}

			if ( $show_desc && $series['video_data'][0]->description ) {
				$html .= '<p class="description">' . $series['video_data'][0]->description . '</p>';
			}

			if ( sizeof( $videos ) > 0 ) {
				$hide_others = boolval( $options['hide_others'] ?? false );
				if ( ! $hide_others ) {
					$list_attribute_html = $class ? ' class="' . $class . '-items items" ' : 'class="items"';
					$html               .= '<ol ' . $list_attribute_html . ' > ';
					foreach ( $videos as $video ) {
						if ( isset( $video->title ) ) {
							$html .= '<li class="' . $class . '-item item">';
							$html .= '<a class="item-link" href="' . $series['guid'] . '">';
							$html .= $video->title;
							$html .= '</a>';
							$html .= '</li>';
						}
					}
					$html .= '</ol >';
				}
			}
			$html .= '</article >';
		}

		return $html;
	}
}

add_shortcode(
	'eluminate-recent',
	function ( $attr ) {
		$a = shortcode_atts(
			array(
				'class'          => null,
				'hide_others'    => false,
				'id'             => null,
				'limit'          => 20,
				'show_desc'      => false,
				'title_position' => 'hide',
			),
			$attr
		);

		// Get the data.
		$data = eluminate_recent_videos_data( $a['limit'] );
		// Generate the html.
		return eluminate_videos_html(
			$data,
			array(
				'class'          => $a['class'],
				'hide_others'    => $a['hide_others'],
				'id'             => $a['id'],
				'show_desc'      => $a['show_desc'],
				'title_position' => $a['title_position'],
			)
		);
	}
);

if ( ! function_exists( 'eluminate_standalone_defer_heavy_shortcode_for_editor' ) ) {
	/**
	 * Whether to skip expensive shortcode output so REST/AJAX responses stay valid JSON/HTML.
	 *
	 * Not all installs define {@see REST_REQUEST} early; some use `?rest_route=` instead of `/wp-json/`.
	 *
	 * @return bool
	 */
	function eluminate_standalone_defer_heavy_shortcode_for_editor(): bool {
		if ( ! empty( $GLOBALS['eluminate_standalone_rest_dispatch'] ) ) {
			return true;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		// Plain-permalink REST: index.php?rest_route=/wp/v2/...
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only URL shape probe, not form processing.
		if ( isset( $_GET['rest_route'] ) ) {
			$rest_route_get = wp_unslash( $_GET['rest_route'] );
			if ( is_string( $rest_route_get ) && $rest_route_get !== '' ) {
				return true;
			}
		}
		if ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) {
			return true;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( $uri !== '' ) {
			if ( strpos( $uri, 'wp-json' ) !== false ) {
				return true;
			}
			if ( strpos( $uri, 'rest_route=' ) !== false || strpos( $uri, 'rest_route%3D' ) !== false ) {
				return true;
			}
		}
		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return true;
		}
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && function_exists( 'is_admin' ) && is_admin() ) {
			return true;
		}

		return false;
	}
}

add_shortcode(
	'eluminate-featured',
	function ( $attr ) {
		/*
		 * Building `content.rendered` for the block editor runs `do_shortcode()` via `the_content`.
		 * Full markup + Niztech queries here can fatal or emit notices that break JSON responses,
		 * which locks the editor ("item doesn't exist" / empty error). Skip heavy output during REST.
		 */
		if ( eluminate_standalone_defer_heavy_shortcode_for_editor() ) {
			return '<div class="eluminate-featured-placeholder" aria-hidden="true"></div>';
		}

		$a = shortcode_atts(
			array(
				'class' => null,
				'id'    => null,
				'limit' => 3,
			),
			$attr,
			'eluminate-featured'
		);

		$data = eluminate_featured_videos_data( (int) $a['limit'] );

		return eluminate_videos_shows_page_grid_html(
			$data,
			array(
				'class' => $a['class'],
				'id'    => $a['id'],
			)
		);
	}
);

add_action(
	'upload_mimes',
	function ( $file_types ): array {
		$new_filetypes        = array();
		$new_filetypes['svg'] = 'image/svg+xml';
		return array_merge( $file_types, $new_filetypes );
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_script(
			'ga-tag',
			'https://www.googletagmanager.com/gtag/js?id=G-GJWGXY822L',
			null,
			null,
			array(
				'in_footer' => false,
				'strategy'  => 'async',
			)
		);
		wp_enqueue_script(
			'ga-include',
			join( DIRECTORY_SEPARATOR, array( get_stylesheet_directory_uri(), 'assets', 'js', 'google-analytics.js' ) ),
			array( 'ga-tag' ),
			null,
			array( 'in_footer' => false )
		);
		wp_enqueue_script(
			'eluminate-video-thumbnail-light-border',
			get_theme_file_uri( 'assets/js/video-thumbnail-light-border.js' ),
			array(),
			THEME_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		if ( is_singular( 'videos' ) ) {
			$series_js = trailingslashit( get_template_directory() ) . 'assets/js/series-episodes.js';
			if ( is_readable( $series_js ) ) {
				wp_enqueue_script(
					'eluminate-series-episodes',
					get_template_directory_uri() . '/assets/js/series-episodes.js',
					array(),
					(string) filemtime( $series_js ),
					array(
						'in_footer' => true,
						'strategy'  => 'defer',
					)
				);
			}
		}
	}
);

add_action(
	'wp_head',
	function () {
		print( '<meta name="google-site-verification" content="Ipj67ZzaLTCcAWzwFb_8A1GpibW34MNQxCnrJxWve6E" />' );
	}
);

/**
 * Load the tag templates from a subfolder.
 */
add_filter(
	'tag_template',
	function ( $template ) {

		$tag = get_queried_object();

		if ( ! $tag || ! isset( $tag->slug ) ) {
			return $template;
		}

		$tags_with_special_template = array( 'popular', 'archive' );

		if ( in_array( $tag->slug, $tags_with_special_template, true ) ) {
			$template_path = join( DIRECTORY_SEPARATOR, array( 'templates', 'tag', "{$tag->slug}.php" ) );

			// Change subfolder name...
			$alternative_template = locate_template( $template_path );

			// If we do have "tag-{$tag->slug}.php" in a subfolder, load it...
			if ( $alternative_template ) {
				return $alternative_template;
			}
		}

		// If we don't have a "tag-{$tag->slug}.php", load default templates from hierarchy...
		return $template;
	}
);


if ( ! function_exists( 'eluminate_standalone_ensure_tags_nav_menu_exists' ) ) {
	/**
	 * Ensures the nav menu used for By Topic term sync exists (named "By Topic", empty until sync runs).
	 * If only the legacy "List In" menu exists, it is renamed to {@see ELUMINATE_BY_TOPIC_SYNC_MENU_NAME}.
	 *
	 * @return int Menu term_id or 0 on failure.
	 */
	function eluminate_standalone_ensure_tags_nav_menu_exists(): int {
		$name = ELUMINATE_BY_TOPIC_SYNC_MENU_NAME;
		$menu = wp_get_nav_menu_object( $name );
		if ( $menu && ! is_wp_error( $menu ) ) {
			return (int) $menu->term_id;
		}

		$legacy = wp_get_nav_menu_object( 'List In' );
		if ( $legacy && ! is_wp_error( $legacy ) ) {
			$legacy_id = (int) $legacy->term_id;
			$updated   = wp_update_term( $legacy_id, 'nav_menu', array( 'name' => $name ) );
			if ( ! is_wp_error( $updated ) && isset( $updated['term_id'] ) ) {
				return (int) $updated['term_id'];
			}
			return $legacy_id;
		}

		$menu_id = wp_create_nav_menu( $name );
		if ( is_wp_error( $menu_id ) ) {
			return 0;
		}

		return (int) $menu_id;
	}
}

if ( ! function_exists( 'eluminate_standalone_menu_tags_init' ) ) {
	/**
	 * Back-compat wrapper: ensures the By Topic sync nav menu object exists.
	 *
	 * @return void
	 */
	function eluminate_standalone_menu_tags_init(): void {
		eluminate_standalone_ensure_tags_nav_menu_exists();
	}
}

/**
 * Flush rewrite rules on theme activation to ensure custom post type permalinks work.
 */
add_action(
	'after_switch_theme',
	function () {
		flush_rewrite_rules();
		update_option( THEME_KEY . '_rewrite_build', THEME_VERSION );
	}
);

/**
 * Persist rewrite rules after CPT/taxonomy registration (once per THEME_VERSION bump).
 * Fixes 404s when the theme is updated without switching themes or saving permalinks.
 */
add_action(
	'init',
	function (): void {
		$saved = (int) get_option( THEME_KEY . '_rewrite_build', 0 );
		if ( $saved >= THEME_VERSION ) {
			return;
		}
		flush_rewrite_rules( true );
		update_option( THEME_KEY . '_rewrite_build', THEME_VERSION );
	},
	99
);

if ( ! function_exists( 'eluminate_standalone_menu_init' ) ) {
	/**
	 * Creates nav menus.
	 *
	 * @return void
	 */
	function eluminate_standalone_menu_init(): void {
		register_nav_menus(
			array(
				'orbit-main'       => __( 'Orbit Ring', 'eluminate-standalone' ),
				'about-us'         => __( 'About us', 'eluminate-standalone' ),
				'tags'             => __( 'Tags', 'eluminate-standalone' ),
				'shows'            => __( 'Shows', 'eluminate-standalone' ),
				'blog'             => __( 'Blog', 'eluminate-standalone' ),
				'womens-health'    => __( "Women's health", 'eluminate-standalone' ),
				'womens-finances'  => __( "Women's finances", 'eluminate-standalone' ),
				'social'           => __( 'Social Menu', 'eluminate-standalone' ),
				'footer'           => __( 'Footer Menu', 'eluminate-standalone' ),
				'by-topic-order'   => __( 'Tags', 'eluminate-standalone' ),
			)
		);
	}
}

if ( ! function_exists( 'eluminate_standalone_tags_topic_nav' ) ) {
	/**
	 * Prints “By Topic” links for the Shows panel (markup matches wp_nav_menu output for styling).
	 *
	 * Built from taxonomy permalinks instead of the synced By Topic nav menu so hrefs are never stale
	 * (custom menu items can keep wrong URLs after URL or permalink changes).
	 *
	 * @return void
	 */
	function eluminate_standalone_tags_topic_nav(): void {
		$terms = get_terms(
			array(
				'taxonomy'   => 'tags',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		echo '<ul class="menu-list">';

		foreach ( $terms as $term ) {
			if ( 'popular' === $term->slug ) {
				continue;
			}

			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}

			printf(
				'<li class="menu-item"><a href="%s">%s</a></li>' . "\n",
				esc_url( $link ),
				esc_html( $term->name )
			);
		}

		echo '</ul>';
	}
}

if ( ! function_exists( 'eluminate_standalone_exclude_front_page_from_orbit_pages' ) ) {
	/**
	 * Drops the static front page from orbit top-level items (home is the menu home icon).
	 *
	 * @param WP_Post[] $pages Pages from get_pages().
	 * @return WP_Post[]
	 */
	function eluminate_standalone_exclude_front_page_from_orbit_pages( array $pages ): array {
		$front_id = (int) get_option( 'page_on_front' );
		if ( $front_id <= 0 || empty( $pages ) ) {
			return $pages;
		}

		return array_values(
			array_filter(
				$pages,
				static function ( $p ) use ( $front_id ) {
					return $p instanceof WP_Post && (int) $p->ID !== $front_id;
				}
			)
		);
	}
}

if ( ! function_exists( 'eluminate_standalone_get_main_page_children' ) ) {
	/**
	 * Returns first-level pages used as top-level orbit menu entries.
	 *
	 * Primary source is children of the page at path "main-page".
	 * Falls back to public top-level pages when that parent page is absent.
	 *
	 * @return WP_Post[]
	 */
	function eluminate_standalone_get_main_page_children(): array {
		$main_page   = get_page_by_path( 'main-page' );
		$main_page_id = $main_page instanceof WP_Post ? (int) $main_page->ID : 0;

		$args = array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'sort_column'    => 'menu_order,post_title',
			'sort_order'     => 'ASC',
			'hierarchical'   => false,
			'parent'         => $main_page_id,
		);

		$pages = get_pages( $args );
		if ( ! empty( $pages ) || $main_page_id > 0 ) {
			return eluminate_standalone_exclude_front_page_from_orbit_pages( $pages );
		}

		$fallback = get_pages(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'sort_column'  => 'menu_order,post_title',
				'sort_order'   => 'ASC',
				'hierarchical' => false,
				'parent'       => 0,
				'exclude'      => array_filter(
					array(
						(int) get_option( 'page_on_front' ),
						(int) get_option( 'page_for_posts' ),
					)
				),
			)
		);

		return eluminate_standalone_exclude_front_page_from_orbit_pages( $fallback );
	}
}

if ( ! function_exists( 'eluminate_standalone_build_page_menu_branch' ) ) {
	/**
	 * Recursively builds a page-menu branch.
	 *
	 * @param WP_Post[] $pages Parent-level pages.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function eluminate_standalone_build_page_menu_branch( array $pages ): array {
		$branch = array();
		foreach ( $pages as $page ) {
			if ( ! $page instanceof WP_Post ) {
				continue;
			}
			$children = get_pages(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'sort_column'  => 'menu_order,post_title',
					'sort_order'   => 'ASC',
					'hierarchical' => false,
					'parent'       => (int) $page->ID,
				)
			);
			$branch[] = array(
				'id'       => (int) $page->ID,
				'slug'     => (string) $page->post_name,
				/*
				 * get_the_title() runs wptexturize via the_title — apostrophes become &#8217; etc.
				 * Orbit labels use JS textContent, which does not decode entities; decode for JSON/JS.
				 */
				'title'    => html_entity_decode( wp_strip_all_tags( get_the_title( $page ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'url'      => get_permalink( $page ),
				'children' => eluminate_standalone_build_page_menu_branch( $children ),
			);
		}

		return $branch;
	}
}

if ( ! function_exists( 'eluminate_standalone_get_page_menu_tree' ) ) {
	/**
	 * Returns recursive menu tree derived from parent/child page relationships.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function eluminate_standalone_get_page_menu_tree(): array {
		$top_level_pages = eluminate_standalone_get_main_page_children();
		return eluminate_standalone_build_page_menu_branch( $top_level_pages );
	}
}

if ( ! function_exists( 'eluminate_standalone_nav_menu_item_orbit_slug' ) ) {
	/**
	 * Slug for orbit JS (e.g. parentSlug === "by-topic" enables tags terms from the By Topic checkbox).
	 * Prefer the linked content slug for post-type items; detect "by-topic" as final URL path segment for custom links.
	 *
	 * @param WP_Post $item Nav menu item (post_type nav_menu_item).
	 */
	function eluminate_standalone_nav_menu_item_orbit_slug( WP_Post $item ): string {
		$type      = isset( $item->type ) ? (string) $item->type : '';
		$object_id = isset( $item->object_id ) ? (int) $item->object_id : 0;
		$url       = isset( $item->url ) ? (string) $item->url : '';

		if ( 'post_type' === $type && $object_id > 0 ) {
			$resolved = get_post( $object_id );
			if ( $resolved instanceof WP_Post && $resolved->post_name !== '' ) {
				return (string) $resolved->post_name;
			}
		}

		if ( '' !== $url ) {
			$path = (string) wp_parse_url( $url, PHP_URL_PATH );
			if ( '' !== $path && '/' !== $path ) {
				$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
				if ( array() !== $segments ) {
					$last = end( $segments );
					if ( is_string( $last ) && strtolower( $last ) === 'by-topic' ) {
						return 'by-topic';
					}
				}
			}
		}

		$slug = (string) $item->post_name;
		if ( '' !== $slug ) {
			return $slug;
		}
		$from_title = sanitize_title( (string) $item->title );
		if ( '' !== $from_title ) {
			return $from_title;
		}
		return 'item-' . (string) (int) $item->ID;
	}
}

if ( ! function_exists( 'eluminate_standalone_nav_menu_item_tags_term_id' ) ) {
	/**
	 * Resolves a nav menu item to a tags term ID (taxonomy item, or custom URL under the tags rewrite base).
	 *
	 * @param WP_Post $item Nav menu item.
	 * @return int|null Term ID or null if not a tags link.
	 */
	function eluminate_standalone_nav_menu_item_tags_term_id( WP_Post $item ): ?int {
		$type      = isset( $item->type ) ? (string) $item->type : '';
		$object    = isset( $item->object ) ? (string) $item->object : '';
		$object_id = isset( $item->object_id ) ? (int) $item->object_id : 0;

		if ( 'taxonomy' === $type && 'tags' === $object && $object_id > 0 ) {
			return $object_id;
		}

		$url = isset( $item->url ) ? (string) $item->url : '';
		if ( '' === $url ) {
			return null;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( '' === $path || '/' === $path ) {
			return null;
		}

		$tax = get_taxonomy( 'tags' );
		$base = ( is_object( $tax ) && ! empty( $tax->rewrite['slug'] ) )
			? (string) $tax->rewrite['slug']
			: 'tags';

		$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
		$slug     = null;
		foreach ( $segments as $i => $seg ) {
			if ( $seg === $base && isset( $segments[ $i + 1 ] ) ) {
				$slug = (string) $segments[ $i + 1 ];
				break;
			}
		}

		if ( null === $slug || '' === $slug ) {
			return null;
		}

		$term = get_term_by( 'slug', sanitize_title( $slug ), 'tags' );
		return ( $term instanceof WP_Term ) ? (int) $term->term_id : null;
	}
}

if ( ! function_exists( 'eluminate_standalone_nav_menu_items_to_orbit_branch' ) ) {
	/**
	 * Converts one level of nav_menu_item posts into the orbit tree node shape (matches page tree keys).
	 *
	 * @param array<int, WP_Post>   $items          Same-level menu items.
	 * @param array<int, WP_Post[]> $children_map   menu_item_parent ID => ordered child posts.
	 * @return array<int, array<string, mixed>>
	 */
	function eluminate_standalone_nav_menu_items_to_orbit_branch( array $items, array $children_map ): array {
		$branch = array();
		foreach ( $items as $item ) {
			if ( ! $item instanceof WP_Post ) {
				continue;
			}
			$item_id = (int) $item->ID;
			$kids    = isset( $children_map[ $item_id ] ) && is_array( $children_map[ $item_id ] )
				? $children_map[ $item_id ]
				: array();
			$url  = isset( $item->url ) ? (string) $item->url : '';
			$slug = eluminate_standalone_nav_menu_item_orbit_slug( $item );
			$branch[] = array(
				'id'       => $item_id,
				'slug'     => $slug,
				'title'    => html_entity_decode( wp_strip_all_tags( (string) $item->title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'url'      => $url,
				'children' => eluminate_standalone_nav_menu_items_to_orbit_branch( $kids, $children_map ),
				'source'   => 'nav_menu',
			);
		}
		return $branch;
	}
}

if ( ! function_exists( 'eluminate_standalone_orbit_branch_has_child_for_page' ) ) {
	/**
	 * Whether an orbit branch already contains a node for this page (nav item or page-tree node).
	 *
	 * @param array<int, array<string, mixed>> $children    Child orbit nodes.
	 * @param WP_Post                           $page        Page post.
	 * @param array<int, WP_Post>               $items_by_id Menu item ID => nav_menu_item post.
	 *
	 * @return bool
	 */
	function eluminate_standalone_orbit_branch_has_child_for_page( array $children, WP_Post $page, array $items_by_id ): bool {
		$want_url = get_permalink( $page );
		$want_id  = (int) $page->ID;
		foreach ( $children as $ch ) {
			if ( ! is_array( $ch ) ) {
				continue;
			}
			$cid = isset( $ch['id'] ) ? (int) $ch['id'] : 0;
			if ( $cid === $want_id ) {
				return true;
			}
			$ch_url = isset( $ch['url'] ) ? (string) $ch['url'] : '';
			if ( $ch_url !== '' && is_string( $want_url ) && $ch_url === $want_url ) {
				return true;
			}
			$mi = $items_by_id[ $cid ] ?? null;
			if ( $mi instanceof WP_Post && 'post_type' === $mi->type && 'page' === $mi->object && (int) $mi->object_id === $want_id ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'eluminate_standalone_merge_page_children_into_nav_orbit_branch' ) ) {
	/**
	 * Adds published child pages under each orbit node that links to a page, when those children are missing
	 * from the Orbit Ring nav menu (menu hierarchy alone does not reflect Page Attributes).
	 *
	 * @param array<int, array<string, mixed>> $branch      Orbit branch nodes.
	 * @param array<int, WP_Post>               $items_by_id Menu item ID => nav_menu_item post (same menu).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function eluminate_standalone_merge_page_children_into_nav_orbit_branch( array $branch, array $items_by_id ): array {
		$out = array();
		foreach ( $branch as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();
			$mid      = isset( $node['id'] ) ? (int) $node['id'] : 0;
			$item     = $items_by_id[ $mid ] ?? null;

			if ( $item instanceof WP_Post && 'post_type' === $item->type && 'page' === $item->object ) {
				$page_id = (int) $item->object_id;
				if ( $page_id > 0 ) {
					$orphans = get_pages(
						array(
							'post_type'    => 'page',
							'post_status'  => 'publish',
							'sort_column'  => 'menu_order,post_title',
							'sort_order'   => 'ASC',
							'hierarchical' => false,
							'parent'       => $page_id,
						)
					);
					foreach ( $orphans as $page ) {
						if ( ! $page instanceof WP_Post ) {
							continue;
						}
						if ( eluminate_standalone_orbit_branch_has_child_for_page( $children, $page, $items_by_id ) ) {
							continue;
						}
						$added = eluminate_standalone_build_page_menu_branch( array( $page ) );
						if ( ! empty( $added[0] ) && is_array( $added[0] ) ) {
							$added[0]['source'] = 'page_hierarchy';
							$children[]         = $added[0];
						}
					}
				}
			}

			$children         = eluminate_standalone_merge_page_children_into_nav_orbit_branch( $children, $items_by_id );
			$node['children'] = $children;
			$out[]            = $node;
		}
		return $out;
	}
}

if ( ! function_exists( 'eluminate_standalone_get_nav_menu_orbit_tree' ) ) {
	/**
	 * Orbit tree from the menu assigned to the "Orbit Ring" location (Appearance → Menus → Manage Locations).
	 *
	 * Published pages whose parent matches a menu-linked page are merged in when absent from the menu
	 * so Page Attributes stay in sync with the orbit submenu.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function eluminate_standalone_get_nav_menu_orbit_tree(): array {
		$locations = get_nav_menu_locations();
		if ( empty( $locations['orbit-main'] ) ) {
			return array();
		}
		$menu_id = (int) $locations['orbit-main'];
		if ( $menu_id <= 0 ) {
			return array();
		}
		$menu_object = wp_get_nav_menu_object( $menu_id );
		if ( ! $menu_object instanceof WP_Term ) {
			return array();
		}
		$items = wp_get_nav_menu_items(
			$menu_id,
			array(
				'post_status' => 'publish',
				'orderby'     => 'menu_order',
				'order'       => 'ASC',
			)
		);
		if ( empty( $items ) || ! is_array( $items ) ) {
			return array();
		}
		$items_by_id = array();
		foreach ( $items as $item ) {
			if ( $item instanceof WP_Post && 'nav_menu_item' === $item->post_type ) {
				$items_by_id[ (int) $item->ID ] = $item;
			}
		}
		$children_map = array();
		foreach ( $items as $item ) {
			if ( ! $item instanceof WP_Post || 'nav_menu_item' !== $item->post_type ) {
				continue;
			}
			$parent_id = (int) $item->menu_item_parent;
			if ( ! isset( $children_map[ $parent_id ] ) ) {
				$children_map[ $parent_id ] = array();
			}
			$children_map[ $parent_id ][] = $item;
		}
		if ( empty( $children_map[0] ) || ! is_array( $children_map[0] ) ) {
			return array();
		}
		foreach ( $children_map as &$kids ) {
			usort(
				$kids,
				static function ( $a, $b ) {
					$ao = $a instanceof WP_Post ? (int) $a->menu_order : 0;
					$bo = $b instanceof WP_Post ? (int) $b->menu_order : 0;
					return $ao <=> $bo;
				}
			);
		}
		unset( $kids );
		$branch = eluminate_standalone_nav_menu_items_to_orbit_branch( $children_map[0], $children_map );
		return eluminate_standalone_merge_page_children_into_nav_orbit_branch( $branch, $items_by_id );
	}
}

if ( ! function_exists( 'eluminate_standalone_get_orbit_menu_tree' ) ) {
	/**
	 * Main orbit data: nav menu location when set and non-empty, else page tree under main-page.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function eluminate_standalone_get_orbit_menu_tree(): array {
		$nav_tree = eluminate_standalone_get_nav_menu_orbit_tree();
		if ( array() !== $nav_tree ) {
			return $nav_tree;
		}
		return eluminate_standalone_get_page_menu_tree();
	}
}

if ( ! function_exists( 'eluminate_standalone_get_by_topic_ordered_terms' ) ) {
	/**
	 * tags terms with "List in By Topic menu" checked, ordered for display.
	 *
	 * Order follows the **By Topic** nav menu (Appearance → Menus, menu name matches
	 * {@see ELUMINATE_BY_TOPIC_SYNC_MENU_NAME}): top-level items in menu order that map to checked
	 * terms. Any checked term missing from that menu is listed after, sorted by name. If nothing is
	 * ordered from that menu, falls back to the "Tags" theme location if assigned, then name.
	 *
	 * @return WP_Term[]
	 */
	function eluminate_standalone_get_by_topic_ordered_terms(): array {
		$toggled_terms = get_terms(
			array(
				'taxonomy'   => 'tags',
				'hide_empty' => false,
				'meta_query' => array(
					array(
						'key'   => '_eluminate_by_topic_menu',
						'value' => '1',
					),
				),
			)
		);
		if ( is_wp_error( $toggled_terms ) || empty( $toggled_terms ) ) {
			return array();
		}

		$by_id = array();
		foreach ( $toggled_terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$by_id[ (int) $term->term_id ] = $term;
			}
		}
		if ( empty( $by_id ) ) {
			return array();
		}

		$ordered_ids = array();

		$topic_menu = wp_get_nav_menu_object( ELUMINATE_BY_TOPIC_SYNC_MENU_NAME );
		$topic_id   = ( $topic_menu && ! is_wp_error( $topic_menu ) ) ? (int) $topic_menu->term_id : 0;
		if ( $topic_id > 0 ) {
			$list_items = wp_get_nav_menu_items(
				$topic_id,
				array(
					'orderby' => 'menu_order',
					'order'   => 'ASC',
				)
			);
			if ( is_array( $list_items ) ) {
				foreach ( $list_items as $item ) {
					if ( ! $item instanceof WP_Post ) {
						continue;
					}
					if ( 0 !== (int) $item->menu_item_parent ) {
						continue;
					}
					$tid = eluminate_standalone_nav_menu_item_tags_term_id( $item );
					if ( null === $tid || ! isset( $by_id[ $tid ] ) ) {
						continue;
					}
					if ( in_array( $tid, $ordered_ids, true ) ) {
						continue;
					}
					$ordered_ids[] = $tid;
				}
			}
		}

		if ( empty( $ordered_ids ) ) {
			$locations = get_nav_menu_locations();
			$menu_id   = isset( $locations['by-topic-order'] ) ? (int) $locations['by-topic-order'] : 0;
			if ( $menu_id > 0 ) {
				$menu_items = wp_get_nav_menu_items(
					$menu_id,
					array(
						'orderby' => 'menu_order',
						'order'   => 'ASC',
					)
				);
				if ( is_array( $menu_items ) ) {
					foreach ( $menu_items as $item ) {
						if ( ! $item instanceof WP_Post ) {
							continue;
						}
						if ( 0 !== (int) $item->menu_item_parent ) {
							continue;
						}
						$tid = eluminate_standalone_nav_menu_item_tags_term_id( $item );
						if ( null === $tid || ! isset( $by_id[ $tid ] ) ) {
							continue;
						}
						if ( in_array( $tid, $ordered_ids, true ) ) {
							continue;
						}
						$ordered_ids[] = $tid;
					}
				}
			}
		}

		$remaining_ids = array_diff( array_keys( $by_id ), $ordered_ids );
		$remaining     = array();
		foreach ( $remaining_ids as $rid ) {
			$remaining[] = $by_id[ $rid ];
		}
		usort(
			$remaining,
			static function ( $a, $b ) {
				return strcasecmp( (string) $a->name, (string) $b->name );
			}
		);

		$final_terms = array();
		foreach ( $ordered_ids as $tid ) {
			$final_terms[] = $by_id[ $tid ];
		}
		foreach ( $remaining as $term ) {
			$final_terms[] = $term;
		}

		return $final_terms;
	}
}

if ( ! function_exists( 'eluminate_standalone_get_by_topic_menu_terms' ) ) {
	/**
	 * Returns WP-admin toggled tags terms for the "By Topic" submenu.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function eluminate_standalone_get_by_topic_menu_terms(): array {
		$final_terms = eluminate_standalone_get_by_topic_ordered_terms();
		$items       = array();
		foreach ( $final_terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$items[] = array(
				'id'       => (int) $term->term_id,
				'slug'     => (string) $term->slug,
				'title'    => (string) $term->name,
				'url'      => (string) $link,
				'children' => array(),
				'source'   => 'term',
			);
		}

		return $items;
	}
}

if ( ! function_exists( 'eluminate_standalone_delete_nav_menu_item_safe' ) ) {
	/**
	 * @param int $menu_item_id Nav menu item post ID.
	 */
	function eluminate_standalone_delete_nav_menu_item_safe( int $menu_item_id ): void {
		if ( $menu_item_id <= 0 ) {
			return;
		}
		if ( ! function_exists( 'wp_delete_nav_menu_item' ) ) {
			require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
		}
		if ( function_exists( 'wp_delete_nav_menu_item' ) ) {
			wp_delete_nav_menu_item( $menu_item_id );
		} else {
			wp_delete_post( $menu_item_id, true );
		}
	}
}

if ( ! function_exists( 'eluminate_standalone_sync_tags_nav_menu' ) ) {
	/**
	 * Keeps the "By Topic" nav menu aligned with By Topic checkbox state without resetting drag order.
	 *
	 * Adds taxonomy links for newly checked terms (appended). Removes entries for unchecked or
	 * deleted terms, non–tags top-level links, children, and duplicates. After changes, reapplies
	 * the previous top-level order (WordPress often renumbers menu_order when a new item is inserted).
	 * Front-end order follows this menu (see eluminate_standalone_get_by_topic_ordered_terms()).
	 *
	 * @return void
	 */
	function eluminate_standalone_sync_tags_nav_menu(): void {
		static $syncing = false;
		if ( $syncing ) {
			return;
		}
		$syncing = true;

		try {
			if ( ! function_exists( 'wp_update_nav_menu_item' ) || ! function_exists( 'wp_delete_nav_menu_item' ) ) {
				require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
			}

			$menu_id = eluminate_standalone_ensure_tags_nav_menu_exists();
			if ( $menu_id <= 0 ) {
				return;
			}

			$checked_terms = get_terms(
				array(
					'taxonomy'   => 'tags',
					'hide_empty' => false,
					'meta_query' => array(
						array(
							'key'   => '_eluminate_by_topic_menu',
							'value' => '1',
						),
					),
				)
			);
			if ( is_wp_error( $checked_terms ) ) {
				$checked_terms = array();
			}

			$checked_ids = array();
			foreach ( $checked_terms as $t ) {
				if ( $t instanceof WP_Term ) {
					$checked_ids[] = (int) $t->term_id;
				}
			}
			$checked_set = array_fill_keys( $checked_ids, true );

			// Before any edits, record top-level order of checked tags rows. Core often renumbers
			// menu_order when inserting a new item; we restore this sequence at the end.
			$snapshot_order = array();
			$snapshot_items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) );
			if ( is_array( $snapshot_items ) ) {
				usort(
					$snapshot_items,
					static function ( $a, $b ): int {
						if ( ! $a instanceof WP_Post || ! $b instanceof WP_Post ) {
							return 0;
						}
						return ( (int) $a->menu_order ) <=> ( (int) $b->menu_order );
					}
				);
				foreach ( $snapshot_items as $item ) {
					if ( ! $item instanceof WP_Post || 0 !== (int) $item->menu_item_parent ) {
						continue;
					}
					$tid = eluminate_standalone_nav_menu_item_tags_term_id( $item );
					if ( null === $tid || ! isset( $checked_set[ $tid ] ) ) {
						continue;
					}
					if ( in_array( $tid, $snapshot_order, true ) ) {
						continue;
					}
					$snapshot_order[] = $tid;
				}
			}

			$items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) );
			if ( ! is_array( $items ) ) {
				$items = array();
			}

			if ( array() !== $items ) {
				$by_post_id = array();
				foreach ( $items as $item ) {
					if ( $item instanceof WP_Post ) {
						$by_post_id[ (int) $item->ID ] = $item;
					}
				}
				$depth_fn = static function ( WP_Post $item ) use ( $by_post_id ): int {
					$d = 0;
					$p = (int) $item->menu_item_parent;
					while ( $p > 0 && isset( $by_post_id[ $p ] ) ) {
						++$d;
						$p = (int) $by_post_id[ $p ]->menu_item_parent;
					}
					return $d;
				};
				usort(
					$items,
					static function ( $a, $b ) use ( $depth_fn ): int {
						if ( ! $a instanceof WP_Post || ! $b instanceof WP_Post ) {
							return 0;
						}
						return $depth_fn( $b ) <=> $depth_fn( $a );
					}
				);
				foreach ( $items as $item ) {
					if ( $item instanceof WP_Post && 0 !== (int) $item->menu_item_parent ) {
						eluminate_standalone_delete_nav_menu_item_safe( (int) $item->ID );
					}
				}
			}

			$items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) );
			if ( ! is_array( $items ) ) {
				$items = array();
			}

			usort(
				$items,
				static function ( $a, $b ): int {
					if ( ! $a instanceof WP_Post || ! $b instanceof WP_Post ) {
						return 0;
					}
					return ( (int) $a->menu_order ) <=> ( (int) $b->menu_order );
				}
			);

			$kept_tid = array();
			foreach ( $items as $item ) {
				if ( ! $item instanceof WP_Post || 0 !== (int) $item->menu_item_parent ) {
					continue;
				}
				$tid = eluminate_standalone_nav_menu_item_tags_term_id( $item );
				if ( null === $tid ) {
					eluminate_standalone_delete_nav_menu_item_safe( (int) $item->ID );
					continue;
				}
				if ( ! isset( $checked_set[ $tid ] ) ) {
					eluminate_standalone_delete_nav_menu_item_safe( (int) $item->ID );
					continue;
				}
				if ( isset( $kept_tid[ $tid ] ) ) {
					eluminate_standalone_delete_nav_menu_item_safe( (int) $item->ID );
					continue;
				}
				$kept_tid[ $tid ] = (int) $item->ID;
			}

			$items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) );
			if ( ! is_array( $items ) ) {
				$items = array();
			}

			$max_order = 0;
			foreach ( $items as $item ) {
				if ( $item instanceof WP_Post && 0 === (int) $item->menu_item_parent ) {
					$max_order = max( $max_order, (int) $item->menu_order );
				}
			}

			$missing_ids = array_diff( $checked_ids, array_keys( $kept_tid ) );
			$missing     = array();
			foreach ( $missing_ids as $mid ) {
				$t = get_term( (int) $mid, 'tags' );
				if ( $t instanceof WP_Term ) {
					$missing[] = $t;
				}
			}
			usort(
				$missing,
				static function ( $a, $b ) {
					return strcasecmp( (string) $a->name, (string) $b->name );
				}
			);
			foreach ( $missing as $term ) {
				++$max_order;
				$r = wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'      => $term->name,
						'menu-item-object'     => 'tags',
						'menu-item-object-id'  => (int) $term->term_id,
						'menu-item-type'       => 'taxonomy',
						'menu-item-status'     => 'publish',
						'menu-item-position'   => $max_order,
					)
				);
				if ( is_wp_error( $r ) ) {
					break;
				}
			}

			$tid_to_db = array();
			$items_end = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) );
			if ( is_array( $items_end ) ) {
				foreach ( $items_end as $item ) {
					if ( ! $item instanceof WP_Post || 0 !== (int) $item->menu_item_parent ) {
						continue;
					}
					$tid = eluminate_standalone_nav_menu_item_tags_term_id( $item );
					if ( null === $tid || ! isset( $checked_set[ $tid ] ) ) {
						continue;
					}
					if ( ! isset( $tid_to_db[ $tid ] ) ) {
						$tid_to_db[ $tid ] = (int) $item->ID;
					}
				}
			}

			$final_order = array();
			foreach ( $snapshot_order as $tid ) {
				if ( isset( $checked_set[ $tid ] ) && ! in_array( $tid, $final_order, true ) ) {
					$final_order[] = $tid;
				}
			}
			$append_ids = array_diff( $checked_ids, $final_order );
			$append_terms = array();
			foreach ( $append_ids as $aid ) {
				$at = get_term( (int) $aid, 'tags' );
				if ( $at instanceof WP_Term ) {
					$append_terms[] = $at;
				}
			}
			usort(
				$append_terms,
				static function ( $a, $b ) {
					return strcasecmp( (string) $a->name, (string) $b->name );
				}
			);
			foreach ( $append_terms as $at ) {
				$final_order[] = (int) $at->term_id;
			}

			foreach ( $final_order as $index => $tid ) {
				if ( ! isset( $tid_to_db[ $tid ] ) ) {
					continue;
				}
				$term = get_term( $tid, 'tags' );
				if ( ! $term instanceof WP_Term ) {
					continue;
				}
				wp_update_nav_menu_item(
					$menu_id,
					$tid_to_db[ $tid ],
					array(
						'menu-item-title'      => $term->name,
						'menu-item-object'     => 'tags',
						'menu-item-object-id'  => $tid,
						'menu-item-type'       => 'taxonomy',
						'menu-item-status'     => 'publish',
						'menu-item-position'   => $index + 1,
					)
				);
			}
		} finally {
			$syncing = false;
		}
	}
}

if ( ! function_exists( 'eluminate_standalone_maybe_migrate_tags_nav_menu_sync' ) ) {
	/**
	 * One-time sync of the By Topic menu after this behavior ships (admin only).
	 *
	 * @return void
	 */
	function eluminate_standalone_maybe_migrate_tags_nav_menu_sync(): void {
		if ( '1' === get_option( 'eluminate_tags_menu_by_topic_sync_v1', '' ) ) {
			return;
		}
		eluminate_standalone_sync_tags_nav_menu();
		update_option( 'eluminate_tags_menu_by_topic_sync_v1', '1', false );
	}
}
add_action( 'admin_init', 'eluminate_standalone_maybe_migrate_tags_nav_menu_sync', 5 );
add_action( 'created_tags', 'eluminate_standalone_sync_tags_nav_menu', 20 );
add_action( 'edited_tags', 'eluminate_standalone_sync_tags_nav_menu', 20 );
add_action(
	'delete_term',
	static function ( $term_id, $tt_id, $taxonomy ): void {
		if ( 'tags' !== $taxonomy ) {
			return;
		}
		eluminate_standalone_sync_tags_nav_menu();
	},
	20,
	3
);

/**
 * tags hooks do not run when only the mirror nav menu changes; repopulate after By Topic menu save / removal.
 */
add_action(
	'wp_update_nav_menu',
	static function ( $menu_id ): void {
		$menu = wp_get_nav_menu_object( (int) $menu_id );
		if ( ! $menu instanceof WP_Term ) {
			return;
		}
		if ( (string) $menu->name !== ELUMINATE_BY_TOPIC_SYNC_MENU_NAME ) {
			return;
		}
		eluminate_standalone_sync_tags_nav_menu();
	},
	20,
	1
);
add_action(
	'before_delete_term',
	static function ( $term_id, $taxonomy ): void {
		if ( 'nav_menu' !== $taxonomy ) {
			return;
		}
		$t = get_term( (int) $term_id, 'nav_menu' );
		if ( ! $t instanceof WP_Term ) {
			return;
		}
		if ( (string) $t->name !== ELUMINATE_BY_TOPIC_SYNC_MENU_NAME ) {
			return;
		}
		add_action(
			'shutdown',
			static function (): void {
				eluminate_standalone_sync_tags_nav_menu();
			},
			1
		);
	},
	20,
	2
);

if ( ! function_exists( 'eluminate_standalone_tags_add_by_topic_field' ) ) {
	/**
	 * Renders "List in By Topic menu" checkbox on add term form.
	 *
	 * @return void
	 */
	function eluminate_standalone_tags_add_by_topic_field(): void {
		?>
		<div class="form-field term-eluminate-by-topic-wrap">
			<label for="eluminate-by-topic-menu"><?php echo esc_html__( 'By Topic menu', 'eluminate-standalone' ); ?></label>
			<label>
				<input type="checkbox" id="eluminate-by-topic-menu" name="eluminate_by_topic_menu" value="1" />
				<?php echo esc_html__( 'List in By Topic menu', 'eluminate-standalone' ); ?>
			</label>
			<p class="description"><?php echo esc_html__( 'Optionally, manage "By Topic" menu order in Appearance > Menus > By Topic. Otherwise, it is appended to current menu order.', 'eluminate-standalone' ); ?></p>
		</div>
		<?php
	}
}
add_action( 'tags_add_form_fields', 'eluminate_standalone_tags_add_by_topic_field' );

if ( ! function_exists( 'eluminate_standalone_tags_edit_by_topic_field' ) ) {
	/**
	 * Renders "List in By Topic menu" checkbox on edit term form.
	 *
	 * @param WP_Term $term Current term.
	 *
	 * @return void
	 */
	function eluminate_standalone_tags_edit_by_topic_field( WP_Term $term ): void {
		$enabled = (int) get_term_meta( $term->term_id, '_eluminate_by_topic_menu', true ) === 1;
		?>
		<tr class="form-field term-eluminate-by-topic-wrap">
			<th scope="row">
				<label for="eluminate-by-topic-menu"><?php echo esc_html__( 'By Topic menu', 'eluminate-standalone' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" id="eluminate-by-topic-menu" name="eluminate_by_topic_menu" value="1" <?php checked( $enabled ); ?> />
					<?php echo esc_html__( 'List in By Topic menu', 'eluminate-standalone' ); ?>
				</label>
				<p class="description"><?php echo esc_html__( 'Optionally, manage "By Topic" menu order in Appearance > Menus > By Topic. Otherwise, it is appended to current menu order.', 'eluminate-standalone' ); ?></p>
			</td>
		</tr>
		<?php
	}
}
add_action( 'tags_edit_form_fields', 'eluminate_standalone_tags_edit_by_topic_field' );

if ( ! function_exists( 'eluminate_standalone_save_tags_by_topic_field' ) ) {
	/**
	 * Persists "By Topic menu" term toggle.
	 *
	 * @param int $term_id Term ID.
	 *
	 * @return void
	 */
	function eluminate_standalone_save_tags_by_topic_field( int $term_id ): void {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$enabled = isset( $_POST['eluminate_by_topic_menu'] ) && '1' === (string) wp_unslash( $_POST['eluminate_by_topic_menu'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $enabled ) {
			update_term_meta( $term_id, '_eluminate_by_topic_menu', 1 );
			return;
		}
		delete_term_meta( $term_id, '_eluminate_by_topic_menu' );
	}
}
add_action( 'created_tags', 'eluminate_standalone_save_tags_by_topic_field' );
add_action( 'edited_tags', 'eluminate_standalone_save_tags_by_topic_field' );

if ( ! function_exists( 'eluminate_standalone_tags_term_page_titles_map' ) ) {
	/**
	 * Maps tags term IDs to current page titles that list them (shortcodes and/or mapping meta).
	 *
	 * Titles are read live from pages so renames appear automatically. Cached per request.
	 *
	 * @return array<int, string[]> term_id => page titles (natural-case sorted).
	 */
	function eluminate_standalone_tags_term_page_titles_map(): array {
		static $cached = null;
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$cached = array();
		$pages  = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page'         => -1,
				'orderby'                => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $pages as $page ) {
			if ( ! $page instanceof WP_Post ) {
				continue;
			}
			$term_ids = array();
			if ( function_exists( 'eluminate_standalone_get_shows_videos_shortcode_term_ids' ) ) {
				$term_ids = eluminate_standalone_get_shows_videos_shortcode_term_ids( (string) $page->post_content );
			}
			if ( empty( $term_ids ) && function_exists( 'eluminate_standalone_get_page_tags_term_ids' ) ) {
				$term_ids = eluminate_standalone_get_page_tags_term_ids( (int) $page->ID );
			}
			if ( empty( $term_ids ) ) {
				continue;
			}

			$title = get_the_title( $page );
			if ( '' === $title ) {
				$title = __( '(no title)', 'eluminate-standalone' );
			}

			foreach ( $term_ids as $term_id ) {
				$tid = (int) $term_id;
				if ( $tid <= 0 ) {
					continue;
				}
				if ( ! isset( $cached[ $tid ] ) ) {
					$cached[ $tid ] = array();
				}
				// Keep first occurrence order from page query; de-dupe by title string.
				if ( ! in_array( $title, $cached[ $tid ], true ) ) {
					$cached[ $tid ][] = $title;
				}
			}
		}

		foreach ( $cached as $tid => $titles ) {
			natcasesort( $titles );
			$cached[ $tid ] = array_values( $titles );
		}

		return $cached;
	}
}

if ( ! function_exists( 'eluminate_standalone_tags_build_listed_in_label' ) ) {
	/**
	 * Builds the admin "Listed In" cell text for a tags term.
	 *
	 * Includes "By Topic" when that checkbox is enabled, plus current page titles
	 * that list the term, joined with " / ". Empty → Unlisted.
	 *
	 * @param int $term_id tags term ID.
	 *
	 * @return string Unescaped label (escape when outputting HTML).
	 */
	function eluminate_standalone_tags_build_listed_in_label( int $term_id ): string {
		$parts = array();
		if ( (int) get_term_meta( $term_id, '_eluminate_by_topic_menu', true ) === 1 ) {
			$parts[] = __( 'By Topic', 'eluminate-standalone' );
		}

		$map    = eluminate_standalone_tags_term_page_titles_map();
		$titles = $map[ $term_id ] ?? array();
		foreach ( $titles as $title ) {
			$parts[] = $title;
		}

		if ( empty( $parts ) ) {
			return __( 'Unlisted', 'eluminate-standalone' );
		}
		return implode( ' / ', $parts );
	}
}

if ( ! function_exists( 'eluminate_standalone_tags_columns_by_topic_submenu' ) ) {
	/**
	 * Inserts the "Listed In" column between Slug and Count (posts) on the tags terms list.
	 *
	 * @param array<string, string> $columns Column slug => heading.
	 * @return array<string, string>
	 */
	function eluminate_standalone_tags_columns_by_topic_submenu( array $columns ): array {
		$new      = array();
		$inserted = false;
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'slug' === $key ) {
				$new['by_topic_submenu'] = __( 'Listed In', 'eluminate-standalone' );
				$inserted                = true;
			}
		}
		if ( ! $inserted ) {
			$new['by_topic_submenu'] = __( 'Listed In', 'eluminate-standalone' );
		}
		return $new;
	}
}
add_filter( 'manage_edit-tags_columns', 'eluminate_standalone_tags_columns_by_topic_submenu', 20 );

if ( ! function_exists( 'eluminate_standalone_tags_custom_column_by_topic_submenu' ) ) {
	/**
	 * Returns Listed In labels for the tags terms table (core uses apply_filters for this hook).
	 *
	 * "By Topic" when enabled, plus current titles of pages that list the term, joined with " / ".
	 *
	 * @param string     $output      Default empty output.
	 * @param string     $column_name Column key.
	 * @param int|string $term_id     Term ID.
	 *
	 * @return string
	 */
	function eluminate_standalone_tags_custom_column_by_topic_submenu( string $output, string $column_name, $term_id ): string {
		if ( 'by_topic_submenu' !== $column_name ) {
			return $output;
		}
		$tid = (int) $term_id;
		if ( $tid <= 0 ) {
			return $output;
		}
		return esc_html( eluminate_standalone_tags_build_listed_in_label( $tid ) );
	}
}
add_filter( 'manage_tags_custom_column', 'eluminate_standalone_tags_custom_column_by_topic_submenu', 10, 3 );

if ( ! function_exists( 'eluminate_standalone_tags_sortable_listed_in_column' ) ) {
	/**
	 * Registers the Listed In column as sortable (same screen id convention as manage_edit-tags_columns).
	 *
	 * @param array<string, string|mixed[]> $sortable Columns keyed by slug.
	 * @return array<string, string|mixed[]>
	 */
	function eluminate_standalone_tags_sortable_listed_in_column( array $sortable ): array {
		$abbr = __( 'Listed In', 'eluminate-standalone' );

		$sortable['by_topic_submenu'] = array(
			'by_topic_submenu',
			false,
			$abbr,
			sprintf(
				/* translators: %s: taxonomy column heading */
				__( 'Table sorted by %s.', 'eluminate-standalone' ),
				$abbr
			),
			'asc',
		);

		return $sortable;
	}
}
add_filter( 'manage_edit-tags_sortable_columns', 'eluminate_standalone_tags_sortable_listed_in_column', 20 );

if ( ! function_exists( 'eluminate_standalone_tags_terms_clauses_order_listed_in' ) ) {
	/**
	 * Orders tags admin table by Listed In page-title labels.
	 *
	 * @param array<string, string> $clauses    Terms query clauses.
	 * @param string[]              $taxonomies Taxonomies in the query.
	 * @param array<string, mixed>  $args       get_terms-style arguments.
	 * @return array<string, string>
	 */
	function eluminate_standalone_tags_terms_clauses_order_listed_in( array $clauses, array $taxonomies, array $args ): array {
		if ( empty( $args['orderby'] ) || 'by_topic_submenu' !== $args['orderby'] ) {
			return $clauses;
		}
		if ( ! in_array( 'tags', $taxonomies, true ) ) {
			return $clauses;
		}
		if ( ! is_admin() ) {
			return $clauses;
		}

		// Avoid re-entering this filter while loading terms for sort labels.
		static $sorting = false;
		if ( $sorting ) {
			return $clauses;
		}
		$sorting = true;
		$terms   = get_terms(
			array(
				'taxonomy'   => 'tags',
				'hide_empty' => false,
			)
		);
		$sorting = false;

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return $clauses;
		}

		$labels = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$labels[ (int) $term->term_id ] = eluminate_standalone_tags_build_listed_in_label( (int) $term->term_id );
		}
		if ( empty( $labels ) ) {
			return $clauses;
		}

		$unlisted = __( 'Unlisted', 'eluminate-standalone' );
		uasort(
			$labels,
			static function ( string $a, string $b ) use ( $unlisted ): int {
				$a_un = ( $a === $unlisted );
				$b_un = ( $b === $unlisted );
				if ( $a_un !== $b_un ) {
					return $a_un ? 1 : -1;
				}
				return strcasecmp( $a, $b );
			}
		);

		$ordered_ids = array_keys( $labels );
		if ( isset( $args['order'] ) && 'desc' === strtolower( (string) $args['order'] ) ) {
			$ordered_ids = array_reverse( $ordered_ids );
		}
		$ordered_ids = array_filter(
			array_map( 'intval', $ordered_ids ),
			static function ( int $id ): bool {
				return $id > 0;
			}
		);
		if ( empty( $ordered_ids ) ) {
			return $clauses;
		}

		$field_list         = implode( ',', $ordered_ids );
		$clauses['orderby'] = "ORDER BY FIELD(t.term_id, {$field_list}), t.name ASC";
		$clauses['order']   = '';

		return $clauses;
	}
}
add_filter( 'terms_clauses', 'eluminate_standalone_tags_terms_clauses_order_listed_in', 10, 3 );

if ( ! function_exists( 'eluminate_standalone_by_topic_terms_toggled_count' ) ) {
	/**
	 * Returns number of tags terms enabled for By Topic submenu.
	 *
	 * @return int
	 */
	function eluminate_standalone_by_topic_terms_toggled_count(): int {
		$terms = get_terms(
			array(
				'taxonomy'   => 'tags',
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 1,
				'meta_query' => array(
					array(
						'key'   => '_eluminate_by_topic_menu',
						'value' => '1',
					),
				),
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return 0;
		}
		return count( $terms );
	}
}

if ( ! function_exists( 'eluminate_standalone_by_topic_zero_terms_admin_notice' ) ) {
	/**
	 * Shows an admin warning when By Topic has no enabled terms.
	 *
	 * @return void
	 */
	function eluminate_standalone_by_topic_zero_terms_admin_notice(): void {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen instanceof WP_Screen ) {
			return;
		}

		$is_tags_taxonomy_screen = 'edit-tags' === $screen->base && 'tags' === $screen->taxonomy;
		$is_theme_page_screen       = 'page' === $screen->post_type && in_array( $screen->base, array( 'post', 'edit' ), true );
		if ( ! $is_tags_taxonomy_screen && ! $is_theme_page_screen ) {
			return;
		}

		if ( eluminate_standalone_by_topic_terms_toggled_count() > 0 ) {
			return;
		}

		$manage_terms_url = admin_url( 'edit-tags.php?taxonomy=tags&post_type=videos' );
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php echo esc_html__( 'By Topic submenu is currently empty: no tags are enabled for it.', 'eluminate-standalone' ); ?>
				<a href="<?php echo esc_url( $manage_terms_url ); ?>">
					<?php echo esc_html__( 'Manage terms', 'eluminate-standalone' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'eluminate_standalone_by_topic_zero_terms_admin_notice' );

if ( ! function_exists( 'eluminate_standalone_is_shows_child_page' ) ) {
	/**
	 * Whether a page belongs to Main Page > Shows descendants.
	 *
	 * @param int $page_id Page ID.
	 *
	 * @return bool
	 */
	function eluminate_standalone_is_shows_child_page( int $page_id ): bool {
		$shows_root_ids = array();
		$nested_shows   = get_page_by_path( 'main-page/shows' );
		$top_level_shows = get_page_by_path( 'shows' );

		if ( $nested_shows instanceof WP_Post ) {
			$shows_root_ids[] = (int) $nested_shows->ID;
		}
		if ( $top_level_shows instanceof WP_Post ) {
			$shows_root_ids[] = (int) $top_level_shows->ID;
		}
		$shows_root_ids = array_values( array_unique( array_filter( $shows_root_ids ) ) );
		if ( empty( $shows_root_ids ) ) {
			return false;
		}

		if ( in_array( $page_id, $shows_root_ids, true ) ) {
			return true;
		}

		$ancestors = get_post_ancestors( $page_id );
		$ancestor_ids = array_map( 'intval', $ancestors );
		foreach ( $shows_root_ids as $shows_root_id ) {
			if ( in_array( $shows_root_id, $ancestor_ids, true ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'eluminate_standalone_get_page_tags_term_id' ) ) {
	/**
	 * Returns legacy single mapped tags term id for a page.
	 *
	 * @param int $page_id Page ID.
	 *
	 * @return int
	 */
	function eluminate_standalone_get_page_tags_term_id( int $page_id ): int {
		$term_id = (int) get_post_meta( $page_id, '_eluminate_tags_term_id', true );
		return $term_id > 0 ? $term_id : 0;
	}
}

if ( ! function_exists( 'eluminate_standalone_get_page_tags_term_ids' ) ) {
	/**
	 * Returns mapped tags term ids for a page (multi-term + legacy single meta).
	 *
	 * @param int $page_id Page ID.
	 *
	 * @return int[]
	 */
	function eluminate_standalone_get_page_tags_term_ids( int $page_id ): array {
		$ids = get_post_meta( $page_id, '_eluminate_tags_term_ids', true );
		if ( is_array( $ids ) ) {
			$ids = array_values(
				array_unique(
					array_filter(
						array_map( 'intval', $ids ),
						static function ( int $term_id ): bool {
							return $term_id > 0;
						}
					)
				)
			);
			if ( ! empty( $ids ) ) {
				return $ids;
			}
		}

		$legacy = eluminate_standalone_get_page_tags_term_id( $page_id );
		return $legacy > 0 ? array( $legacy ) : array();
	}
}

if ( ! function_exists( 'eluminate_standalone_videos_shortcode_tag' ) ) {
	/**
	 * Canonical shortcode tag for a tagged video grid.
	 *
	 * @return string
	 */
	function eluminate_standalone_videos_shortcode_tag(): string {
		return 'eluminate-videos';
	}
}

if ( ! function_exists( 'eluminate_standalone_shows_videos_shortcode_tag' ) ) {
	/**
	 * @deprecated Use {@see eluminate_standalone_videos_shortcode_tag()}.
	 *
	 * @return string
	 */
	function eluminate_standalone_shows_videos_shortcode_tag(): string {
		return eluminate_standalone_videos_shortcode_tag();
	}
}

if ( ! function_exists( 'eluminate_standalone_videos_shortcode_tags' ) ) {
	/**
	 * Shortcode tags recognized in content.
	 *
	 * @return string[]
	 */
	function eluminate_standalone_videos_shortcode_tags(): array {
		return array( eluminate_standalone_videos_shortcode_tag() );
	}
}

if ( ! function_exists( 'eluminate_standalone_resolve_tags_term_from_atts' ) ) {
	/**
	 * Resolve a tags term from shortcode attributes (requires tag_slug or term_id).
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 *
	 * @return WP_Term|null
	 */
	function eluminate_standalone_resolve_tags_term_from_atts( array $atts ): ?WP_Term {
		if ( ! empty( $atts['term_id'] ) ) {
			$term = get_term( (int) $atts['term_id'], 'tags' );
			if ( $term instanceof WP_Term && 'tags' === $term->taxonomy ) {
				return $term;
			}
		}

		if ( ! empty( $atts['tag_slug'] ) ) {
			$term = get_term_by( 'slug', sanitize_title( (string) $atts['tag_slug'] ), 'tags' );
			if ( $term instanceof WP_Term ) {
				return $term;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'eluminate_standalone_get_shows_videos_shortcode_term_ids' ) ) {
	/**
	 * Term ids referenced by explicit [eluminate-videos tag_slug="…"] shortcodes in content.
	 *
	 * @param string $content Post content.
	 *
	 * @return int[]
	 */
	function eluminate_standalone_get_shows_videos_shortcode_term_ids( string $content ): array {
		if ( '' === $content ) {
			return array();
		}

		$tag_names = eluminate_standalone_videos_shortcode_tags();
		$has_tag   = false;
		foreach ( $tag_names as $tag_name ) {
			if ( str_contains( $content, '[' . $tag_name ) ) {
				$has_tag = true;
				break;
			}
		}
		if ( ! $has_tag ) {
			return array();
		}

		/*
		 * Avoid get_shortcode_regex() on block-editor markup — it can be very slow or fail on
		 * large Gutenberg HTML/JSON payloads and break the page editor (blank canvas).
		 */
		$ids = array();
		if ( preg_match_all( '/\btag_slug=["\']([^"\']+)["\']/', $content, $slug_matches ) ) {
			foreach ( $slug_matches[1] as $slug ) {
				$term = get_term_by( 'slug', sanitize_title( (string) $slug ), 'tags' );
				if ( $term instanceof WP_Term ) {
					$ids[] = (int) $term->term_id;
				}
			}
		}
		if ( preg_match_all( '/\bterm_id=["\']?(\d+)["\']?/', $content, $id_matches ) ) {
			foreach ( $id_matches[1] as $term_id ) {
				$term = get_term( (int) $term_id, 'tags' );
				if ( $term instanceof WP_Term && 'tags' === $term->taxonomy ) {
					$ids[] = (int) $term->term_id;
				}
			}
		}

		if ( ! empty( $ids ) ) {
			return array_values( array_unique( array_filter( $ids ) ) );
		}

		$regex = get_shortcode_regex( $tag_names );
		if ( ! preg_match_all( '/' . $regex . '/', $content, $matches, PREG_SET_ORDER ) ) {
			return array();
		}

		foreach ( $matches as $match ) {
			$atts = shortcode_parse_atts( $match[3] ?? '' );
			if ( ! is_array( $atts ) ) {
				$atts = array();
			}
			$term = eluminate_standalone_resolve_tags_term_from_atts( $atts );
			if ( $term instanceof WP_Term ) {
				$ids[] = (int) $term->term_id;
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}
}

if ( ! function_exists( 'eluminate_standalone_page_has_shows_videos_shortcode' ) ) {
	/**
	 * Whether page content includes a videos shortcode.
	 *
	 * @param string $content Post content.
	 *
	 * @return bool
	 */
	function eluminate_standalone_page_has_shows_videos_shortcode( string $content ): bool {
		foreach ( eluminate_standalone_videos_shortcode_tags() as $tag ) {
			if ( has_shortcode( $content, $tag ) ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'eluminate_standalone_shows_videos_shortcode_chunk_for_term' ) ) {
	/**
	 * Markup used when auto-inserting a term-specific videos shortcode.
	 *
	 * @param string  $content Existing content (classic vs block format hint).
	 * @param WP_Term $term    tags term.
	 *
	 * @return string
	 */
	function eluminate_standalone_shows_videos_shortcode_chunk_for_term( string $content, WP_Term $term ): string {
		$tag       = eluminate_standalone_videos_shortcode_tag();
		$shortcode = sprintf( '[%s tag_slug="%s"]', $tag, $term->slug );
		if ( '' === trim( $content ) || ( function_exists( 'has_blocks' ) && has_blocks( $content ) ) ) {
			return "<!-- wp:shortcode -->\n{$shortcode}\n<!-- /wp:shortcode -->";
		}
		return $shortcode;
	}
}

if ( ! function_exists( 'eluminate_standalone_content_has_videos_shortcode_for_term' ) ) {
	/**
	 * Whether content already includes a videos shortcode for this term (slug or term_id).
	 *
	 * @param string  $content Post content.
	 * @param WP_Term $term    tags term.
	 *
	 * @return bool
	 */
	function eluminate_standalone_content_has_videos_shortcode_for_term( string $content, WP_Term $term ): bool {
		if ( in_array( (int) $term->term_id, eluminate_standalone_get_shows_videos_shortcode_term_ids( $content ), true ) ) {
			return true;
		}

		// Fallback: string match when shortcode_atts parsing misses block/JSON encodings.
		$tag  = preg_quote( eluminate_standalone_videos_shortcode_tag(), '/' );
		$slug = preg_quote( (string) $term->slug, '/' );
		$id   = (int) $term->term_id;
		$patterns = array(
			'/\[' . $tag . '[^\]]*?\btag_slug=["\']' . $slug . '["\']/i',
			'/\[' . $tag . '[^\]]*?\bterm_id=["\']?' . $id . '["\']?/i',
			'/"tag_slug\\\\?":\\\\?"' . $slug . '\\\\?"/i',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'eluminate_standalone_count_videos_shortcodes_for_term' ) ) {
	/**
	 * Counts videos shortcodes targeting a term.
	 *
	 * @param string  $content Post content.
	 * @param WP_Term $term    tags term.
	 *
	 * @return int
	 */
	function eluminate_standalone_count_videos_shortcodes_for_term( string $content, WP_Term $term ): int {
		$tag  = preg_quote( eluminate_standalone_videos_shortcode_tag(), '/' );
		$slug = preg_quote( (string) $term->slug, '/' );
		$id   = (int) $term->term_id;
		$count = 0;
		if ( preg_match_all( '/\[' . $tag . '[^\]]*?\btag_slug=["\']' . $slug . '["\']/i', $content, $m ) ) {
			$count += count( $m[0] );
		}
		if ( preg_match_all( '/\[' . $tag . '[^\]]*?\bterm_id=["\']?' . $id . '["\']?/i', $content, $m ) ) {
			$count += count( $m[0] );
		}
		return $count;
	}
}

if ( ! function_exists( 'eluminate_standalone_stash_tags_slug_before_edit' ) ) {
	/**
	 * Stores or retrieves the tags term slug before an edit (for slug-change rewrites).
	 *
	 * @param int         $term_id Term ID.
	 * @param string|null $slug    Slug to stash; omit to pop and return the stashed value.
	 *
	 * @return string|null
	 */
	function eluminate_standalone_stash_tags_slug_before_edit( int $term_id, ?string $slug = null ): ?string {
		static $stash = array();

		if ( null !== $slug ) {
			$stash[ $term_id ] = $slug;
			return $slug;
		}

		$old = $stash[ $term_id ] ?? null;
		unset( $stash[ $term_id ] );

		return $old;
	}
}

if ( ! function_exists( 'eluminate_standalone_replace_tag_slug_in_content' ) ) {
	/**
	 * Rewrites [eluminate-videos tag_slug="…"] attributes in post content.
	 *
	 * @param string $content  Post content.
	 * @param string $old_slug Previous tags term slug.
	 * @param string $new_slug Updated tags term slug.
	 *
	 * @return string
	 */
	function eluminate_standalone_replace_tag_slug_in_content( string $content, string $old_slug, string $new_slug ): string {
		if ( $old_slug === $new_slug || '' === $old_slug || '' === $new_slug ) {
			return $content;
		}

		$old_esc = preg_quote( $old_slug, '/' );
		$content = (string) preg_replace(
			'/(\btag_slug=)(["\'])' . $old_esc . '(\\2)/',
			'$1$2' . $new_slug . '$2',
			$content
		);
		$content = (string) preg_replace(
			'/(\btag_slug=\\\\")' . $old_esc . '(\\\\")/',
			'$1' . $new_slug . '$2',
			$content
		);

		return $content;
	}
}

if ( ! function_exists( 'eluminate_standalone_get_page_ids_with_tag_slug_in_content' ) ) {
	/**
	 * Page IDs whose content references a tags term via tag_slug.
	 *
	 * @param string $slug tags term slug.
	 *
	 * @return int[]
	 */
	function eluminate_standalone_get_page_ids_with_tag_slug_in_content( string $slug ): array {
		if ( '' === $slug ) {
			return array();
		}

		global $wpdb;

		$like_double = '%' . $wpdb->esc_like( 'tag_slug="' . $slug . '"' ) . '%';
		$like_single = '%' . $wpdb->esc_like( "tag_slug='" . $slug . "'" ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'page'
				AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
				AND ( post_content LIKE %s OR post_content LIKE %s )",
				$like_double,
				$like_single
			)
		);

		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'intval', $ids ) ) );
	}
}

if ( ! function_exists( 'eluminate_standalone_rewrite_pages_tag_slug' ) ) {
	/**
	 * Updates page content when a tags term slug changes.
	 *
	 * @param int    $term_id  tags term ID.
	 * @param string $old_slug Previous slug.
	 * @param string $new_slug New slug.
	 *
	 * @return int Number of pages updated.
	 */
	function eluminate_standalone_rewrite_pages_tag_slug( int $term_id, string $old_slug, string $new_slug ): int {
		if ( $term_id <= 0 || $old_slug === $new_slug || '' === $old_slug || '' === $new_slug ) {
			return 0;
		}

		$page_ids = eluminate_standalone_get_page_ids_with_tag_slug_in_content( $old_slug );
		if ( empty( $page_ids ) ) {
			return 0;
		}

		$updated = 0;
		foreach ( $page_ids as $page_id ) {
			$post = get_post( $page_id );
			if ( ! ( $post instanceof WP_Post ) || 'page' !== $post->post_type ) {
				continue;
			}

			$new_content = eluminate_standalone_replace_tag_slug_in_content( (string) $post->post_content, $old_slug, $new_slug );
			if ( $new_content === $post->post_content ) {
				continue;
			}

			// Direct DB write: wp_update_post() runs wp_insert_post_data filters that can corrupt block markup.
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->update(
				$wpdb->posts,
				array( 'post_content' => $new_content ),
				array( 'ID' => $page_id ),
				array( '%s' ),
				array( '%d' )
			);

			if ( false !== $rows ) {
				clean_post_cache( $page_id );
				++$updated;
			}
		}

		return $updated;
	}
}

if ( ! function_exists( 'eluminate_standalone_capture_tags_slug_before_edit' ) ) {
	/**
	 * Records the current slug before wp_update_term runs.
	 *
	 * @param int                  $term_id  Term ID.
	 * @param string               $taxonomy Taxonomy slug.
	 * @param array<string, mixed> $args     Arguments passed to wp_update_term().
	 *
	 * @return void
	 */
	function eluminate_standalone_capture_tags_slug_before_edit( int $term_id, string $taxonomy, array $args = array() ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		unset( $args );

		if ( 'tags' !== $taxonomy ) {
			return;
		}

		$term = get_term( $term_id, 'tags' );
		if ( ! ( $term instanceof WP_Term ) ) {
			return;
		}

		eluminate_standalone_stash_tags_slug_before_edit( $term_id, (string) $term->slug );
	}
}

if ( ! function_exists( 'eluminate_standalone_rewrite_pages_on_tags_slug_change' ) ) {
	/**
	 * Rewrites page shortcodes after a tags term slug is saved.
	 *
	 * @param int $term_id tags term ID.
	 *
	 * @return void
	 */
	function eluminate_standalone_rewrite_pages_on_tags_slug_change( int $term_id ): void {
		$old_slug = eluminate_standalone_stash_tags_slug_before_edit( $term_id );
		if ( null === $old_slug ) {
			return;
		}

		$term = get_term( $term_id, 'tags' );
		if ( ! ( $term instanceof WP_Term ) ) {
			return;
		}

		eluminate_standalone_rewrite_pages_tag_slug( $term_id, $old_slug, (string) $term->slug );
	}
}
add_action( 'edit_terms', 'eluminate_standalone_capture_tags_slug_before_edit', 5, 3 );
add_action( 'edited_tags', 'eluminate_standalone_rewrite_pages_on_tags_slug_change', 15 );

if ( ! function_exists( 'eluminate_standalone_dedupe_videos_shortcodes' ) ) {
	/**
	 * Ensures at most one videos shortcode per tags term.
	 *
	 * @param string $content Post content.
	 *
	 * @return string
	 */
	function eluminate_standalone_dedupe_videos_shortcodes( string $content ): string {
		$term_ids = eluminate_standalone_get_shows_videos_shortcode_term_ids( $content );
		if ( empty( $term_ids ) ) {
			return $content;
		}

		foreach ( $term_ids as $term_id ) {
			$term = get_term( (int) $term_id, 'tags' );
			if ( ! ( $term instanceof WP_Term ) ) {
				continue;
			}
			if ( eluminate_standalone_count_videos_shortcodes_for_term( $content, $term ) <= 1 ) {
				continue;
			}
			// Collapse duplicates: drop all, then put a single shortcode back.
			$content = eluminate_standalone_strip_shows_videos_shortcode_for_term( $content, $term );
			$content = eluminate_standalone_append_shows_videos_shortcode_for_term( $content, $term );
		}

		return $content;
	}
}

if ( ! function_exists( 'eluminate_standalone_append_shows_videos_shortcode_for_term' ) ) {
	/**
	 * Appends a term-specific videos shortcode if that term is not already present.
	 *
	 * @param string  $content Post content.
	 * @param WP_Term $term    tags term.
	 *
	 * @return string
	 */
	function eluminate_standalone_append_shows_videos_shortcode_for_term( string $content, WP_Term $term ): string {
		if ( eluminate_standalone_content_has_videos_shortcode_for_term( $content, $term ) ) {
			return $content;
		}
		$chunk = eluminate_standalone_shows_videos_shortcode_chunk_for_term( $content, $term );
		if ( '' === trim( $content ) ) {
			return $chunk;
		}
		return rtrim( $content ) . "\n\n" . $chunk;
	}
}

if ( ! function_exists( 'eluminate_standalone_strip_shows_videos_shortcode_for_term' ) ) {
	/**
	 * Removes videos shortcodes that target a specific tags term.
	 *
	 * @param string  $content Post content.
	 * @param WP_Term $term    tags term.
	 *
	 * @return string
	 */
	function eluminate_standalone_strip_shows_videos_shortcode_for_term( string $content, WP_Term $term ): string {
		$slug = preg_quote( (string) $term->slug, '/' );
		$id   = (int) $term->term_id;

		foreach ( eluminate_standalone_videos_shortcode_tags() as $tag_name ) {
			$tag      = preg_quote( $tag_name, '/' );
			$patterns = array(
				'/<!--\s*wp:shortcode\s*-->\s*\[' . $tag . '[^\]]*?\btag_slug=["\']' . $slug . '["\'][^\]]*\]\s*<!--\s*\/wp:shortcode\s*-->\s*/i',
				'/<!--\s*wp:shortcode\s*-->\s*\[' . $tag . '[^\]]*?\bterm_id=["\']?' . $id . '["\']?[^\]]*\]\s*<!--\s*\/wp:shortcode\s*-->\s*/i',
				'/\[' . $tag . '[^\]]*?\btag_slug=["\']' . $slug . '["\'][^\]]*\]\s*/i',
				'/\[' . $tag . '[^\]]*?\bterm_id=["\']?' . $id . '["\']?[^\]]*\]\s*/i',
			);
			foreach ( $patterns as $pattern ) {
				$content = (string) preg_replace( $pattern, '', $content );
			}
		}

		return $content;
	}
}

if ( ! function_exists( 'eluminate_standalone_strip_bare_shows_videos_shortcodes' ) ) {
	/**
	 * Removes bare videos shortcodes (no tag_slug / term_id attributes).
	 *
	 * @param string $content Post content.
	 *
	 * @return string
	 */
	function eluminate_standalone_strip_bare_shows_videos_shortcodes( string $content ): string {
		foreach ( eluminate_standalone_videos_shortcode_tags() as $tag_name ) {
			$tag     = preg_quote( $tag_name, '/' );
			$content = (string) preg_replace(
				'/<!--\s*wp:shortcode\s*-->\s*\[' . $tag . '\s*\]\s*<!--\s*\/wp:shortcode\s*-->\s*/i',
				'',
				$content
			);
			$content = (string) preg_replace( '/\[' . $tag . '\s*\]\s*/i', '', $content );
		}
		return $content;
	}
}

if ( ! function_exists( 'eluminate_standalone_strip_all_shows_videos_shortcodes' ) ) {
	/**
	 * Removes every videos shortcode instance (with or without attrs).
	 *
	 * @param string $content Post content.
	 *
	 * @return string
	 */
	function eluminate_standalone_strip_all_shows_videos_shortcodes( string $content ): string {
		foreach ( eluminate_standalone_videos_shortcode_tags() as $tag_name ) {
			$tag     = preg_quote( $tag_name, '/' );
			$content = (string) preg_replace(
				'/<!--\s*wp:shortcode\s*-->\s*\[' . $tag . '[^\]]*\]\s*<!--\s*\/wp:shortcode\s*-->\s*/i',
				'',
				$content
			);
			$content = (string) preg_replace( '/\[' . $tag . '[^\]]*\]\s*/i', '', $content );
		}
		return $content;
	}
}

if ( ! function_exists( 'eluminate_standalone_add_page_tags_mapping_meta_box' ) ) {
	/**
	 * Adds a tags mapping meta box to page editor.
	 *
	 * @return void
	 */
	function eluminate_standalone_add_page_tags_mapping_meta_box(): void {
		add_meta_box(
			'eluminate-tags-mapping',
			__( 'Tags mapping', 'eluminate-standalone' ),
			'eluminate_standalone_render_page_tags_mapping_meta_box',
			'page',
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes_page', 'eluminate_standalone_add_page_tags_mapping_meta_box' );

if ( ! function_exists( 'eluminate_standalone_render_page_tags_mapping_meta_box' ) ) {
	/**
	 * Renders multi-term tags mapping checkboxes (each maps to its own shortcode).
	 *
	 * @param WP_Post $post Current page post.
	 *
	 * @return void
	 */
	function eluminate_standalone_render_page_tags_mapping_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'eluminate_tags_mapping_save', 'eluminate_tags_mapping_nonce' );

		$selected_ids = eluminate_standalone_get_shows_videos_shortcode_term_ids( (string) $post->post_content );
		if ( empty( $selected_ids ) ) {
			$selected_ids = eluminate_standalone_get_page_tags_term_ids( (int) $post->ID );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'tags',
				'hide_empty' => false,
			)
		);
		?>
		<p><?php echo esc_html__( 'Choose one or more tags. Each checked tag gets its own shortcode section you can move in the editor.', 'eluminate-standalone' ); ?></p>
		<?php
		if ( is_wp_error( $terms ) || empty( $terms ) ) :
			?>
			<p><em><?php echo esc_html__( 'No tags found.', 'eluminate-standalone' ); ?></em></p>
			<?php
			return;
		endif;
		?>
		<ul style="margin:0.5em 0;padding-left:0;list-style:none;">
			<?php
			foreach ( $terms as $term ) :
				if ( ! $term instanceof WP_Term ) {
					continue;
				}
				$input_id = 'eluminate-tags-term-' . (int) $term->term_id;
				?>
				<li style="margin:0 0 0.35em;">
					<label for="<?php echo esc_attr( $input_id ); ?>">
						<input
							type="checkbox"
							class="eluminate-tags-term-checkbox"
							id="<?php echo esc_attr( $input_id ); ?>"
							name="eluminate_tags_term_ids[]"
							value="<?php echo esc_attr( (string) $term->term_id ); ?>"
							data-term-slug="<?php echo esc_attr( $term->slug ); ?>"
							<?php checked( in_array( (int) $term->term_id, $selected_ids, true ) ); ?>
						/>
						<?php echo esc_html( $term->name ); ?>
						<code style="font-size:11px;">tag_slug="<?php echo esc_html( $term->slug ); ?>"</code>
					</label>
				</li>
				<?php
			endforeach;
			?>
		</ul>
		<p>
			<em>
				<?php
				echo esc_html__(
					'Checking a term inserts [eluminate-videos tag_slug="…"] into the content immediately. Uncheck a term or delete its shortcode to remove that section. Drag shortcodes to reorder sections; drag series cards in “Video Order” below.',
					'eluminate-standalone'
				);
				?>
			</em>
		</p>
		<?php
	}
}

if ( ! function_exists( 'eluminate_standalone_enqueue_tags_mapping_admin_script' ) ) {
	/**
	 * Editor script: toggle tag shortcodes when mapping checkboxes change.
	 *
	 * @param string $hook_suffix Current admin page.
	 *
	 * @return void
	 */
	function eluminate_standalone_enqueue_tags_mapping_admin_script( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'page' !== $screen->post_type ) {
			return;
		}

		$script_path = trailingslashit( get_template_directory() ) . 'assets/js/tags-mapping.js';
		if ( ! is_readable( $script_path ) ) {
			return;
		}

		wp_enqueue_script(
			'eluminate-tags-mapping',
			get_template_directory_uri() . '/assets/js/tags-mapping.js',
			array( 'wp-blocks', 'wp-data', 'wp-dom-ready' ),
			(string) filemtime( $script_path ),
			true
		);
	}
}
add_action( 'admin_enqueue_scripts', 'eluminate_standalone_enqueue_tags_mapping_admin_script' );

if ( ! function_exists( 'eluminate_standalone_sync_page_tags_mapping_content' ) ) {
	/**
	 * Syncs multi-tag checkboxes with per-tag shortcodes on save.
	 *
	 * @param array<string, mixed> $data    Post data to be saved.
	 * @param array<string, mixed> $postarr Raw post array.
	 *
	 * @return array<string, mixed>
	 */
	function eluminate_standalone_sync_page_tags_mapping_content( array $data, array $postarr ): array {
		if ( ( $data['post_type'] ?? '' ) !== 'page' ) {
			return $data;
		}

		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;

		/*
		 * Block editor sidebar meta boxes POST via meta-box-loader with an empty/stale classic
		 * #content field. Never let that overwrite stored block markup.
		 */
		if ( isset( $_POST['meta-box-loader'] ) && $post_id > 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$existing = get_post( $post_id );
			if ( $existing instanceof WP_Post ) {
				$data['post_content'] = wp_slash( (string) $existing->post_content );
				$data['post_title']   = wp_slash( (string) $existing->post_title );
				$data['post_excerpt'] = wp_slash( (string) $existing->post_excerpt );
			}
			return $data;
		}

		$is_rest_save = ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| ! empty( $GLOBALS['eluminate_standalone_rest_dispatch'] );

		if ( $is_rest_save ) {
			$content     = wp_unslash( (string) ( $data['post_content'] ?? '' ) );
			$content_ids = eluminate_standalone_get_shows_videos_shortcode_term_ids( $content );
			if ( ! empty( $content_ids ) ) {
				$GLOBALS['eluminate_standalone_tags_term_ids_to_save'] = $content_ids;
			}
			return $data;
		}

		$incoming_content = wp_unslash( (string) ( $data['post_content'] ?? '' ) );
		if ( '' === trim( $incoming_content ) && $post_id > 0 ) {
			$existing = get_post( $post_id );
			if ( $existing instanceof WP_Post && '' !== trim( (string) $existing->post_content ) ) {
				$data['post_content'] = wp_slash( (string) $existing->post_content );
				return $data;
			}
		}

		if ( ! isset( $_POST['eluminate_tags_mapping_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $data;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['eluminate_tags_mapping_nonce'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! wp_verify_nonce( $nonce, 'eluminate_tags_mapping_save' ) ) {
			return $data;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $data;
		}

		if ( $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
			return $data;
		}
		if ( $post_id <= 0 && ! current_user_can( 'edit_pages' ) ) {
			return $data;
		}

		$posted_raw = isset( $_POST['eluminate_tags_term_ids'] ) ? wp_unslash( $_POST['eluminate_tags_term_ids'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! is_array( $posted_raw ) ) {
			$posted_raw = array();
		}
		$posted_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $posted_raw ),
					static function ( int $term_id ): bool {
						return $term_id > 0;
					}
				)
			)
		);

		$using_block_editor = false;
		if ( $post_id > 0 && function_exists( 'use_block_editor_for_post' ) ) {
			$using_block_editor = (bool) use_block_editor_for_post( $post_id );
		} elseif ( function_exists( 'use_block_editor_for_post_type' ) ) {
			$using_block_editor = (bool) use_block_editor_for_post_type( 'page' );
		}

		if ( $using_block_editor ) {
			$content = eluminate_standalone_dedupe_videos_shortcodes( wp_unslash( (string) ( $data['post_content'] ?? '' ) ) );
			$data['post_content'] = wp_slash( $content );
			$content_ids          = eluminate_standalone_get_shows_videos_shortcode_term_ids( $content );
			$GLOBALS['eluminate_standalone_tags_term_ids_to_save'] = ! empty( $content_ids ) ? $content_ids : $posted_ids;
			return $data;
		}

		$content = wp_unslash( (string) ( $data['post_content'] ?? '' ) );

		// Classic editor: checkboxes are the mapping source of truth (JS also inserts/removes shortcodes).
		$desired_terms = array();
		foreach ( $posted_ids as $term_id ) {
			$term = get_term( (int) $term_id, 'tags' );
			if ( $term instanceof WP_Term ) {
				$desired_terms[ (int) $term->term_id ] = $term;
			}
		}
		$desired_ids = array_keys( $desired_terms );

		// Drop shortcodes for terms no longer desired.
		$current_ids = eluminate_standalone_get_shows_videos_shortcode_term_ids( $content );
		foreach ( $current_ids as $term_id ) {
			if ( isset( $desired_terms[ $term_id ] ) ) {
				continue;
			}
			$term = get_term( (int) $term_id, 'tags' );
			if ( $term instanceof WP_Term ) {
				$content = eluminate_standalone_strip_shows_videos_shortcode_for_term( $content, $term );
			}
		}

		// Remove unsupported bare shortcodes (must use term / term_id).
		$content = eluminate_standalone_strip_bare_shows_videos_shortcodes( $content );

		if ( empty( $desired_ids ) && eluminate_standalone_page_has_shows_videos_shortcode( $content ) ) {
			$content = eluminate_standalone_strip_all_shows_videos_shortcodes( $content );
		}

		// Append missing per-term shortcodes after existing content.
		foreach ( $desired_terms as $term ) {
			$content = eluminate_standalone_append_shows_videos_shortcode_for_term( $content, $term );
		}

		$content = eluminate_standalone_dedupe_videos_shortcodes( $content );

		$data['post_content'] = wp_slash( $content );
		$GLOBALS['eluminate_standalone_tags_term_ids_to_save'] = $desired_ids;

		return $data;
	}
}
add_filter( 'wp_insert_post_data', 'eluminate_standalone_sync_page_tags_mapping_content', 10, 2 );

if ( ! function_exists( 'eluminate_standalone_save_page_tags_mapping_meta_box' ) ) {
	/**
	 * Persists multi-term tags mapping meta.
	 *
	 * Prefers ids staged by classic-form sync; otherwise mirrors term shortcodes in content
	 * (block editor / REST saves do not post the meta box fields).
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	function eluminate_standalone_save_page_tags_mapping_meta_box( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$ids = array();
		if ( isset( $GLOBALS['eluminate_standalone_tags_term_ids_to_save'] ) && is_array( $GLOBALS['eluminate_standalone_tags_term_ids_to_save'] ) ) {
			$ids = array_values(
				array_unique(
					array_filter(
						array_map( 'intval', $GLOBALS['eluminate_standalone_tags_term_ids_to_save'] ),
						static function ( int $term_id ): bool {
							return $term_id > 0;
						}
					)
				)
			);
			unset( $GLOBALS['eluminate_standalone_tags_term_ids_to_save'] );
		} else {
			$post = get_post( $post_id );
			if ( $post instanceof WP_Post ) {
				$ids = eluminate_standalone_get_shows_videos_shortcode_term_ids( (string) $post->post_content );
			}
		}

		if ( empty( $ids ) ) {
			delete_post_meta( $post_id, '_eluminate_tags_term_ids' );
			delete_post_meta( $post_id, '_eluminate_tags_term_id' );
			return;
		}

		update_post_meta( $post_id, '_eluminate_tags_term_ids', $ids );
		// Keep legacy single meta aligned to the first mapped term for older readers.
		update_post_meta( $post_id, '_eluminate_tags_term_id', (int) $ids[0] );
	}
}
add_action( 'save_post_page', 'eluminate_standalone_save_page_tags_mapping_meta_box' );

if ( ! function_exists( 'eluminate_standalone_shortcode_bool' ) ) {
	/**
	 * Parse a shortcode attribute as a boolean ("true"/"false", "1"/"0", etc.).
	 *
	 * @param mixed $value   Raw attribute value.
	 * @param bool  $default Fallback when empty / unrecognized.
	 *
	 * @return bool
	 */
	function eluminate_standalone_shortcode_bool( $value, bool $default = false ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( null === $value || '' === $value ) {
			return $default;
		}
		$normalized = strtolower( trim( (string) $value ) );
		if ( in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true ) ) {
			return true;
		}
		if ( in_array( $normalized, array( '0', 'false', 'no', 'off' ), true ) ) {
			return false;
		}
		return $default;
	}
}

if ( ! function_exists( 'eluminate_standalone_render_tags_videos_for_term' ) ) {
	/**
	 * Renders video cards for a specific tags term.
	 *
	 * @param WP_Term              $term    tags term.
	 * @param array<string, mixed> $options Optional: limit, hide_others, hide_title.
	 *
	 * @return string
	 */
	function eluminate_standalone_render_tags_videos_for_term( WP_Term $term, array $options = array() ): string {
		if ( 'tags' !== $term->taxonomy ) {
			return '';
		}
		if ( class_exists( 'Niztech_Youtube' ) && ! class_exists( 'Niztech_Youtube_Client' ) ) {
			$path_to_plugins = join( DIRECTORY_SEPARATOR, array( WP_PLUGIN_DIR, 'niztech-youtube', 'class-niztech-youtube-client.php' ) );
			if ( file_exists( $path_to_plugins ) ) {
				include_once $path_to_plugins;
			}
		}

		$limit        = isset( $options['limit'] ) ? (int) $options['limit'] : 0;
		$hide_others  = ! empty( $options['hide_others'] );
		$hide_title   = ! empty( $options['hide_title'] );
		$limited      = $limit > 0;
		$per_page     = $limited ? $limit : 10;
		$paged        = $limited ? 1 : max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

		$page_id = isset( $options['page_id'] ) ? (int) $options['page_id'] : eluminate_standalone_get_host_page_id();

		$result = eluminate_standalone_query_series_for_term(
			$term,
			$page_id,
			$per_page,
			$paged,
			$limited ? $limit : 0
		);

		$series_posts = $result['posts'];
		$max_pages    = $result['max_pages'];
		$paged        = $result['paged'];

		if ( empty( $series_posts ) ) {
			return '';
		}

		ob_start();
		$rendered_videos = 0;
		echo '<section class="shows-page-videos">';
		foreach ( $series_posts as $series_post ) {
			if ( ! $series_post instanceof WP_Post ) {
				continue;
			}
			if ( ! class_exists( 'Niztech_Youtube_Client' ) ) {
				continue;
			}
			$video_data = eluminate_standalone_video_content( (int) $series_post->ID );
			if ( empty( $video_data ) ) {
				continue;
			}
			$first_video_data = $video_data[0];
			$rendered_videos += 1;
			$thumb_url = function_exists( 'eluminate_standalone_get_video_thumbnail_url' )
				? eluminate_standalone_get_video_thumbnail_url( $first_video_data )
				: '';
			echo '<article class="video-series-entry">';
			setup_postdata( $series_post );
			get_template_part(
				'template-parts/videos',
				'card',
				array(
					'video'      => $first_video_data,
					'shortlink'  => wp_get_shortlink( (int) $series_post->ID ),
					'hide_title' => $hide_title,
					'thumb_url'  => $thumb_url,
				)
			);
			// hide_others: skip the "N videos in series" line (legacy episode-list equivalent).
			if ( ! $hide_others && count( $video_data ) > 0 ) {
				$n = count( $video_data );
				echo '<p class="video-series-count">';
				echo esc_html(
					sprintf(
						/* translators: %s is the number of videos in this series. */
						_n( '%s video in series', '%s videos in series', $n, 'eluminate-standalone' ),
						number_format_i18n( $n )
					)
				);
				echo '</p>';
			}
			echo '</article>';
		}
		if ( ! $limited ) {
			echo wp_kses_post(
				get_the_posts_pagination(
					array(
						'total'   => $max_pages,
						'current' => $paged,
					)
				)
			);
		}
		echo '</section>';
		wp_reset_postdata();
		if ( 0 === $rendered_videos ) {
			ob_end_clean();
			return '';
		}
		return (string) ob_get_clean();
	}
}

/**
 * Renders a tagged video grid.
 *
 * Usage:
 * - [eluminate-videos tag_slug="health"]
 * - [eluminate-videos term_id="123"]
 * - [eluminate-videos tag_slug="featured" limit="3" hide_others="true" hide_title="false"]
 */
add_shortcode(
	'eluminate-videos',
	static function ( $attr ): string {
		/*
		 * Building `content.rendered` for the block editor runs `do_shortcode()` via `the_content`.
		 * Full markup + Niztech queries here can fatal or emit notices that break JSON responses.
		 */
		if ( eluminate_standalone_defer_heavy_shortcode_for_editor() ) {
			return '<div class="eluminate-videos-placeholder" aria-hidden="true"></div>';
		}

		$atts = shortcode_atts(
			array(
				'tag_slug'    => '',
				'term_id'     => 0,
				'limit'       => '',
				'hide_others' => 'false',
				'hide_title'  => 'false',
			),
			$attr,
			eluminate_standalone_videos_shortcode_tag()
		);

		$resolved_term = eluminate_standalone_resolve_tags_term_from_atts(
			array(
				'tag_slug' => (string) $atts['tag_slug'],
				'term_id'  => (int) $atts['term_id'],
			)
		);
		if ( ! ( $resolved_term instanceof WP_Term ) ) {
			return '';
		}

		return eluminate_standalone_render_tags_videos_for_term(
			$resolved_term,
			array(
				'page_id'     => eluminate_standalone_get_host_page_id(),
				'limit'       => ( '' === $atts['limit'] || null === $atts['limit'] ) ? 0 : (int) $atts['limit'],
				'hide_others' => eluminate_standalone_shortcode_bool( $atts['hide_others'], false ),
				'hide_title'  => eluminate_standalone_shortcode_bool( $atts['hide_title'], false ),
			)
		);
	}
);


/*
 * Add custom taxonomy terms
 */
if ( ! function_exists( 'eluminate_standalone_prefill_taxonomies_init' ) ) {
	/**
	 * Seeds default tags taxonomy terms (not the Appearance → Menus "By Topic" object; that menu is synced separately).
	 *
	 * @return void
	 */
	function eluminate_standalone_prefill_taxonomies_init(): void {
		$terms = array(
			array(
				'term'     => __( 'Popular shows', 'eluminate-standalone' ),
				'taxonomy' => 'tags',
				'args'     => array(
					'description' => __( 'Shows that we may want to highlight', 'eluminate-standalone' ),
					'slug'        => 'popular',
				),
			),
			array(
				'term'     => __( 'Health', 'eluminate-standalone' ),
				'taxonomy' => 'tags',
				'args'     => array(
					'description' => __( 'Relate with health', 'eluminate-standalone' ),
					'slug'        => 'health',
				),
			),
			array(
				'term'     => __( 'Business', 'eluminate-standalone' ),
				'taxonomy' => 'tags',
				'args'     => array(
					'description' => __( 'Relate with business', 'eluminate-standalone' ),
					'slug'        => 'business',
				),
			),
			array(
				'term'     => __( 'Science / Technology', 'eluminate-standalone' ),
				'taxonomy' => 'tags',
				'args'     => array(
					'description' => __( 'Relate with science and technology', 'eluminate-standalone' ),
					'slug'        => 'science-technology',
				),
			),
			array(
				'term'     => __( 'PSA / Promo', 'eluminate-standalone' ),
				'taxonomy' => 'tags',
				'args'     => array(
					'description' => __( 'Promoting or providing public service announcements', 'eluminate-standalone' ),
					'slug'        => 'psa-promo',
				),
			),
			array(
				'term'     => __( 'Housing / Community', 'eluminate-standalone' ),
				'taxonomy' => 'tags',
				'args'     => array(
					'description' => __( 'Relate with housing and community', 'eluminate-standalone' ),
					'slug'        => 'community',
				),
			),
			array(
				'term'     => __( 'History / Culture', 'eluminate-standalone' ),
				'taxonomy' => 'tags',
				'args'     => array(
					'description' => __( 'Relate with history and culture', 'eluminate-standalone' ),
					'slug'        => 'history-culture',
				),
			),
			array(
				'term'     => __( 'Law / Politics / Policy', 'eluminate-standalone' ),
				'taxonomy' => 'tags',
				'args'     => array(
					'description' => __( 'Relate with law politics and policy', 'eluminate-standalone' ),
					'slug'        => 'politics-policy',
				),
			),
			array(
				'term'     => __( 'Family / Youth', 'eluminate-standalone' ),
				'taxonomy' => 'tags',
				'args'     => array(
					'description' => __( 'Relate with families and young adults', 'eluminate-standalone' ),
					'slug'        => 'family-youth',
				),
			),
		);

		$init_version = get_option( THEME_KEY . '_init_version_run', 0 );
		if ( $init_version < THEME_VERSION ) {
			foreach ( $terms as $term_data ) {
				if ( ! term_exists( $term_data['term'] ) ) {
					wp_insert_term( $term_data['term'], $term_data['taxonomy'], $term_data['args'] );
				}
			}
		}
	}
}


if ( ! function_exists( 'eluminate_standalone_register_post_type_init' ) ) {
	/**
	 * Called by init.
	 * - Registers the "tags" taxonomy adn related terms.
	 * - Registers the "videos" post type.
	 * - Removes comments feature from "videos" post type. .
	 *
	 * @return void
	 */
	function eluminate_standalone_register_post_type_init(): void {
		$labels = array(
			'name'                  => _x( 'Videos', 'Post Type General Name', 'eluminate-standalone' ),
			'singular_name'         => _x( 'Video', 'Post Type Singular Name', 'eluminate-standalone' ),
			'menu_name'             => __( 'Videos', 'eluminate-standalone' ),
			'name_admin_bar'        => __( 'Video', 'eluminate-standalone' ),
			'archives'              => __( 'Video Archives', 'eluminate-standalone' ),
			'attributes'            => __( 'Video Attributes', 'eluminate-standalone' ),
			'parent_item_colon'     => __( 'Parent Item:', 'eluminate-standalone' ),
			'all_items'             => __( 'Imported Videos', 'eluminate-standalone' ),
			'add_new_item'          => __( 'Add Videos', 'eluminate-standalone' ),
			'add_new'               => __( 'Add Videos', 'eluminate-standalone' ),
			'new_item'              => __( 'New Video', 'eluminate-standalone' ),
			'edit_item'             => __( 'Edit Video', 'eluminate-standalone' ),
			'update_item'           => __( 'Update Video', 'eluminate-standalone' ),
			'view_item'             => __( 'View Video', 'eluminate-standalone' ),
			'view_items'            => __( 'View Items', 'eluminate-standalone' ),
			'search_items'          => __( 'Search Videos', 'eluminate-standalone' ),
			'not_found'             => __( 'Not found', 'eluminate-standalone' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'eluminate-standalone' ),
			'featured_image'        => __( 'Featured Image', 'eluminate-standalone' ),
			'set_featured_image'    => __( 'Set featured image', 'eluminate-standalone' ),
			'remove_featured_image' => __( 'Remove featured image', 'eluminate-standalone' ),
			'use_featured_image'    => __( 'Use as featured image', 'eluminate-standalone' ),
			'insert_into_item'      => __( 'Insert into item', 'eluminate-standalone' ),
			'uploaded_to_this_item' => __( 'Uploaded to this item', 'eluminate-standalone' ),
			'items_list'            => __( 'Videos list', 'eluminate-standalone' ),
			'items_list_navigation' => __( 'Items list navigation', 'eluminate-standalone' ),
			'filter_items_list'     => __( 'Filter videos list', 'eluminate-standalone' ),
		);
		$args   = array(
			'label'               => __( 'Videos', 'eluminate-standalone' ),
			'description'         => __( 'Posts that show a playlist of videos', 'eluminate-standalone' ),
			'labels'              => $labels,
			'supports'            => array(
				'title',
				'revisions',
				'thumbnail',
			),
			'taxonomies'          => array( 'video_category', 'tags' ),
			'hierarchical'        => false,
			'posts_per_page'      => 12,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-video-alt',
			'menu_position'       => 5,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => 'recent',
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'page',
			'show_in_rest'        => false,
			'rewrite'             => array(
				'slug'       => 'videos',
				'with_front' => false,
			),
		);
		register_post_type( 'videos', $args );

		$tags_labels = array(
			'name'                       => __( 'Tags', 'eluminate-standalone' ),
			'singular_name'              => __( 'Tag', 'eluminate-standalone' ),
			'menu_name'                  => __( 'Tags', 'eluminate-standalone' ),
			'search_items'               => __( 'Search Tags', 'eluminate-standalone' ),
			'popular_items'              => __( 'Popular Tags', 'eluminate-standalone' ),
			'all_items'                  => __( 'All Tags', 'eluminate-standalone' ),
			'parent_item'                => __( 'Parent Tag', 'eluminate-standalone' ),
			'parent_item_colon'          => __( 'Parent Tag:', 'eluminate-standalone' ),
			'edit_item'                  => __( 'Edit Tag', 'eluminate-standalone' ),
			'update_item'                => __( 'Update Tag', 'eluminate-standalone' ),
			'add_new_item'               => __( 'Add New Tag', 'eluminate-standalone' ),
			'new_item_name'              => __( 'New Tag Name', 'eluminate-standalone' ),
			'separate_items_with_commas' => __( 'Separate tags with commas', 'eluminate-standalone' ),
			'add_or_remove_items'        => __( 'Add or remove tags', 'eluminate-standalone' ),
			'choose_from_most_used'      => __( 'Choose from the most used tags', 'eluminate-standalone' ),
			'not_found'                  => __( 'No tags found.', 'eluminate-standalone' ),
			'no_terms'                   => __( 'No tags', 'eluminate-standalone' ),
			'items_list_navigation'      => __( 'Tags list navigation', 'eluminate-standalone' ),
			'items_list'                 => __( 'Tags list', 'eluminate-standalone' ),
			'back_to_items'              => __( '&larr; Back to Tags', 'eluminate-standalone' ),
		);

		register_taxonomy(
			'tags',
			array( 'videos' ),
			array(
				'hierarchical'       => false,
				'labels'             => $tags_labels,
				'public'             => true,
				'rewrite'            => array(
					'slug'       => 'tags',
					'with_front' => false,
				),
				'show_admin_column'  => true,
				'show_in_menu'       => true,
				'show_in_nav_menus'  => true,
				'show_in_quick_edit' => true,
				'show_in_rest'       => true,
				'show_ui'            => true,
			)
		);

		// Removes comments from the post types we created.
		remove_post_type_support( 'videos', 'comments' );
	}
}

/**
 * Title field placeholder on the Videos edit screen.
 */
add_filter(
	'enter_title_here',
	function ( string $title, WP_Post $post ): string {
		if ( 'videos' === $post->post_type ) {
			return __( 'Video title', 'eluminate-standalone' );
		}

		return $title;
	},
	10,
	2
);

/**
 * Add Video Count column to Videos admin list
 */
add_filter(
	'manage_videos_posts_columns',
	function ( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				$new_columns['video_count'] = __( 'Video Count', 'eluminate-standalone' );
			}
		}
		return $new_columns;
	}
);

/**
 * Populate Video Count column values
 */
add_action(
	'manage_videos_posts_custom_column',
	function ( $column, $post_id ) {
		if ( 'video_count' === $column && class_exists( 'Niztech_Youtube_Client' ) ) {
			$video_data = Niztech_Youtube_Client::video_content( $post_id );
			if ( $video_data ) {
				echo '<span style="color: #999;">' . count( $video_data ) . '</span>';
			}
		}
	},
	10,
	2
);

remove_action( 'wp_head', 'wp_generator' );

add_action(
	'customize_register',
	function ( $wp_customize ) {
		// Add a new section for footer settings.
		$wp_customize->add_section(
			'eluminate_standalone_global_settings',
			array(
				'title'    => __( 'Global settings', 'eluminate-standalone' ),
				'priority' => 120,
			)
		);

		// Add setting for footer text.
		$wp_customize->add_setting(
			'eluminate_standalone_mailing_address',
			array(
				'default'           => 'Soroptimist International of Novato<br />PO Box 1267<br />Novato, CA 94948',
				'sanitize_callback' => 'wp_kses_post', // Allows basic HTML.
				'transport'         => 'refresh',
			)
		);

		// Add control for the footer text.
		$wp_customize->add_control(
			'eluminate_standalone_mailing_address_control',
			array(
				'label'       => __( 'Mailing address', 'eluminate-standalone' ),
				'description' => __( 'will show in the footer', 'eluminate-standalone' ),
				'section'     => 'eluminate_standalone_global_settings',
				'settings'    => 'eluminate_standalone_mailing_address',
				'type'        => 'textarea',
			)
		);
	}
);

/**
 * global change wp_query
 */
add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( ! is_admin() && $query->is_main_query() ) {
			if ( ! is_home() ) {
				$query->set( 'posts_per_page', 12 );
			}
		}
	}
);
