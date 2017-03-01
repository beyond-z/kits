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
	add_image_size( 'header', 768, 360, array ('center', 'center') );

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
// Kit:
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

// Activity:
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
		'hierarchical'          => false,
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
// Custom content (for things we'd like to edit like the footer text, copyright notice, etc.):
function bz_register_custom_content() {

	$labels = array(
		'name'                  => _x( 'Custom Content', 'Post Type General Name', 'bz' ),
		'singular_name'         => _x( 'Custom Content', 'Post Type Singular Name', 'bz' ),
		'menu_name'             => __( 'Custom Content', 'bz' ),
		'name_admin_bar'        => __( 'Custom Content', 'bz' ),
		'archives'              => __( 'Item Archives', 'bz' ),
		'parent_item_colon'     => __( 'Parent Item:', 'bz' ),
		'all_items'             => __( 'All Custom Content', 'bz' ),
		'add_new_item'          => __( 'Add New Custom Content', 'bz' ),
		'add_new'               => __( 'Add New', 'bz' ),
		'new_item'              => __( 'New Custom Content', 'bz' ),
		'edit_item'             => __( 'Edit Custom Content', 'bz' ),
		'update_item'           => __( 'Update Custom Content', 'bz' ),
		'view_item'             => __( 'View Custom Content', 'bz' ),
		'search_items'          => __( 'Search Custom Content', 'bz' ),
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
		'label'                 => __( 'Custom Content', 'bz' ),
		'labels'                => $labels,
		'supports'              => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields', 'page-attributes', ),
		//'taxonomies'            => array( 'material', 'post_tag' ),
		'hierarchical'          => false,
		'public'                => false,
		'show_ui'               => true,
		'show_in_menu'          => true,
		'menu_position'         => 6,
		'menu_icon'             => 'dashicons-post-status',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => false,		
		'exclude_from_search'   => true,
		'publicly_queryable'    => false,
		'capability_type'       => 'page',
	);
	register_post_type( 'customcontent', $args );

}
add_action( 'init', 'bz_register_custom_content', 0 );
/**/
/* Add metaboxes (small dialog boxes on the editor screen to input custom fields) */
/**/
/**/
/* IMPORTANT: This implementation relies on a plugin called meta-box (https://metabox.io)                 */
/*            but can be done without it (just lots more work). See the plugin website for documentation  */
/**/

add_filter( 'rwmb_meta_boxes', 'bz_meta_boxes' );
// Need a few of these elsewhere so creating globally accesible arrays:
$bz_scopes = array(
					 	'cohort' => __('Cohort', 'bz'),
					 	'pairs' => __('Pairs/Triads', 'bz'),
					 	'ind' => __('Individuals', 'bz'),
					 	'all' => __('All Cohorts', 'bz'),
					 );
