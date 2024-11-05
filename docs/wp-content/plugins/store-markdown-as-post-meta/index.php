<?php
/**
 * Plugin Name: Block to Markdown Saver
 * Description: Converts blocks to markdown and saves it as post meta when the post is saved.
 * Version: 1.0
 */

function enqueue_block_to_markdown_plugin() {
    wp_enqueue_script(
        'block-to-markdown-saver',
        plugin_dir_url(__FILE__) . 'store-markdown-as-post-meta.js',
        array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor', 'wp-data', 'wp-compose', 'wp-hooks'),
        filemtime(plugin_dir_path(__FILE__) . 'store-markdown-as-post-meta.js')
    );
}

add_action('enqueue_block_editor_assets', 'enqueue_block_to_markdown_plugin');

function register_markdown_meta() {
    register_post_meta('page', 'markdown_original_path', array(
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'string',
    ));
    register_post_meta('page', 'markdown_content', array(
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'string',
    ));
    register_post_meta('page', 'markdown_is_index', array(
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'boolean',
    ));
}

add_action('init', 'register_markdown_meta');
