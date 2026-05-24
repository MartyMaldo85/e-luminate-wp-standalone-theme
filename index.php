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
<?php get_template_part( 'template-parts/logo', 'menu-hint' ); ?>
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
