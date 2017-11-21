<?php
/**
 * The template for page type posts
 *
 * @package WordPress
 * @subpackage Braven_LL_Kit
 * @since LL Kit 1.0
 */

get_header(); ?>
<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">
		<header class="kit-header">
			<?php if(has_post_thumbnail()) {  ?>
				<div class="bkg">
					<?php the_post_thumbnail('header'); ?>
				</div>
			<?php } else { ?>
				<div class="no-bkg-img">&nbsp;</div>
			<?php } ?>
			<div class="kit-masthead">
				<h1>
					<?php the_title(); ?>
				</h1>
				<?php the_excerpt();?>
			</div>
		</header>
		<div class="page-contents">
			<?php echo apply_filters('the_content', $post->post_content); ?>
		</div>
	</main><!-- .site-main -->
</div><!-- .content-area -->

<?php get_footer(); ?>
