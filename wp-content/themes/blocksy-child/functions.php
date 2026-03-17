<?php

if (! defined('WP_DEBUG')) {
	die( 'Direct access forbidden.' );
}

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
	wp_enqueue_style( 'child-style', get_stylesheet_uri(), array( 'parent-style' ), wp_get_theme()->get('Version') );
       	 wp_enqueue_script('jquery');

 	 wp_enqueue_script( 'child-custom-js', get_stylesheet_directory_uri() . '/custom.js','');
});


// add_action('wp_footer', function() {
//     get_template_part('popup');
// });


// Register Custom Post Type: Board Member
function create_board_member_cpt() {

    $labels = array(
        'name'                  => _x( 'Board Members', 'Post Type General Name', 'textdomain' ),
        'singular_name'         => _x( 'Board Member', 'Post Type Singular Name', 'textdomain' ),
        'menu_name'             => __( 'Board Members', 'textdomain' ),
        'name_admin_bar'        => __( 'Board Member', 'textdomain' ),
        'add_new_item'          => __( 'Add New Board Member', 'textdomain' ),
        'edit_item'             => __( 'Edit Board Member', 'textdomain' ),
        'new_item'              => __( 'New Board Member', 'textdomain' ),
        'view_item'             => __( 'View Board Member', 'textdomain' ),
        'search_items'          => __( 'Search Board Members', 'textdomain' ),
        'not_found'             => __( 'No Board Members found', 'textdomain' ),
        'not_found_in_trash'    => __( 'No Board Members found in Trash', 'textdomain' ),
    );

    $args = array(
        'label'                 => __( 'Board Member', 'textdomain' ),
        'labels'                => $labels,
        'supports'              => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
        'public'                => true,
        'show_in_menu'          => true,
        'menu_icon'             => 'dashicons-groups', // WordPress dashicon
        'has_archive'           => true,
        'rewrite'               => array( 'slug' => 'board-member' ),
        'show_in_rest'          => true, // Enables Gutenberg & REST API
    );

    register_post_type( 'board_member', $args );
}
add_action( 'init', 'create_board_member_cpt', 0 );
