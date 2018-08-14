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
			
			// Build the query to get the user's courses:
			$args = array (
				'post_type'             => array( 'course' ),
				'post_status'           => array( 'publish' ),
				'nopaging'              => true,
				'posts_per_page'        => '-1',
				'order'                 => 'ASC',
				'orderby'               => 'post_title',
			);

			// If user isn't part of Braven staff, limit them 
			// to view courses they're associated with on the Portal,
			// by adding an argument to the query:

			// $current_user_email = wp_get_current_user()->user_email;
			// FOR TESTING:
			/*
			if ( strpos($current_user_email, 'bebraven.org') === FALSE) {
				$user_courses = bz_get_user_courses($current_user_email);
				$args['meta_query']	= array(
					'relation'		=> 'AND',
					array(
						'key'	 	=> 'bz_course_attributes_portal_id',
						'value'	  	=> $user_courses,
						'compare' 	=> 'IN',
					),
				);
			}
			*/
			$courses = new WP_Query( $args );
			
			if ( $courses->found_posts == 1 ) {
				// If the user only has one course, might as well 
				// redirect them to it:
				$courses->the_post();
				?>
				<script>
					location.replace("<?php the_permalink(); ?>");
				</script>
				<?php
			} elseif ( $courses->have_posts() ) { 
				// In case of more than one course:
				?>
				<h2><?php echo __('Please select course:', 'bz');?></h2>				
				<table id="courses">
					<?php // loop through the courses:
					while ( $courses->have_posts() ) {
						$courses->the_post(); ?>
	
							<tr id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
								<td class="visual">
									<?php if ( has_post_thumbnail() ) the_post_thumbnail('list'); ?>
								</td>
								<td class="desc">
									<header class="entry-header">
										<h3 class="entry-title">
											<a href="<?php the_permalink();?>" title="<?php the_title();?>">
												<?php	the_title();?>
											</a>
										</h3>
									</header><!-- .entry-header -->
									<div class="entry-content">
										<?php the_excerpt();	?>
									</div><!-- .entry-content -->
								</td><!-- #activity-## -->
							</tr>
						<?php
					} //end while ?>
					</table><!-- #courses -->
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
