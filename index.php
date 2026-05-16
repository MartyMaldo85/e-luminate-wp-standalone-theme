<?php
/**
 * Template Name: Default template index.php.
 *
 * Default template index.php.
 *
 * @category   Theme
 * @package eluminate-standalone
 * @author     Nazario A. Ayala <nazario@niztech.com>
 * @license    opensource.org MIT License
 * @link       https://www.niztech.com
 * @since      0.0.1
 */

get_template_part( 'template-parts/layout', 'start' );
get_template_part( 'template-parts/header' );
get_template_part( 'template-parts/layout', 'nav' );
get_template_part( 'template-parts/main', 'start' );
?>
<?php if ( is_front_page() ) : ?>
	<div id="logo-menu-hint" class="logo-menu-hint-wrap logo-menu-hint--in-content" role="status">
		<span class="logo-menu-hint__arrow" aria-hidden="true">
			<img
				src="<?php echo esc_url( get_theme_file_uri( 'assets/arrow.svg' ) ); ?>"
				alt=""
				width="30"
				height="30"
				decoding="async"
			/>
		</span>
		<p class="logo-menu-hint">
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s: the word "menu" (emphasized). */
					__( 'click logo for %s', 'eluminate-standalone' ),
					'<span class="logo-menu-hint__accent">' . esc_html__( 'menu', 'eluminate-standalone' ) . '</span>'
				)
			);
			?>
		</p>
	</div>
	<?php if ( ELUMINATE_LOGO_HINT_DISMISS_ENABLED ) : ?>
	<script>
		(function () {
			try {
				if ( localStorage.getItem( 'eluminate_logo_menu_hint_dismissed' ) === '1' ) {
					const el = document.getElementById( 'logo-menu-hint' );
					if ( el ) {
						el.remove();
					}
				}
			} catch ( e ) {}
		})();
	</script>
	<?php endif; ?>
<?php endif; ?>
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

get_template_part( 'template-parts/main', 'end' );
get_template_part( 'template-parts/footer' );
get_template_part( 'template-parts/layout', 'end' );
