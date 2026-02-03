<?php
/**
 * FIle Name: functions.php
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
const THEME_VERSION = 4;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'Niztech_Youtube' ) ) {
	$path_to_plugins = join( DIRECTORY_SEPARATOR, array( WP_PLUGIN_DIR, 'niztech-youtube', 'class-niztech-youtube-client.php' ) );
	include_once $path_to_plugins;
}

/**
 * Enqueue styles.
 * and scripts
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'style', get_stylesheet_uri() );
	}
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
		eluminate_standalone_menu_list_in_init();

		eluminate_standalone_prefill_taxonomies_init();

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

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'parent',
			trailingslashit( get_template_directory_uri() ) . 'style.css'
		);
	},
	2
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


if ( ! function_exists( 'eluminate_video_series_html' ) ) {
	/**
	 * Generates the html to display.
	 *
	 * @param array $video_series_data array of video_series objects.
	 * @param array $options extra parameters: id, class, hide_others, hide_title.
	 *
	 * @return string Html string.
	 */
	function eluminate_video_series_html( array $video_series_data, array $options = array() ): string {
		$section_attribute_html[] = isset( $options['id'] ) ? 'id="' . $options['id'] . '"' : '';
		$section_attribute_html[] = isset( $options['class'] ) ? 'class="' . $options['class'] . '"' : '';
		$html                     = '<section ' . join( ' ', $section_attribute_html ) . '>';
		$hide_title               = boolval( $options['hide_title'] ?? false );
		foreach ( $video_series_data as $series ) {
			$videos                 = $series['video_data'] ?? array();
			$class                  = $options['class'] ?? null;
			$article_attribute_html = $class ? ' class="' . $class . '-series" ' : 'class="series"';
			$html                  .= '<article ' . $article_attribute_html . '>';
			if ( ! $hide_title && isset( $series['post_title'] ) ) {
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
				'class'       => null,
				'hide_others' => false,
				'hide_title'  => false,
				'id'          => null,
				'limit'       => 20,
			),
			$attr
		);

		// Get the data.
		$data = eluminate_recent_video_series_data( $a['limit'] );
		// Generate the html.
		return eluminate_video_series_html(
			$data,
			array(
				'class'       => $a['class'],
				'hide_others' => $a['hide_others'],
				'hide_title'  => $a['hide_title'],
				'id'          => $a['id'],
			)
		);
	}
);

add_shortcode(
	'eluminate-featured',
	function ( $attr ) {
		$a = shortcode_atts(
			array(
				'class'       => null,
				'hide_others' => false,
				'hide_title'  => false,
				'id'          => null,
				'limit'       => 3,
			),
			$attr
		);

		// Get the data.
		$data = eluminate_featured_video_series_data( $a['limit'] );
		// Generate the html.
		return eluminate_video_series_html(
			$data,
			array(
				'class'       => $a['class'],
				'hide_others' => $a['hide_others'],
				'hide_title'  => $a['hide_title'],
				'id'          => $a['id'],
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


if ( ! function_exists( 'eluminate_standalone_menu_list_in_init' ) ) {
	/**
	 * Generates and adds "list_in" menu items from "list_in" taxonomy
	 * It will omit popular as that appears in a separate menu.
	 * If the "list_in" menu does not exist, it creates it.
	 *
	 * @return void
	 */
	function eluminate_standalone_menu_list_in_init(): void {
		$init_version = get_option( THEME_KEY . '_init_version_run', 0 );
		if ( $init_version >= THEME_VERSION ) {
			return;
		}

		// Check if the menu already exists...
		$menu_name   = 'List In';
		$menu_exists = wp_get_nav_menu_object( $menu_name );

		if ( $menu_exists ) {
			$menu_id = $menu_exists->term_id;
		} else {
			$menu_id = wp_create_nav_menu( $menu_name );
		}

		// Create the menu...

		$args = array(
			'taxonomy'   => 'list_in',
			'hide_empty' => false,
		);

		$list_in_terms = get_terms( $args );

		if ( ! empty( $list_in_terms ) && ! is_wp_error( $list_in_terms ) ) {
			foreach ( $list_in_terms as $term ) {
				if ( 'popular' !== $term->slug ) {
					wp_update_nav_menu_item(
						$menu_id,
						0,
						array(
							'menu-item-title'  => $term->name,
							'menu-item-url'    => home_url( "/listing/{$term->slug}" ),
							'menu-item-status' => 'publish',
						)
					);
				}
			}
		}
		wp_nav_menu(
			array(
				'menu'           => $menu_id,
				'theme_location' => 'list-in',
			)
		);
	}
}

/**
 * Flush rewrite rules on theme activation to ensure custom post type permalinks work.
 */
add_action(
	'after_switch_theme',
	function () {
		flush_rewrite_rules();
	}
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
				'about-us' => __( 'About us', 'eluminate-standalone' ),
				'list-in'  => __( 'List in', 'eluminate-standalone' ),
				'shows'    => __( 'Shows', 'eluminate-standalone' ),
				'social'   => __( 'Social Menu', 'eluminate-standalone' ),
				'footer'   => __( 'Footer Menu', 'eluminate-standalone' ),
			)
		);
	}
}


/*
 * Add custom taxonomy terms
 */
if ( ! function_exists( 'eluminate_standalone_prefill_taxonomies_init' ) ) {
	/**
	 * Prefills "list_in" menu with terms.
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
		register_taxonomy(
			'list_in',
			array( 'video_series' ),
			array(
				'hierarchical'       => false,
				'label'              => __( 'List in section', 'eluminate-standalone' ),
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

		// Removes comments from the post types we created.
		remove_post_type_support( 'video_series', 'comments' );
	}
}

/**
 * Add Video count column to Video Series admin list
 */
add_filter(
	'manage_video_series_posts_columns',
	function ( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				$new_columns['video_count'] = __( 'Video count', 'eluminate-standalone' );
			}
		}
		return $new_columns;
	}
);

/**
 * Populate Video count column values
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
				'default'           => '',
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
