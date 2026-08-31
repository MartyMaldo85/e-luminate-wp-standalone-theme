<footer class="layout-footer">
	<?php
	$mailing_address = get_theme_mod( 'eluminate_standalone_mailing_address' );
	if ( ! empty( $mailing_address ) ) {
		echo '<address class="copyright copyright--mailing mailing-address">';
		echo wp_kses_post( $mailing_address );
		echo '</address>';
	}
	?>
	<div class="logos">
		<a class="logos__link" href="https://www.soropnovato.org" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr__( 'Soroptimist International of Novato', 'eluminate-standalone' ); ?>">
			<?php get_template_part( 'assets/soroptimist-international-of-novato-logo-mono' ); ?>
		</a>
		<a class="logos__link" href="https://www.soroptimistinternational.org" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr__( 'Soroptimist International', 'eluminate-standalone' ); ?>">
			<?php get_template_part( 'assets/soroptimist-international-logo-mono' ); ?>
		</a>
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
