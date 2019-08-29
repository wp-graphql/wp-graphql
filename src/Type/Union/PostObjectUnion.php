<?php

namespace WPGraphQL\Type\Union;

use WPGraphQL\Registry\TypeRegistry;

class PostObjectUnion {

	/**
	 * Registers the Type
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @access public
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_union_type(
			'PostObjectUnion',
			[
				'name'        => 'PostObjectUnion',
				'typeNames'   => self::get_possible_types(),
				'resolveType' => function( $value ) use ( $type_registry ) {

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
	 * @access public
	 * @return array
	 */
	public static function get_possible_types() {
		$possible_types     = [];
		$allowed_post_types = \WPGraphQL::get_allowed_post_types();

		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
			foreach ( $allowed_post_types as $allowed_post_type ) {
				if ( empty( $possible_types[ $allowed_post_type ] ) ) {
					$post_type_object = get_post_type_object( $allowed_post_type );
					if ( isset( $post_type_object->graphql_single_name ) ) {
						$possible_types[ $allowed_post_type ] = $post_type_object->graphql_single_name;
					}
				}
			}
		}

		return $possible_types;
	}

}
