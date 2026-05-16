<?php
/**
 * File Name: functions.php
 * Requires 'niztech-youtube' plugin. Creates custom post type 'video_series' and 'list_in' terms.
 *
 * @category   Theme
 * @package eluminate-standalone
 * @author     Nazario A. Ayala <nazario@niztech.com>
 * @license    opensource.org MIT License
 * @link       https://www.niztech.com
 * @since      0.0.1
 */

const THEME_KEY     = 'eluminate-standalone';
const THEME_VERSION = 6;

/** Admin nav menu whose top-level rows define order for "List in By Topic menu" list_in terms (auto-sync). */
const ELUMINATE_BY_TOPIC_SYNC_MENU_NAME = 'By Topic';

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logo hint: hide on reload if dismissed; remove when logo is clicked. Override in wp-config.php if needed.
 */
if ( ! defined( 'ELUMINATE_LOGO_HINT_DISMISS_ENABLED' ) ) {
	define( 'ELUMINATE_LOGO_HINT_DISMISS_ENABLED', true );
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
 * Register video_series Post Type
 */
add_action(
	'init',
	function () {
		eluminate_standalone_register_post_type_init();

		eluminate_standalone_menu_init();
		// Prefill taxonomy terms before building the "By Topic" menu so get_terms() is not empty.
		eluminate_standalone_prefill_taxonomies_init();
		eluminate_standalone_menu_list_in_init();

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
		load_theme_textdomain( 'eluminate-standalone', implode( DIRECTORY_SEPARATOR, array( get_template_directory(), 'languages' ) ) );
	}
);

/**
 * This forces `video_series` pages to use the video_series.php
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
		if ( 'video_series' === $post->post_type ) {
			return join( DIRECTORY_SEPARATOR, array( __DIR__, 'templates', 'video_series.php' ) );
		}

		return $single_template;
	}
);

/**
 * Taxonomy archives for `list_in` must query `video_series`; WordPress defaults the main query to `post`.
 */
add_action(
	'pre_get_posts',
	function ( $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->is_tax( 'list_in' ) ) {
			$query->set( 'post_type', 'video_series' );
			$query->set( 'posts_per_page', 10 );
		}
	}
);

if ( ! function_exists( 'eluminate_recent_video_series_data' ) ) {
	/**
	 * Fetches published video_series data by descending date order.
	 *
	 * @param int $post_count number of posts to include. Default 20.
	 *
	 * @return array
	 */
	function eluminate_recent_video_series_data( int $post_count = 20 ): array {
		$video_series_data = wp_get_recent_posts(
			array(
				'numberposts' => $post_count,
				'orderby'     => 'post_date',
				'order'       => 'DESC',
				'post_type'   => 'video_series',
				'post_status' => 'publish',
			)
		);

		if ( class_exists( 'Niztech_Youtube_Client' ) ) {
			foreach ( $video_series_data as &$video ) {
				$video['video_data'] = Niztech_Youtube_Client::video_content( $video['ID'] );
			}
		}

		return $video_series_data;
	}
}

if ( ! function_exists( 'eluminate_featured_video_series_data' ) ) {
	/**
	 * Fetches published video_series data by descending date order.
	 *
	 * @param int $post_count number of posts to include. Default 20.
	 *
	 * @return array
	 */
	function eluminate_featured_video_series_data( int $post_count = 3 ): array {
		$video_series_data = wp_get_recent_posts(
			array(
				'numberposts' => $post_count,
				'orderby'     => 'post_date',
				'order'       => 'DESC',
				'post_type'   => 'video_series',
				'post_status' => 'publish',
				'tax_query'   => array(
					array(
						'taxonomy' => 'list_in',
						'field'    => 'slug',
						'terms'    => 'featured',
						'operator' => 'IN',
					),
				),
			)
		);

		if ( class_exists( 'Niztech_Youtube_Client' ) ) {
			foreach ( $video_series_data as &$video ) {
				$video['video_data'] = Niztech_Youtube_Client::video_content( $video['ID'] );
			}
		}

		return $video_series_data;
	}
}

