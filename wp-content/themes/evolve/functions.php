<?php

$evolve_themename = "evolve";

if ( get_stylesheet_directory() == get_template_directory() ) {
	define('EVOLVE_URL', get_template_directory() . '/library/functions/');
	define('EVOLVE_DIRECTORY', get_template_directory_uri() . '/library/functions/');
} else {
	define('EVOLVE_URL', get_template_directory() . '/library/functions/');
	define('EVOLVE_DIRECTORY', get_template_directory_uri() . '/library/functions/');
}

get_template_part( 'library/functions/options-framework' );
get_template_part( 'library/functions/basic-functions' );
get_template_part( 'library/functions/options' );

function wp_blog_head() {
     echo "<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-K6DZL4G');</script>
<!-- End Google Tag Manager -->";
}  add_action('wp_head', 'wp_blog_head');

?>
