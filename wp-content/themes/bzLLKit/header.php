<?php
/**
 * The template for displaying the header
 *
 * Displays all of the head element and everything up until the "site-content" div.
 *
 * @package WordPress
 * @subpackage Braven_LL_Kit
 * @since LL Kit 1.0
 */

global $course;
$course = ( isset( $_GET['bzcourse'] ) ) ? $_GET['bzcourse'] : ''; 
global $course_custom_fields;
$home_target = esc_url( home_url() );
$home_target .= ($course) ? esc_url( '/course/' ) . $course : '';

?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="http://gmpg.org/xfn/11">
	<?php if ( is_singular() && pings_open( get_queried_object() ) ) : ?>
	<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
	<?php endif; ?>
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<div id="page" class="site">
	<div class="site-inner">
		<a class="skip-link screen-reader-text" href="#content"><?php _e( 'Skip to content', 'bz' ); ?></a>
		<header id="masthead" class="site-header" role="banner">
			<div class="site-header-main">
			
				<div class="site-branding">
					<?php 
					if ( is_front_page() && is_home() ) { 
					} else { ?>
						<div class="site-title">
							<a href="<?php echo $home_target; ?>" rel="home">
								<?php echo __('Home', 'bz'); //bloginfo( 'name' ); ?>
							</a>
							<?php if ('kit' == $post->post_type) { ?>
								<form class="course-selector" id="course-selector" method="get">
									<span>
										<?php echo __('Course: ','bz'); ?>
									</span>
									<?php 
										// Make a list of options from currently available (=published) courses:
										$args = array(
											'post_type' => 'course',
											'post_status' => 'publish',
											'posts_per_page' => -1,
										);
										$courses = new WP_Query($args);
										if ( $courses->have_posts() ) {
											?>
											<select class="form-control" name="bzcourse" id="active-course-selector" onchange="document.getElementById('course-selector').submit();">
												<option value="">&nbsp;</option>
												<?php 
											while ( $courses->have_posts() ) {
												$courses->the_post();
												$selected = '';
												if ($post->post_name == $course) {
													// Help select the course from the dropdown:
													$selected = 'selected';
													// While we're at it, get the course's custom field such as LL start time:
													$course_custom_fields = get_post_meta($post->ID);
												}
												echo '<option value="'.$post->post_name.'" '.$selected.'>' . get_the_title() . '</option>';
											}
											echo '</select>';
											/* Restore original Post Data */
											wp_reset_postdata();
										} else {
											// no posts found
										}
									?>
								</form>
								<a href="/help" target="_blank"><?php echo __('Help', 'bz');?></a>
							<?php } // END if ('kit' == $post->post_type) ?>
						</div><!-- .site-title -->
					<?php } // END if ( is_front_page() && is_home() ) else ?>
				</div><!-- .site-branding -->

	
			</div><!-- .site-header-main -->
		</header><!-- .site-header -->

		<div id="content" class="site-content">
								
