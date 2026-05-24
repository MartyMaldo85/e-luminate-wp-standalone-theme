<?php
/**
 * Template Name: Generic page header template.
 *
 * @category   Theme
 * @package eluminate-standalone
 * @author     Nazario A. Ayala <nazario@niztech.com>
 * @license    opensource.org MIT License
 * @link       https://www.niztech.com
 * @since      0.0.1
 */

$logo = trailingslashit( get_template_directory_uri() ) . 'assets/e-luminate-logo.svg';
$orbit_page_tree = eluminate_standalone_get_orbit_menu_tree();
$by_topic_terms  = eluminate_standalone_get_by_topic_menu_terms();
?>

<div class="header-logo-cluster">
	<div class="header-logo-cluster__spin-pane">
		<h1 class="body-header">
			<a class="logo" href="#" onclick="event.preventDefault(); event.stopPropagation(); if (typeof toggleLogoMenu === 'function') { toggleLogoMenu(); }">
				<img alt="<?php echo esc_attr__( 'Open menu', 'eluminate-standalone' ); ?> — <?php echo esc_attr__( 'e-luminate', 'eluminate-standalone' ); ?>" src="<?php echo esc_url( $logo ); ?>" decoding="async" />
			</a>
		</h1>
		<div class="orbit-section-title" id="orbit-section-title" data-title="">
			<span class="orbit-section-title__main"></span>
			<span class="orbit-section-title__divider" aria-hidden="true"></span>
			<span class="orbit-section-title__sub"></span>
			<span class="visually-hidden" id="orbit-section-title-live" aria-live="polite"></span>
		</div>
		<div class="orbit-submenu orbit-submenu--pages" id="orbit-submenu-pages" aria-label="<?php echo esc_attr__( 'Page submenu', 'eluminate-standalone' ); ?>">
			<?php for ( $i = 0; $i < 8; $i++ ) : ?>
				<button type="button" class="btn btn-orbit btn-submenu btn-page-orbit btn-orbit--phantom" data-page-slot="<?php echo esc_attr( (string) $i ); ?>" style="--orbit-i: <?php echo esc_attr( (string) $i ); ?>" aria-hidden="true"></button>
			<?php endfor; ?>
		</div>
		<script>
			window.eluminatePageMenuTree = <?php echo wp_json_encode( $orbit_page_tree ); ?>;
			window.eluminateOrbitTopicTerms = <?php echo wp_json_encode( $by_topic_terms ); ?>;
			window.eluminateByTopicTerms = window.eluminateOrbitTopicTerms;
		</script>
		<?php get_template_part( 'template-parts/orbit', 'hub' ); ?>
	</div>
</div>
