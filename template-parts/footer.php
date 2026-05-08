<footer class="layout-footer">
	<p class="copyright copyright--mailing">
		<?php echo esc_html( 'Soroptimist International of Novato' ); ?><br />
		<?php echo esc_html( 'PO Box 1267' ); ?><br />
		<?php echo esc_html( 'Novato, CA 94948' ); ?>
	</p>
	<div class="logos">
		<?php get_template_part( 'assets/soroptimist-international-of-novato-logo-mono' ); ?>
		<?php get_template_part( 'assets/soroptimist-international-logo-mono' ); ?>
	</div>
	<p class="copyright">&copy; <?php echo gmdate( 'Y' ); ?> Soroptimist International of Novato </p>
	<?php
	if ( has_nav_menu( 'footer' ) ) {
		?>
	<nav class="footer-menu" aria-label="<?php echo esc_attr__( 'Footer', 'eluminate-standalone' ); ?>">
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'footer',
				'container'      => false,
				'menu_class'     => 'menu',
				'depth'          => 1,
				'fallback_cb'    => false,
			)
		);
		?>
	</nav>
		<?php
	}
	?>
	<div class="break">&nbsp;</div>
</footer>
