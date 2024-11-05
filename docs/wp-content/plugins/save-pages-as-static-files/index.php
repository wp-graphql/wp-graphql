<?php
/*
Plugin Name: Documentation Pages
Plugin URI: https://w.org/playground
Description: Manage HTML documentation pages using WordPress.
Version: 0.1
Author: WordPress Contributors
License: GPL2
*/


/**
 * Placeholder site URL to be used in the exported static files.
 */
define('DOCS_INTERNAL_SITE_URL', 'https://playground.internal');

/**
 * Recreate the entire file structure when any post is saved.
 * 
 * It's easier to recreate everything than to keep track of
 * which files have been added, deleted, renamed and moved under
 * another parent, or changed via a direct SQL query.
 */
$should_regenerate_docs = false;
add_action('save_post_page', function ($post_id) use(&$should_regenerate_docs) {
    // Prevent collisions between the initial create_db_pages_from_static_files call
    // process and the save_post_page hook.
    if (!get_option('static_files_imported')) {
        return;
    }

    // Mark the docs for regeneration after the request is done.
    // We can't just regenerate it here because markdown gets stored as post meta,
    // and post meta is not yet updated at this point.
    $should_regenerate_docs = true;
});

function regenerate_static_files_after_request($response, $server) {
    if ($GLOBALS['should_regenerate_docs']) {
        docs_plugin_deltree(STATIC_FILES_ROOT);
        save_db_pages_as_static_files(STATIC_FILES_ROOT);
    }

    return $response;
}
add_filter('rest_post_dispatch', 'regenerate_static_files_after_request', 10, 2);

function docs_plugin_deltree($path, $rmroot=false) {
    if (!file_exists($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        /** @var SplFileInfo $file */
        if($file->getBasename() === '.' || $file->getBasename() === '..') {
            continue;
        }
        if ($file->isDir()) {
            // Only delete empty directories
            if(scandir($file->getRealPath()) === array('.', '..')) {
                rmdir($file->getRealPath());
            }
        } else if($file->isFile()) {
            // Only delete markdown and blockhtml files
            if ($ext === 'md' || $ext === 'blockhtml') {
                unlink($file->getRealPath());
            }
        }
    }

    if ($rmroot) {
        rmdir($path);
    }
}


function save_db_pages_as_static_files($path, $parent_id = 0) {
    $file_extensions = array(
        WP_Serialized_Page_Content::FORMAT_HTML => 'html',
        WP_Serialized_Page_Content::FORMAT_MARKDOWN => 'md',
    );

    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }

    $args = array(
        'post_type'      => 'page',
        'posts_per_page' => -1,
        'post_parent'    => $parent_id,
        'post_status'    => 'publish',
    );
    $pages = new WP_Query($args);

    if ($pages->have_posts()) {
        while ($pages->have_posts()) {
            $pages->the_post();
            $page_id = get_the_ID();
            $page = get_post($page_id);
            
            $serialized_page = serialize_page($page_id);

            // Replace current site URL with a placeholder URL for the export.
            // @TODO: This is very naive, let's actually parse the block 
            //        markup and the static markup and make these replacements
            //        in the JSON and HTML attributes structures, not just in
            //        their textual representation.
            $serialized_page->content = str_replace(
                get_site_url(),
                DOCS_INTERNAL_SITE_URL,
                $serialized_page->content
            );
            $child_pages = get_pages(array('child_of' => $page_id, 'post_type' => 'page'));

            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }

            $ext = $file_extensions[$serialized_page->format];
            $is_index = !empty($child_pages) || get_post_meta($page_id, 'markdown_is_index', true);
            if ($is_index) {
                $new_parent = $path . '/' . $page->post_name;
                if (!file_exists($new_parent)) {
                    mkdir($new_parent, 0777, true);
                }
                file_put_contents($new_parent . '/README.' . $ext, $serialized_page->content);
                save_db_pages_as_static_files($new_parent, $page_id);
            } else {
                file_put_contents($path . '/' . $page->post_name . '.' . $ext, $serialized_page->content);
            }
        }
    }
    wp_reset_postdata();
}

function serialize_page($page_id) {
    $page = get_post($page_id);

    // Infer the format
    $format = WP_Serialized_Page_Content::FORMAT_HTML;
    $markdown_content = get_post_meta($page_id, 'markdown_content', true);
    if(!empty($markdown_content)) {
        $format = WP_Serialized_Page_Content::FORMAT_MARKDOWN;
    }

    // Serialize the content
    switch($format) {
        case 'markdown':
            return new WP_Serialized_Page_Content(
                WP_Serialized_Page_Content::FORMAT_MARKDOWN,
                $markdown_content
            );
        case 'html':
        default:
            return new WP_Serialized_Page_Content(
                WP_Serialized_Page_Content::FORMAT_HTML,
                '<h1>' . esc_html($page->post_title) . "</h1>\n\n" . $page->post_content
            );
    }
}


class WP_Serialized_Page_Content {
    const FORMAT_HTML = 'html';
    const FORMAT_MARKDOWN = 'markdown';

    public $content;
    public $format;

    public function __construct($format, $content) {
        $this->format = $format;
        $this->content = $content;
    }
}
