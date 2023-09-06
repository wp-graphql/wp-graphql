<?php

namespace WPGraphQL\Type\ObjectType;

use WP_Taxonomy;

/**
 * Class TermObject
 *
 * @package WPGraphQL\Type\Object
 * @deprecated 1.12.0
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
		_deprecated_function( __FUNCTION__, '1.12.0', esc_attr( \WPGraphQL\Registry\Utils\TermObject::class ) . '::register_types()' );

		\WPGraphQL\Registry\Utils\TermObject::register_types( $tax_object );
	}
}
