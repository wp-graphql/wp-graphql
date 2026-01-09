<?php

namespace WPGraphQL\Type\ObjectType;

use WPGraphQL\Registry\TypeRegistry;
use WP_Post_Type;

/**
 * @todo Remove in 3.0.0
 * @deprecated 1.12.0
 * @codeCoverageIgnore
 */
class PostObject {
	/**
	 * @todo remove in 3.0.0
	 *
	 * @param \WP_Post_Type                    $post_type_object Post type.
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry The Type Registry
	 *
	 * @return void
	 * @throws \Exception
	 * @deprecated 1.12.0
	 */
	public static function register_post_object_types( WP_Post_Type $post_type_object, TypeRegistry $type_registry ) {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				// translators: %s is the class name that is deprecated.
				esc_html__( 'This function will be removed in the next major version of WPGraphQL. Use %s instead.', 'wp-graphql' ),
				esc_html( \WPGraphQL\Registry\Utils\PostObject::class . '::register_types()' ),
			),
			'1.12.0'
		);

		\WPGraphQL\Registry\Utils\PostObject::register_types( $post_type_object );
	}

	/**
	 * @todo remove in 3.0.0
	 *
	 * @param \WP_Post_Type                    $post_type_object Post type.
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry The Type Registry
	 *
	 * @deprecated 1.12.0
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_fields( $post_type_object, $type_registry ) {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				// translators: %s is the class name that is deprecated.
				esc_html__( 'This function will be removed in the next major version of WPGraphQL. Use %s instead.', 'wp-graphql' ),
				esc_html( \WPGraphQL\Registry\Utils\PostObject::class . '::get_fields()' ),
			),
			'1.12.0'
		);

		return \WPGraphQL\Registry\Utils\PostObject::get_fields( $post_type_object );
	}
}
