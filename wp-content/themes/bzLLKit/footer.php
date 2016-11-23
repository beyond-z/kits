<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after
 *
 * @package WordPress
 * @subpackage Braven_LL_Kit
 * @since LL Kit 1.0
 */
?>
		</div><!-- .site-content -->
		<footer id="footer" class="site-footer" role="contentinfo">
			<p>
				<?php 
				$copyrightID = 175;
				$copyright = get_post($copyrightID);
				echo apply_filters('the_content', $copyright->post_content);
				?>
			</p>
		</footer><!-- .site-footer -->
	</div><!-- .site-inner -->
</div><!-- .site -->

<?php wp_footer(); ?>
</body>
</html>
