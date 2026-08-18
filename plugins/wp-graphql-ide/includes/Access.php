<?php
/**
 * Access-control filters for IDE post types.
 *
 * @package WPGraphQLIDE
 */

declare(strict_types = 1);

namespace WPGraphQLIDE;

/**
 * Per-user scoping and visibility filters that gate every read path
 * touching Smart Cache's `graphql_document` — REST list queries,
 * REST single fetches, GraphQL connections, GraphQL node-by-id
 * resolution, plus the title-length cap on writes.
 */
class Access {

	/**
	 * Scope REST API queries for IDE documents to the current user.
	 *
	 * @param array<string, mixed> $args WP_Query arguments.
	 * @return array<string, mixed> Modified arguments.
	 */
	public static function scope_rest_queries( $args ) {
		$args['author'] = get_current_user_id();
		return $args;
	}

	/**
	 * Mark Smart Cache `graphql_document` posts as private when the
	 * current user isn't the author. This is the GraphQL-side enforcement
	 * point for per-user document isolation: it covers connections
	 * (private models are dropped from the results), `node(id: "...")`
	 * lookups, and any nested field that resolves a document model.
	 * Without this filter, a user holding `manage_graphql_ide` could read
	 * another user's saved Smart Cache document just by guessing or
	 * sharing its global ID.
	 *
	 * Historical note: 5.0.1 through 5.4.x also author-scoped connection
	 * query args via `graphql_connection_query_args`. That filter matched
	 * any connection whose `post_type` list contained `graphql_document`,
	 * which — with Smart Cache active — includes every `post_type: 'any'`
	 * connection (core `contentNodes`, ACF relationship fields), so
	 * authenticated queries silently lost other authors' posts
	 * (https://github.com/wp-graphql/wp-graphql/issues/4117). The IDE's
	 * document list now scopes itself with an `author` where arg
	 * client-side instead (see `src/api/documents.js`).
	 *
	 * Visibility is decided in the WPGraphQL Model layer: returning true
	 * here marks the data private so the model's restricted fields (id,
	 * databaseId, isRestricted, etc.) are still readable, but the rest
	 * resolves to null. This matches WPGraphQL's existing pattern for
	 * draft posts and is the same path used by core's `is_post_private()`.
	 *
	 * @since 5.0.1
	 *
	 * @param bool        $is_private   Whether the model is private.
	 * @param string      $model_name   Name of the WPGraphQL model.
	 * @param mixed       $data         The un-modeled data (a `WP_Post` for `PostObject`).
	 * @param string|null $visibility   Visibility set so far.
	 * @param int|null    $owner        Owner user ID, if any.
	 * @param \WP_User    $current_user Current user for the session.
	 */
	public static function restrict_post_visibility( $is_private, $model_name, $data, $visibility, $owner, $current_user ): bool {
		unset( $visibility ); // Unused — we make a final decision based on the data + current user.

		if ( 'PostObject' !== $model_name ) {
			return (bool) $is_private;
		}

		if ( ! $data instanceof \WP_Post ) {
			return (bool) $is_private;
		}

		// `graphql_document` is Smart Cache's saved-document post type and
		// only exists when Smart Cache is active.
		if ( 'graphql_document' !== $data->post_type ) {
			return (bool) $is_private;
		}

		$current_user_id = $current_user instanceof \WP_User ? (int) $current_user->ID : 0;
		$post_author_id  = (int) $data->post_author;

		// Owner is the source of truth when the model layer set it (matches
		// the User-owner pattern used elsewhere in WPGraphQL).
		if ( null !== $owner ) {
			$post_author_id = (int) $owner;
		}

		if ( $current_user_id > 0 && $current_user_id === $post_author_id ) {
			return (bool) $is_private;
		}

		return true;
	}

