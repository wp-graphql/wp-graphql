<?php
/**
 * Test-only probe for the ext-matrix A/B run (dev site only, delete after).
 * - RootQuery.debugGlobals: JSON snapshot of the global state a root field sees.
 * - ACF options page "Site Settings" for the ACF wysiwyg canary.
 */
function zz_ext_matrix_snapshot() {
	global $wp_query, $authordata;
	$p = get_post();
	return wp_json_encode( [
		'post'       => $p ? $p->ID : null,
		'postTitle'  => $p ? $p->post_title : null,
		'queried'    => get_queried_object_id() ?: null,
		'singular'   => is_singular(),
		'tax'        => is_tax() || is_category() || is_tag(),
		'author'     => is_author(),
		'home'       => is_home(),
		'authordata' => ( $authordata instanceof WP_User ) ? $authordata->ID : null,
		'product'    => isset( $GLOBALS['product'] ) && is_object( $GLOBALS['product'] ) && method_exists( $GLOBALS['product'], 'get_id' ) ? $GLOBALS['product']->get_id() : null,
	] );
}

function zz_ext_matrix_late( int $hops ) {
	return new \GraphQL\Deferred( static function () use ( $hops ) {
		return $hops > 0 ? zz_ext_matrix_late( $hops - 1 ) : zz_ext_matrix_snapshot();
	} );
}

add_action( 'graphql_register_types', function () {
	// Same snapshot, but resolved after N chained Deferreds, so it runs after the other root
	// fields' (deferred) node subtrees have resolved.
	register_graphql_field( 'RootQuery', 'debugGlobalsLate', [
		'type'    => 'String',
		'args'    => [ 'hops' => [ 'type' => 'Int' ] ],
		'resolve' => function ( $root, $args ) {
			return zz_ext_matrix_late( $args['hops'] ?? 25 );
		},
	] );
	register_graphql_field( 'RootQuery', 'debugGlobals', [
		'type'    => 'String',
		'resolve' => function () {
			global $wp_query, $authordata;
			$p = get_post();
			return wp_json_encode( [
				'post'       => $p ? $p->ID : null,
				'postTitle'  => $p ? $p->post_title : null,
				'queried'    => get_queried_object_id() ?: null,
				'singular'   => is_singular(),
				'tax'        => is_tax() || is_category() || is_tag(),
				'author'     => is_author(),
				'home'       => is_home(),
				'authordata' => ( $authordata instanceof WP_User ) ? $authordata->ID : null,
				'product'    => isset( $GLOBALS['product'] ) && is_object( $GLOBALS['product'] ) && method_exists( $GLOBALS['product'], 'get_id' ) ? $GLOBALS['product']->get_id() : null,
			] );
		},
	] );
} );

add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_options_page' ) ) {
		return;
	}
	acf_add_options_page( [ 'page_title' => '[ext-matrix] Site Settings', 'menu_slug' => 'ext-matrix-site-settings', 'show_in_graphql' => true, 'graphql_type_name' => 'ExtMatrixSiteSettings' ] );
	acf_add_local_field_group( [ 'key' => 'group_extmx_site', 'title' => '[ext-matrix] Site Fields', 'show_in_graphql' => 1, 'graphql_field_name' => 'extMatrixSiteFields', 'graphql_types' => [ 'ExtMatrixSiteSettings' ],
		'fields' => [ [ 'key' => 'field_extmx_site_body', 'label' => 'Body', 'name' => 'extmx_site_body', 'type' => 'wysiwyg', 'show_in_graphql' => 1, 'graphql_field_name' => 'body' ] ],
		'location' => [ [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'ext-matrix-site-settings' ] ] ] ] );
} );

