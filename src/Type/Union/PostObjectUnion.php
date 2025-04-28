<?php

namespace WPGraphQL\Type\Union;

use WPGraphQL\Registry\TypeRegistry;

/**
 * Class PostObjectUnion
 *
 * @package WPGraphQL\Type\Union
 * @deprecated use ContentNode interface instead
 */
class PostObjectUnion {

	/**
	 * Registers the Type
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {
		register_graphql_union_type(
			'PostObjectUnion',
			[
				'name'        => 'PostObjectUnion',
				'typeNames'   => self::get_possible_types(),
				'description' => static function () {
					return __( 'Union between the post, page and media item types', 'wp-graphql' );
				},
				'resolveType' => static function ( $value ) use ( $type_registry ) {
					_doing_it_wrong( 'PostObjectUnion', esc_attr__( 'The PostObjectUnion GraphQL type is deprecated. Use the ContentNode interface instead.', 'wp-graphql' ), '1.14.1' );

					$type = null;
					if ( isset( $value->post_type ) ) {
						$post_type_object = get_post_type_object( $value->post_type );
						if ( isset( $post_type_object->graphql_single_name ) ) {
							$type = $type_registry->get_type( $post_type_object->graphql_single_name );
						}
					}

					return ! empty( $type ) ? $type : null;
				},
			]
		);
	}

	/**
	 * Returns a list of possible types for the union
	 *
	 * @return string[]
	 */
	public static function get_possible_types() {
		$possible_types     = [];
		$allowed_post_types = \WPGraphQL::get_allowed_post_types( 'objects', [ 'graphql_kind' => 'object' ] );

		foreach ( $allowed_post_types as $post_type_object ) {
			if ( empty( $possible_types[ $post_type_object->name ] ) && isset( $post_type_object->graphql_single_name ) ) {
				$possible_types[ $post_type_object->name ] = $post_type_object->graphql_single_name;
			}
		}

		return $possible_types;
	}
}
