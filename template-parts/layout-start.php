<?php
$css_class = ( ! empty( $args['class'] ) ? $args['class'] : array() );
array_push( $css_class, 'layout-body' )
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset='<?php bloginfo( 'charset' ); ?>'>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="http://gmpg.org/xfn/11">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
	<?php if ( is_singular() && pings_open( get_queried_object() ) ) : ?>
		<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
	<?php endif; ?>
	<?php wp_head(); ?>
</head>
<body>
<div <?php post_class( implode( ' ', $css_class ) ); ?>>
