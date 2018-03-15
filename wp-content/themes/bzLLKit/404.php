<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @package WordPress
 * @subpackage Braven_LL_Kit
 * @since LL Kit 1.0
 */

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

			<section class="error-404 not-found">
				<header class="page-header">
					<h1 class="page-title"><?php _e( 'Oops! That content can&rsquo;t be found.', 'bz' ); ?></h1>
				</header><!-- .page-header -->
				
				<div class="page-content">
					<p><?php _e( 'Please contact the Braven team for help: <a target="_blank" href="mailto:support@bebraven.org">support@bebraven.org</a>', 'bz' ); ?></p>
				</div><!-- .page-content -->
			</section><!-- .error-404 -->

		</main><!-- .site-main -->

	</div><!-- .content-area -->

<?php get_footer(); ?>
