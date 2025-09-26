<?php

namespace WPGraphQL\Type\ObjectType;

use WP_Taxonomy;

/**
 * @todo Remove in 3.0.0
 * @deprecated 1.12.0
 * @codeCoverageIgnore
 */
class TermObject {

	/**
	 * Register the Type for each kind of Taxonomy
	 *
	 * @param \WP_Taxonomy $tax_object The taxonomy being registered
	 *
	 * @return void
	 * @throws \Exception
	 * @deprecated 1.12.0
	 */
	public static function register_taxonomy_object_type( WP_Taxonomy $tax_object ) {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				// translators: %s is the class name that is deprecated.
				esc_html__( 'This function will be removed in the next major version of WPGraphQL. Use %s instead.', 'wp-graphql' ),
				esc_html( \WPGraphQL\Registry\Utils\TermObject::class . '::register_types()' ),
			),
			'1.12.0'
		);

		\WPGraphQL\Registry\Utils\TermObject::register_types( $tax_object );
	}
}
