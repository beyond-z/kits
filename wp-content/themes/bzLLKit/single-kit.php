<?php
/**
 * The template for displaying kits
 *
 * @package WordPress
 * @subpackage Braven_LL_Kit
 * @since LL Kit 1.0
 */

// Reset because we had to run a sub-query to form the title:
wp_reset_query();

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
					<?php 
					//bz_kit_title_prefix(); // see functions.php
					the_title(); ?>
				</h1>
				<?php the_excerpt();?>
			</div>
		</header>
	<?php the_content();?>
	<?php
	$customfields=(get_post_custom($post->ID));

	if (!empty($customfields['bz_kit_vision'])){ ?>
		<div class="outcomes">
			<h2><?php echo __('Vision', 'bz'); ?></h2>
			<?php echo apply_filters('the_content',$customfields['bz_kit_vision'][0]);?>
		</div> <?php
	} ?>
	<?php 
	// Make sure there's at least the first outcome, then get all three:
	if (!empty($customfields['bz_kit_outcomes'])){ ?>
		<div class="outcomes">
			<h2><?php echo __('Fellows Will:', 'bz'); ?></h2>
			<?php echo apply_filters('the_content',$customfields['bz_kit_outcomes'][0]);?>
		</div> <?php
	} ?>
	<?php 

	// Set up materials array to collect while we're iterating throug activities:
	// Then add the materials listed on the kit itself (i.e. not derived from linked activities):
	$materials;
	$kit_level_materials = wp_get_object_terms( $post->ID, 'material' );
	foreach ($kit_level_materials as $kit_level_material) {
		$materials[$kit_level_material->slug] = $kit_level_material;
	}
	
	if (!empty($customfields['bz_kit_agenda'])) {
		// Get the activities linked from the kit's agenda list:
		$activity_posts = array();
		$activity_links = array();
		$list_of_links = $customfields['bz_kit_agenda'][0];
	
		// make an array of clean urls:
		$dom = new DOMDocument;
		$dom->loadHTML($list_of_links);
		$xpath = new DOMXPath($dom);
		$nodes = $xpath->query('//a/@href');
		foreach($nodes as $href) {
			 $activity_links[] = $href->nodeValue;
		}

		foreach ($activity_links as $activity_link) {
			// Get the post data into our array, if it is a valid id (url_to_postid returns 0 if not)
			// NOTE: this is not very efficient, because we're using get_post query the DB several times. 
			// Might not matter much since it's a low volume app and we can cache things,
			// but maybe in the future let's upgrade this.
			$activity_id = url_to_postid($activity_link);
			if ($activity_id) $activity_posts[] = get_post($activity_id);
		}
		if (!empty($activity_posts)) {
		
			// Init generating the agenda timetable
			$dt = DateTime::createFromFormat('H:i', '18:00'); 
				// of course at some point we should make this NOT HARDCODED! e.g. draw the start time from the CMS or LMS...
			$dtadjust = get_post_custom_values('bz_kit_start_time_adjust', $post->ID);
			if ($dtadjust[0] < 0) {
				$dt->sub(new DateInterval('PT'.abs($dtadjust[0]).'M'));
			} else if ($dtadjust[0] > 0) {
				$dt->add(new DateInterval('PT'.$dtadjust[0].'M'));
			}		
			
			echo '<h2>'.__('Agenda','bz').'</h2>';	?>
			
			<table class="agenda">
				<?php 
				foreach ($activity_posts as $activity_post) { 
					if ($activity_post->post_status == 'publish') { ?>
						<tr>
							<td>
								<?php 
								$activity_duration = get_post_meta( $activity_post->ID, 'bz_activity_attributes_minutes', 'true' );
								// show start time and add minutes from this activity
								// forcing (int)$activity_duration to convert empties to zeros.
								echo $dt->format('g:i a');
								$dt->add(new DateInterval('PT'.(int)$activity_duration.'M'));           
								?>
							</td>
							<td>
								<a href="<?php echo '#'.$activity_post->post_name; ?>">
									<span class="activity-name"><?php echo $activity_post->post_title; ?></span>
								</a>
								<span class="duration">(<?php echo $activity_duration;?>)</span>
								<br />
								<span class="activity-desc"><?php echo apply_filters('the_content', $activity_post->post_excerpt);?></span>
							</td>
						</tr>
						<?php
						$activity_materials = wp_get_object_terms( $activity_post->ID, 'material');
						if (!empty($activity_materials)) {
							foreach ($activity_materials as $activity_material) {
								$materials[$activity_material->slug] = $activity_material;
							}
						}
					} // end if ($activity_post->post_status == 'publish')
				} // end foreach 
				?>
			</table>
		<?php  
	
		}	// end if (!empty($activity_posts))
	} // end if (!empty(customfields['bz_kit_agenda']))
	?>
	
	<?php
	if (!empty($customfields['bz_kit_prework'])){ ?>
		<div class="outcomes">
			<h2><?php echo __('Fellows\' Prework', 'bz'); ?></h2>
			<?php echo apply_filters('the_content',$customfields['bz_kit_prework'][0]);?>
		</div> <?php
	} 
	?>
	
	<?php
	
	if (!empty($materials)) {
		echo '<h2>'.__('Materials','bz').'</h2>';
		echo '<ul>';
		foreach ($materials as $material) {
			echo '<li>'.$material->name.'</li>';
		}
		echo '</ul>';
	}
	?>
	
	<?php
	if (!empty($customfields['bz_kit_important'])){ ?>
		<div class="outcomes">
			<h2><?php echo __('What\'s most important', 'bz'); ?></h2>
			<?php echo apply_filters('the_content',$customfields['bz_kit_important'][0]);?>
		</div> <?php
	} 
	?>
	
	<?php 
	// Iterate through logistics fields if there are any
	global $bz_logistics; // from functions.php
	if(!empty(array_intersect_key($customfields, $bz_logistics))) { ?>
		<h2 id="logistics-header"><?php echo __('Logistical Information', 'bz');?></h2>
		<div id="logistics">
			<?php 
			// and now iterate through the logistics fields:
			foreach ($bz_logistics as $bz_logistics_field_key => $bz_logistics_field_attributes) {
				if (!empty($customfields[$bz_logistics_field_key])){ ?>
					<div class="<?php echo $bz_logistics_field_key; ?>">
						<h4><?php echo $bz_logistics_field_attributes['name'] ?></h4>
						<?php echo apply_filters('the_content',$customfields[$bz_logistics_field_key][0]);?>
					</div> <?php
				} 
			} // end foreach
			?>
		</div>
	<?php	} ?>
	<?php 
	// Iterate through staff tasks if there are any:
	global $bz_staff_tasks; // from functions.php
	if(!empty(array_intersect_key($customfields, $bz_staff_tasks))) { ?>
		<h2 id="staff-tasks-header"><?php echo __('What Staff Needs To Do', 'bz');?></h2>
		<div id="staff-tasks">
			<?php 
			// and now iterate through the logistics fields:
			foreach ($bz_staff_tasks as $bz_staff_tasks_field_key => $bz_staff_tasks_attributes) {
				if (!empty($customfields[$bz_staff_tasks_field_key])){ ?>
					<div class="<?php echo $bz_staff_tasks_field_key; ?>">
						<h4><?php echo $bz_staff_tasks_attributes['name'] ?></h4>
						<?php echo apply_filters('the_content',$customfields[$bz_staff_tasks_field_key][0]);?>
					</div> <?php
				} 
			} // end foreach
			?>
		</div>
	<?php } ?>
	<?php 
	// query full activity content and display it:

	if (!empty($activity_posts)) { ?>
		<div class="sub-activities">
			<h2 id="activity-plan-header"><?php echo __('Activity Plan', 'bz'); ?></h2>
			<?php 
			foreach ($activity_posts as $activity) {
				// since we already querried the DB for all post data, we can fake a WP_Query thus:
				global $post; 
				$post = get_post( $activity->ID, OBJECT );
				setup_postdata( $post );			
				get_template_part('content','activity');
				wp_reset_postdata();
			} // end foreach ?>
		</div>
	<?php } //!empty($activity_posts)  ?>
	<?php
	
	if (!empty($customfields['bz_kit_appendix'])){ ?>
		<div class="outcomes">
			<h2><?php echo __('Appendix', 'bz'); ?></h2>
			<?php echo apply_filters('the_content',$customfields['bz_kit_appendix'][0]);?>
		</div> <?php
	} 
	?>
	
	</main><!-- .site-main -->
</div><!-- .content-area -->

<?php get_footer(); ?>
