<?php

namespace WPGraphQL\Type\ObjectType;

use WP_Post_Type;
use WPGraphQL\Data\PostObjectType;
use WPGraphQL\Registry\TypeRegistry;

/**
 * WPObject - PostObject
 *
 * @package WPGraphQL\Type
 * @deprecated @todo
 */
class PostObject {

	/**
	 * Registers a post_type WPObject type to the schema.
	 *
	 * @param WP_Post_Type $post_type_object Post type.
	 * @param TypeRegistry $type_registry    The Type Registry
	 *
	 * @deprecated @todo
	 *
	 * @return void
	 */
	public static function register_post_object_types( WP_Post_Type $post_type_object, TypeRegistry $type_registry ) {

		_deprecated_function( __FUNCTION__, '@todo', PostObjectType::class . '::register_post_object_types()' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		PostObjectType::register_post_object_types( $post_type_object );
	}


	/**
	 * Registers common post type fields on schema type corresponding to provided post type object.
	 *
	 * @param WP_Post_Type $post_type_object Post type.
	 * @param TypeRegistry $type_registry    The Type Registry
	 *
	 * @deprecated @todo
	 *
	 * @return array
	 */
	public static function get_post_object_fields( $post_type_object, $type_registry ) {
		_deprecated_function( __FUNCTION__, '@todo', PostObjectType::class . '::get_post_object_fields()' );

		return PostObjectType::get_post_object_fields( $post_type_object );
	}
}
