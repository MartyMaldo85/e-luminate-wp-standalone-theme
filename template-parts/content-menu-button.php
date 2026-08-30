<?php
/**
 * Persistent in-content control that opens the orbit menu (replaces the old logo hint).
 *
 * @package eluminate-standalone
 */
?>
<div class="content-menu-trigger-wrap">
	<button
		type="button"
		class="content-menu-trigger"
		id="content-menu-trigger"
		onclick="if (typeof toggleLogoMenu === 'function') { toggleLogoMenu(); }"
		aria-expanded="false"
		aria-controls="body-nav"
		aria-label="<?php echo esc_attr__( 'Open menu', 'eluminate-standalone' ); ?>"
	>
		<svg class="content-menu-trigger__icon" xmlns="http://www.w3.org/2000/svg" viewBox="-1 0 44 42" aria-hidden="true" focusable="false">
			<g class="orbit-dots">
				<circle cx="4.2" cy="21" r="4.2" />
				<circle cx="37.8" cy="21" r="4.2" />
				<circle cx="12.6" cy="35.5" r="4.2" />
				<circle cx="29.4" cy="6.5" r="4.2" />
				<circle cx="29.4" cy="35.5" r="4.2" />
				<circle cx="12.6" cy="6.5" r="4.2" />
			</g>
			<path d="M16.3 25.2c-2.3 0-4.2-1.9-4.2-4.2s1.9-4.2 4.2-4.2h9.4c2.3 0 4.2 1.9 4.2 4.2s-1.9 4.2-4.2 4.2z" />
		</svg>
		<span class="content-menu-trigger__label"><?php echo esc_html__( 'menu', 'eluminate-standalone' ); ?></span>
	</button>
</div>