if ( ! function_exists( 'eluminate_video_series_shows_page_grid_html' ) ) {
	/**
	 * Renders video_series cards in the same grid markup as list_in taxonomy archives (`.shows-page-videos`).
	 *
	 * Each `$series` row must include `ID` and `video_data` (from Niztech), same shape as
	 * {@see eluminate_featured_video_series_data()} / {@see eluminate_recent_video_series_data()}.
	 *
	 * @param array $video_series_data Series rows from wp_get_recent_posts plus video_data.
	 * @param array $options Optional id, extra section class name(s).
	 *
	 * @return string HTML or empty string.
	 */
	function eluminate_video_series_shows_page_grid_html( array $video_series_data, array $options = array() ): string {
		if ( empty( $video_series_data ) || ! class_exists( 'Niztech_Youtube_Client' ) ) {
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
		foreach ( $video_series_data as $series ) {
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
			echo '<article class="video-series-entry">';
			get_template_part(
				'template-parts/video_series',
				'poop',
				array(
					'video'     => $first_video_data,
					'shortlink' => wp_get_shortlink( $post_id ),
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


if ( ! function_exists( 'eluminate_video_series_html' ) ) {
	/**
	 * Generates the html to display.
	 *
	 * @param array $video_series_data array of video_series objects.
	 * @param array $options extra parameters: id, class, hide_others, title_position, show_desc
	 *
	 * @return string Html string.
	 */
	function eluminate_video_series_html( array $video_series_data, array $options = array() ): string {
		$show_desc = false;
		if ( isset( $options['show_desc'] ) && ( '' === $options['show_desc'] || 'true' === $options['show_desc'] ) ) {
			$show_desc = true;
		}

		$section_attribute_html[] = isset( $options['id'] ) ? 'id="' . $options['id'] . '"' : '';
		$section_attribute_html[] = isset( $options['class'] ) ? 'class="' . $options['class'] . '"' : '';
		$html                     = '<section ' . join( ' ', $section_attribute_html ) . '>';
		$title_position           = $options['title_position'] ?? 'hide';
		foreach ( $video_series_data as $series ) {
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
		$data = eluminate_recent_video_series_data( $a['limit'] );
		// Generate the html.
		return eluminate_video_series_html(
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

		$data = eluminate_featured_video_series_data( (int) $a['limit'] );

		return eluminate_video_series_shows_page_grid_html(
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


if ( ! function_exists( 'eluminate_standalone_ensure_list_in_nav_menu_exists' ) ) {
	/**
	 * Ensures the nav menu used for By Topic term sync exists (named "By Topic", empty until sync runs).
	 * If only the legacy "List In" menu exists, it is renamed to {@see ELUMINATE_BY_TOPIC_SYNC_MENU_NAME}.
	 *
	 * @return int Menu term_id or 0 on failure.
	 */
	function eluminate_standalone_ensure_list_in_nav_menu_exists(): int {
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

if ( ! function_exists( 'eluminate_standalone_menu_list_in_init' ) ) {
	/**
	 * Back-compat wrapper: ensures the By Topic sync nav menu object exists.
	 *
	 * @return void
	 */
	function eluminate_standalone_menu_list_in_init(): void {
		eluminate_standalone_ensure_list_in_nav_menu_exists();
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
				'list-in'          => __( 'List in', 'eluminate-standalone' ),
				'shows'            => __( 'Shows', 'eluminate-standalone' ),
				'blog'             => __( 'Blog', 'eluminate-standalone' ),
				'womens-health'    => __( "Women's health", 'eluminate-standalone' ),
				'womens-finances'  => __( "Women's finances", 'eluminate-standalone' ),
				'social'           => __( 'Social Menu', 'eluminate-standalone' ),
				'footer'           => __( 'Footer Menu', 'eluminate-standalone' ),
				'by-topic-order'   => __( 'List in Section Tags', 'eluminate-standalone' ),
			)
		);
	}
}

if ( ! function_exists( 'eluminate_standalone_list_in_topic_nav' ) ) {
	/**
	 * Prints “By Topic” links for the Shows panel (markup matches wp_nav_menu output for styling).
	 *
	 * Built from taxonomy permalinks instead of the synced By Topic nav menu so hrefs are never stale
	 * (custom menu items can keep wrong URLs after URL or permalink changes).
	 *
	 * @return void
	 */
	function eluminate_standalone_list_in_topic_nav(): void {
		$terms = get_terms(
			array(
				'taxonomy'   => 'list_in',
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
	 * Slug for orbit JS (e.g. parentSlug === "by-topic" enables list_in terms from the By Topic checkbox).
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

if ( ! function_exists( 'eluminate_standalone_nav_menu_item_list_in_term_id' ) ) {
	/**
	 * Resolves a nav menu item to a list_in term ID (taxonomy item, or custom URL under the list_in rewrite base).
	 *
	 * @param WP_Post $item Nav menu item.
	 * @return int|null Term ID or null if not a list_in link.
	 */
	function eluminate_standalone_nav_menu_item_list_in_term_id( WP_Post $item ): ?int {
		$type      = isset( $item->type ) ? (string) $item->type : '';
		$object    = isset( $item->object ) ? (string) $item->object : '';
		$object_id = isset( $item->object_id ) ? (int) $item->object_id : 0;

		if ( 'taxonomy' === $type && 'list_in' === $object && $object_id > 0 ) {
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

		$tax = get_taxonomy( 'list_in' );
		$base = ( is_object( $tax ) && ! empty( $tax->rewrite['slug'] ) )
			? (string) $tax->rewrite['slug']
			: 'list_in';

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

		$term = get_term_by( 'slug', sanitize_title( $slug ), 'list_in' );
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
	 * list_in terms with "List in By Topic menu" checked, ordered for display.
	 *
	 * Order follows the **By Topic** nav menu (Appearance → Menus, menu name matches
	 * {@see ELUMINATE_BY_TOPIC_SYNC_MENU_NAME}): top-level items in menu order that map to checked
	 * terms. Any checked term missing from that menu is listed after, sorted by name. If nothing is
	 * ordered from that menu, falls back to the "List in Section Tags" theme location if assigned, then name.
	 *
	 * @return WP_Term[]
	 */
	function eluminate_standalone_get_by_topic_ordered_terms(): array {
		$toggled_terms = get_terms(
			array(
				'taxonomy'   => 'list_in',
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
					$tid = eluminate_standalone_nav_menu_item_list_in_term_id( $item );
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
						$tid = eluminate_standalone_nav_menu_item_list_in_term_id( $item );
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
	 * Returns WP-admin toggled list_in terms for the "By Topic" submenu.
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

if ( ! function_exists( 'eluminate_standalone_sync_list_in_nav_menu' ) ) {
	/**
	 * Keeps the "By Topic" nav menu aligned with By Topic checkbox state without resetting drag order.
	 *
	 * Adds taxonomy links for newly checked terms (appended). Removes entries for unchecked or
	 * deleted terms, non–list_in top-level links, children, and duplicates. After changes, reapplies
	 * the previous top-level order (WordPress often renumbers menu_order when a new item is inserted).
	 * Front-end order follows this menu (see eluminate_standalone_get_by_topic_ordered_terms()).
	 *
	 * @return void
	 */
	function eluminate_standalone_sync_list_in_nav_menu(): void {
		static $syncing = false;
		if ( $syncing ) {
			return;
		}
		$syncing = true;

		try {
			if ( ! function_exists( 'wp_update_nav_menu_item' ) || ! function_exists( 'wp_delete_nav_menu_item' ) ) {
				require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
			}

			$menu_id = eluminate_standalone_ensure_list_in_nav_menu_exists();
			if ( $menu_id <= 0 ) {
				return;
			}

			$checked_terms = get_terms(
				array(
					'taxonomy'   => 'list_in',
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

			// Before any edits, record top-level order of checked list_in rows. Core often renumbers
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
					$tid = eluminate_standalone_nav_menu_item_list_in_term_id( $item );
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
				$tid = eluminate_standalone_nav_menu_item_list_in_term_id( $item );
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
				$t = get_term( (int) $mid, 'list_in' );
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
						'menu-item-object'     => 'list_in',
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
					$tid = eluminate_standalone_nav_menu_item_list_in_term_id( $item );
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
				$at = get_term( (int) $aid, 'list_in' );
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
				$term = get_term( $tid, 'list_in' );
				if ( ! $term instanceof WP_Term ) {
					continue;
				}
				wp_update_nav_menu_item(
					$menu_id,
					$tid_to_db[ $tid ],
					array(
						'menu-item-title'      => $term->name,
						'menu-item-object'     => 'list_in',
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

if ( ! function_exists( 'eluminate_standalone_maybe_migrate_list_in_nav_menu_sync' ) ) {
	/**
	 * One-time sync of the By Topic menu after this behavior ships (admin only).
	 *
	 * @return void
	 */
	function eluminate_standalone_maybe_migrate_list_in_nav_menu_sync(): void {
		if ( '1' === get_option( 'eluminate_list_in_menu_by_topic_sync_v1', '' ) ) {
			return;
		}
		eluminate_standalone_sync_list_in_nav_menu();
		update_option( 'eluminate_list_in_menu_by_topic_sync_v1', '1', false );
	}
}
add_action( 'admin_init', 'eluminate_standalone_maybe_migrate_list_in_nav_menu_sync', 5 );
add_action( 'created_list_in', 'eluminate_standalone_sync_list_in_nav_menu', 20 );
add_action( 'edited_list_in', 'eluminate_standalone_sync_list_in_nav_menu', 20 );
add_action(
	'delete_term',
	static function ( $term_id, $tt_id, $taxonomy ): void {
		if ( 'list_in' !== $taxonomy ) {
			return;
		}
		eluminate_standalone_sync_list_in_nav_menu();
	},
	20,
	3
);

/**
 * list_in hooks do not run when only the mirror nav menu changes; repopulate after By Topic menu save / removal.
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
		eluminate_standalone_sync_list_in_nav_menu();
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
				eluminate_standalone_sync_list_in_nav_menu();
			},
			1
		);
	},
	20,
	2
);

if ( ! function_exists( 'eluminate_standalone_list_in_add_by_topic_field' ) ) {
	/**
	 * Renders "List in By Topic menu" checkbox on add term form.
	 *
	 * @return void
	 */
	function eluminate_standalone_list_in_add_by_topic_field(): void {
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
add_action( 'list_in_add_form_fields', 'eluminate_standalone_list_in_add_by_topic_field' );

if ( ! function_exists( 'eluminate_standalone_list_in_edit_by_topic_field' ) ) {
	/**
	 * Renders "List in By Topic menu" checkbox on edit term form.
	 *
	 * @param WP_Term $term Current term.
	 *
	 * @return void
	 */
	function eluminate_standalone_list_in_edit_by_topic_field( WP_Term $term ): void {
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
add_action( 'list_in_edit_form_fields', 'eluminate_standalone_list_in_edit_by_topic_field' );

if ( ! function_exists( 'eluminate_standalone_save_list_in_by_topic_field' ) ) {
	/**
	 * Persists "By Topic menu" term toggle.
	 *
	 * @param int $term_id Term ID.
	 *
	 * @return void
	 */
	function eluminate_standalone_save_list_in_by_topic_field( int $term_id ): void {
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
add_action( 'created_list_in', 'eluminate_standalone_save_list_in_by_topic_field' );
add_action( 'edited_list_in', 'eluminate_standalone_save_list_in_by_topic_field' );

if ( ! function_exists( 'eluminate_standalone_list_in_term_ids_mapped_by_pages' ) ) {
	/**
	 * Returns list_in term IDs referenced by at least one page's "List in Section mapping".
	 *
	 * Result is cached for the request.
	 *
	 * @return int[]
	 */
	function eluminate_standalone_list_in_term_ids_mapped_by_pages(): array {
		static $cached = null;
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin terms table; single query per request.
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT CAST(pm.meta_value AS UNSIGNED)
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = %s
					AND p.post_status NOT IN ('trash','auto-draft')
				WHERE pm.meta_key = %s
				AND pm.meta_value NOT IN ('','0')",
				'page',
				'_eluminate_list_in_term_id'
			)
		);

		$ids = array();
		foreach ( (array) $rows as $row ) {
			$tid = (int) $row;
			if ( $tid > 0 ) {
				$ids[] = $tid;
			}
		}
		$cached = array_values( array_unique( $ids ) );
		return $cached;
	}
}

if ( ! function_exists( 'eluminate_standalone_list_in_build_listed_in_label' ) ) {
	/**
	 * Builds the admin "Listed In" cell text for list_in terms.
	 *
	 * Order: Homepage (slug `featured`), By Topic, Shows — joined with " / ". Empty → Unlisted.
	 *
	 * @param string $slug     Term slug.
	 * @param bool   $by_topic Whether "List in By Topic menu" is enabled.
	 * @param bool   $mapped   Whether a page maps to this term.
	 *
	 * @return string Unescaped label (escape when outputting HTML).
	 */
	function eluminate_standalone_list_in_build_listed_in_label( string $slug, bool $by_topic, bool $mapped ): string {
		$parts = array();
		if ( 'featured' === $slug ) {
			$parts[] = __( 'Homepage', 'eluminate-standalone' );
		}
		if ( $by_topic ) {
			$parts[] = __( 'By Topic', 'eluminate-standalone' );
		}
		if ( $mapped ) {
			$parts[] = __( 'Shows', 'eluminate-standalone' );
		}
		if ( empty( $parts ) ) {
			return __( 'Unlisted', 'eluminate-standalone' );
		}
		return implode( ' / ', $parts );
	}
}

if ( ! function_exists( 'eluminate_standalone_list_in_columns_by_topic_submenu' ) ) {
	/**
	 * Inserts the "Listed In" column between Slug and Count (posts) on the list_in terms list.
	 *
	 * @param array<string, string> $columns Column slug => heading.
	 * @return array<string, string>
	 */
	function eluminate_standalone_list_in_columns_by_topic_submenu( array $columns ): array {
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
add_filter( 'manage_edit-list_in_columns', 'eluminate_standalone_list_in_columns_by_topic_submenu', 20 );

if ( ! function_exists( 'eluminate_standalone_list_in_custom_column_by_topic_submenu' ) ) {
	/**
	 * Returns Listed In labels for the list_in terms table (core uses apply_filters for this hook).
	 *
	 * Homepage: term slug is `featured`. By Topic / Shows as before; multiple segments joined with " / ".
	 *
	 * @param string $output      Default empty output.
	 * @param string $column_name Column key.
	 * @param int|string $term_id Term ID.
	 *
	 * @return string
	 */
	function eluminate_standalone_list_in_custom_column_by_topic_submenu( string $output, string $column_name, $term_id ): string {
		if ( 'by_topic_submenu' !== $column_name ) {
			return $output;
		}
		$tid = (int) $term_id;
		if ( $tid <= 0 ) {
			return $output;
		}
		$term = get_term( $tid, 'list_in' );
		if ( ! $term instanceof WP_Term ) {
			return $output;
		}
		$by_topic = (int) get_term_meta( $tid, '_eluminate_by_topic_menu', true ) === 1;
		$mapped   = in_array( $tid, eluminate_standalone_list_in_term_ids_mapped_by_pages(), true );
		$label    = eluminate_standalone_list_in_build_listed_in_label( (string) $term->slug, $by_topic, $mapped );
		return esc_html( $label );
	}
}
add_filter( 'manage_list_in_custom_column', 'eluminate_standalone_list_in_custom_column_by_topic_submenu', 10, 3 );

if ( ! function_exists( 'eluminate_standalone_list_in_sortable_listed_in_column' ) ) {
	/**
	 * Registers the Listed In column as sortable (same screen id convention as manage_edit-list_in_columns).
	 *
	 * @param array<string, string|mixed[]> $sortable Columns keyed by slug.
	 * @return array<string, string|mixed[]>
	 */
	function eluminate_standalone_list_in_sortable_listed_in_column( array $sortable ): array {
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
add_filter( 'manage_edit-list_in_sortable_columns', 'eluminate_standalone_list_in_sortable_listed_in_column', 20 );

if ( ! function_exists( 'eluminate_standalone_list_in_terms_clauses_order_listed_in' ) ) {
	/**
	 * Orders list_in admin table by Listed In labels (aligned with translated cell text).
	 *
	 * @param array<string, string> $clauses Terms query clauses.
	 * @param string[]               $taxonomies Taxonomies in the query.
	 * @param array<string, mixed>  $args get_terms-style arguments.
	 * @return array<string, string>
	 */
	function eluminate_standalone_list_in_terms_clauses_order_listed_in( array $clauses, array $taxonomies, array $args ): array {
		if ( empty( $args['orderby'] ) || 'by_topic_submenu' !== $args['orderby'] ) {
			return $clauses;
		}
		if ( ! in_array( 'list_in', $taxonomies, true ) ) {
			return $clauses;
		}
		if ( ! is_admin() ) {
			return $clauses;
		}

		global $wpdb;

		if ( strpos( $clauses['join'], 'elbto.term_id' ) === false ) {
			$clauses['join'] .= " LEFT JOIN {$wpdb->termmeta} AS elbto ON ( t.term_id = elbto.term_id AND elbto.meta_key = '_eluminate_by_topic_menu' )";
		}

		if ( strpos( $clauses['join'], 'ellimap.map_tid' ) === false ) {
			$key = esc_sql( '_eluminate_list_in_term_id' );
			$clauses['join'] .= " LEFT JOIN (
				SELECT DISTINCT CAST(pm.meta_value AS UNSIGNED) AS map_tid
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'page'
					AND p.post_status NOT IN ('trash','auto-draft')
				WHERE pm.meta_key = '{$key}'
				AND pm.meta_value NOT IN ('','0')
			) AS ellimap ON ellimap.map_tid = t.term_id";
		}

		$sort_dummy_slug = '__non_featured__';
		$l_hp_bt_sh       = eluminate_standalone_list_in_build_listed_in_label( 'featured', true, true );
		$l_hp_bt          = eluminate_standalone_list_in_build_listed_in_label( 'featured', true, false );
		$l_hp_sh          = eluminate_standalone_list_in_build_listed_in_label( 'featured', false, true );
		$l_hp             = eluminate_standalone_list_in_build_listed_in_label( 'featured', false, false );
		$l_bt_sh          = eluminate_standalone_list_in_build_listed_in_label( $sort_dummy_slug, true, true );
		$l_bt             = eluminate_standalone_list_in_build_listed_in_label( $sort_dummy_slug, true, false );
		$l_sh             = eluminate_standalone_list_in_build_listed_in_label( $sort_dummy_slug, false, true );
		$l_un             = eluminate_standalone_list_in_build_listed_in_label( $sort_dummy_slug, false, false );

		$label_fragment = sprintf(
			"CASE
WHEN t.slug = 'featured' AND COALESCE(elbto.meta_value, '') = '1' AND ellimap.map_tid IS NOT NULL THEN '%s'
WHEN t.slug = 'featured' AND COALESCE(elbto.meta_value, '') = '1' THEN '%s'
WHEN t.slug = 'featured' AND ellimap.map_tid IS NOT NULL THEN '%s'
WHEN t.slug = 'featured' THEN '%s'
WHEN COALESCE(elbto.meta_value, '') = '1' AND ellimap.map_tid IS NOT NULL THEN '%s'
WHEN COALESCE(elbto.meta_value, '') = '1' THEN '%s'
WHEN ellimap.map_tid IS NOT NULL THEN '%s'
ELSE '%s' END",
			esc_sql( $l_hp_bt_sh ),
			esc_sql( $l_hp_bt ),
			esc_sql( $l_hp_sh ),
			esc_sql( $l_hp ),
			esc_sql( $l_bt_sh ),
			esc_sql( $l_bt ),
			esc_sql( $l_sh ),
			esc_sql( $l_un )
		);

		$dir = ( isset( $args['order'] ) && 'desc' === strtolower( (string) $args['order'] ) ) ? 'DESC' : 'ASC';

		$clauses['orderby'] = "ORDER BY {$label_fragment} {$dir}, t.name ASC";
		$clauses['order']   = '';

		return $clauses;
	}
}
add_filter( 'terms_clauses', 'eluminate_standalone_list_in_terms_clauses_order_listed_in', 10, 3 );

if ( ! function_exists( 'eluminate_standalone_by_topic_terms_toggled_count' ) ) {
	/**
	 * Returns number of list_in terms enabled for By Topic submenu.
	 *
	 * @return int
	 */
	function eluminate_standalone_by_topic_terms_toggled_count(): int {
		$terms = get_terms(
			array(
				'taxonomy'   => 'list_in',
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

		$is_list_in_taxonomy_screen = 'edit-tags' === $screen->base && 'list_in' === $screen->taxonomy;
		$is_theme_page_screen       = 'page' === $screen->post_type && in_array( $screen->base, array( 'post', 'edit' ), true );
		if ( ! $is_list_in_taxonomy_screen && ! $is_theme_page_screen ) {
			return;
		}

		if ( eluminate_standalone_by_topic_terms_toggled_count() > 0 ) {
			return;
		}

		$manage_terms_url = admin_url( 'edit-tags.php?taxonomy=list_in&post_type=video_series' );
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php echo esc_html__( 'By Topic submenu is currently empty: no "List in Section" terms are enabled for it.', 'eluminate-standalone' ); ?>
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

if ( ! function_exists( 'eluminate_standalone_get_page_list_in_term_id' ) ) {
	/**
	 * Returns mapped list_in term id for a page.
	 *
	 * @param int $page_id Page ID.
	 *
	 * @return int
	 */
	function eluminate_standalone_get_page_list_in_term_id( int $page_id ): int {
		$term_id = (int) get_post_meta( $page_id, '_eluminate_list_in_term_id', true );
		return $term_id > 0 ? $term_id : 0;
	}
}

if ( ! function_exists( 'eluminate_standalone_get_page_list_in_term' ) ) {
	/**
	 * Resolve list_in term for a shows child page.
	 *
	 * Priority:
	 * 1) Explicit page-level mapping field.
	 * 2) Fallback to page slug matching a list_in term slug.
	 *
	 * @param WP_Post $page Page object.
	 *
	 * @return WP_Term|null
	 */
	function eluminate_standalone_get_page_list_in_term( WP_Post $page ): ?WP_Term {
		$mapped_term_id = eluminate_standalone_get_page_list_in_term_id( (int) $page->ID );
		if ( $mapped_term_id > 0 ) {
			$mapped_term = get_term( $mapped_term_id, 'list_in' );
			if ( $mapped_term instanceof WP_Term ) {
				return $mapped_term;
			}
		}

		$fallback_slug = sanitize_title( (string) $page->post_name );
		if ( '' === $fallback_slug ) {
			return null;
		}
		$fallback_term = get_term_by( 'slug', $fallback_slug, 'list_in' );
		if ( $fallback_term instanceof WP_Term ) {
			return $fallback_term;
		}

		return null;
	}
}

if ( ! function_exists( 'eluminate_standalone_add_page_list_in_mapping_meta_box' ) ) {
	/**
	 * Adds a list_in mapping meta box to page editor.
	 *
	 * @return void
	 */
	function eluminate_standalone_add_page_list_in_mapping_meta_box(): void {
		add_meta_box(
			'eluminate-list-in-mapping',
			__( 'List in Section mapping', 'eluminate-standalone' ),
			'eluminate_standalone_render_page_list_in_mapping_meta_box',
			'page',
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes_page', 'eluminate_standalone_add_page_list_in_mapping_meta_box' );

if ( ! function_exists( 'eluminate_standalone_render_page_list_in_mapping_meta_box' ) ) {
	/**
	 * Renders the page-level list_in mapping select.
	 *
	 * @param WP_Post $post Current page post.
	 *
	 * @return void
	 */
	function eluminate_standalone_render_page_list_in_mapping_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'eluminate_list_in_mapping_save', 'eluminate_list_in_mapping_nonce' );
		$selected_term_id = eluminate_standalone_get_page_list_in_term_id( (int) $post->ID );
		$terms            = get_terms(
			array(
				'taxonomy'   => 'list_in',
				'hide_empty' => false,
			)
		);
		?>
		<p><?php echo esc_html__( 'Select which List in Section term should populate this page.', 'eluminate-standalone' ); ?></p>
		<label class="screen-reader-text" for="eluminate-list-in-term-id"><?php echo esc_html__( 'List in Section term', 'eluminate-standalone' ); ?></label>
		<select id="eluminate-list-in-term-id" name="eluminate_list_in_term_id" style="width:100%;">
			<option value="0"><?php echo esc_html__( 'No mapping', 'eluminate-standalone' ); ?></option>
			<?php
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) :
				foreach ( $terms as $term ) :
					if ( ! $term instanceof WP_Term ) {
						continue;
					}
					?>
					<option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php selected( $selected_term_id, (int) $term->term_id ); ?>>
						<?php echo esc_html( $term->name ); ?>
					</option>
					<?php
				endforeach;
			endif;
			?>
		</select>
		<?php
		if ( eluminate_standalone_is_shows_child_page( (int) $post->ID ) ) :
			?>
			<p><em><?php echo esc_html__( 'This page is in Main Page > Shows; mapped videos will replace page content on the frontend.', 'eluminate-standalone' ); ?></em></p>
			<?php
		else :
			?>
			<p><em><?php echo esc_html__( 'Mapping is used when the page is under Main Page > Shows.', 'eluminate-standalone' ); ?></em></p>
			<?php
		endif;
	}
}

if ( ! function_exists( 'eluminate_standalone_save_page_list_in_mapping_meta_box' ) ) {
	/**
	 * Persists page-level list_in mapping.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	function eluminate_standalone_save_page_list_in_mapping_meta_box( int $post_id ): void {
		if ( ! isset( $_POST['eluminate_list_in_mapping_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['eluminate_list_in_mapping_nonce'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! wp_verify_nonce( $nonce, 'eluminate_list_in_mapping_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw_term_id = isset( $_POST['eluminate_list_in_term_id'] ) ? wp_unslash( $_POST['eluminate_list_in_term_id'] ) : '0'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$term_id     = (int) $raw_term_id;
		if ( $term_id <= 0 ) {
			delete_post_meta( $post_id, '_eluminate_list_in_term_id' );
			return;
		}

		$term = get_term( $term_id, 'list_in' );
		if ( ! ( $term instanceof WP_Term ) ) {
			delete_post_meta( $post_id, '_eluminate_list_in_term_id' );
			return;
		}

		update_post_meta( $post_id, '_eluminate_list_in_term_id', (int) $term->term_id );
	}
}
add_action( 'save_post_page', 'eluminate_standalone_save_page_list_in_mapping_meta_box' );

if ( ! function_exists( 'eluminate_standalone_render_list_in_videos_for_term' ) ) {
	/**
	 * Renders video cards for a specific list_in term.
	 *
	 * @param WP_Term $term list_in term.
	 *
	 * @return string
	 */
	function eluminate_standalone_render_list_in_videos_for_term( WP_Term $term ): string {
		if ( 'list_in' !== $term->taxonomy ) {
			return '';
		}
		if ( class_exists( 'Niztech_Youtube' ) && ! class_exists( 'Niztech_Youtube_Client' ) ) {
			$path_to_plugins = join( DIRECTORY_SEPARATOR, array( WP_PLUGIN_DIR, 'niztech-youtube', 'class-niztech-youtube-client.php' ) );
			if ( file_exists( $path_to_plugins ) ) {
				include_once $path_to_plugins;
			}
		}

		$paged = max( 1, get_query_var( 'paged' ), get_query_var( 'page' ) );
		$query = new WP_Query(
			array(
				'post_type'      => 'video_series',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'paged'          => $paged,
				'tax_query'      => array(
					array(
						'taxonomy' => 'list_in',
						'field'    => 'term_id',
						'terms'    => array( (int) $term->term_id ),
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return '';
		}

		ob_start();
		$rendered_videos = 0;
		echo '<section class="shows-page-videos">';
		while ( $query->have_posts() ) {
			$query->the_post();
			if ( ! class_exists( 'Niztech_Youtube_Client' ) ) {
				continue;
			}
			$video_data = Niztech_Youtube_Client::video_content( get_the_ID() );
			if ( empty( $video_data ) ) {
				continue;
			}
			$first_video_data = $video_data[0];
			$rendered_videos += 1;
			echo '<article class="video-series-entry">';
			get_template_part(
				'template-parts/video_series',
				'poop',
				array(
					'video'     => $first_video_data,
					'shortlink' => wp_get_shortlink( get_the_ID() ),
				)
			);
			if ( count( $video_data ) > 0 ) {
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
		echo wp_kses_post(
			get_the_posts_pagination(
				array(
					'total'   => $query->max_num_pages,
					'current' => $paged,
				)
			)
		);
		echo '</section>';
		wp_reset_postdata();
		if ( 0 === $rendered_videos ) {
			ob_end_clean();
			return '';
		}
		return (string) ob_get_clean();
	}
}

add_filter(
	'the_content',
	function ( string $content ): string {
		if ( is_admin() || ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post = get_post();
		if ( ! ( $post instanceof WP_Post ) ) {
			return $content;
		}

		if ( ! eluminate_standalone_is_shows_child_page( (int) $post->ID ) ) {
			return $content;
		}

		$resolved_term = eluminate_standalone_get_page_list_in_term( $post );
		if ( ! ( $resolved_term instanceof WP_Term ) ) {
			return $content;
		}

		$videos_markup = eluminate_standalone_render_list_in_videos_for_term( $resolved_term );
		if ( '' === $videos_markup ) {
			return $content;
		}

		return $videos_markup;
	},
	20
);


/*
 * Add custom taxonomy terms
 */
if ( ! function_exists( 'eluminate_standalone_prefill_taxonomies_init' ) ) {
	/**
	 * Seeds default list_in taxonomy terms (not the Appearance → Menus "By Topic" object; that menu is synced separately).
	 *
	 * @return void
	 */
	function eluminate_standalone_prefill_taxonomies_init(): void {
		$terms = array(
			array(
				'term'     => __( 'Popular shows', 'eluminate-standalone' ),
				'taxonomy' => 'list_in',
				'args'     => array(
					'description' => __( 'Shows that we may want to highlight', 'eluminate-standalone' ),
					'slug'        => 'popular',
				),
			),
			array(
				'term'     => __( 'Health', 'eluminate-standalone' ),
				'taxonomy' => 'list_in',
				'args'     => array(
					'description' => __( 'Relate with health', 'eluminate-standalone' ),
					'slug'        => 'health',
				),
			),
			array(
				'term'     => __( 'Business', 'eluminate-standalone' ),
				'taxonomy' => 'list_in',
				'args'     => array(
					'description' => __( 'Relate with business', 'eluminate-standalone' ),
					'slug'        => 'business',
				),
			),
			array(
				'term'     => __( 'Science / Technology', 'eluminate-standalone' ),
				'taxonomy' => 'list_in',
				'args'     => array(
					'description' => __( 'Relate with science and technology', 'eluminate-standalone' ),
					'slug'        => 'science-technology',
				),
			),
			array(
				'term'     => __( 'PSA / Promo', 'eluminate-standalone' ),
				'taxonomy' => 'list_in',
				'args'     => array(
					'description' => __( 'Promoting or providing public service announcements', 'eluminate-standalone' ),
					'slug'        => 'psa-promo',
				),
			),
			array(
				'term'     => __( 'Housing / Community', 'eluminate-standalone' ),
				'taxonomy' => 'list_in',
				'args'     => array(
					'description' => __( 'Relate with housing and community', 'eluminate-standalone' ),
					'slug'        => 'community',
				),
			),
			array(
				'term'     => __( 'History / Culture', 'eluminate-standalone' ),
				'taxonomy' => 'list_in',
				'args'     => array(
					'description' => __( 'Relate with history and culture', 'eluminate-standalone' ),
					'slug'        => 'history-culture',
				),
			),
			array(
				'term'     => __( 'Law / Politics / Policy', 'eluminate-standalone' ),
				'taxonomy' => 'list_in',
				'args'     => array(
					'description' => __( 'Relate with law politics and policy', 'eluminate-standalone' ),
					'slug'        => 'politics-policy',
				),
			),
			array(
				'term'     => __( 'Family / Youth', 'eluminate-standalone' ),
				'taxonomy' => 'list_in',
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
	 * - Registers the "list_in" taxonomy adn related terms.
	 * - Registers the "video_series" post type.
	 * - Removes comments feature from "video_series" post type. .
	 *
	 * @return void
	 */
	function eluminate_standalone_register_post_type_init(): void {
		$labels = array(
			'name'                  => _x( 'Video Series', 'Post Type General Name', 'eluminate-standalone' ),
			'singular_name'         => _x( 'Video Series', 'Post Type Singular Name', 'eluminate-standalone' ),
			'menu_name'             => __( 'Video Series', 'eluminate-standalone' ),
			'name_admin_bar'        => __( 'Video Series', 'eluminate-standalone' ),
			'archives'              => __( 'Video Series Archives', 'eluminate-standalone' ),
			'attributes'            => __( 'Video Series Attributes', 'eluminate-standalone' ),
			'parent_item_colon'     => __( 'Parent Item:', 'eluminate-standalone' ),
			'all_items'             => __( 'All Video Series', 'eluminate-standalone' ),
			'add_new_item'          => __( 'Add New Video Series', 'eluminate-standalone' ),
			'add_new'               => __( 'Add New', 'eluminate-standalone' ),
			'new_item'              => __( 'New Video Series', 'eluminate-standalone' ),
			'edit_item'             => __( 'Edit Video Series', 'eluminate-standalone' ),
			'update_item'           => __( 'Update Video Series', 'eluminate-standalone' ),
			'view_item'             => __( 'View Video Series', 'eluminate-standalone' ),
			'view_items'            => __( 'View Items', 'eluminate-standalone' ),
			'search_items'          => __( 'Search Video Series', 'eluminate-standalone' ),
			'not_found'             => __( 'Not found', 'eluminate-standalone' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'eluminate-standalone' ),
			'featured_image'        => __( 'Featured Image', 'eluminate-standalone' ),
			'set_featured_image'    => __( 'Set featured image', 'eluminate-standalone' ),
			'remove_featured_image' => __( 'Remove featured image', 'eluminate-standalone' ),
			'use_featured_image'    => __( 'Use as featured image', 'eluminate-standalone' ),
			'insert_into_item'      => __( 'Insert into item', 'eluminate-standalone' ),
			'uploaded_to_this_item' => __( 'Uploaded to this item', 'eluminate-standalone' ),
			'items_list'            => __( 'Video Series list', 'eluminate-standalone' ),
			'items_list_navigation' => __( 'Items list navigation', 'eluminate-standalone' ),
			'filter_items_list'     => __( 'Filter Video Series list', 'eluminate-standalone' ),
		);
		$args   = array(
			'label'               => __( 'Video Series', 'eluminate-standalone' ),
			'description'         => __( 'Posts that show a series of videos', 'eluminate-standalone' ),
			'labels'              => $labels,
			'supports'            => array(
				'title',
				'revisions',
				'thumbnail',
			),
			'taxonomies'          => array( 'video_category', 'list_in' ),
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
		);
		register_post_type( 'video_series', $args );

		register_taxonomy(
			'list_in',
			array( 'video_series' ),
			array(
				'hierarchical'       => false,
				'label'              => __( 'List in Section', 'eluminate-standalone' ),
				'public'             => true,
				'rewrite'            => array(
					'slug'       => 'listing',
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
		remove_post_type_support( 'video_series', 'comments' );
	}
}

/**
 * Add Video Count column to Video Series admin list
 */
add_filter(
	'manage_video_series_posts_columns',
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
	'manage_video_series_posts_custom_column',
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
