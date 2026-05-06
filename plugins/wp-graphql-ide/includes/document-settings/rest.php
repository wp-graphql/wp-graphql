<?php
/**
 * REST exposure for Document Settings fields.
 *
 * Adds a single `documentSettings` field to the `graphql_ide_query` REST
 * resource. Reads return a key/value map of all registered fields. Updates
 * accept the same shape and dispatch each value through the storage adapter.
 *
 * Errors from the storage layer (e.g. alias uniqueness conflicts) propagate
 * back as WP_Error so REST returns a structured 4xx response.
 *
 * @package WPGraphQLIDE\DocumentSettings
 */

namespace WPGraphQLIDE\DocumentSettings;

add_action( 'rest_api_init', __NAMESPACE__ . '\\register_rest_field', 20 );

function register_rest_field(): void {
	\register_rest_field(
		'graphql_ide_query',
		'documentSettings',
		[
			'get_callback'    => __NAMESPACE__ . '\\rest_read',
			'update_callback' => __NAMESPACE__ . '\\rest_update',
			'schema'          => [
				'description'          => __( 'Per-document settings (description, alias names, max-age, allow/deny).', 'wpgraphql-ide' ),
				'type'                 => 'object',
				'context'              => [ 'view', 'edit' ],
				// Field shapes are dynamic; clients should treat unknown keys as forward-compatible.
				'additionalProperties' => true,
			],
		]
	);
}

/**
 * @param array<string,mixed> $object
 *
 * @return array<string,mixed>
 */
function rest_read( $object ): array {
	$post_id = isset( $object['id'] ) ? (int) $object['id'] : 0;
	$values  = [];

	foreach ( Registry::instance()->get_fields() as $name => $field ) {
		if ( ! current_user_can_field( $field, $post_id ) ) {
			continue;
		}
		$values[ $name ] = Storage::read( $field, $post_id );
	}

	return $values;
}

/**
 * @param mixed    $value
 * @param \WP_Post $post
 *
 * @return true|\WP_Error
 */
function rest_update( $value, $post ) {
	if ( ! is_array( $value ) ) {
		return new \WP_Error(
			'rest_invalid_param',
			__( 'documentSettings must be an object.', 'wpgraphql-ide' ),
			[ 'status' => 400 ]
		);
	}

	$post_id = $post instanceof \WP_Post ? (int) $post->ID : 0;
	if ( $post_id <= 0 ) {
		return new \WP_Error( 'rest_invalid_post', __( 'Invalid post.', 'wpgraphql-ide' ), [ 'status' => 400 ] );
	}

	$registry = Registry::instance();

	foreach ( $value as $name => $field_value ) {
		$field = $registry->get_field( (string) $name );
		if ( null === $field ) {
			// Silently ignore unknown keys so older clients can co-exist with
			// newer servers that have removed a field, and vice versa.
			continue;
		}

		if ( ! current_user_can_field( $field, $post_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				/* translators: %s: setting field name */
				sprintf( __( 'You do not have permission to edit "%s".', 'wpgraphql-ide' ), $name ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		$result = Storage::write( $field, $post_id, $field_value );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
	}

	return true;
}

/**
 * @param array<string,mixed> $field
 */
function current_user_can_field( array $field, int $post_id ): bool {
	$capability = $field['capability'] ?? 'edit_posts';

	if ( $post_id > 0 ) {
		// Use the post-aware capability check so authors can edit their own
		// documents but not others'. `manage_graphql_ide` is the gate for
		// using the IDE at all; map_meta_cap handles the per-post check.
		return current_user_can( 'edit_post', $post_id ) && current_user_can( $capability );
	}

	return current_user_can( $capability );
}
