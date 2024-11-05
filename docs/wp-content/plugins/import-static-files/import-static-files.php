<?php
/**
 * Plugin Name: Import static files to WordPress
 */

require_once __DIR__ . '/playground-post-import-processor.php';

// Disable KSES filters for all users
remove_filter('content_save_pre', 'wp_filter_post_kses');
remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');
remove_filter('excerpt_save_pre', 'wp_filter_post_kses');
remove_filter('textarea_pre', 'wp_filter_kses');
remove_filter('pre_comment_content', 'wp_filter_kses');
remove_filter('title_save_pre', 'wp_filter_kses');

// Allow unfiltered HTML for all users, including administrators and non-administrators
function allow_unfiltered_html($caps, $cap, $user_id) {
    if ($cap === 'unfiltered_html') {
        $caps = array();
    }
    return $caps;
}
add_filter('map_meta_cap', 'allow_unfiltered_html', 1, 3);

function import_static_files_from_directory($static_files_path, $options=array()) {
	$files = get_static_files_to_import($static_files_path, $options=array());
	$admin_id = get_admin_id();
	create_pages($files, $admin_id);
    update_option('docs_populated', true);
}

function get_static_files_to_import($dir, $options = array())
{
    $options = wp_parse_args($options, array(
        'index_file_name' => 'README.md',
        'page_extension' => 'md',
        'load_content_from_extensions' => ['md', 'blockhtml'],
    ));

    function find_files($dir, $options) {
        $files = array();
        if (is_dir($dir)) {
            $dh = opendir($dir);
            while (($file = readdir($dh)) !== false) {
                if ($file != "." && $file != "..") {
                    $filePath = $dir . '/' . $file;
                    if (is_dir($filePath)) {
                        $nestedFiles = find_files($filePath, $options);
                        $files = array_merge($files, $nestedFiles);
                    } elseif (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === $options['page_extension']) {
                        $extensionless_path = remove_extension($filePath);
                        $is_index = str_ends_with($file, $options['index_file_name']);
                        $file = array(
                            'is_index' => $is_index,
                            'path' => $filePath,
                            'name' => $is_index
                                ? basename(dirname($extensionless_path))
                                : basename($extensionless_path),
                        );

                        foreach ($options['load_content_from_extensions'] as $ext) {
                            if (file_exists($extensionless_path . '.' . $ext)) {
                                $file[$ext] = file_get_contents($extensionless_path . '.' . $ext);
                            }
                        }

                        $files[] = $file;
                    }
                }
            }
            closedir($dh);
        }
        return $files;
    };

    $files = find_files($dir, $options);
    foreach ($files as $k => $file) {
        $files[$k]['path'] = substr($file['path'], strlen($dir) + 1);
    }
    return $files;
}

function get_admin_id() {
	$admins = get_users(array(
		'role' => 'administrator',
		'orderby' => 'ID',
		'order' => 'ASC',
		'number' => 1
	));

	// Check if there is at least one admin
	if (!empty($admins)) {
		return $admins[0]->ID;
	}
}

function create_pages($pages, $options = array())
{
    $options = wp_parse_args($options, array(
        'author_id' => null,
        'index_file_name' => 'README.md',
        'page_extension' => 'md',
    ));
    if(!$options['author_id']) {
        $options['author_id'] = get_admin_id();
    }

    $by_path = [];
    foreach($pages as $page) {
        $by_path[$page['path']] = $page;
    }
    sortByIndexAndKeyLength($by_path);

    $ids_by_path = [];
    foreach($by_path as $page) {
        if($page['is_index']) {
            $parent_path = dirname(dirname($page['path'])) . '/' . $options['index_file_name'];
        } else {
            $parent_path = dirname($page['path']) . '/' . $options['index_file_name'];
        }
        if (isset($ids_by_path[$parent_path])) {
            $parent_id = $ids_by_path[$parent_path];
        } else {
            $parent_id = null;
        }
        // Relevant in CLI but not in a REST API endpoint:
        // echo "Creating page: " . $page['path'] . " (parent: $parent_path, parent ID: ".$parent_id.")\n";
        $ids_by_path[$page['path']] = create_page($page, array(
            'parent_id' => $parent_id,
            'author_id' => $options['author_id'],
        ));
    }
    
    return $ids_by_path;
}

