<?php
/**
 * The template for displaying generic single posts and attachments
 *
 * @package WordPress
 * @subpackage Braven_LL_Kit
 * @since LL Kit 1.0
 */

get_header(); ?>
<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">
		<?php get_template_part('content','activity');?>
	</main><!-- .site-main -->
</div><!-- .content-area -->

<?php get_footer(); ?>