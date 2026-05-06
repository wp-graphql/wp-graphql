<?php
/**
 * Storage adapter for Document Settings fields.
 *
 * Reads and writes a field's value for a given graphql_ide_query post,
 * dispatching on the field's `storage.kind` (post_field | post_meta | taxonomy).
 * Centralizes the taxonomy term <-> value mapping (single, multi, integer
 * normalization) so REST callbacks stay thin.
 *
 * @package WPGraphQLIDE\DocumentSettings
 */

namespace WPGraphQLIDE\DocumentSettings;

class Storage {

	/**
	 * Read a field's current value from the post.
	 *
	 * @param array<string,mixed> $field
	 *
	 * @return mixed
	 */
	public static function read( array $field, int $post_id ) {
		$storage = $field['storage'];
		$kind    = $storage['kind'] ?? 'post_meta';
		$key     = $storage['key'] ?? '';

		if ( '' === $key || $post_id <= 0 ) {
			return $field['default'] ?? '';
		}

		switch ( $kind ) {
			case 'post_field':
				$post = get_post( $post_id );
				if ( ! $post instanceof \WP_Post ) {
					return $field['default'] ?? '';
				}
				return $post->{$key} ?? ( $field['default'] ?? '' );

			case 'post_meta':
				return get_post_meta( $post_id, $key, true );

			case 'taxonomy':
				$terms = get_the_terms( $post_id, $key );
				if ( ! is_array( $terms ) ) {
					return ! empty( $storage['multi'] ) ? [] : ( $field['default'] ?? '' );
				}
				$names = array_values( array_map( static fn( $t ) => $t->name, $terms ) );
				return ! empty( $storage['multi'] ) ? $names : ( $names[0] ?? ( $field['default'] ?? '' ) );
		}

		return $field['default'] ?? '';
	}

	/**
	 * Write a field's value to the post.
	 *
	 * @param array<string,mixed> $field
	 * @param mixed               $value
	 *
	 * @return true|\WP_Error
	 */
	public static function write( array $field, int $post_id, $value ) {
		$storage = $field['storage'];
		$kind    = $storage['kind'] ?? 'post_meta';
		$key     = $storage['key'] ?? '';

		if ( '' === $key || $post_id <= 0 ) {
			return new \WP_Error( 'wpgraphql_ide_invalid_storage', __( 'Invalid storage configuration.', 'wpgraphql-ide' ) );
		}

		if ( is_callable( $field['sanitize_callback'] ?? null ) ) {
			$value = call_user_func( $field['sanitize_callback'], $value );
		}

		switch ( $kind ) {
			case 'post_field':
				$update = wp_update_post(
					[
						'ID' => $post_id,
						$key => is_string( $value ) ? $value : '',
					],
					true
				);
				return is_wp_error( $update ) ? $update : true;

			case 'post_meta':
				update_post_meta( $post_id, $key, $value );
				return true;

			case 'taxonomy':
				return self::write_taxonomy( $field, $post_id, $value );
		}

		return new \WP_Error( 'wpgraphql_ide_unknown_storage_kind', __( 'Unknown storage kind.', 'wpgraphql-ide' ) );
	}

	/**
	 * @param array<string,mixed> $field
	 * @param mixed               $value
	 *
	 * @return true|\WP_Error
	 */
	private static function write_taxonomy( array $field, int $post_id, $value ) {
		$taxonomy = $field['storage']['key'];
		$multi    = ! empty( $field['storage']['multi'] );
		$unique   = ! empty( $field['storage']['unique'] );

		if ( $multi ) {
			$terms = is_array( $value ) ? $value : [];
			$terms = array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', $terms ) ), 'strlen' ) ) );
		} else {
			$terms = is_string( $value ) || is_numeric( $value ) ? [ trim( (string) $value ) ] : [];
			$terms = array_values( array_filter( $terms, 'strlen' ) );
		}

		if ( $unique && ! empty( $terms ) ) {
			$conflict = self::find_alias_conflict( $taxonomy, $terms, $post_id );
			if ( null !== $conflict ) {
				return new \WP_Error(
					'rest_invalid_param',
					sprintf(
						/* translators: %s: alias string already in use */
						__( 'The alias "%s" is already in use by another saved query.', 'wpgraphql-ide' ),
						$conflict
					),
					[ 'status' => 400 ]
				);
			}
		}

		$result = wp_set_post_terms( $post_id, $terms, $taxonomy, false );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Find the first alias term in $terms that is already assigned to a
	 * different post in the same taxonomy. Returns the conflicting term name
	 * or null.
	 *
	 * @param array<int,string> $terms
	 */
	private static function find_alias_conflict( string $taxonomy, array $terms, int $post_id ): ?string {
		foreach ( $terms as $term_name ) {
			$term = get_term_by( 'name', $term_name, $taxonomy );
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$other_posts = get_posts(
				[
					'post_type'      => 'graphql_ide_query',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'post__not_in'   => [ $post_id ],
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					'tax_query'      => [
						[
							'taxonomy' => $taxonomy,
							'field'    => 'term_id',
							'terms'    => [ $term->term_id ],
						],
					],
				]
			);

			if ( ! empty( $other_posts ) ) {
				return $term_name;
			}
		}

		return null;
	}
}
