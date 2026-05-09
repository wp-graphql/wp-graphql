<?php
/**
 * IDE-specific REST routes (publish, cascade-delete, import/export,
 * reorder) under `wpgraphql-ide/v1`.
 *
 * @package WPGraphQLIDE
 */

declare(strict_types = 1);

namespace WPGraphQLIDE;

/**
 * Registers the IDE's REST routes and provides their handlers. Every
 * route gates on `manage_graphql_ide`; per-document author checks live
 * inside the handlers themselves.
 */
class Rest {

	/**
	 * Register custom REST routes for the IDE.
	 */
	public static function register(): void {
		register_rest_route(
			'wpgraphql-ide/v1',
			'/documents/(?P<id>\d+)/publish',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'publish_document' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_graphql_ide' );
				},
				'args'                => [
					'id' => [
						'required'          => true,
						'validate_callback' => static function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		register_rest_route(
			'wpgraphql-ide/v1',
			'/collections/(?P<id>\d+)/cascade',
			[
				'methods'             => 'DELETE',
				'callback'            => [ self::class, 'delete_collection_cascade' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_graphql_ide' );
				},
				'args'                => [
					'id' => [
						'required'          => true,
						'validate_callback' => static function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		register_rest_route(
			'wpgraphql-ide/v1',
			'/documents/export',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'export_documents' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_graphql_ide' );
				},
			]
		);

		register_rest_route(
			'wpgraphql-ide/v1',
			'/documents/import',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'import_documents' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_graphql_ide' );
				},
			]
		);

		register_rest_route(
			'wpgraphql-ide/v1',
			'/documents/reorder',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'reorder_documents' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_graphql_ide' );
				},
			]
		);

		register_rest_route(
			'wpgraphql-ide/v1',
			'/collections/reorder',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'reorder_collections' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_graphql_ide' );
				},
			]
		);
	}

	/**
	 * Export the current user's documents grouped by collection. Returns
	 * the same JSON shape used by `seeds/example-documents.json` and
	 * accepted by the importer.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function export_documents( \WP_REST_Request $request ) {
		unset( $request );
		return rest_ensure_response( ImportExport::export( get_current_user_id() ) );
	}

	/**
	 * Import a documents JSON payload into the current user's library.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function import_documents( \WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) || empty( $body['collections'] ) ) {
			return new \WP_Error(
				'invalid_payload',
				__( 'Import payload must be an object with a non-empty "collections" array.', 'wpgraphql-ide' ),
				[ 'status' => 400 ]
			);
		}

		$result = ImportExport::import( $body, get_current_user_id() );
		return rest_ensure_response( $result );
	}

	/**
	 * Persist a reorder of documents — sets `menu_order` for each post in
	 * the order provided. The post type's `page-attributes` support
	 * surfaces `menu_order` to WP REST and to the default `WP_Query` sort.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function reorder_documents( \WP_REST_Request $request ) {
		$body  = $request->get_json_params();
		$order = isset( $body['order'] ) && is_array( $body['order'] ) ? $body['order'] : null;
		if ( ! $order ) {
			return new \WP_Error(
				'invalid_payload',
				__( 'Reorder payload must include an "order" array of post IDs.', 'wpgraphql-ide' ),
				[ 'status' => 400 ]
			);
		}
		$author_id = get_current_user_id();
		foreach ( $order as $position => $post_id ) {
			$post_id = (int) $post_id;
			$post    = get_post( $post_id );
			// Only touch posts the user owns and that match our CPT.
			if ( ! $post || 'graphql_ide_query' !== $post->post_type || (int) $post->post_author !== $author_id ) {
				continue;
			}
			wp_update_post(
				[
					'ID'         => $post_id,
					'menu_order' => (int) $position,
				]
			);
		}
		return rest_ensure_response( [ 'ok' => true ] );
	}

	/**
	 * Persist a reorder of collections per-user via term meta. Collection
	 * order is user-scoped because terms themselves are global; per-user
	 * ordering keeps the IDE feeling personal without leaking another
	 * user's preferred order onto everyone.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function reorder_collections( \WP_REST_Request $request ) {
		$body  = $request->get_json_params();
		$order = isset( $body['order'] ) && is_array( $body['order'] ) ? $body['order'] : null;
		if ( ! $order ) {
			return new \WP_Error(
				'invalid_payload',
				__( 'Reorder payload must include an "order" array of term IDs.', 'wpgraphql-ide' ),
				[ 'status' => 400 ]
			);
		}
		$ids = array_values( array_filter( array_map( 'intval', $order ) ) );
		update_user_meta( get_current_user_id(), 'wpgraphql_ide_collection_order', $ids );
		return rest_ensure_response( [ 'ok' => true ] );
	}

	/**
	 * Delete a collection along with all documents in it owned by the
	 * current user. Documents owned by other users are left intact —
	 * removing a shared term's assignment is enough to detach them.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function delete_collection_cascade( \WP_REST_Request $request ) {
		$term_id = (int) $request->get_param( 'id' );
		$term    = get_term( $term_id, 'graphql_ide_collection' );

		if ( ! $term || is_wp_error( $term ) ) {
			return new \WP_Error(
				'not_found',
				__( 'Collection not found.', 'wpgraphql-ide' ),
				[ 'status' => 404 ]
			);
		}

		$user_id  = get_current_user_id();
		$post_ids = get_posts(
			[
				'post_type'      => 'graphql_ide_query',
				'post_status'    => [ 'draft', 'publish' ],
				'author'         => $user_id,
				'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					[
						'taxonomy' => 'graphql_ide_collection',
						'field'    => 'term_id',
						'terms'    => $term_id,
					],
				],
				'fields'         => 'ids',
				'posts_per_page' => -1,
			]
		);

		$deleted = [];
		foreach ( $post_ids as $post_id ) {
			if ( wp_delete_post( (int) $post_id, true ) ) {
				$deleted[] = (int) $post_id;
			}
		}

		$result = wp_delete_term( $term_id, 'graphql_ide_collection' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			[
				'collection_id'    => $term_id,
				'deleted_post_ids' => $deleted,
			]
		);
	}

	/**
	 * Publish a draft document.
	 *
	 * Computes the SHA-256 hash of the AST-normalized query (matching
	 * Smart Cache's algorithm), sets it as the post slug, and changes
	 * the status to publish. If a published document with the same hash
	 * already exists, returns the existing document instead.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function publish_document( \WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || 'graphql_ide_query' !== $post->post_type ) {
			return new \WP_Error(
				'not_found',
				__( 'Document not found.', 'wpgraphql-ide' ),
				[ 'status' => 404 ]
			);
		}

		// Ensure the current user owns this document.
		if ( get_current_user_id() !== (int) $post->post_author ) {
			return new \WP_Error(
				'forbidden',
				__( 'You do not have permission to publish this document.', 'wpgraphql-ide' ),
				[ 'status' => 403 ]
			);
		}

		$query_string = $post->post_content;

		if ( empty( trim( $query_string ) ) ) {
			return new \WP_Error(
				'empty_query',
				__( 'Cannot publish an empty document.', 'wpgraphql-ide' ),
				[ 'status' => 400 ]
			);
		}

		try {
			// Parse and normalize the query using graphql-php (same as Smart Cache).
			$ast        = \GraphQL\Language\Parser::parse( $query_string );
			$normalized = \GraphQL\Language\Printer::doPrint( $ast );
			$hash       = hash( 'sha256', $normalized );
		} catch ( \GraphQL\Error\SyntaxError $e ) {
			return new \WP_Error(
				'invalid_query',
				sprintf(
					// translators: %s is the syntax error message.
					__( 'Invalid GraphQL query: %s', 'wpgraphql-ide' ),
					$e->getMessage()
				),
				[ 'status' => 400 ]
			);
		}

		// Check if a published document with this hash already exists.
		$existing = get_posts(
			[
				'post_type'   => 'graphql_ide_query',
				'post_status' => 'publish',
				'name'        => $hash,
				'numberposts' => 1,
			]
		);

		if ( ! empty( $existing ) ) {
			// Document already published — return the existing one.
			$existing_post = $existing[0];
			return rest_ensure_response(
				[
					'id'             => $existing_post->ID,
					'status'         => $existing_post->post_status,
					'query_hash'     => $hash,
					'already_exists' => true,
					'message'        => __( 'This query is already published.', 'wpgraphql-ide' ),
				]
			);
		}

		// Publish: normalize content, set slug to hash, change status.
		$result = wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => $normalized,
				'post_name'    => $hash,
				'post_status'  => 'publish',
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			[
				'id'         => $post_id,
				'status'     => 'publish',
				'query_hash' => $hash,
			]
		);
	}
}
