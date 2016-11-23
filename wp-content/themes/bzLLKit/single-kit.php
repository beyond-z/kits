<?php
/**
 * The template for displaying kits
 *
 * @package WordPress
 * @subpackage Braven_LL_Kit
 * @since LL Kit 1.0
 */

get_header(); ?>
<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">
		<header class="kit-header">
			<div class="bkg">
				<?php if(has_post_thumbnail()) { 
					the_post_thumbnail('header');
				} ?>
			</div>
			<div class="kit-masthead">
				<h1>
					<?php echo __('Learning Lab ','bz'). bz_calculate_kit_number() .': '. get_the_title(); // bz_calculate_kit_number() is in functions.php ?>
				</h1>
				<?php the_excerpt();?>
			</div>
		</header>
	<?php the_content();?>
	<?php
	$customfields=(get_post_custom($post_id));
	
	//make sure there's at least the first outcome, then get all three:
	if ($customfields['bz_kit_outcomes']){ ?>
		<div class="outcomes">
			<h2><?php echo __('Fellows Will:', 'bz'); ?></h2>
			<?php echo apply_filters('the_content',$customfields['bz_kit_outcomes'][0]);?>
		</div> <?php
	} ?>
	<?php 
	$materials;
	$kit_materials = wp_get_object_terms( $post->ID, 'material' );
	foreach ($kit_materials as $kit_material) {
		$materials[$kit_material->slug] = $kit_material;
	}
	$activity_posts; // to collect activity IDs for query and display further down.	
	echo '<h2>'.__('Agenda','bz').'</h2>';
	$dt = DateTime::createFromFormat('H:i', '18:00'); // of course at some point we should draw the start time from the CMS or LMS...
	$dtadjust = get_post_custom_values('bz_kit_start_time_adjust', $post->ID);
	if ($dtadjust[0] < 0) {
		$dt->sub(new DateInterval('PT'.abs($dtadjust[0]).'M'));
	} else if ($dtadjust[0] > 0) {
		$dt->add(new DateInterval('PT'.$dtadjust[0].'M'));
	}
	$activities = wp_get_object_terms( $post->ID, 'activity', array('orderby' => 'term_taxonomy_id') );
	if ( $activities && !is_wp_error( $activities ) ) { ?>
		<table class="agenda">
			<?php foreach ( $activities as $activity ) { 
			$activity_posts[$activity->term_id] = $activity->term_id; ?>
				<tr>
					<td>
						<?php 
						$activity_duration = get_post_meta( $activity->term_id, 'bz_activity_attributes_minutes', 'true' );
						// show start time and add minutes from this activity
						// forcing (int)$activity_duration to convert empties to zeros.
						echo $dt->format('g:i a');
						$dt->add(new DateInterval('PT'.(int)$activity_duration.'M'));           
						?>
					</td>
					<td>
						<a href="<?php echo '#'.get_post_field( 'post_name', get_post($activity->term_id) );
							//Or, if we ever want to link directly to the activity as a single post:
							//echo $cpt_onomy->get_term_link( $activity, $activity->taxonomy );
						?>">
							<span class="activity-name"><?php echo $activity->name; ?></span>
						</a>
						<span class="duration">(<?php echo $activity_duration;?>)</span>
						<br />
						<span class="activity-desc"><?php echo get_the_excerpt($activity->term_id);?></span>
					</td>
				</tr>
				<?php
				$activity_materials = wp_get_object_terms( $activity->term_id, 'material');
				foreach ($activity_materials as $activity_material) {
					$materials[$activity_material->slug] = $activity_material;
				}
			} // end foreach $activities 
			?>
		</table>
		<?php
		if ($customfields['bz_kit_prework']){ ?>
			<div class="outcomes">
				<h2><?php echo __('Fellows\' Prework', 'bz'); ?></h2>
				<?php echo apply_filters('the_content',$customfields['bz_kit_prework'][0]);?>
			</div> <?php
		} 
		?>
		<?php
		if ($materials) {
			echo '<h2>'.__('Materials','bz').'</h2>';
			echo '<ul>';
			foreach ($materials as $material) {
				echo '<li>'.$material->name.'</li>';
			}
			echo '</ul>';
		}
		?>
		<?php
		if ($customfields['bz_kit_important']){ ?>
			<div class="outcomes">
				<h2><?php echo __('What\'s most important', 'bz'); ?></h2>
				<?php echo apply_filters('the_content',$customfields['bz_kit_important'][0]);?>
			</div> <?php
		} 
		?>
		<?php 
		// query full activity content and display it:
		$args = array(
			'post_type' => 'activity',
			'order' => 'ASC',
			'orderby' => 'menu_order',
			'post_status' => 'publish',
			'post__in' => $activity_posts
		);
		$sub_activities = new WP_Query($args);
		if($sub_activities->have_posts()): ?>
			<div class="sub-activities">
				<h2 id="activity-plan-header"><?php echo __('Activity Plan', 'bz'); ?></h2>
				<?php while ($sub_activities->have_posts()):
					$sub_activities->the_post();			
					get_template_part('content','activity');
				endwhile; ?>
			</div>
		<?php endif; // $sub_activities

	} // if( &activities) ?>

	</main><!-- .site-main -->
</div><!-- .content-area -->

<?php get_footer(); ?>