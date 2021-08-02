<?php

// Disable updates to prevent WP from going into maintenance mode while tests run
add_filter( 'enable_maintenance_mode', '__return_false' );
add_filter( 'wp_auto_update_core', '__return_false' );
add_filter( 'auto_update_plugin', '__return_false' );
add_filter( 'auto_update_theme', '__return_false' );
/**
 * This registers custom Post Type and Taxomomy for use in query tests to make sure
 * APIs for Custom Taxonomies and Custom Post Types work as expected.
 */
add_action( 'init', function() {
	register_post_type(
		'bootstrap_cpt',
		[
			'show_in_graphql'     => true,
			'graphql_single_name' => 'bootstrapPost',
			'graphql_plural_name' => 'bootstrapPosts',
			'hierarchical'        => true,
			'taxonomies' => [ 'bootstrap_tax' ]
		]
	);
	register_taxonomy(
		'bootstrap_tax',
		[ 'bootstrap_cpt' ],
		[
			'show_in_graphql'     => true,
			'graphql_single_name' => 'bootstrapTerm',
			'graphql_plural_name' => 'bootstrapTerms',
			'hierarchical'        => true,
		]
	);
});
