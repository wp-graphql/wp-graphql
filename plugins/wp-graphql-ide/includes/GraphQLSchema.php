<?php
/**
 * IDE GraphQL schema field registrations.
 *
 * @package WPGraphQLIDE
 */

declare(strict_types = 1);

namespace WPGraphQLIDE;

/**
 * Exposes IDE-specific post-meta fields on the WPGraphQL schema. Field
 * names strip the internal `_graphql_ide_` prefix and switch to camelCase
 * to match the rest of the WPGraphQL schema.
 *
 * As of 5.0, the IDE no longer registers fields on its own document type
 * (the `graphql_ide_query` post type has been removed in favor of Smart
 * Cache's `graphql_document`). Only `IdeHistoryEntry` fields are
 * registered here; saved-document fields are owned by Smart Cache.
 */
class GraphQLSchema {

	/**
	 * Register IDE-specific GraphQL fields backed by post meta.
	 *
	 * Post meta isn't auto-exposed by `register_post_meta` — WPGraphQL
	 * needs explicit `register_graphql_field` calls. The field names
	 * strip the internal `_graphql_ide_` prefix and switch to camelCase
	 * to match the rest of the WPGraphQL schema. Resolvers read from the
	 * underlying post meta; the meta keys themselves are unchanged so
	 * existing REST and direct DB access keep working.
	 *
	 * @since x-release-please-version
	 */
	public static function register(): void {
		if ( ! function_exists( 'register_graphql_field' ) ) {
			return;
		}

		$history_meta_fields = [
			'queryString'     => [
				'meta'        => '_graphql_ide_query',
				'type'        => 'String',
				'description' => __( 'The GraphQL document executed for this history entry.', 'wpgraphql-ide' ),
			],
			'variables'       => [
				'meta'        => '_graphql_ide_variables',
				'type'        => 'String',
				'description' => __( 'JSON-encoded variables sent with the request.', 'wpgraphql-ide' ),
			],
			'headers'         => [
				'meta'        => '_graphql_ide_headers',
				'type'        => 'String',
				'description' => __( 'JSON-encoded HTTP headers sent with the request.', 'wpgraphql-ide' ),
			],
			'durationMs'      => [
				'meta'        => '_graphql_ide_duration_ms',
				'type'        => 'Int',
				'description' => __( 'How long the request took, in milliseconds.', 'wpgraphql-ide' ),
			],
			'executionStatus' => [
				'meta'        => '_graphql_ide_status',
				'type'        => 'String',
				'description' => __( 'Result status of the executed request (e.g. success, error). Distinct from post_status (which is on the inherited Post.status field).', 'wpgraphql-ide' ),
			],
			'documentId'      => [
				'meta'        => '_graphql_ide_document_id',
				'type'        => 'Int',
				'description' => __( 'Database ID of the saved GraphqlDocument (Smart Cache post type) this entry was executed against, if any. 0 for ad-hoc executions.', 'wpgraphql-ide' ),
			],
			'isAuthenticated' => [
				'meta'        => '_graphql_ide_is_authenticated',
				'type'        => 'Boolean',
				'description' => __( 'Whether the request was sent with an authenticated session.', 'wpgraphql-ide' ),
			],
			'httpMethod'      => [
				'meta'        => '_graphql_ide_http_method',
				'type'        => 'String',
				'description' => __( 'HTTP method used for the request.', 'wpgraphql-ide' ),
			],
		];

		foreach ( $history_meta_fields as $field_name => $config ) {
			$meta_key = $config['meta'];
			$type     = $config['type'];

			register_graphql_field(
				'IdeHistoryEntry',
				$field_name,
				[
					'type'        => $type,
					'description' => $config['description'],
					'resolve'     => static function ( $post ) use ( $meta_key, $type ) {
						$value = get_post_meta( $post->databaseId, $meta_key, true );

						if ( 'Int' === $type ) {
							return (int) $value;
						}
						if ( 'Boolean' === $type ) {
							// Stored as `1`/empty by post meta. Cast through
							// (int) first to keep `'0'` falsy.
							return (bool) (int) $value;
						}
						return (string) $value;
					},
				]
			);
		}
	}
}
