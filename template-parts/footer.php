<footer class="layout-footer">
	Soroptimist International of Novato &copy; <?php echo date( 'Y' ); ?>
	<?php
	wp_nav_menu(
		array(
			'theme_location' => 'about-us',
			'container'      => false,
			'menu'           => 'about-us',
		)
	);
	wp_nav_menu(
		array(
			'theme_location' => 'social',
			'container'      => false,
			'menu'           => 'social',
		)
	);
	?>
</footer>
