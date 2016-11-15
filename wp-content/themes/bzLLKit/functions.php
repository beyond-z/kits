<?php
/**
 * Braven LL Kit functions and definitions
 *
 * Set up the theme and provides some helper functions, which are used in the
 * theme as custom template tags. Others are attached to action and filter
 * hooks in WordPress to change core functionality.
 *
 * When using a child theme you can override certain functions (those wrapped
 * in a function_exists() call) by defining them first in your child theme's
 * functions.php file. The child theme's functions.php file is included before
 * the parent theme's file, so the child theme functions would be used.
 *
 * @link https://codex.wordpress.org/Theme_Development
 * @link https://codex.wordpress.org/Child_Themes
 *
 * Functions that are not pluggable (not wrapped in function_exists()) are
 * instead attached to a filter or action hook.
 *
 * For more information on hooks, actions, and filters,
 * {@link https://codex.wordpress.org/Plugin_API}
 *
 * @package WordPress
 * @subpackage Braven_LL_Kit
 * @since LL Kit 1.0
 */

/**
 * Braven LL Kit only works in WordPress 4.4 or later.
 */
if ( version_compare( $GLOBALS['wp_version'], '4.4-alpha', '<' ) ) {
	require get_template_directory() . '/inc/back-compat.php';
}

if ( ! function_exists( 'bz_setup' ) ) :
/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 *
 * Create your own bz_setup() function to override in a child theme.
 *
 * @since LL Kit 1.0
 */
function bz_setup() {
	/*
	 * Make theme available for translation.
	 * Translations can be filed at WordPress.org. See: https://translate.wordpress.org/projects/wp-themes/bz
	 * If you're building a theme based on Braven LL Kit, use a find and replace
	 * to change 'bz' to the name of your theme in all the template files
	 */
	//load_theme_bz( 'bz' );

	// Add default posts and comments RSS feed links to head.
	add_theme_support( 'automatic-feed-links' );

	/*
	 * Let WordPress manage the document title.
	 * By adding theme support, we declare that this theme does not use a
	 * hard-coded <title> tag in the document head, and expect WordPress to
	 * provide it for us.
	 */
	add_theme_support( 'title-tag' );

	/*
	 * Enable support for Post Thumbnails on posts and pages.
	 *
	 * @link http://codex.wordpress.org/Function_Reference/add_theme_support#Post_Thumbnails
	 */
	add_theme_support( 'post-thumbnails' );
	set_post_thumbnail_size( 1200, 9999 );
	add_image_size( 'list', 220, 145, true);
	add_image_size( 'header', 2000, 250, array('center','center'));

	// This theme uses wp_nav_menu() in two locations.
	register_nav_menus( array(
		'primary' => __( 'Primary Menu', 'bz' )
	) );

	/*
	 * Switch default core markup for search form, comment form, and comments
	 * to output valid HTML5.
	 */
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
	) );

	/*
	 * This theme styles the visual editor to resemble the theme style,
	 * specifically font, colors, icons, and column width.
	 */
	add_editor_style( array( 'css/editor-style.css', bz_fonts_url() ) );

}
endif; // bz_setup
add_action( 'after_setup_theme', 'bz_setup' );

if ( ! function_exists( 'bz_fonts_url' ) ) :
/**
 * Register Google fonts for Braven LL Kit.
 *
 * Create your own bz_fonts_url() function to override in a child theme.
 *
 * @since LL Kit 1.0
 *
 * @return string Google fonts URL for the theme.
 */
function bz_fonts_url() {
	$fonts_url = '';
	$fonts     = array();
	$subsets   = 'latin,latin-ext';

	/* translators: If there are characters in your language that are not supported by Merriweather, translate this to 'off'. Do not translate into your own language. */
	if ( 'off' !== _x( 'on', 'Oswald font: on or off', 'bz' ) ) {
		$fonts[] = 'Oswald:400,700,900,400italic,700italic';
	}

	if ( $fonts ) {
		$fonts_url = add_query_arg( array(
			'family' => urlencode( implode( '|', $fonts ) ),
			'subset' => urlencode( $subsets ),
		), 'https://fonts.googleapis.com/css' );
	}

	return $fonts_url;
}
endif;

