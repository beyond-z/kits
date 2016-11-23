<article class="activity" id="<?php echo $post->post_name; ?>">
	<header class="activity-header">
		<span class="activity-title"><?php the_title();?></span>
		<span class="duration">
			<?php $activity_duration = get_post_meta( $post->ID, 'bz_activity_attributes_minutes', 'true' ); 
			echo $activity_duration; ?>
		</span>
		<?php 
		global $bz_scopes;
		$activity_scope = get_post_meta( $post->ID, 'bz_activity_attributes_group_scope', 'true' );
		if ($activity_scope) {?>
			<span class="scope scope-<?php echo $activity_scope; ?>">
				<?php echo $bz_scopes[$activity_scope]; // get title by key. $bz_scopes is defined in functions.php ?>
			</span>
			<?php 
		} // end if scope ?>
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
</article>
