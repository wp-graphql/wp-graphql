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
		// `POST /documents/{id}/publish` and `DELETE /collections/{id}/cascade`
		// were removed in 5.0. Smart Cache's Document::save() already
		// validates + normalizes + hashes on every save, so a separate
		// publish step is redundant. Cascade-delete operated on the IDE's
		// own graphql_ide_collection taxonomy, which doesn't exist
		// anymore — collection ops now go through Smart Cache's
		// graphql_document_group taxonomy via standard WP REST endpoints.

		register_rest_route(
			'wpgraphql-ide/v1',
			'/documents/export',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'export_documents' ],
				'permission_callback' => 'wpgraphql_ide_user_can',
			]
		);

		register_rest_route(
			'wpgraphql-ide/v1',
			'/documents/import',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'import_documents' ],
				'permission_callback' => 'wpgraphql_ide_user_can',
			]
		);

		register_rest_route(
			'wpgraphql-ide/v1',
			'/documents/reorder',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'reorder_documents' ],
				'permission_callback' => 'wpgraphql_ide_user_can',
			]
		);

		register_rest_route(
			'wpgraphql-ide/v1',
			'/collections/reorder',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'reorder_collections' ],
				'permission_callback' => 'wpgraphql_ide_user_can',
			]
		);

		// Per-user admin color scheme write. Mirrors `user-edit.php`:
		// writes the slug to `admin_color` user meta, which `get_user_option`
		// reads on every admin pageload to enqueue the right colors.css.
		// Used by the Color Theme section of the IDE Settings tab.
		register_rest_route(
			'wpgraphql-ide/v1',
			'/admin-color',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ self::class, 'update_admin_color' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'scheme' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					],
				],
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
		foreach ( $order as $position => $post_id ) {
			$post_id = (int) $post_id;
			$post    = get_post( $post_id );
			// Only touch posts the user owns and that match our CPT.
			if ( ! $post || 'graphql_document' !== $post->post_type || ! wpgraphql_ide_user_owns_document( $post ) ) {
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
	 * Persist the current user's admin color scheme. The available schemes
	 * are registered by core via `register_admin_color_schemes()`, which
	 * hooks `admin_init` and therefore doesn't fire on REST requests; we
	 * force-load `wp-admin/includes/misc.php` so the validation table is
	 * populated. Mirrors what the user-profile picker writes.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function update_admin_color( \WP_REST_Request $request ) {
		$scheme = (string) $request->get_param( 'scheme' );

		if ( '' === $scheme ) {
			return new \WP_Error(
				'invalid_payload',
				__( 'A color scheme slug is required.', 'wpgraphql-ide' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! function_exists( 'register_admin_color_schemes' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		if ( function_exists( 'register_admin_color_schemes' ) ) {
			register_admin_color_schemes();
		}

		global $_wp_admin_css_colors;
		if ( is_array( $_wp_admin_css_colors ) && ! isset( $_wp_admin_css_colors[ $scheme ] ) ) {
			return new \WP_Error(
				'unknown_scheme',
				__( 'Unknown color scheme.', 'wpgraphql-ide' ),
				[ 'status' => 400 ]
			);
		}

		update_user_meta( get_current_user_id(), 'admin_color', $scheme );
		return rest_ensure_response( [ 'scheme' => $scheme ] );
	}

}
