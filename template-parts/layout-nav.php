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

<nav class="body-nav" id="body-nav" data-selected="">
	<button class="btn btn-main-menu list-in" onclick="toggle('list-in')">Shows</button>
	<button class="btn btn-main-menu about-us" onclick="toggle('about-us')">About Us</button>
</nav>

<div id="body-sub-nav">
	<div class="about-us menu">
		<section>
			<?php wp_nav_menu(
				array(
					'container'      => false,
					'menu'           => 'about-us',
					'menu_class'     => 'menu-list',
					'menu_id'        => '',
					'theme_location' => 'about-us',
				)
			); ?>
		</section>
	</div>
	<div class="list-in menu">
		<section class="shows">
			<h2 class="title">View</h2>
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
			<h2 class="title">By topic</h2>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'list-in',
					'container'      => false,
					'menu_class'     => 'menu-list',
					'menu_id'        => '',
					'menu'           => 'list-in',
				)
			);
			?>
		</section>
	</div>
</div>
<?php