$bz_logistics = array(
		'bz_kit_audience' => array(
			 'name' => __( 'Audience', 'bz' ),
			 'type' => 'wysiwyg',
		),
		'bz_kit_expectations' => array(
			 'name' => __( 'Expectations', 'bz' ),
			 'type' => 'wysiwyg',
		),
		'bz_kit_space' => array(
			 'name' => __( 'Space', 'bz' ),
			 'type' => 'wysiwyg',
		),
		'bz_kit_facilitators' => array(
			 'name' => __( 'Facilitators', 'bz' ),
			 'type' => 'wysiwyg',
		),
		'bz_kit_guests' => array(
			 'name' => __( 'Guests', 'bz' ),
			 'type' => 'wysiwyg',
		),
);
$bz_logistics_fields;
foreach ($bz_logistics as $bzlk => $bzlv) {
	$bz_logistics_fields[] = array (
		'id' => $bzlk,
		'name' => $bzlv['name'],
		'type' => $bzlv['type'],
	);
}
$bz_staff_tasks = array(
	'bz_kit_logistics_3m' => array(
		 'name' => __( '3+ months out', 'bz' ),
		 'type' => 'wysiwyg',
	),
	'bz_kit_logistics_1m' => array(
		 'name' => __( '1 month out', 'bz' ),
		 'type' => 'wysiwyg',
	),
	'bz_kit_logistics_3w' => array(
		 'name' => __( '3 weeks out', 'bz' ),
		 'type' => 'wysiwyg',
	),
	'bz_kit_logistics_2w' => array(
		 'name' => __( '2 weeks out', 'bz' ),
		 'type' => 'wysiwyg',
	),
	'bz_kit_logistics_1w' => array(
		 'name' => __( '1 week out', 'bz' ),
		 'type' => 'wysiwyg',
	),
	'bz_kit_logistics_day-of' => array(
		 'name' => __( 'Day of', 'bz' ),
		 'type' => 'wysiwyg',
	),
	'bz_kit_logistics_day-after' => array(
		 'name' => __( 'Right after', 'bz' ),
		 'type' => 'wysiwyg',
	),
);
$bz_staff_tasks_fields;
foreach ($bz_staff_tasks as $bzstk => $bzstv) {
	$bz_staff_tasks_fields[] = array (
		'id' => $bzstk,
		'name' => $bzstv['name'],
		'type' => $bzstv['type'],
	);
}
// Now let's make the boxes:
function bz_meta_boxes( $meta_boxes ) {
	global $bz_scopes;
	global $bz_logistics_fields;
	global $bz_staff_tasks_fields;
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
				'options' => $bz_scopes
			),
		),
	);
	$meta_boxes[] = array(
		'title'      => __( 'Kit Attributes', 'bz' ),
		'post_types' => array ('kit'),
		'fields'     => array(
			array(
				'id'   => 'bz_kit_type',
				'name' => __( 'Kit Type', 'bz' ),
				'type' => 'radio',
				'options' => array(
					'll' => __('Learning Lab (default)', 'bz'),
					'workshop' => __('Workshop (such as Public Narrative)', 'bz'),
				),
			),
			array(
				'id'   => 'bz_kit_vision',
				'name' => __( 'Vision', 'bz' ),
				'type' => 'wysiwyg',
			),
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
				'id'   => 'bz_kit_after',
				'name' => __( 'After Learning Lab (please use bullets; begin with a term in bold, followed by &ldquo;&nbsp;&ndash;&nbsp;&rdquo;', 'bz' ),
				'type' => 'wysiwyg',
			),
			array(
				'id'   => 'bz_kit_start_time_adjust',
				'name' => __( 'Start time adjustment (e.g. -30 means the first activity starts 30 minutes ahead of usual start time)', 'bz' ),
				'type' => 'text',
			),
		)
	);
	$meta_boxes[] = array(
		'title'      => __( 'Activities', 'bz' ),
		'post_types' => array ('kit'),
		'fields'     => array(
			array(
				'id'   => 'bz_kit_agenda',
				'name' => __( 'Make an ordered list of links to the activities you wish to include in this kit.', 'bz' ),
				'type' => 'wysiwyg',
			),
		),
	);
	$meta_boxes[] = array(
		'title'      => __( 'Logistical Information', 'bz' ),
		'post_types' => array ('kit'),
		'fields'     => $bz_logistics_fields,
	);
	$meta_boxes[] = array(
		'title'      => __( 'What Staff Needs To Do', 'bz' ),
		'post_types' => array ('kit'),
		'fields'     => $bz_staff_tasks_fields,
	);
	$meta_boxes[] = array(
		'title'      => __( 'Appendix', 'bz' ),
		'post_types' => array ('kit'),
		'fields'     => array(
			array(
				'id'   => 'bz_kit_appendix',
				'name' => __( 'This will appear at the end of the kit.', 'bz' ),
				'type' => 'wysiwyg',
			),
		),
	);
	return $meta_boxes;
}

/* Register Custom Taxonomy: */
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

/* Add custom styles to TinyMCE editor: */

// See here for docs: https://codex.wordpress.org/TinyMCE_Custom_Styles
// First enable 'styleselect' into the $buttons array on row 2 of the TinyMCE UI
function bz_mce_buttons_2( $buttons ) {
	array_unshift( $buttons, 'styleselect' );
	return $buttons;
}
add_filter( 'mce_buttons_2', 'bz_mce_buttons_2' );

// Now add the custom styles:
function bz_mce_before_init_insert_formats( $init_array ) {  
	$style_formats = array(  
		// Each array child is a format with it's own settings
		array(  
			'title' => 'Core activity',  
			'block' => 'div',  
			'classes' => 'core',
			'exact' => true,
			'wrapper' => true,
		
		), 
	);  
	// Insert the array, JSON ENCODED, into 'style_formats'
	$init_array['style_formats'] = json_encode( $style_formats );  
	
	return $init_array;  

} 
// Attach callback to 'tiny_mce_before_init' 
add_filter( 'tiny_mce_before_init', 'bz_mce_before_init_insert_formats' );


/* Generate a prefix for LL titles to show week number: */


// collect post IDs in a global array for all the kits that are LLs (or are not defined as anything else):
$only_lls = only_lls();
function only_lls(){
	global $post;
	$only_lls = array();
	// get all kits using this sub-query:
	$cargs = array (
		'post_type'              => 'kit',
		'post_status'            => 'publish',
		'nopaging'               => true,
		'posts_per_page'         => '-1',
		'order'                  => 'ASC',
		'orderby'                => 'menu_order',
	);
	$ckits = new WP_Query( $cargs );
	if ( $ckits->have_posts() ) { 
		while ( $ckits->have_posts() ) { 
			// for each query result, test whether it's a LL or blank, and if so add it to the array:
			$ckits->the_post();
			$kit_type = get_post_custom_values('bz_kit_type', $post->ID);
			if ( $kit_type[0] == 'll' || empty($kit_type)  )  {
				$only_lls[] = $post->ID;	
			}
		}
	}
	return $only_lls;	
}
// Create a "filter" that adds the LL number to relevant kits:
function bz_kit_title_prefix($title) {
	$currentID = get_the_ID();
	global $only_lls;
	$counter = 0;
	
	// only apply to user-facing pages:
	if (is_home() || is_single()) {
		// find a match with the list of LLs we've compiled:
		foreach ($only_lls as $ll) {
			$counter ++;
			if ($ll == $currentID) {
				$title = __('Learning Lab ','bz').$counter.': '.$title;
			} 
		} 
	}
	return $title;

}
add_filter('the_title', 'bz_kit_title_prefix');

/**/
