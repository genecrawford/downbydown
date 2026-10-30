<?php
/**
 * Optional page template: the app with no theme header, footer or sidebar.
 * For installs without a page-builder frame. On downbydown.com the Divi Theme Builder provides the frame instead.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'dbd-standalone' ); ?>>
<?php wp_body_open(); ?>
<?php
while ( have_posts() ) {
	the_post();
	the_content();
}
?>
<?php wp_footer(); ?>
</body>
</html>
