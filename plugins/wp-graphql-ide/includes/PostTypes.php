<?php
/**
 * IDE custom post type and meta — execution history.
 *
 * Saved-query documents live in WPGraphQL Smart Cache's `graphql_document`
 * post type when Smart Cache is active. This file only registers the
 * IDE-specific execution-history post type — Smart Cache has no equivalent.
 *
 * @package WPGraphQLIDE
 */

declare(strict_types = 1);

namespace WPGraphQLIDE;

/**
 * Registers the IDE's execution-history CPT and its post meta.
 *
 * The IDE used to also register `graphql_ide_query` (saved queries) and
 * `graphql_ide_collection` (collections taxonomy). Those have been removed
 * in 5.0 — they duplicated Smart Cache's `graphql_document` and
 * `graphql_document_group`. See `CHANGELOG.md` Unreleased.
 */
class PostTypes {

	/**
	 * Register the IDE history CPT and its post meta.
	 *
	 * History entries are global execution-log records — one row per query
	 * the user runs. The `_graphql_ide_document_id` meta optionally points
	 * at a Smart Cache `graphql_document` post when the run was triggered
	 * from a saved query; for ad-hoc executions the field is 0.
	 */
	public static function register(): void {
		$post_meta_auth = static function () {
			return wpgraphql_ide_user_can();
		};

		$sanitize_json = static function ( $value ) {
			if ( empty( $value ) ) {
				return '';
			}
			// Validate it's valid JSON if non-empty.
			json_decode( $value );
			return json_last_error() === JSON_ERROR_NONE ? $value : '';
		};

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
	}
}
