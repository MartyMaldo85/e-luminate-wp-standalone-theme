<script>
	function toggle(id) {
		const bodyNavElm = document.getElementById('body-nav')
		const currentlySelected = bodyNavElm.getAttribute('data-selected');

		document.querySelectorAll('#body-sub-nav > .menu').forEach(elm => elm.classList.remove('active'));
		document.querySelectorAll('#body-nav .btn-main-menu').forEach(elm => elm.classList.remove('active'));
		if (currentlySelected === id) {
			bodyNavElm.setAttribute('data-selected', '');
		} else {
			document.querySelector(`#body-sub-nav > .menu.${id}`).classList.add('active');
			document.querySelector(`#body-nav .btn-main-menu.${id}`).classList.add('active');
			bodyNavElm.setAttribute('data-selected', id);
		}
	}
</script>

<div class="body-nav" id="body-nav" data-selected="">
	<nav>
		<button class="btn btn-main-menu list-in" onclick="toggle('list-in')"><?php echo __( 'Shows', 'eluminate-standalone' ); ?></button>
		<button class="btn btn-main-menu about-us" onclick="toggle('about-us')"><?php echo __( 'About Us', 'eluminate-standalone' ); ?></button>
	</nav>

	<div id="body-sub-nav">
		<div class="about-us menu">
			<h2 class="title"><?php echo __( 'Page', 'eluminate-standalone' ); ?></h2>
			<section>
				<?php
				wp_nav_menu(
					array(
						'container'      => false,
						'menu'           => 'about-us',
						'menu_class'     => 'menu-list',
						'menu_id'        => '',
						'theme_location' => 'about-us',
					)
				);
				?>
			</section>
		</div>
		<div class="list-in menu">
			<section class="shows">
				<h2 class="title"><?php echo __( 'View', 'eluminate-standalone' ); ?></h2>
				<?php
				wp_nav_menu(
					array(
						'container'      => false,
						'menu'           => 'shows',
						'menu_class'     => 'menu-list',
						'menu_id'        => '',
						'theme_location' => 'shows',
					)
				);
				?>
			</section>
			<section class="topic">
				<h2 class="title"><?php echo __( 'By Topic', 'eluminate-standalone' ); ?></h2>
				<?php
				wp_nav_menu(
					array(
						'container'      => false,
						'menu'           => 'list-in',
						'menu_class'     => 'menu-list',
						'menu_id'        => '',
						'theme_location' => 'list-in',
					)
				);
				?>
			</section>
		</div>
	</div>
</div>
<?php