	/**
	 * Enforce manage_graphql_ide capability on IDE REST routes.
	 *
	 * Prevents users without `manage_graphql_ide` from hitting the
	 * graphql-document (Smart Cache) routes, even if they have
	 * edit_posts from the CPT's capability_type.
	 *
	 * @param mixed            $result  Response to replace the requested version with.
	 * @param \WP_REST_Server  $server  Server instance.
	 * @param \WP_REST_Request $request Request used to generate the response.
	 * @return mixed|\WP_Error
	 */
	public static function enforce_rest_permissions( $result, $server, $request ) {
		unset( $server );

		$route = $request->get_route();

		// Smart Cache registers `graphql_document` without an explicit
		// rest_base, so WP REST uses the post type slug verbatim
		// (underscores preserved). We also gate the four document
		// taxonomies the SmartCacheBridge exposes to REST so that
		// listing terms / reading term metadata is cap-gated through the
		// same filter.
		$is_ide_route = strpos( $route, '/wp/v2/graphql_document' ) === 0
			|| strpos( $route, '/wp/v2/graphql_document_group' ) === 0
			|| strpos( $route, '/wp/v2/graphql_query_alias' ) === 0
			|| strpos( $route, '/wp/v2/graphql_document_grant' ) === 0
			|| strpos( $route, '/wp/v2/graphql_document_http_maxage' ) === 0;

		if ( ! $is_ide_route ) {
			return $result;
		}

		if ( ! wpgraphql_ide_user_can() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access IDE queries.', 'wpgraphql-ide' ),
				[ 'status' => 403 ]
			);
		}

		return $result;
	}

	/**
	 * Restrict single document responses to the document's author or to
	 * users with a personal-collection share grant.
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WP_Post          $post     The post object.
	 * @param \WP_REST_Request  $request  The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function restrict_document_response( $response, $post, $request ) {
		unset( $request );

		if ( wpgraphql_ide_user_owns_document( $post ) ) {
			return $response;
		}

		// Shared-collection grant: a doc is also readable if it appears in a
		// personal collection the current user has been added to via
		// `shared_with`. The grant is read-only — single-doc fetches only;
		// the list route still scopes to the viewer's own docs.
		if (
			'graphql_document' === $post->post_type
			&& wpgraphql_ide_user_can()
			&& self::is_shared_with_current_user( (int) $post->ID )
		) {
			return $response;
		}

		return new \WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to access this document.', 'wpgraphql-ide' ),
			[ 'status' => 403 ]
		);
	}

	/**
	 * Truncate `post_title` to 200 chars on `graphql_document` writes.
	 *
	 * The `post_title` column is TEXT in MySQL with no hard cap, so a
	 * long POST body could bloat the database or break admin UIs (post
	 * lists, the IDE's own tab strip) that can't reasonably display past
	 * ~200 chars. Applies to every write path — REST creates/updates,
	 * the import/upsert flow, and any direct `wp_insert_post` callers.
	 *
	 * Other CPTs pass through unchanged. `mb_substr` is used so we never
	 * slice through a multi-byte character.
	 *
	 * @param array<string,mixed> $data    Slashed, sanitized post data about to be written.
	 * @param array<string,mixed> $postarr Original input array (unsanitized).
	 *
	 * @return array<string,mixed>
	 */
	public static function cap_document_title_length( array $data, array $postarr ): array {
		unset( $postarr );

		if ( ( $data['post_type'] ?? '' ) !== 'graphql_document' ) {
			return $data;
		}
		$title = (string) ( $data['post_title'] ?? '' );
		if ( mb_strlen( $title ) > 200 ) {
			$data['post_title'] = mb_substr( $title, 0, 200 );
		}
		return $data;
	}

	/**
	 * Whether `$doc_id` appears in any personal collection that has been
	 * shared with the current user. Walks IDE-capable users' meta — small
	 * scale only; cache if this turns into a hot path.
	 *
	 * @param int $doc_id Document ID.
	 */
	public static function is_shared_with_current_user( int $doc_id ): bool {
		if ( $doc_id <= 0 ) {
			return false;
		}
		foreach ( UserMeta::aggregate_shared_collections() as $collection ) {
			if ( ! isset( $collection['documents'] ) || ! is_array( $collection['documents'] ) ) {
				continue;
			}
			foreach ( $collection['documents'] as $doc ) {
				if ( isset( $doc['id'] ) && (int) $doc['id'] === $doc_id ) {
					return true;
				}
			}
		}
		return false;
	}
}
