<?php
/**
 * Front-page hint: arrow + “click logo for menu” (pulled up into the logo gap via CSS).
 *
 * @package eluminate-standalone
 */

if ( ! is_front_page() ) {
	return;
}
?>
<div id="logo-menu-hint" class="logo-menu-hint-wrap logo-menu-hint--in-content" role="status">
	<span class="logo-menu-hint__arrow" aria-hidden="true">
		<img
			src="<?php echo esc_url( get_theme_file_uri( 'assets/arrow.svg' ) ); ?>"
			alt=""
			width="30"
			height="30"
			decoding="async"
		/>
	</span>
	<p class="logo-menu-hint">
		<?php
		echo wp_kses_post(
			sprintf(
				/* translators: %s: the word "menu" (emphasized). */
				__( 'click logo for %s', 'eluminate-standalone' ),
				'<span class="logo-menu-hint__accent">' . esc_html__( 'menu', 'eluminate-standalone' ) . '</span>'
			)
		);
		?>
	</p>
</div>
<?php if ( ELUMINATE_LOGO_HINT_DISMISS_ENABLED ) : ?>
<script>
	(function () {
		try {
			if ( localStorage.getItem( 'eluminate_logo_menu_hint_dismissed' ) === '1' ) {
				const el = document.getElementById( 'logo-menu-hint' );
				if ( el ) {
					el.remove();
				}
			}
		} catch ( e ) {}
	})();
</script>
<?php endif; ?>
