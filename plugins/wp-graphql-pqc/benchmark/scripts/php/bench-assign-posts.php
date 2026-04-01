<?php
/**
 * Random category + tag + author assignment for all published posts.
 *
 * @package WPGraphQL\PQC\Benchmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts -- benchmark bulk assignment.
$post_ids = get_posts(
	[
		'post_type'              => 'post',
		'post_status'            => 'publish',
		'posts_per_page'         => -1,
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	]
);

$cat_ids    = get_terms(
	[
		'taxonomy'   => 'category',
		'hide_empty' => false,
		'fields'     => 'ids',
	]
);
$tag_ids    = get_terms(
	[
		'taxonomy'   => 'post_tag',
		'hide_empty' => false,
		'fields'     => 'ids',
	]
);
$author_ids = get_users(
	[
		'role'   => 'author',
		'fields' => 'ID',
	]
);

if ( is_wp_error( $cat_ids ) || is_wp_error( $tag_ids ) || empty( $cat_ids ) || empty( $tag_ids ) || empty( $author_ids ) ) {
	WP_CLI::error( 'Need non-empty categories, post_tags, and author users before assignment.' );
}

foreach ( $post_ids as $pid ) {
	$pid = (int) $pid;
	wp_set_object_terms( $pid, [ (int) $cat_ids[ array_rand( $cat_ids ) ] ], 'category', false );
	wp_set_object_terms( $pid, [ (int) $tag_ids[ array_rand( $tag_ids ) ] ], 'post_tag', false );
	wp_update_post(
		[
			'ID'          => $pid,
			'post_author' => (int) $author_ids[ array_rand( $author_ids ) ],
		]
	);
}

WP_CLI::success( sprintf( 'Assigned categories, tags, and authors on %d posts.', count( $post_ids ) ) );