/**
 * Handles JavaScript detection.
 *
 * Adds a `js` class to the root `<html>` element when JavaScript is detected.
 *
 * @since LL Kit 1.0
 */
function bz_javascript_detection() {
	echo "<script>(function(html){html.className = html.className.replace(/\bno-js\b/,'js')})(document.documentElement);</script>\n";
}
add_action( 'wp_head', 'bz_javascript_detection', 0 );

/**
 * Enqueues scripts and styles.
 *
 * @since LL Kit 1.0
 */
function bz_scripts() {
	// Add custom fonts, used in the main stylesheet.
	wp_enqueue_style( 'bz-fonts', bz_fonts_url(), array(), null );

	// Add Genericons, used in the main stylesheet.
	wp_enqueue_style( 'genericons', get_template_directory_uri() . '/genericons/genericons.css', array(), '3.4.1' );

	// Theme stylesheet.
	wp_enqueue_style( 'bz-style', get_stylesheet_uri() );

	// Load the Internet Explorer specific stylesheet.
	wp_enqueue_style( 'bz-ie', get_template_directory_uri() . '/css/ie.css', array( 'bz-style' ), '20160816' );
	wp_style_add_data( 'bz-ie', 'conditional', 'lt IE 10' );

	// Load the Internet Explorer 8 specific stylesheet.
	wp_enqueue_style( 'bz-ie8', get_template_directory_uri() . '/css/ie8.css', array( 'bz-style' ), '20160816' );
	wp_style_add_data( 'bz-ie8', 'conditional', 'lt IE 9' );

	// Load the Internet Explorer 7 specific stylesheet.
	wp_enqueue_style( 'bz-ie7', get_template_directory_uri() . '/css/ie7.css', array( 'bz-style' ), '20160816' );
	wp_style_add_data( 'bz-ie7', 'conditional', 'lt IE 8' );

	// Load the html5 shiv.
	wp_enqueue_script( 'bz-html5', get_template_directory_uri() . '/js/html5.js', array(), '3.7.3' );
	wp_script_add_data( 'bz-html5', 'conditional', 'lt IE 9' );

	wp_enqueue_script( 'bz-skip-link-focus-fix', get_template_directory_uri() . '/js/skip-link-focus-fix.js', array(), '20160816', true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	if ( is_singular() && wp_attachment_is_image() ) {
		wp_enqueue_script( 'bz-keyboard-image-navigation', get_template_directory_uri() . '/js/keyboard-image-navigation.js', array( 'jquery' ), '20160816' );
	}

	wp_enqueue_script( 'bz-script', get_template_directory_uri() . '/js/functions.js', array( 'jquery' ), '20160816', true );

	wp_localize_script( 'bz-script', 'screenReaderText', array(
		'expand'   => __( 'expand child menu', 'bz' ),
		'collapse' => __( 'collapse child menu', 'bz' ),
	) );
}
add_action( 'wp_enqueue_scripts', 'bz_scripts' );

/**
 * Adds custom classes to the array of body classes.
 *
 * @since LL Kit 1.0
 *
 * @param array $classes Classes for the body element.
 * @return array (Maybe) filtered body classes.
 */
function bz_body_classes( $classes ) {
	// Adds a class of custom-background-image to sites with a custom background image.
	if ( get_background_image() ) {
		$classes[] = 'custom-background-image';
	}

	// Adds a class of hfeed to non-singular pages.
	if ( ! is_singular() ) {
		$classes[] = 'hfeed';
	}

	return $classes;
}
add_filter( 'body_class', 'bz_body_classes' );

/**
 * Converts a HEX value to RGB.
 *
 * @since LL Kit 1.0
 *
 * @param string $color The original color, in 3- or 6-digit hexadecimal form.
 * @return array Array containing RGB (red, green, and blue) values for the given
 *               HEX code, empty array otherwise.
 */
function bz_hex2rgb( $color ) {
	$color = trim( $color, '#' );

	if ( strlen( $color ) === 3 ) {
		$r = hexdec( substr( $color, 0, 1 ).substr( $color, 0, 1 ) );
		$g = hexdec( substr( $color, 1, 1 ).substr( $color, 1, 1 ) );
		$b = hexdec( substr( $color, 2, 1 ).substr( $color, 2, 1 ) );
	} else if ( strlen( $color ) === 6 ) {
		$r = hexdec( substr( $color, 0, 2 ) );
		$g = hexdec( substr( $color, 2, 2 ) );
		$b = hexdec( substr( $color, 4, 2 ) );
	} else {
		return array();
	}

	return array( 'red' => $r, 'green' => $g, 'blue' => $b );
}

/**
 * Add custom image sizes attribute to enhance responsive image functionality
 * for content images
 *
 * @since LL Kit 1.0
 *
 * @param string $sizes A source size value for use in a 'sizes' attribute.
 * @param array  $size  Image size. Accepts an array of width and height
 *                      values in pixels (in that order).
 * @return string A source size value for use in a content image 'sizes' attribute.
 */
function bz_content_image_sizes_attr( $sizes, $size ) {
	$width = $size[0];

	840 <= $width && $sizes = '(max-width: 709px) 85vw, (max-width: 909px) 67vw, (max-width: 1362px) 62vw, 840px';

	if ( 'kit' === get_post_type() ) {
		840 > $width && $sizes = '(max-width: ' . $width . 'px) 85vw, ' . $width . 'px';
	} else {
		840 > $width && 600 <= $width && $sizes = '(max-width: 709px) 85vw, (max-width: 909px) 67vw, (max-width: 984px) 61vw, (max-width: 1362px) 45vw, 600px';
		600 > $width && $sizes = '(max-width: ' . $width . 'px) 85vw, ' . $width . 'px';
	}

	return $sizes;
}
add_filter( 'wp_calculate_image_sizes', 'bz_content_image_sizes_attr', 10 , 2 );

/** Hide admin bar */
show_admin_bar( false );

/** Add custom post types for the LL Kit and its components  */
// Session
function bz_register_kit() {

	$labels = array(
		'name'                  => _x( 'Kits', 'Post Type General Name', 'bz' ),
		'singular_name'         => _x( 'Kits', 'Post Type Singular Name', 'bz' ),
		'menu_name'             => __( 'Kits', 'bz' ),
		'name_admin_bar'        => __( 'Kit', 'bz' ),
		'archives'              => __( 'Item Archives', 'bz' ),
		'parent_item_colon'     => __( 'Parent Item:', 'bz' ),
		'all_items'             => __( 'All Kits', 'bz' ),
		'add_new_item'          => __( 'Add New Kit', 'bz' ),
		'add_new'               => __( 'Add New', 'bz' ),
		'new_item'              => __( 'New Kit', 'bz' ),
		'edit_item'             => __( 'Edit Kit', 'bz' ),
		'update_item'           => __( 'Update Kit', 'bz' ),
		'view_item'             => __( 'View Kit', 'bz' ),
		'search_items'          => __( 'Search Item', 'bz' ),
		'not_found'             => __( 'Not found', 'bz' ),
		'not_found_in_trash'    => __( 'Not found in Trash', 'bz' ),
		'featured_image'        => __( 'Featured Image', 'bz' ),
		'set_featured_image'    => __( 'Set featured image', 'bz' ),
		'remove_featured_image' => __( 'Remove featured image', 'bz' ),
		'use_featured_image'    => __( 'Use as featured image', 'bz' ),
		'insert_into_item'      => __( 'Insert into item', 'bz' ),
		'uploaded_to_this_item' => __( 'Uploaded to this item', 'bz' ),
		'items_list'            => __( 'Items list', 'bz' ),
		'items_list_navigation' => __( 'Items list navigation', 'bz' ),
		'filter_items_list'     => __( 'Filter items list', 'bz' ),
	);
	$args = array(
		'label'                 => __( 'Kit', 'bz' ),
		'labels'                => $labels,
		'supports'              => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields', 'page-attributes', ),
		'taxonomies'            => array( 'material', 'post_tag' ),
		'hierarchical'          => false,
		'public'                => true,
		'show_ui'               => true,
		'show_in_menu'          => true,
		'menu_position'         => 6,
		'menu_icon'             => 'dashicons-clipboard',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => true,		
		'exclude_from_search'   => false,
		'publicly_queryable'    => true,
		'capability_type'       => 'page',
	);
	register_post_type( 'kit', $args );

}
add_action( 'init', 'bz_register_kit', 0 );

function bz_register_activity() {

	$labels = array(
		'name'                  => _x( 'Activities', 'Post Type General Name', 'bz' ),
		'singular_name'         => _x( 'Activity', 'Post Type Singular Name', 'bz' ),
		'menu_name'             => __( 'Activities', 'bz' ),
		'name_admin_bar'        => __( 'Activity', 'bz' ),
		'archives'              => __( 'Activity Archives', 'bz' ),
		'parent_item_colon'     => __( 'Parent Activity:', 'bz' ),
		'all_items'             => __( 'All Activities', 'bz' ),
		'add_new_item'          => __( 'Add New Activity', 'bz' ),
		'add_new'               => __( 'Add New', 'bz' ),
		'new_item'              => __( 'New Activity', 'bz' ),
		'edit_item'             => __( 'Edit Activity', 'bz' ),
		'update_item'           => __( 'Update Activity', 'bz' ),
		'view_item'             => __( 'View Activity', 'bz' ),
		'search_items'          => __( 'Search Activity', 'bz' ),
		'not_found'             => __( 'Not found', 'bz' ),
		'not_found_in_trash'    => __( 'Not found in Trash', 'bz' ),
		'featured_image'        => __( 'Featured Image', 'bz' ),
		'set_featured_image'    => __( 'Set featured image', 'bz' ),
		'remove_featured_image' => __( 'Remove featured image', 'bz' ),
		'use_featured_image'    => __( 'Use as featured image', 'bz' ),
		'insert_into_item'      => __( 'Insert into item', 'bz' ),
		'uploaded_to_this_item' => __( 'Uploaded to this item', 'bz' ),
		'items_list'            => __( 'Items list', 'bz' ),
		'items_list_navigation' => __( 'Items list navigation', 'bz' ),
		'filter_items_list'     => __( 'Filter items list', 'bz' ),
	);
	$args = array(
		'label'                 => __( 'Activity', 'bz' ),
		'labels'                => $labels,
		'supports'              => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields', 'page-attributes', ),
		'taxonomies'            => array( 'material', 'post_tag' ),
		'hierarchical'          => true,
		'public'                => true,
		'show_ui'               => true,
		'show_in_menu'          => true,
		'menu_position'         => 7,
		'menu_icon'             => 'dashicons-clock',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => true,		
		'exclude_from_search'   => false,
		'publicly_queryable'    => true,
		'capability_type'       => 'page',
	);
	register_post_type( 'activity', $args );

}
add_action( 'init', 'bz_register_activity', 0 );
/**/
/* Add metaboxes (small dialog boxes on the editor screen to input custom fields) */
/**/
/**/
/* IMPORTANT: This implementation relies on a plugin called meta-box (https://metabox.io)                 */
/*            but can be done without it (just lots more work). See the plugin website for documentation  */
/**/

add_filter( 'rwmb_meta_boxes', 'bz_meta_boxes' );
function bz_meta_boxes( $meta_boxes ) {
    $meta_boxes[] = array(
        'title'      => __( 'Activity Attributes', 'bz' ),
        'post_types' => 'activity',
        'fields'     => array(
            array(
                'id'   => 'bz_activity_attributes_minutes',
                'name' => __( 'Minutes', 'bz' ),
                'type' => 'number',
            ),
				array(
                'id'   => 'bz_activity_attributes_group_scope',
                'name' => __( 'Group Scope', 'bz' ),
                'type' => 'radio',
					 'options' => array(
					 	'cohort' => __('Cohort (default if none selected)', 'bz'),
					 	'pairs' => __('Pairs/Triads', 'bz'),
					 	'ind' => __('Individuals', 'bz'),
					 	'all' => __('All Cohorts', 'bz'),
					 )
            ),
        ),
    );
	 $meta_boxes[] = array(
        'title'      => __( 'Kit Attributes', 'bz' ),
        'post_types' => 'kit',
        'fields'     => array(
            array(
                'id'   => 'bz_kit_outcomes',
                'name' => __( 'Fellows Will... (please use bullet list)', 'bz' ),
                'type' => 'wysiwyg',
            ),
				array(
                'id'   => 'bz_kit_important',
                'name' => __( 'What is most important (please use bullets; begin with a term in bold, followed by &ldquo;&nbsp;&ndash;&nbsp;&rdquo;', 'bz' ),
                'type' => 'wysiwyg',
            ),
				array(
                'id'   => 'bz_kit_prework',
                'name' => __( 'Fellows&#39;s Pre-work (please use bullet list)', 'bz' ),
                'type' => 'wysiwyg',
            ),
            array(
                'id'   => 'bz_kit_start_time_adjust',
                'name' => __( 'Start time adjustment (e.g. -30 means the first activity starts 30 minutes ahead of usual start time)', 'bz' ),
                'type' => 'text',
            ),
        ),
    );
    return $meta_boxes;
}

// Register Custom Taxonomy
function bz_generate_materials_tax() {

	$labels = array(
		'name'                       => _x( 'Materials', 'Taxonomy General Name', 'bz' ),
		'singular_name'              => _x( 'Material', 'Taxonomy Singular Name', 'bz' ),
		'menu_name'                  => __( 'Materials', 'bz' ),
		'all_items'                  => __( 'All Items', 'bz' ),
		'parent_item'                => __( 'Parent Item', 'bz' ),
		'parent_item_colon'          => __( 'Parent Item:', 'bz' ),
		'new_item_name'              => __( 'New Item Name', 'bz' ),
		'add_new_item'               => __( 'Add New Item', 'bz' ),
		'edit_item'                  => __( 'Edit Item', 'bz' ),
		'update_item'                => __( 'Update Item', 'bz' ),
		'view_item'                  => __( 'View Item', 'bz' ),
		'separate_items_with_commas' => __( 'Separate items with commas', 'bz' ),
		'add_or_remove_items'        => __( 'Add or remove items', 'bz' ),
		'choose_from_most_used'      => __( 'Choose from the most used', 'bz' ),
		'popular_items'              => __( 'Popular Items', 'bz' ),
		'search_items'               => __( 'Search Items', 'bz' ),
		'not_found'                  => __( 'Not Found', 'bz' ),
		'no_terms'                   => __( 'No items', 'bz' ),
		'items_list'                 => __( 'Items list', 'bz' ),
		'items_list_navigation'      => __( 'Items list navigation', 'bz' ),
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => false,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
	);
	register_taxonomy( 'material', array( 'kit', 'activity' ), $args );

}
add_action( 'init', 'bz_generate_materials_tax', 0 );
/** Hide post types we're not using: */

function bz_remove_menus(){
  
  remove_menu_page( 'index.php' );                  //Dashboard
  remove_menu_page( 'jetpack' );                    //Jetpack* 
  remove_menu_page( 'edit.php' );                   //Posts
  remove_menu_page( 'upload.php' );                 //Media
  remove_menu_page( 'edit.php?post_type=page' );    //Pages
  remove_menu_page( 'edit-comments.php' );          //Comments
  //remove_menu_page( 'themes.php' );                 //Appearance
  //remove_menu_page( 'plugins.php' );                //Plugins
  //remove_menu_page( 'users.php' );                  //Users
  //remove_menu_page( 'tools.php' );                  //Tools
  //remove_menu_page( 'options-general.php' );        //Settings
  
}
add_action( 'admin_menu', 'bz_remove_menus' );

/* Fucking assholes trying to put me in the FEMA camps and take my liberty: */
remove_filter( 'the_title', 'capital_P_dangit', 11 );
remove_filter( 'the_content', 'capital_P_dangit', 11 );
remove_filter( 'comment_text', 'capital_P_dangit', 31 );