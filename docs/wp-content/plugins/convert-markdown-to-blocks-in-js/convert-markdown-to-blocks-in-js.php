<?php
/*
Plugin Name: Convert markdown to blocks in JS
Description: A plugin that loads markdown files into WordPress
Version: 1.0
Author: WordPress community
*/

function convert_markdown_markdown_scripts() {
    // Only load the markdown files once
    // @TODO: Two way sync
    if(get_option('static_files_imported')) {
        return;
    }

    $data = array(
        'markdown' => get_static_files_to_import(STATIC_FILES_ROOT, array(
            'index_file_name' => 'README.md',
            'page_extension' => 'md',
            'load_content_from_extensions' => ['md'],
        ))
    );
    wp_register_script('playground-markdown', plugin_dir_url(__FILE__) . 'convert-markdown-to-blocks-in-js.js', array('wp-api', 'wp-blocks'));
    wp_localize_script('playground-markdown', 'playgroundMarkdown', $data);
    wp_enqueue_script('playground-markdown');

    wp_enqueue_style('playground-markdown', plugin_dir_url(__FILE__) . 'convert-markdown-to-blocks-in-js.css');
}
add_action('enqueue_block_editor_assets', 'convert_markdown_markdown_scripts');


function convert_markdown_markdown_loader($classes) {
    $classes[] = 'playground-markdown-loading';
    return $classes;
}
add_filter('body_class', 'convert_markdown_markdown_loader');

function convert_markdown_register_rest_endpoint() {
    register_rest_route('wp/v2', 'markdown-bulk-import', array(
        'methods' => 'POST',
        'callback' => 'convert_markdown_handle_rest_request',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ));
}
add_action('rest_api_init', 'convert_markdown_register_rest_endpoint');

function convert_markdown_handle_rest_request($request) {
    if(get_option('static_files_imported')) {
        return rest_ensure_response(array(
            'message' => 'Markdown already imported',
        ));
    }

    // Handle the REST request here
    $hierarchy = create_pages($request->get_params()['pages']);

    update_option('static_files_imported', time());

    // Example response
    $response = array(
        'message' => 'Endpoint called successfully',
        'hierarchy' => $hierarchy
    );

    return rest_ensure_response($response);
}

// Redirect every logged-in user to the page editor where the markdown to blocks
// conversion will happen.
function check_static_files_imported() {
    // Check if the current user can manage options (i.e., an admin)
    if (
        is_user_logged_in() && 
        current_user_can('manage_options') && 
        !get_option('static_files_imported') &&
        strpos($_SERVER['REQUEST_URI'], 'post-new.php?post_type=page') === false
    ) {
        // Redirect to /wp-admin/post-new.php?post_type=page
        $return_to = http_build_query(array(
            'markdown_import_return_to' => $_SERVER['REQUEST_URI']
        ));
        wp_redirect(admin_url('post-new.php?post_type=page&' . $return_to));
        exit;
    }
}
// Hook for admin area
add_action('admin_init', 'check_static_files_imported');

// Hook for frontend area
function check_static_files_imported_frontend() {
    if (
        is_user_logged_in() && 
        current_user_can('manage_options') &&
        !get_option('static_files_imported')
    ) {
        // Redirect to /wp-admin/post-new.php?post_type=page
        $return_to = http_build_query(array(
            'markdown_import_return_to' => $_SERVER['REQUEST_URI']
        ));
        wp_redirect(admin_url('post-new.php?post_type=page&' . $return_to));
        exit;
    }
}
add_action('template_redirect', 'check_static_files_imported_frontend');
