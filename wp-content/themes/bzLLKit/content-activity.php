<article class="activity" id="<?php echo $post->post_name; ?>">
	<header class="activity-header">
		<span class="activity-title"><?php the_title();?></span>
		<span class="duration">
			<?php $activity_duration = get_post_meta( $post->ID, 'bz_activity_attributes_minutes', 'true' ); 
			echo $activity_duration; ?>
		</span>
		<?php $activity_scope = get_post_meta( $post->ID, 'bz_activity_attributes_group_scope', 'true' );
		if ($activity_scope && $activity_scope != 'cohort') {?>
		<span class="scope scope-<?php echo $activity_scope; ?>">
			<?php echo $activity_scope; ?>
		</span>
		<?php } // end if scope ?>
	</header>
	<div class="activity-outcomes"><?php echo apply_filters('the_content', $post->post_excerpt);?></div>
	<div class="activity-content"><?php echo apply_filters('the_content', $post->post_content); ?></div>
	<footer class="activity-footer">
		<?php
			edit_post_link(
				sprintf(
					/* translators: %s: Name of current post */
					__( 'Edit<span class="screen-reader-text"> "%s"</span>', 'bz' ),
					get_the_title()
				),
				'<span class="edit-link">',
				'</span>'
			);
		?>
	</footer><!-- .activity-footer -->
	<?php /* get any child activities and nest them recursively */
	// query and then loop through any sub-activites:
	$args = array(
		'post_type' => 'activity',
		'post_parent' => $post->ID,
		'order' => 'ASC',
		'orderby' => 'menu_order',
		'post_status' => 'publish'
	);
	$sub_activities = new WP_Query($args);
	if($sub_activities->have_posts()): ?>
		<div class="sub-activities">
		<?php while ($sub_activities->have_posts()):
			$sub_activities->the_post();			
			get_template_part('content','activity');
		endwhile; ?>
		</div>
	<?php endif; // $sub_activities?>
</article>