// ACF canary field groups (local, nothing stored in the DB).
add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	$wysiwyg = function ( $key, $name ) {
		return [ 'key' => $key, 'label' => 'Body', 'name' => $name, 'type' => 'wysiwyg', 'show_in_graphql' => 1, 'graphql_field_name' => 'body' ];
	};
	acf_add_local_field_group( [ 'key' => 'group_extmx_story', 'title' => '[ext-matrix] Story Fields', 'show_in_graphql' => 1, 'graphql_field_name' => 'extMatrixStory', 'graphql_types' => [ 'Post' ],
		'fields' => [
			$wysiwyg( 'field_extmx_story_body', 'extmx_story_body' ),
			[ 'key' => 'field_extmx_story_rows', 'label' => 'Rows', 'name' => 'extmx_story_rows', 'type' => 'repeater', 'show_in_graphql' => 1, 'graphql_field_name' => 'rows',
				'sub_fields' => [ [ 'key' => 'field_extmx_story_row_body', 'label' => 'Row body', 'name' => 'row_body', 'type' => 'wysiwyg', 'show_in_graphql' => 1, 'graphql_field_name' => 'rowBody' ] ] ],
		],
		'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ] ] ] ] );
	acf_add_local_field_group( [ 'key' => 'group_extmx_term', 'title' => '[ext-matrix] Term Fields', 'show_in_graphql' => 1, 'graphql_field_name' => 'extMatrixTerm', 'graphql_types' => [ 'Category' ],
		'fields' => [ $wysiwyg( 'field_extmx_term_body', 'extmx_term_body' ) ],
		'location' => [ [ [ 'param' => 'taxonomy', 'operator' => '==', 'value' => 'category' ] ] ] ] );
	acf_add_local_field_group( [ 'key' => 'group_extmx_user', 'title' => '[ext-matrix] User Fields', 'show_in_graphql' => 1, 'graphql_field_name' => 'extMatrixUser', 'graphql_types' => [ 'User' ],
		'fields' => [ $wysiwyg( 'field_extmx_user_body', 'extmx_user_body' ) ],
		'location' => [ [ [ 'param' => 'user_form', 'operator' => '==', 'value' => 'all' ] ] ] ] );
} );

// The same snapshot as a field on node types, to see the state inside a node's part of the query.
add_action( 'graphql_register_types', function () {
	foreach ( [ 'Post', 'Page', 'Category', 'User', 'MediaItem' ] as $type ) {
		register_graphql_field( $type, 'debugNodeGlobals', [ 'type' => 'String', 'resolve' => function () { return zz_ext_matrix_snapshot(); } ] );
	}
}, 99 );

// A root field that resolves late (after N chained Deferreds) to an object, whose "state" subfield
// takes the snapshot. Models a root field whose own fields read globals (like an options page's fields).
function zz_ext_matrix_late_obj( int $hops ) {
	return new \GraphQL\Deferred( static function () use ( $hops ) {
		return $hops > 0 ? zz_ext_matrix_late_obj( $hops - 1 ) : [ 'ok' => true ];
	} );
}
add_action( 'graphql_register_types', function () {
	register_graphql_object_type( 'ExtMatrixDebugState', [ 'fields' => [ 'state' => [ 'type' => 'String', 'resolve' => function () { return zz_ext_matrix_snapshot(); } ] ] ] );
	register_graphql_field( 'RootQuery', 'debugLateObject', [
		'type'    => 'ExtMatrixDebugState',
		'args'    => [ 'hops' => [ 'type' => 'Int' ] ],
		'resolve' => function ( $root, $args ) { return zz_ext_matrix_late_obj( $args['hops'] ?? 25 ); },
	] );
} );

// Yoast group fixtures: two CPTs with archives and a hierarchical custom taxonomy; let Yoast keep
// indexables on this non-production site.
add_action( 'init', function () {
	foreach ( [ 'book' => [ 'Books', 'Book' ], 'movie' => [ 'Movies', 'Movie' ] ] as $pt => $l ) {
		register_post_type( $pt, [ 'public' => true, 'has_archive' => true, 'labels' => [ 'name' => $l[0], 'singular_name' => $l[1] ],
			'supports' => [ 'title', 'editor', 'thumbnail', 'author' ], 'show_in_graphql' => true,
			'graphql_single_name' => $pt, 'graphql_plural_name' => $pt . 's' ] );
	}
	register_taxonomy( 'genre', [ 'post', 'book' ], [ 'public' => true, 'hierarchical' => true, 'label' => 'Genres',
		'show_in_graphql' => true, 'graphql_single_name' => 'genre', 'graphql_plural_name' => 'genres' ] );
}, 5 );
add_filter( 'Yoast\WP\SEO\should_index_indexables', '__return_true' );

// WooCommerce group probe (from the WooGraphQL matrix file).
add_action( 'graphql_register_types', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	$probe = function () {
		global $post;
		$p = ( isset( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof WC_Product ) ? $GLOBALS['product']->get_id() : null;
		return wp_json_encode( [
			'post'      => $post instanceof WP_Post ? $post->ID : null,
			'product'   => $p,
			'queried'   => get_queried_object_id(),
			'isProduct' => function_exists( 'is_product' ) ? is_product() : null,
		] );
	};
	foreach ( [ 'RootQuery', 'Product', 'ProductVariation' ] as $type ) {
		register_graphql_field( $type, 'probeGlobals', [ 'type' => 'String', 'resolve' => $probe ] );
	}
	register_graphql_field( 'RootQuery', 'probeGlobalsLate', [ 'type' => 'ExtMatrixDebugState', 'resolve' => function () { return zz_ext_matrix_late_obj( 25 ); } ] );
}, 99 );