function remove_extension($path) {
    $path_parts = pathinfo($path);
    return $path_parts['dirname'] . (str_contains( $path, '/' ) ? '/' : '') . $path_parts['filename'];
}

function create_page($page, $options = array())
{
    $options = wp_parse_args($options, array(
        'parent_id' => null,
        'author_id' => null
    ));
    if(!$options['author_id']) {
        $options['author_id'] = get_admin_id();
    }

    $post_title = $page['name'];

    // Source the page title from the first heading in the document.
    $content = $page['blockhtml'];
    $p = new Playground_Post_Import_Processor($content);
    $seen_no_meaningful_content = true;
    while($p->next_token()) {
        $token_type = $p->get_token_type();
        if(
            $token_type === '#funky-comment' ||
            $token_type === '#cdata-section' ||
            ($token_type === '#comment' && str_starts_with($p->get_modifiable_text(), ' wp:heading ')) ||
            ($token_type === '#text' && trim($p->get_modifiable_text()) === '')
        ) {
            continue;
        }
        if(!in_array($p->get_tag(), array('H1','H2','H3','H4','H5','H6'), true)) {
            $seen_no_meaningful_content = false;
            continue;
        }

        $p->set_bookmark('start');

        // Find the text node inside the heading
        $p->next_token();

        // Extract the text node content
        $inner_text = trim($p->get_modifiable_text());
        if($inner_text) {
            $post_title = $inner_text;
        }

        if ($seen_no_meaningful_content) {
            // If nothing other than the heading has been seen yet, we can remove
            // the content up to the current token to avoid creating a post with
            // duplication between the post title and the very first block (heading).
            // 
            // Let's look for the header closer within the next 3 tokens:
            // * h1 closer
            // * optional empty text node
            // * the closing comment
            for ($i = 0; $i < 3; $i++) {
                $p->next_token();
                if (
                    $p->get_token_type() === '#comment' &&
                    $p->get_modifiable_text() === ' /wp:heading '
                ) {
                    $bookmark = $p->get_token_indices();
                    $content = substr($content, $bookmark->start + $bookmark->length);
                    break;
                }
            }
        }

        break;
    }

	$post_id = wp_insert_post(array(
		'post_title' => $post_title,
		'post_content' => wp_slash($content),
		'post_status' => 'publish',
		'post_type' => 'page',
		'post_parent' => $options['parent_id'],
		'post_author' => $options['author_id'],
        'post_name' => $page['name'],
        'meta_input' => array(
            'markdown_content' => wp_slash($page['md']),
            'markdown_original_path' => $page['path'],
            'markdown_is_index' => $page['is_index'],
        ),
	));
    
    if (is_wp_error($post_id)) {
        exit(1);
    }

	return $post_id;
}

function sortByIndexAndKeyLength(&$array) {
    // Step 1: Extract the keys and sort them
    $keys = array_keys($array);

    usort($keys, function($a, $b) use($array) {
        // Bubble the index file to the top within the same directory
        if ($array[$a]['is_index'] && $array[$b]['is_index']) {
            return strlen($a) <=> strlen($b);
        }
        if ($array[$a]['is_index']) return -1;
        if ($array[$b]['is_index']) return 1;
        return strlen($a) <=> strlen($b);
    });

    // Step 2: Re-create the array with sorted keys
    $sorted = [];
    foreach ($keys as $key) {
        $sorted[$key] = $array[$key];
    }

    $array = $sorted;
}


// Redirect to page edit page by its meta key markdown_original_path
function redirect_to_page_edit_page() {
    if(!is_user_logged_in()) {
        return;
    }
    if(!isset($_GET['markdown-file-path'])) {
        return;
    }
    
    $args = array(
        'meta_key' => 'markdown_original_path',
        'meta_value' => $_GET['markdown-file-path'],
        'post_type' => 'page',
        'post_status' => 'any',
        'posts_per_page' => -1
    );
    $posts = get_posts($args);
    if(count($posts) === 0) {
        return;
    }

    $post_id = $posts[0]->ID;
    wp_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
    exit;    
}
add_action('admin_init', 'redirect_to_page_edit_page');

