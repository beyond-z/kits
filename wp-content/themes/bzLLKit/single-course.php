<?php
/**
 * Course template
 *
*/

get_header(); ?>
  <div id="primary" class="content-area">
    <main id="main" class="site-main" role="main">
      <h1><?php the_title();?></h1>
      <p><?php the_excerpt();?></p>
      
      <?php /*
      <ul>
        <li><?php echo __('Learning Labs start at:', 'bz');?> 
          <?php 
            $starttime = get_post_custom_values('bz_course_default_start_time', $post->ID);
            $starttime = ($starttime[0]) ? $starttime[0] : '18:00';
            echo date('g:i a', strtotime($starttime));
          ?>
        </li>
        <li><?php echo __('Sepcial events start at:', 'bz');?>  
          <?php 

            $eventtime = get_post_custom_values('bz_course_event_start_time', $post->ID);
            $eventtime = ($eventtime[0]) ? $eventtime[0] : '9:00';
            echo date('g:i a', strtotime($eventtime));
          ?>
        </li>
      </ul>
      */ ?>
      
      <?php

      // Set up a var to count non-workshop kits for numbering purposes:
      $llcounter = 1;

      if (!empty($post->post_content)) {
        // Get the activities linked from the kit's agenda list:
        $kits_links = array();
        $list_of_links = $post->post_content;
      
        // make an array of clean urls:
        $dom = new DOMDocument;
        $dom->loadHTML($list_of_links);
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//a/@href');
        foreach($nodes as $href) {
           $kits_links[] = $href->nodeValue;
        }

        if (!empty($kits_links)) { ?>

          <table id="kits">

            <?php
            // make an array of the full posts based on those urls:
            $kit_posts = array();
            foreach ($kits_links as $kit_link) {
              // Get the post data into our array, if it is a valid id (url_to_postid returns 0 if not)
              // NOTE: this is not very efficient, because we're using get_post query the DB several times. 
              // Might not matter much since it's a low volume app and we can cache things,
              // but maybe in the future let's upgrade this.
              $kit_id = url_to_postid($kit_link);
              if ($kit_id) $kit_posts[] = get_post($kit_id);
            }

            foreach ($kit_posts as $kit_key => $kit_post) { 
              if ($kit_post->post_status == 'publish') { 
                // Cycle through the kits: 

                $kit_link = get_permalink($kit_post->ID).'?bzcourse='.$post->post_name; 

                ?>
                
                <tr id="kit-<?php echo $kit_post->ID; ?>" <?php //echo get_post_class($kit_post->ID); ?>>
                      <td class="visual" style="background-image: url('<?php 
                        echo ( has_post_thumbnail($kit_post->ID) ) ? get_the_post_thumbnail_url($kit_post->ID, 'list') : none; ?>');">
                        <a href="<?php echo $kit_link;?>">
                          <?php 
                            $kit_type = get_post_custom_values('bz_kit_type', $kit_post->ID);
                            echo ( $kit_type[0] == 'll' || empty($kit_type)  ) ? $llcounter++ : '';
                          ?>
                        </a>
                      </td>
                      <td class="desc">
                        <header class="entry-header">
                          <h3 class="entry-title">
                            <a href="<?php echo $kit_link;?>">
                              <?php echo $kit_post->post_title;?>
                            </a>
                          </h3>
                        </header><!-- .entry-header -->
                        <div class="entry-content">
                          <?php echo apply_filters('the_content', $kit_post->post_excerpt);  ?>
                        </div><!-- .entry-content -->
                      
                        <?php
                          edit_post_link(
                            sprintf(
                              // translators: %s: Name of current post 
                              __( 'Edit<span class="screen-reader-text"> "%s"</span>', 'bz' ),
                              get_the_title($kit_post->ID)
                            ),
                            '<footer class="entry-footer"><span class="edit-link">',
                            '</span></footer><!-- .entry-footer -->',
                            $kit_post->ID
                          );
                        ?>
                      
                      </td><!-- #activity-## -->
                    </tr>
                <?php
              } // END if ($kit_post->post_status == 'publish') 
            } // END foreach ?>
          </table><!-- #kits -->
          <?php 
        } // END if (!is_empty($kits_links))
      } // END if (!empty($post['post_content'])) ?>
    </main><!-- .site-main -->
  </div><!-- .content-area -->

<?php get_footer(); ?>
