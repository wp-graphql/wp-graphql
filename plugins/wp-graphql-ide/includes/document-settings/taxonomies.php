<?php
/**
 * Register taxonomies that back Document Settings fields.
 *
 * Each taxonomy is non-public and attached to graphql_ide_query only.
 * - graphql_ide_query_alias: multi-value, term names are alias strings (cross-document unique).
 * - graphql_ide_query_maxage: single-value, term name is a non-negative integer string.
 * - graphql_ide_query_grant: single-value, term name is one of allow|deny|"".
 *
 * @package WPGraphQLIDE\DocumentSettings
 */

namespace WPGraphQLIDE\DocumentSettings;

add_action( 'init', __NAMESPACE__ . '\\register_taxonomies', 10 );

function register_taxonomies(): void {
	$capabilities = array_fill_keys(
		[ 'manage_terms', 'edit_terms', 'delete_terms', 'assign_terms' ],
		wpgraphql_ide_get_capability()
	);

	register_taxonomy(
		'graphql_ide_query_alias',
		'graphql_ide_query',
		[
			'labels'            => [
				'name'          => __( 'Alias Names', 'wpgraphql-ide' ),
				'singular_name' => __( 'Alias', 'wpgraphql-ide' ),
			],
			'public'            => false,
			'show_in_rest'      => false,
			'show_in_graphql'   => false,
			'hierarchical'      => false,
			'show_ui'           => false,
			'show_admin_column' => false,
			'capabilities'      => $capabilities,
		]
	);

	register_taxonomy(
		'graphql_ide_query_maxage',
		'graphql_ide_query',
		[
			'labels'            => [
				'name'          => __( 'Max-Age', 'wpgraphql-ide' ),
				'singular_name' => __( 'Max-Age', 'wpgraphql-ide' ),
			],
			'public'            => false,
			'show_in_rest'      => false,
			'show_in_graphql'   => false,
			'hierarchical'      => false,
			'show_ui'           => false,
			'show_admin_column' => false,
			'capabilities'      => $capabilities,
		]
	);

	register_taxonomy(
		'graphql_ide_query_grant',
		'graphql_ide_query',
		[
			'labels'            => [
				'name'          => __( 'Allow / Deny', 'wpgraphql-ide' ),
				'singular_name' => __( 'Allow / Deny', 'wpgraphql-ide' ),
			],
			'public'            => false,
			'show_in_rest'      => false,
			'show_in_graphql'   => false,
			'hierarchical'      => false,
			'show_ui'           => false,
			'show_admin_column' => false,
			'capabilities'      => $capabilities,
		]
	);
}
