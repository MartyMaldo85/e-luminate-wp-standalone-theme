<?php
/**
 * Orbital main menu buttons, centered on the site logo. Loaded inside .header-logo-cluster.
 * Eight physical positions (45° each). When the source list has more than eight entries,
 * JS keeps eight DOM slots and scroll-remaps labels through them (same behavior across all orbit contexts).
 *
 * @package eluminate-standalone
 */
?>
<div class="orbit-hub" id="orbit-hub" aria-label="<?php echo esc_attr__( 'Main menu', 'eluminate-standalone' ); ?>">
	<?php
	$orbit_items  = eluminate_standalone_get_orbit_menu_tree();
	$orbit_slots  = array( 6, 5, 4, 3, 2, 1, 0, 7 );
	$item_count   = count( $orbit_items );

	foreach ( $orbit_slots as $slot_index => $orbit_slot ) :
		if ( $slot_index < $item_count ) :
			$item          = $orbit_items[ $slot_index ];
			$item_children = isset( $item['children'] ) && is_array( $item['children'] ) ? $item['children'] : array();
			$has_children  = ! empty( $item_children );
			?>
			<button
				type="button"
				class="btn btn-main-menu btn-orbit page-<?php echo esc_attr( (string) $item['id'] ); ?>"
				style="--orbit-i: <?php echo esc_attr( (string) $orbit_slot ); ?>"
				data-nav-mode="<?php echo esc_attr( $has_children ? 'submenu' : 'link' ); ?>"
				data-page-id="<?php echo esc_attr( (string) $item['id'] ); ?>"
				data-page-slug="<?php echo esc_attr( (string) ( $item['slug'] ?? '' ) ); ?>"
				data-page-label="<?php echo esc_attr( (string) $item['title'] ); ?>"
				data-target-url="<?php echo esc_url( (string) $item['url'] ); ?>"
				onclick="return activateMainMenuItem(this)"
			><span class="btn-orbit__label"><?php echo esc_html( (string) $item['title'] ); ?></span></button>
			<?php
		else :
			?>
			<button
				type="button"
				class="btn btn-orbit btn-orbit--phantom btn-hub-slot"
				style="--orbit-i: <?php echo esc_attr( (string) $orbit_slot ); ?>"
				aria-hidden="true"
				tabindex="-1"
			></button>
			<?php
		endif;
	endforeach;
	?>
</div>
