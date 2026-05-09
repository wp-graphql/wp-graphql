<?php
/**
 * IDE custom post types, post meta, and collections taxonomy.
 *
 * @package WPGraphQLIDE
 */

declare(strict_types = 1);

namespace WPGraphQLIDE;

/**
 * Registers the IDE's custom post types, post meta, and the
 * collections taxonomy.
 */
class PostTypes {

	/**
	 * Register the IDE query and history CPTs, their post meta, and the
	 * collections taxonomy.
	 *
	 * Each query document stores a GraphQL query, its variables, and
	 * headers. History entries are global execution log records, not
	 * scoped to a document. Both expose to GraphQL behind per-user
	 * scoping filters wired from the entry file.
	 */
	public static function register(): void {
		register_post_type(
			'graphql_ide_query',
			[
				'label'               => __( 'IDE Queries', 'wpgraphql-ide' ),
				'description'         => __( 'Saved GraphQL IDE query documents.', 'wpgraphql-ide' ),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => true,
				'rest_base'           => 'graphql-ide-queries',
				// Expose to WPGraphQL so the IDE (and integrations that
				// follow) can read saved queries through the GraphQL
				// surface instead of REST. A scoping filter on
				// `graphql_connection_query_args` enforces per-user
				// ownership so this isn't a leak of other users' data.
				'show_in_graphql'     => true,
				'graphql_single_name' => 'IdeQuery',
				'graphql_plural_name' => 'IdeQueries',
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => [ 'title', 'editor', 'excerpt', 'author', 'custom-fields', 'page-attributes' ],
			]
		);

		$post_meta_auth = static function () {
			return current_user_can( 'manage_graphql_ide' );
		};

		$sanitize_json = static function ( $value ) {
			if ( empty( $value ) ) {
				return '';
			}
			// Validate it's valid JSON if non-empty.
			json_decode( $value );
			return json_last_error() === JSON_ERROR_NONE ? $value : '';
		};

		register_post_meta(
			'graphql_ide_query',
			'_graphql_ide_variables',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '',
				'auth_callback'     => $post_meta_auth,
				'sanitize_callback' => $sanitize_json,
			]
		);

		register_post_meta(
			'graphql_ide_query',
			'_graphql_ide_headers',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '',
				'auth_callback'     => $post_meta_auth,
				'sanitize_callback' => $sanitize_json,
			]
		);

		// History CPT — global execution history, not scoped to a document.
		register_post_type(
			'graphql_ide_history',
			[
				'label'               => __( 'IDE History', 'wpgraphql-ide' ),
				'description'         => __( 'GraphQL IDE execution history entries.', 'wpgraphql-ide' ),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => true,
				'rest_base'           => 'graphql-ide-history',
				'show_in_graphql'     => true,
				'graphql_single_name' => 'IdeHistoryEntry',
				'graphql_plural_name' => 'IdeHistoryEntries',
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => [ 'author', 'custom-fields' ],
			]
		);

		register_post_meta(
			'graphql_ide_history',
			'_graphql_ide_query',
			[
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'default'       => '',
				'auth_callback' => $post_meta_auth,
			]
		);

		register_post_meta(
			'graphql_ide_history',
			'_graphql_ide_variables',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '',
				'auth_callback'     => $post_meta_auth,
				'sanitize_callback' => $sanitize_json,
			]
		);

		register_post_meta(
			'graphql_ide_history',
			'_graphql_ide_headers',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '',
				'auth_callback'     => $post_meta_auth,
				'sanitize_callback' => $sanitize_json,
			]
		);

		register_post_meta(
			'graphql_ide_history',
			'_graphql_ide_duration_ms',
			[
				'type'          => 'integer',
				'single'        => true,
				'show_in_rest'  => true,
				'default'       => 0,
				'auth_callback' => $post_meta_auth,
			]
		);

		register_post_meta(
			'graphql_ide_history',
			'_graphql_ide_status',
			[
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'default'       => '',
				'auth_callback' => $post_meta_auth,
			]
		);

		register_post_meta(
			'graphql_ide_history',
			'_graphql_ide_document_id',
			[
				'type'          => 'integer',
				'single'        => true,
				'show_in_rest'  => true,
				'default'       => 0,
				'auth_callback' => $post_meta_auth,
			]
		);

		register_post_meta(
			'graphql_ide_history',
			'_graphql_ide_is_authenticated',
			[
				'type'          => 'boolean',
				'single'        => true,
				'show_in_rest'  => true,
				'default'       => true,
				'auth_callback' => $post_meta_auth,
			]
		);

		register_post_meta(
			'graphql_ide_history',
			'_graphql_ide_http_method',
			[
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'default'       => 'POST',
				'auth_callback' => $post_meta_auth,
			]
		);

		// Collections taxonomy for grouping saved queries.
		register_taxonomy(
			'graphql_ide_collection',
			'graphql_ide_query',
			[
				'labels'              => [
					'name'          => __( 'Collections', 'wpgraphql-ide' ),
					'singular_name' => __( 'Collection', 'wpgraphql-ide' ),
				],
				'public'              => false,
				'show_in_rest'        => true,
				'rest_base'           => 'graphql-ide-collections',
				'show_in_graphql'     => true,
				'graphql_single_name' => 'IdeCollection',
				'graphql_plural_name' => 'IdeCollections',
				'hierarchical'        => true,
				'show_ui'             => false,
				'show_admin_column'   => false,
				'capabilities'        => [
					'manage_terms' => 'manage_graphql_ide',
					'edit_terms'   => 'manage_graphql_ide',
					'delete_terms' => 'manage_graphql_ide',
					'assign_terms' => 'manage_graphql_ide',
				],
			]
		);
	}
}
