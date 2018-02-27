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

// Set up a place to store activities so we don't have to query the DB more than once.
$activity_posts = array();
$full_activities ="";

// Kit content varies based on Course (e.g. LL start time);
// Get the Course from the URL query's passed param.
global $course;
global $course_custom_fields;


get_header(); ?>
<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">
		<header class="kit-header">
			<?php if(has_post_thumbnail()) {  ?>
				<div class="bkg">
					<?php the_post_thumbnail('header');	?>
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
		<?php if (!empty($post->post_content)) { ?>
			<div class="kit-component intro">
				<?php the_content();?>
			</div>
		<?php } ?>
	<?php
	$customfields=(get_post_custom($post->ID));

	if (!empty($customfields['bz_kit_vision'])){ ?>
		<div class="kit-component vision">
			<h2><?php echo __('Vision', 'bz'); ?></h2>
			<?php echo apply_filters('the_content',$customfields['bz_kit_vision'][0]);?>
		</div> <?php
	} 
	// Make sure there's at least the first outcome, then get all three:
	if (!empty($customfields['bz_kit_outcomes'])){ ?>
		<div class="kit-component outcomes">
			<h2><?php echo __('Fellows Will:', 'bz'); ?></h2>
			<?php echo apply_filters('the_content',$customfields['bz_kit_outcomes'][0]);?>
		</div> <?php
	} 

	// Show goals, if there are any:
	if (!empty($customfields['bz_kit_goals'])){ ?>
		<div class="kit-component goals">
			<h2><?php echo __('Goals:', 'bz'); ?></h2>
			<?php echo apply_filters('the_content',$customfields['bz_kit_goals'][0]);?>
		</div> <?php
	} 

	// Show objectives, if there are any:
	if (!empty($customfields['bz_kit_objectives'])){ ?>
		<div class="kit-component goals">
			<h2><?php echo __('Objectives:', 'bz'); ?></h2>
			<?php echo apply_filters('the_content',$customfields['bz_kit_objectives'][0]);?>
		</div> <?php
	} 

	// Show Look and Feel instructions if there are any:
	if (!empty($customfields['bz_kit_look_and_feel'])){ ?>
		<div class="kit-look-and-feel">
			<h2><?php echo __('Look and Feel:', 'bz'); ?></h2>
			<?php echo apply_filters('the_content',$customfields['bz_kit_look_and_feel'][0]);?>
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
			// Figure out start time based on referring course:
      $stt = (!empty($course_custom_fields['bz_course_default_start_time'][0])) ? $course_custom_fields['bz_course_default_start_time'][0] : '18:00';

			// If there's a custom event start time and this is an event (not a Learning Lab), use the event start time instead:
			$eventtime = (!empty($course_custom_fields['bz_course_event_start_time'][0])) ? $course_custom_fields['bz_course_event_start_time'][0] : '9:00';
			$kit_type = (!empty ($customfields['bz_kit_type'][0]) ) ? $customfields['bz_kit_type'][0] : 'll';

			//print_r($course_custom_fields);
			//print_r($kit_type);
      if ( $kit_type != 'll' ) {
				$stt = $eventtime;
			}
			// Init generating the agenda timetable
			$dt = DateTime::createFromFormat('H:i', $stt); 
				// of course at some point we should make this NOT HARDCODED! e.g. draw the start time from the CMS or LMS...
			$dtadjust = get_post_custom_values('bz_kit_start_time_adjust', $post->ID);
			if ($dtadjust[0] < 0) {
				$dt->sub(new DateInterval('PT'.abs($dtadjust[0]).'M'));
			} else if ($dtadjust[0] > 0) {
				$dt->add(new DateInterval('PT'.$dtadjust[0].'M'));
			}		
			?>
			<div id="agenda" class="kit-component agenda">
				<h2><?php echo __('Agenda','bz');?></h2>
				<table>
				<?php 
				foreach ($activity_posts as $activity_key => $activity_post) { 
					if ($activity_post->post_status == 'publish') { ?>
						<tr>
							<td>
								<?php 
								$activity_duration = get_post_meta( $activity_post->ID, 'bz_activity_attributes_minutes', 'true' );
								// Show start time and add minutes from this activity
								// forcing (int)$activity_duration to convert empties to zeros.
								// Also add those times as properties to the post object for later use.
								echo $dt->format('g:i a');
								// Convert post object to array so we can add properties:
								$activity_post = (array)$activity_post;
								$activity_post['start_time'] = (string)$dt->format('g:i');
								// Increase $dt by the activity's duration and add it to the post object as well:
								$dt->add(new DateInterval('PT'.(int)$activity_duration.'M'));
								// And store the duration and end time for later:
								$activity_post['duration'] = (string)(int)$activity_duration;
								$activity_post['end_time'] = (string)$dt->format('g:i a');   
								// Now convert it back to an object:
								$activity_post = (object)$activity_post;
								// And save the changes back to the posts array so we can use them in the content:
								$activity_posts[$activity_key] = $activity_post;
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
			</div>
		<?php  
	
		}	// end if (!empty($activity_posts))
	} // end if (!empty(customfields['bz_kit_agenda']))
	?>
	
	<?php
	if (!empty($customfields['bz_kit_prework'])){ ?>
		<div class="kit-component prework start-collapsed">
			<h2><?php echo __('Fellows\' Prework', 'bz'); ?></h2>
			<?php echo apply_filters('the_content',$customfields['bz_kit_prework'][0]);?>
		</div> <?php
	} 
	?>
	
	<?php
	
	if (!empty($materials)) { ?>
		<div class="kit-component materials start-collapsed">
			<h2><?php echo __('Materials','bz'); ?></h2>
			<ul>
				<?php foreach ($materials as $material) { ?>
					<li>
						<?php if ( strpos($material->description,'http') === 0 ) { ?>
							<a href="<?php echo $material->description; ?>" target="_blank" title="<?php echo __('Open this resource in a new tab', 'bz');?>"><?php echo $material->name; ?></a>
						<?php } else {
							echo $material->name;
						} ?>
					</li>
				<?php } // end foreach ?>
			</ul>
		</div>
	<?php } // end if (!empty($materials)) ?>
  
  <?php
	if (!empty($customfields['bz_kit_important'])){ ?>
		<div class="kit-component important">
			<h2><?php echo __('What\'s most important', 'bz'); ?></h2>
			<?php echo apply_filters('the_content',$customfields['bz_kit_important'][0]);?>
		</div> <?php
	} 
	?>
  
	<?php 
	// query full activity content and display it:

	if (!empty($activity_posts)) { ?>
		<div class="kit-component sub-activities non-collapsible">
			<h2 id="activity-plan-header"><?php echo __('Activity Plan', 'bz'); ?></h2>
			<?php 
			
			foreach ($activity_posts as $activity_post) {	?>
				<article class="activity" id="<?php echo $activity_post->post_name; ?>">
					<header class="activity-header">
						<div class="duration">
							<span class="start"><?php echo $activity_post->start_time;?></span>
							<span class="end">&ndash;&nbsp;<?php echo $activity_post->end_time;?></span>
							<span class="minutes"><?php echo $activity_post->duration .'&nbsp;'. __('Minutes', 'bz'); ?></span>
						</div>						
						<span class="activity-title"><?php echo $activity_post->post_title;?></span>
						<?php 
							global $bz_scopes;
							$activity_scope = get_post_meta( $activity_post->ID, 'bz_activity_attributes_group_scope', 'true' );
								if ($activity_scope) { ?>
									<span class="scope scope-'<?php echo $activity_scope;?>">
										<?php echo $bz_scopes[$activity_scope]; // get title by key. $bz_scopes is defined in functions.php ?>
									</span>
								<?php } // end if scope ?>
						<div class="activity-outcomes"><?php echo apply_filters('the_content', $activity_post->post_excerpt); ?></div>
					</header>
					
					<div class="activity-content"><?php echo apply_filters('the_content', $activity_post->post_content); ?></div>
					<?php if ( current_user_can( 'edit_posts' ) ) { ?>
						<footer class="activity-footer">
							<span class="edit-link">
								<a href="<?php get_edit_post_link($activity_post->ID); ?>">
									<?php echo __('Edit', 'bz');?>
									<span class="screen-reader-text"><?php echo $activity_post->post_title; ?></span>
								</a>
							</span>
						</footer><!-- .activity-footer -->
					<?php } ?>
				</article>

			<?php } // end foreach */ ?>
		</div>
	<?php } //!empty($activity_posts)  ?>
  
	<?php
	if (!empty($customfields['bz_kit_after'])){ ?>
		<div class="kit-component important">
			<h2><?php echo __('After Learning Lab', 'bz'); ?></h2>
			<?php echo apply_filters('the_content',$customfields['bz_kit_after'][0]);?>
		</div> <?php
	} 
	?>
  
	<?php
	
	if (!empty($customfields['bz_kit_appendix'])){ ?>
		<div class="kit-component appendix start-collapsed">
			<h2><?php echo __('Appendix', 'bz'); ?></h2>
			<?php echo apply_filters('the_content',$customfields['bz_kit_appendix'][0]);?>
		</div> <?php
	} 
	?>
	
	<?php 
	// Iterate through logistics fields if there are any
	global $bz_logistics; // from functions.php
	if(!empty(array_intersect_key($customfields, $bz_logistics))) { ?>
		<div id="logistics" class="kit-component for-staff logistics start-collapsed">
			<h2 id="logistics-header"><?php echo __('Logistical Information', 'bz');?></h2>
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
		<div id="staff-tasks" class="kit-component for-staff staff-tasks start-collapsed">
			<h2 id="staff-tasks-header"><?php echo __('What Staff Needs To Do', 'bz');?></h2>
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

	</main><!-- .site-main -->
</div><!-- .content-area -->

<?php get_footer(); ?>
