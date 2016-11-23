<?php
/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link http://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPress
 * @subpackage Braven_LL_Kit
 * @since LL Kit 1.0
 */

get_header(); ?>
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

			<?php
			// List the kits:
			$args = array (
				'post_type'              => array( 'kit' ),
				'post_status'            => array( 'publish' ),
				'nopaging'               => true,
				'posts_per_page'         => '-1',
				'order'                  => 'ASC',
				'orderby'                => 'menu_order',
			);
			$kits = new WP_Query( $args );
			if ( $kits->have_posts() ) { ?>
				<h2><?php echo __('All Kits:', 'bz');?></h2>				
				<table id="kits">
					<?php // loop through the kits:
					while ( $kits->have_posts() ) {
						$kits->the_post(); ?>
	
							<tr id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
								<td class="visual">
									<?php if ( has_post_thumbnail() ) the_post_thumbnail('list'); ?>
								</td>
								<td class="desc">
									<header class="entry-header">
										<h3 class="entry-title">
											<a href="<?php the_permalink();?>" title="<?php the_title();?>">
												<?php echo __('Learning Lab ','bz'). bz_calculate_kit_number() .': '. get_the_title(); // bz_calculate_kit_number() is in functions.php ?>
											</a>
										</h3>
									</header><!-- .entry-header -->
									<div class="entry-content">
										<?php
										the_content();
										?>
									</div><!-- .entry-content -->
								
									<?php
										edit_post_link(
											sprintf(
												/* translators: %s: Name of current post */
												__( 'Edit<span class="screen-reader-text"> "%s"</span>', 'bz' ),
												get_the_title()
											),
											'<footer class="entry-footer"><span class="edit-link">',
											'</span></footer><!-- .entry-footer -->'
										);
									?>
								
								</td><!-- #activity-## -->
							</tr>
						<?php
					} //end while ?>
					</table><!-- #kits -->
				<?php 
				} else {
					// no posts found
				}
				
				// Restore original Post Data
				wp_reset_postdata();	
				?>
		</main><!-- .site-main -->
	</div><!-- .content-area -->

<?php get_footer(); ?>
