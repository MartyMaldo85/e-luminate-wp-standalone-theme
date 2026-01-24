<footer class="layout-footer">
	<h4 class="footer-title"><?php echo __( 'Contact us', 'eluminate-standalone' ); ?></h4>
	<div class="footer-contact">
		<?php
		$mailing_address = get_theme_mod( 'eluminate_standalone_mailing_address' );
		if ( ! empty( $mailing_address ) ) {
			echo '<address class="mailing-address">';
			echo wp_kses_post( $mailing_address );
			echo '</address>';
		}

		wp_nav_menu(
			array(
				'class'          => 'social',
				'theme_location' => 'social',
				'container'      => false,
				'menu'           => 'social',
			)
		);
		?>
	</div>

	<?php
	wp_nav_menu(
		array(
			'menu_class'     => 'footer-menu',
			'theme_location' => 'footer',
			'container'      => false,
			'menu'           => 'footer',
		)
	);
	?>
	<div class="logos">
		<?php get_template_part( 'assets/soroptimist-international-of-novato-logo-mono' ); ?>
		<?php get_template_part( 'assets/soroptimist-international-logo-mono' ); ?>
	</div>
	<p class="copyright">&copy; <?php echo gmdate( 'Y' ); ?> Soroptimist International of Novato </p>
	<div class="break">&nbsp;</div>
</footer>
