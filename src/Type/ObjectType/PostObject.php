<?php

namespace WPGraphQL\Type\ObjectType;

use Exception;
use WP_Post_Type;
use WPGraphQL\Registry\TypeRegistry;

/**
 * WPObject - PostObject
 *
 * @package WPGraphQL\Type
 * @deprecated 1.12.0
 */
class PostObject {

	/**
	 * Registers a post_type WPObject type to the schema.
	 *
	 * @param WP_Post_Type $post_type_object Post type.
	 * @param TypeRegistry $type_registry    The Type Registry
	 *
	 * @return void
	 * @throws Exception
	 * @deprecated 1.12.0
	 */
	public static function register_post_object_types( WP_Post_Type $post_type_object, TypeRegistry $type_registry ) {

		_deprecated_function( __FUNCTION__, '1.12.0', esc_attr( \WPGraphQL\Registry\Utils\PostObject::class ) . '::register_types()' );

		\WPGraphQL\Registry\Utils\PostObject::register_types( $post_type_object );
	}


	/**
	 * Registers common post type fields on schema type corresponding to provided post type object.
	 *
	 * @param WP_Post_Type $post_type_object Post type.
	 * @param TypeRegistry $type_registry    The Type Registry
	 *
	 * @deprecated 1.12.0
	 *
	 * @return array
	 */
	public static function get_fields( $post_type_object, $type_registry ) {
		_deprecated_function( __FUNCTION__, '1.12.0', esc_attr( \WPGraphQL\Registry\Utils\PostObject::class ) . '::get_fields()' );

		return \WPGraphQL\Registry\Utils\PostObject::get_fields( $post_type_object );
	}
}
