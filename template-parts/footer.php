<?php
$logo = implode( DIRECTORY_SEPARATOR, array( get_template_directory_uri(), 'assets', 'soroptimist-logo.svg' ) )
?>

<footer class="layout-footer">
	<img src="<?php echo $logo; ?>" alt="" width="37" height="66" class="sorop-logo"/>
	<div>
		<p>Soroptimist International of Novato &copy; <?php echo gmdate( 'Y' ); ?></p>
		<?php
		wp_nav_menu(
			array(
				'class'          => 'social',
				'theme_location' => 'social',
				'container'      => false,
				'menu'           => 'social',
			)
		);
		wp_nav_menu(
			array(
				'class'          => 'footer',
				'theme_location' => 'footer',
				'container'      => false,
				'menu'           => 'footer',
			)
		);
		?>
	</div>
</footer>
