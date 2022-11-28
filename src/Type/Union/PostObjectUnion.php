<?php

namespace WPGraphQL\Type\Union;

use Exception;
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
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {
		register_graphql_union_type(
			'PostObjectUnion',
			[
				'name'        => 'PostObjectUnion',
				'typeNames'   => self::get_possible_types(),
				'description' => __( 'Union between the post, page and media item types', 'wp-graphql' ),
				'resolveType' => function ( $value ) use ( $type_registry ) {

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
	 * @return array
	 */
	public static function get_possible_types() {
		$possible_types = [];
		/** @var \WP_Post_Type[] */
		$allowed_post_types = \WPGraphQL::get_allowed_post_types( 'objects', [ 'graphql_kind' => 'object' ] );

		foreach ( $allowed_post_types as $post_type_object ) {
			if ( empty( $possible_types[ $post_type_object->name ] ) && isset( $post_type_object->graphql_single_name ) ) {
				$possible_types[ $post_type_object->name ] = $post_type_object->graphql_single_name;
			}
		}

		return $possible_types;
	}
}
